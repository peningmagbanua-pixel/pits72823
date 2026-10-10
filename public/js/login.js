document.addEventListener("DOMContentLoaded", function () {

    const googleBtn = document.getElementById('googleLoginBtn');
    if (googleBtn) {
        const gText = googleBtn.querySelector('.btn-text');
        const gLoading = googleBtn.querySelector('.btn-loading');
        const gIcon = googleBtn.querySelector('.btn-icon');

        googleBtn.addEventListener('click', function () {
            googleBtn.classList.add('disabled');
            googleBtn.setAttribute('aria-disabled', 'true');
            googleBtn.style.pointerEvents = "none";

            gIcon.classList.add('d-none');
            gText.classList.add('d-none');
            gLoading.classList.remove('d-none');
        });
    }

    const loginBtn = document.getElementById('loginBtn');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    function toggleLoginBtn() {
        if (emailInput.value.trim() !== "" && passwordInput.value.trim() !== "") {
            loginBtn.removeAttribute('disabled');
        } else {
            loginBtn.setAttribute('disabled', 'true');
        }
    }

    if (loginBtn) {
        loginBtn.addEventListener('click', function () {
            loginBtn.classList.add('disabled');
            loginBtn.setAttribute('aria-disabled', 'true');
            loginBtn.style.pointerEvents = "none";
        });
    }

    if (emailInput && passwordInput && loginBtn) {
        emailInput.addEventListener('input', toggleLoginBtn);
        passwordInput.addEventListener('input', toggleLoginBtn);
        toggleLoginBtn();
    }

});