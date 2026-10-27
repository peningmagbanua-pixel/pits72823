import { Modal } from 'bootstrap';

// ── Session Timeout (inactivity warning + auto logout) ─────────────────────
// Server-side inactivity is authoritative. The SPA mirrors the server's
// deadline (GET statusUrl), refreshes it through throttled pings (POST
// activityUrl) on real user activity, and treats any 401 as an expired
// session. Values come from Admin Settings (settings table) →
// window.__SESSION_TIMEOUT__ (injected by the dashboard blades), falling
// back to SESSION_TIMEOUT_MINUTES and SESSION_WARNING_MINUTES.
//
// The warning modal uses a static backdrop, so it must never be able to get
// stuck: the modal is never shown from a hidden tab (background timers and
// transitions are throttled there), every dismissal is verified against the
// DOM and force-removed when Bootstrap refuses the hide (its hide() is a
// silent no-op while a show transition is pending), and failed or hung status
// pings always re-arm the timers so the state machine can never die silently.

const HEARTBEAT_MIN_INTERVAL = 30000; // min ms between activity pings
const SYNC_MIN_INTERVAL = 10000; // min ms between status re-syncs
const ACTIVITY_THROTTLE = 1000; // local throttle (mousemove storms)
const REQUEST_TIMEOUT = 15000; // ms before a session ping is considered dead
const ACTIVE_RECHECK_INTERVAL = 2000; // warning re-check while the user is active
const HIDDEN_RECHECK_INTERVAL = 1000; // warning re-check while the tab is hidden
const CLEANUP_DELAY = 600; // ms to wait for Bootstrap's hide() before forcing
const NAV_RETRY_DELAY = 5000; // ms before retrying a stuck login redirect

let started = false;
let idleTimer = null;
let warnTimer = null;
let deadline = 0; // client ms epoch of the server-side session expiration
let warningVisible = false;
let loggingOut = false;
let recovering = false;
let hidingUntil = 0; // ms epoch until which a dismissal-driven hide settles
let lastActivity = 0;
let lastHeartbeatAt = 0;
let lastSyncAt = 0;

let logoutUrl = '';
let loginPath = '/admin/login';
let statusUrl = '';
let activityUrl = '';

const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'click', 'keydown', 'scroll', 'touchstart'];

const timeoutMinutes = () => Number(window.__SESSION_TIMEOUT__?.timeoutMinutes) || 15;
const warningMinutes = () => Number(window.__SESSION_TIMEOUT__?.warningMinutes) || 2;
const warningMs = () => warningMinutes() * 60000;

const formatCountdown = (ms) => {
    const total = Math.max(0, Math.ceil(ms / 1000));
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
};

const modalEl = () => document.getElementById('session-timeout-modal');
const countdownEl = () => document.getElementById('session-timeout-countdown');

const ensureModal = () => {
    if (modalEl()) return;
    document.body.insertAdjacentHTML('beforeend', `
        <div class="modal fade" id="session-timeout-modal" tabindex="-1"
             data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 p-3">
                    <div class="modal-body">
                        <h6 class="text-center mb-2">Session Timeout Warning</h6>
                        <p class="text-center mb-1">
                            Your session will expire in
                            <span id="session-timeout-countdown" class="fw-bold">2:00</span>
                            due to inactivity.
                        </p>
                        <p class="text-center text-muted mb-0 small">
                            Move your mouse, click, scroll, or press any key to stay signed in.
                        </p>
                    </div>
                </div>
            </div>
        </div>`);
    // Keep the flag mirroring Bootstrap's real modal state.
    modalEl().addEventListener('hidden.bs.modal', () => {
        warningVisible = false;
    });
};

const clearTimers = () => {
    clearTimeout(idleTimer);
    clearInterval(warnTimer);
    idleTimer = null;
    warnTimer = null;
};

const navigateToLogin = () => {
    window.location.href = loginPath;
    // If the navigation cannot commit (tab frozen/offline right after wake),
    // retry once so the app never stays parked on a dead session.
    setTimeout(() => {
        window.location.href = loginPath;
    }, NAV_RETRY_DELAY);
};

const redirectToLogin = () => {
    if (loggingOut) return;
    loggingOut = true;
    clearTimers();
    navigateToLogin();
};

// Another tab rotated the CSRF token (session invalidated/expired there), so
// every request from this page now fails with 419. Reloading picks up a fresh
// token and re-evaluates the session; the middleware redirects to login if it
// is gone.
const recoverFromTokenMismatch = () => {
    if (recovering) return;
    recovering = true;
    clearTimers();
    window.location.reload();
};

const isTokenMismatch = (error) => error?.response?.status === 419;

const doLogout = async () => {
    if (loggingOut) return;
    loggingOut = true;
    clearTimers();
    try {
        await window.axios.post(logoutUrl, undefined, { timeout: REQUEST_TIMEOUT });
    } catch (e) {
        // Session may already be invalid — still redirect to the login page.
    }
    navigateToLogin();
};

