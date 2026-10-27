/**
 * CNHS Spinner Helper
 * Creates the iOS-style spinner HTML and manages loading states.
 */

/**
 * Returns the HTML string for the CNHS spinner.
 * @param {string} size - '' | 'sm' | 'lg'
 * @param {string} variant - '' | 'white'
 */
function cnhsSpinnerHTML(size = '', variant = '') {
    const cls = ['cnhs-spinner', size ? `cnhs-spinner-${size}` : '', variant].filter(Boolean).join(' ');
    const bars = Array.from({ length: 12 }, () => '<div class="bar"></div>').join('');
    return `<span class="${cls}" role="status" aria-label="Loading">${bars}</span>`;
}

/**
 * Show a full-page loading overlay with CNHS spinner.
 * @param {string} message
 */
function showCnhsLoader(message = 'Please wait…') {
    let overlay = document.getElementById('cnhsLoadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'cnhsLoadingOverlay';
        overlay.className = 'cnhs-loading-overlay';
        overlay.innerHTML = `
            <span class="cnhs-spinner" style="width:56px;height:56px" role="status" aria-label="Loading">
                ${'<div class="bar"></div>'.repeat(12)}
            </span>
            <p id="cnhsLoaderMsg">${message}</p>
        `;
        document.body.appendChild(overlay);
    } else {
        document.getElementById('cnhsLoaderMsg').textContent = message;
    }
    requestAnimationFrame(() => overlay.classList.add('active'));
}

/**
 * Hide the full-page loading overlay.
 */
function hideCnhsLoader() {
    const overlay = document.getElementById('cnhsLoadingOverlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}
