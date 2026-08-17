import Swal from 'sweetalert2';

export const sweetAlert = (type, message) => {
    const status = {
        success: {
            icon: type,
            title: "Success",
            message: message
        },
        info: {
            icon: type,
            title: "Info",
            message: message
        },
        error: {
            icon: type,
            title: "Error",
            message: message ? message : "Something went wrong"
        },
    };

    Swal.fire({
        icon: status[type].icon,
        title: status[type].title,
        text: status[type].message,
        confirmButtonColor: "#3085d6",
        showConfirmButton: true,
        allowOutsideClick: false,
        allowEscapeKey: false,
    });
};

export const processingAlert = (btnSelector, title = 'Processing . . .') => {
    const button = document.querySelector(btnSelector);
    if (button) {
        button.disabled = true;
    }
    Swal.fire({
        html: `
            <div class="d-flex align-items-center gap-2"  style="margin-top: 5px;">
                <div class="spinner-border spinner-border-sm text-primary mt-1" role="status"></div>
                <small class="fw-medium" role="status">${title}</small>
            </div>
        `,
        width: '300px',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        timerProgressBar: true,
        didOpen: (popup) => {
            popup.style.borderRadius = '2px';
        },
        willClose: () => {
            if (button) {
                button.disabled = false;
            }
        }
    });
}

export const deletingAlert = (title = 'Deleting . . .') => {
    Swal.fire({
        html: `
            <div class="d-flex align-items-center gap-2"  style="margin-top: 5px;">
                <div class="spinner-border spinner-border-sm text-primary mt-1" role="status"></div>
                <small class="fw-medium" role="status">${title}</small>
            </div>
        `,
        width: "300px",
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: (popup) => {
            popup.style.borderRadius = '2px';
        }
    });
};

export const loadingAlert = (title = 'Loading . . .') => {
    Swal.fire({
        html: `
            <div class="d-flex align-items-center gap-2"  style="margin-top: 5px;">
                <div class="spinner-border spinner-border-sm text-primary mt-1" role="status"></div>
                <small class="fw-medium" role="status">${title}</small>
            </div>
        `,
        width: '300px',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: (popup) => {
            popup.style.borderRadius = '2px';
        }
    });
};

export const logoutAlert = (title = 'Logging out . . .') => {
    Swal.fire({
        html: `
            <div class="d-flex align-items-center gap-2"  style="margin-top: 5px;">
                <div class="spinner-border spinner-border-sm text-primary mt-1" role="status"></div>
                <small class="fw-medium" role="status">${title}</small>
            </div>
        `,
        width: '300px',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: (popup) => {
            popup.style.borderRadius = '2px';
        }
    });
}