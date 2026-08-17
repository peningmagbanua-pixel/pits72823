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
        
        updateSliderValue('blueLightSlider', 0);
        updateSliderValue('blueLightValue', 0);
        updateSliderValue('brightnessSlider', 100);
        updateSliderValue('brightnessValue', 100);
        
        applyBlueLight(0);
        applyBrightness(100);
    }

    function init() {
        setTimeout(() => {
            loadPreferences();

            const blueLightSlider = document.getElementById('blueLightSlider');
            if (blueLightSlider) {
                blueLightSlider.addEventListener('input', function(e) {
                    handleBlueLightChange(e.target.value);
                });
            }

            const brightnessSlider = document.getElementById('brightnessSlider');
            if (brightnessSlider) {
                brightnessSlider.addEventListener('input', function(e) {
                    handleBrightnessChange(e.target.value);
                });
            }

            const resetBtn = document.getElementById('resetEyeProtection');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    resetSettings();
                });
            }
        }, 100);
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