const applyRemaining = (seconds) => {
    deadline = Date.now() + Math.max(0, Number(seconds) || 0) * 1000;
};

const scheduleWarning = () => {
    clearTimeout(idleTimer);
    idleTimer = null;
    if (!deadline) return;
    const delay = deadline - warningMs() - Date.now();
    if (delay > 0) {
        idleTimer = setTimeout(showWarning, delay);
        return;
    }
    // Already inside the warning window. While the user is demonstrably
    // active, keep re-checking instead of flashing the blocking modal back on
    // top of their clicks — the in-flight heartbeat extends the deadline; if
    // it never succeeds the session 401s anyway. Once activity stops for a
    // couple of seconds, show the warning.
    if (Date.now() - lastActivity < ACTIVE_RECHECK_INTERVAL) {
        idleTimer = setTimeout(scheduleWarning, ACTIVE_RECHECK_INTERVAL);
        return;
    }
    showWarning();
};

const tickCountdown = () => {
    const remaining = deadline - Date.now();
    const el = countdownEl();
    if (el) el.textContent = formatCountdown(remaining);
    if (remaining <= 0) {
        clearInterval(warnTimer);
        warnTimer = null;
        confirmExpiry();
    }
};

const isWarningUp = () => {
    if (warningVisible) return true;
    const el = modalEl();
    // The flag can desync from Bootstrap's actual state (hide() silently
    // no-ops mid-show). Trust the DOM too so a stuck backdrop is dismissed
    // even when the flag says the modal is already gone.
    return Boolean(el && el.classList.contains('show'));
};

const forceRemoveWarningModal = (el) => {
    const instance = Modal.getInstance(el);
    if (instance) instance.dispose();
    el.remove();
    document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
    document.body.classList.remove('modal-open');
    resetBodyScrollLock();
    warningVisible = false;
    clearInterval(warnTimer);
    warnTimer = null;
};

// Mirror Bootstrap's ScrollBarHelper.reset() — show() locks body scrolling
// with inline styles that a forced removal would otherwise leave behind.
const resetBodyScrollLock = () => {
    const restore = (element, property) => {
        const saved = element.getAttribute(`data-bs-${property}`);
        if (saved === null) {
            element.style.removeProperty(property);
            return;
        }
        element.style.setProperty(property, saved);
        element.removeAttribute(`data-bs-${property}`);
    };
    document.body.style.removeProperty('overflow');
    restore(document.body, 'padding-right');
    document.querySelectorAll('.fixed-top, .fixed-bottom, .is-fixed, .sticky-top').forEach((element) => {
        restore(element, 'padding-right');
    });
    document.querySelectorAll('.sticky-top').forEach((element) => {
        restore(element, 'margin-right');
    });
};

// Bootstrap's hide() returns silently while the modal is mid-show
// (_isTransitioning), which happens exactly when a throttled background tab
// flushes the warning timer just before the user returns. If Bootstrap hasn't
// finished hiding the modal a moment later, tear it down by hand so the
// backdrop can never keep the page blocked.
const scheduleCleanup = (el) => {
    setTimeout(() => {
        if (!el.isConnected) return;
        // Never tear down state another modal legitimately owns.
        if (document.querySelector('.modal.show:not(#session-timeout-modal)')) return;
        const instance = Modal.getInstance(el);
        const stuck =
            el.classList.contains('show') ||
            Boolean(document.querySelector('.modal-backdrop')) ||
            document.body.classList.contains('modal-open') ||
            Boolean(instance && (instance._isShown || instance._isTransitioning));
        if (stuck) forceRemoveWarningModal(el);
    }, CLEANUP_DELAY);
};

const dismissWarning = () => {
    warningVisible = false;
    hidingUntil = Date.now() + CLEANUP_DELAY;
    clearInterval(warnTimer);
    warnTimer = null;
    const el = modalEl();
    if (!el) return;
    const instance = Modal.getInstance(el);
    if (instance) instance.hide(); // may no-op mid-show — the cleanup below finishes the job
    scheduleCleanup(el);
};

const showWarning = () => {
    if (warningVisible || loggingOut) return;
    if (document.visibilityState !== 'visible') {
        // Never show the blocking modal from a background tab — that is what
        // created the show/dismiss race in the first place. Re-check while
        // hidden; the visibilitychange handler also re-arms on return.
        clearTimeout(idleTimer);
        idleTimer = setTimeout(showWarning, HIDDEN_RECHECK_INTERVAL);
        return;
    }
    if (Date.now() < hidingUntil) {
        // Bootstrap is still settling a dismissal — retrying show() now would
        // silently no-op and desync the flag. Re-check once it settles.
        clearTimeout(idleTimer);
        idleTimer = setTimeout(showWarning, hidingUntil - Date.now() + 50);
        return;
    }
    ensureModal(); // in case a force-removal deleted it earlier
    const modal = modalEl();
    if (!modal) return;
    warningVisible = true;
    const el = countdownEl();
    if (el) el.textContent = formatCountdown(deadline - Date.now());
    Modal.getOrCreateInstance(modal).show();
    warnTimer = setInterval(tickCountdown, 250);
};

