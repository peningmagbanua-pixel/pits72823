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
};

export const APP = {
    ADMIN: 'admin-app',
    APPLICANT: 'applicant-app',
}

export const ADMIN = {
    YES: 'Yes',
    NO: 'No',
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