import { Modal } from 'bootstrap';

// ── Session Timeout (inactivity warning + auto logout) ─────────────────────
// Server-side inactivity is authoritative. The SPA mirrors the server's
// deadline (GET statusUrl), refreshes it through throttled pings (POST
// activityUrl) on real user activity, and treats any 401 as an expired
// session. Values come from Admin Settings (settings table) →
// window.__SESSION_TIMEOUT__ (injected by the dashboard blades), falling
// back to SESSION_TIMEOUT_MINUTES and SESSION_WARNING_MINUTES.

const HEARTBEAT_MIN_INTERVAL = 30000; // min ms between activity pings
const SYNC_MIN_INTERVAL = 10000; // min ms between status re-syncs
const ACTIVITY_THROTTLE = 1000; // local throttle (mousemove storms)

let started = false;
let idleTimer = null;
let warnTimer = null;
let deadline = 0; // client ms epoch of the server-side session expiration
let warningVisible = false;
let loggingOut = false;
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
};

const clearTimers = () => {
    clearTimeout(idleTimer);
    clearInterval(warnTimer);
    idleTimer = null;
    warnTimer = null;
};

const redirectToLogin = () => {
    if (loggingOut) return;
    loggingOut = true;
    clearTimers();
    window.location.href = loginPath;
};

const doLogout = async () => {
    if (loggingOut) return;
    loggingOut = true;
    clearTimers();
    try {
        await window.axios.post(logoutUrl);
    } catch (e) {
        // Session may already be invalid — still redirect to the login page.
    }
    window.location.href = loginPath;
};

const applyRemaining = (seconds) => {
    deadline = Date.now() + Math.max(0, Number(seconds) || 0) * 1000;
};

const scheduleWarning = () => {
    clearTimeout(idleTimer);
    idleTimer = null;
    if (!deadline) return;
    const delay = deadline - warningMs() - Date.now();
    if (delay <= 0) {
        showWarning();
    } else {
        idleTimer = setTimeout(showWarning, delay);
    }
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

const dismissWarning = () => {
    if (!warningVisible) return;
    warningVisible = false;
    clearInterval(warnTimer);
    warnTimer = null;
    const el = modalEl();
    if (el) Modal.getOrCreateInstance(el).hide();
};

const showWarning = () => {
    if (warningVisible || loggingOut) return;
    warningVisible = true;
    const el = countdownEl();
    if (el) el.textContent = formatCountdown(deadline - Date.now());
    const modal = modalEl();
    if (modal) Modal.getOrCreateInstance(modal).show();
    warnTimer = setInterval(tickCountdown, 250);
};

// Final server check before logging out: another tab may have kept the
// session alive through its own activity while this tab was in the background.
const confirmExpiry = async () => {
    if (loggingOut) return;
    try {
        const { data } = await window.axios.get(statusUrl);
        applyRemaining(data.remaining_seconds);
        if (deadline - Date.now() > 0) {
            warningVisible = false;
            const modal = modalEl();
            if (modal) Modal.getOrCreateInstance(modal).hide();
            scheduleWarning();
            return;
        }
        doLogout();
    } catch (e) {
        redirectToLogin(); // Session already expired server-side.
    }
};

const sendHeartbeat = async () => {
    if (loggingOut || !activityUrl) return;
    const now = Date.now();
    if (now - lastHeartbeatAt < HEARTBEAT_MIN_INTERVAL) return;
    lastHeartbeatAt = now;
    try {
        const { data } = await window.axios.post(activityUrl);
        if (loggingOut) return;
        applyRemaining(data.remaining_seconds);
        if (warningVisible && deadline - Date.now() > warningMs()) {
            dismissWarning();
        }
        scheduleWarning();
    } catch (e) {
        // 401s are handled by the response interceptor below.
    }
};

const syncStatus = async () => {
    if (loggingOut || !statusUrl) return;
    const now = Date.now();
    if (now - lastSyncAt < SYNC_MIN_INTERVAL) return;
    lastSyncAt = now;
    try {
        const { data } = await window.axios.get(statusUrl);
        if (loggingOut) return;
        applyRemaining(data.remaining_seconds);
        if (warningVisible && deadline - Date.now() > warningMs()) {
            dismissWarning(); // Kept alive by activity in another tab.
        }
        scheduleWarning();
    } catch (e) {
        // 401s are handled by the response interceptor below.
    }
};

const onActivity = () => {
    if (loggingOut) return;
    const now = Date.now();
    if (now - lastActivity < ACTIVITY_THROTTLE) return;
    lastActivity = now;
    if (warningVisible) {
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
    if (document.visibilityState === 'visible') syncStatus();
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
