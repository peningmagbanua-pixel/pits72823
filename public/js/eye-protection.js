(function() {
    let blueLight = 0;
    let brightness = 100;

    function loadPreferences() {
        const savedBlueLight = localStorage.getItem('blueLight');
        const savedBrightness = localStorage.getItem('brightness');

        if (savedBlueLight) {
            blueLight = parseInt(savedBlueLight);
        }

        if (savedBrightness) {
            brightness = parseInt(savedBrightness);
        }

        applyBlueLight(blueLight);
        applyBrightness(brightness);

        syncSliderUI();
    }

    function syncSliderUI() {
        updateSliderValue('blueLightSlider', blueLight);
        updateSliderValue('blueLightValue', blueLight);
        updateSliderValue('brightnessSlider', brightness);
        updateSliderValue('brightnessValue', brightness);
    }

    function updateSliderValue(id, value) {
        const element = document.getElementById(id);
        if (element) {
            if (element.tagName === 'INPUT') {
                element.value = value;
            } else {
                element.textContent = value + '%';
            }
        }
    }

    function applyBlueLight(value) {
        if (value === 0) {
            const overlay = document.getElementById('blue-light-overlay');
            if (overlay) {
                overlay.remove();
            }
            return;
        }

        const overlay = document.getElementById('blue-light-overlay');
        if (overlay) {
            overlay.style.opacity = value / 100;
        } else {
            createBlueLight(value);
        }
    }

    function createBlueLight(value) {
        let overlay = document.getElementById('blue-light-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'blue-light-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(255, 180, 100, 0.3);
                pointer-events: none;
                z-index: 9999;
                transition: opacity 0.3s ease;
            `;
            document.body.appendChild(overlay);
        }
        overlay.style.opacity = value / 100;
    }

    function applyBrightness(value) {
        if (value === 100) {
            const overlay = document.getElementById('brightness-overlay');
            if (overlay) {
                overlay.remove();
            }
            return;
        }

        const overlay = document.getElementById('brightness-overlay');
        if (overlay) {
            overlay.style.opacity = (100 - value) / 100;
        } else {
            createBrightness(value);
        }
    }

    function createBrightness(value) {
        let overlay = document.getElementById('brightness-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'brightness-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 1);
                pointer-events: none;
                z-index: 9998;
                transition: opacity 0.3s ease;
            `;
            document.body.appendChild(overlay);
        }
        overlay.style.opacity = (100 - value) / 100;
    }

    function handleBlueLightChange(value) {
        blueLight = parseInt(value);
        applyBlueLight(blueLight);
        localStorage.setItem('blueLight', blueLight.toString());
        updateSliderValue('blueLightValue', blueLight);
    }

    function handleBrightnessChange(value) {
        brightness = parseInt(value);
        applyBrightness(brightness);
        localStorage.setItem('brightness', brightness.toString());
        updateSliderValue('brightnessValue', brightness);
    }

    function resetSettings() {
        blueLight = 0;
        brightness = 100;
        localStorage.removeItem('blueLight');
        localStorage.removeItem('brightness');

        syncSliderUI();

        applyBlueLight(0);
        applyBrightness(100);
    }

    // Native range-input dragging is unreliable on many mobile browsers,
    // especially inside the fixed app header: the drag frequently cancels
    // mid-gesture and the ends of the track are hard to reach. On touch
    // devices the slider value is therefore driven directly from pointer
    // events (with pointer capture, so the finger can travel past the
    // track edges). Mouse input keeps the browser's native behavior.
    function enableTouchDrag(slider) {
        if (!slider || slider.dataset.touchDrag) return;
        slider.dataset.touchDrag = '1';
        slider.style.touchAction = 'none';

        const setFromClientX = (clientX) => {
            const rect = slider.getBoundingClientRect();
            const min = parseFloat(slider.min) || 0;
            const max = parseFloat(slider.max) || 100;
            const ratio = rect.width > 0 ? (clientX - rect.left) / rect.width : 0;
            const clamped = Math.min(1, Math.max(0, ratio));

            slider.value = Math.round(min + clamped * (max - min));
            // Re-dispatch so the existing 'input' delegation keeps working
            slider.dispatchEvent(new Event('input', { bubbles: true }));
        };

        slider.addEventListener('pointerdown', (e) => {
            if (e.pointerType === 'mouse') return;
            e.preventDefault();
            slider.setPointerCapture(e.pointerId);
            setFromClientX(e.clientX);
        });

        slider.addEventListener('pointermove', (e) => {
            if (e.pointerType === 'mouse') return;
            if (!slider.hasPointerCapture(e.pointerId)) return;
            e.preventDefault();
            setFromClientX(e.clientX);
        });

        const release = (e) => {
            if (slider.hasPointerCapture(e.pointerId)) {
                slider.releasePointerCapture(e.pointerId);
            }
        };
        slider.addEventListener('pointerup', release);
        slider.addEventListener('pointercancel', release);
    }

    function init() {
        loadPreferences();

        document.addEventListener('input', function(e) {
            const target = e.target;
            if (!target || !target.id) {
                return;
            }
            if (target.id === 'blueLightSlider') {
                handleBlueLightChange(target.value);
            } else if (target.id === 'brightnessSlider') {
                handleBrightnessChange(target.value);
            }
        });

        document.addEventListener('click', function(e) {
            if (e.target.closest('#resetEyeProtection')) {
                resetSettings();
            }
        });

        const observer = new MutationObserver(function() {
            if (document.getElementById('blueLightSlider')) {
                enableTouchDrag(document.getElementById('brightnessSlider'));
                syncSliderUI();
                observer.disconnect();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.EyeProtection = {
        reset: resetSettings,
        setBlueLight: handleBlueLightChange,
        setBrightness: handleBrightnessChange
    };
})();
