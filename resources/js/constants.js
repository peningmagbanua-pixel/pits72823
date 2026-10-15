export const SORT = {
    ASC: 'asc',
    DESC: 'desc',
};

export const SORT_FIELD = {
    CREATED_AT: 'created_at',
}

export const STATUS = {
    ACTIVE: 1,
    INACTIVE: 0,
};

export const ROLE = {
    SYSTEM_ADMINISTRATOR: 'System Administrator',
    ENCODER: 'Encoder',
    COORDINATOR: 'Coordinator',
};

export const APP = {
    ADMIN: 'admin-app',
    APPLICANT: 'applicant-app',
}

export const ADMIN = {
    YES: 'Yes',
    NO: 'No',
}

export const PROGRAM_ACCESS = [
    { value: 'SPICT', label: 'SPICT' },
    { value: 'STE', label: 'STE' },
]

export const PROGRAM_DISPLAY_NAMES = {
    STE: 'Science, Technology, and Engineering (STE)',
    SPICT: 'Special Program in Information and Communication Technology (SPICT)',
}

export const formatProgramName = (program) => {
    const key = String(program ?? '').trim().toUpperCase()
    return PROGRAM_DISPLAY_NAMES[key] ?? String(program ?? '')
}

export const ACTION = {
    CREATED: 'Created',
    UPDATED: 'Updated',
    DELETED: 'Deleted'
}

export const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

export const resolveAssetUrl = (file) => {
    const icon = document.querySelector('link[rel="icon"]')?.href;
    if (!icon) return `/images/${file}`;
    try { return new URL(file, icon).href; } catch { return `/images/${file}`; }
};

export const waitForImages = (win, timeout = 4000) => new Promise(resolve => {
    const start = Date.now();
    const check = () => {
        const images = Array.from(win.document?.images || []);
        if (images.length && images.every(img => img.complete)) { resolve(); return; }
        if (Date.now() - start >= timeout) { resolve(); return; }
        setTimeout(check, 100);
    };
    check();
});