// Final server check before logging out: another tab may have kept the
// session alive through its own activity while this tab was in the background.
const confirmExpiry = async () => {
    if (loggingOut) return;
    try {
        const { data } = await window.axios.get(statusUrl, { timeout: REQUEST_TIMEOUT });
        applyRemaining(data.remaining_seconds);
        if (deadline - Date.now() > 0) {
            dismissWarning();
            scheduleWarning();
            return;
        }
        doLogout();
    } catch (e) {
        if (isTokenMismatch(e)) {
            recoverFromTokenMismatch();
            return;
        }
        redirectToLogin(); // Session already expired server-side.
    }
};

const sendHeartbeat = async () => {
    if (loggingOut || !activityUrl) return;
    const now = Date.now();
    if (now - lastHeartbeatAt < HEARTBEAT_MIN_INTERVAL) return;
    lastHeartbeatAt = now;
    try {
        const { data } = await window.axios.post(activityUrl, undefined, { timeout: REQUEST_TIMEOUT });
        if (loggingOut) return;
        applyRemaining(data.remaining_seconds);
        if (warningVisible && deadline - Date.now() > warningMs()) {
            dismissWarning();
        }
    } catch (e) {
        if (isTokenMismatch(e)) {
            recoverFromTokenMismatch();
            return;
        }
        // 401s are handled by the response interceptor below.
    } finally {
        // Re-arm even after a failure/hang so the timers never die silently
        // (this was the path that previously left the UI frozen until reload).
        if (!loggingOut) scheduleWarning();
    }
};

const syncStatus = async () => {
    if (loggingOut || !statusUrl) return;
    const now = Date.now();
    if (now - lastSyncAt < SYNC_MIN_INTERVAL) {
        scheduleWarning(); // throttled: still re-arm from the known deadline
        return;
    }
    lastSyncAt = now;
    try {
        const { data } = await window.axios.get(statusUrl, { timeout: REQUEST_TIMEOUT });
        if (loggingOut) return;
        applyRemaining(data.remaining_seconds);
        if (warningVisible && deadline - Date.now() > warningMs()) {
            dismissWarning(); // Kept alive by activity in another tab.
        }
    } catch (e) {
        if (isTokenMismatch(e)) {
            recoverFromTokenMismatch();
            return;
        }
        // 401s are handled by the response interceptor below.
    } finally {
        if (!loggingOut) scheduleWarning();
    }
};

const onActivity = () => {
    if (loggingOut) return;
    const now = Date.now();
    if (now - lastActivity < ACTIVITY_THROTTLE) return;
    lastActivity = now;
    if (isWarningUp()) {
        // User became active during the warning period — react immediately;
        // the deadline is extended once the server confirms the ping below.
        dismissWarning();
        scheduleWarning();
    }
    sendHeartbeat();
};

// Exported for the routers: SPA navigation counts as user activity.
export const sessionActivity = () => onActivity();

const onVisibilityChange = () => {
    // Becoming visible is not activity by itself — re-sync with the server
    // so the countdown matches reality after a throttled background tab.
    // Re-arm first: scheduleWarning works from the known deadline even when
    // the sync below is throttled or the network is still waking up.
    if (document.visibilityState !== 'visible') return;
    scheduleWarning();
    syncStatus();
};

const registerInterceptor = () => {
    window.axios.interceptors.response.use(
        (response) => response,
        (error) => {
            if (error.response && error.response.status === 401) {
                redirectToLogin(); // Session expired server-side.
            }
            return Promise.reject(error);
        }
    );
};

export const startSessionTimeout = ({
    logoutUrl: url,
    loginPath: path,
    statusUrl: status,
    activityUrl: activity,
}) => {
    if (started) return; // never register duplicate timers/listeners
    started = true;

    logoutUrl = url;
    loginPath = path;
    statusUrl = status;
    activityUrl = activity;

    ensureModal();
    registerInterceptor();

    // Fallback deadline from the injected config (identical to the previous
    // behavior), refined as soon as the server status arrives.
    applyRemaining((timeoutMinutes() + warningMinutes()) * 60);
    scheduleWarning();
    syncStatus();

    ACTIVITY_EVENTS.forEach((event) =>
        document.addEventListener(event, onActivity, { capture: true, passive: true })
    );
    window.addEventListener('focus', syncStatus);
    document.addEventListener('visibilitychange', onVisibilityChange);
};
