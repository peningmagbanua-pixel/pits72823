document.addEventListener("DOMContentLoaded", function () {
    const registerBtn = document.getElementById('registerBtn');
    const googleRegisterBtn = document.getElementById('googleRegisterBtn');

    if (registerBtn) {
        const form = registerBtn.closest('form');
        const rText = registerBtn.querySelector('.btn-text');
        const rLoading = registerBtn.querySelector('.btn-loading');

        form.addEventListener('submit', function () {
            registerBtn.classList.add('disabled');
            registerBtn.setAttribute('aria-disabled', 'true');
            registerBtn.style.pointerEvents = "none";
            rText.classList.add('d-none');
            rLoading.classList.remove('d-none');
        });
    }

    if (googleRegisterBtn) {
        const gText = googleRegisterBtn.querySelector('.btn-text');
        const gLoading = googleRegisterBtn.querySelector('.btn-loading');
        const gIcon = googleRegisterBtn.querySelector('.btn-icon');

        googleRegisterBtn.addEventListener('click', function () {
            googleRegisterBtn.classList.add('disabled');
            googleRegisterBtn.setAttribute('aria-disabled', 'true');
            googleRegisterBtn.style.pointerEvents = "none";
            gIcon.classList.add('d-none');
            gText.classList.add('d-none');
            gLoading.classList.remove('d-none');
        });
    }
});