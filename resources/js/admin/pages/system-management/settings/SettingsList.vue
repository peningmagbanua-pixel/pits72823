<script setup>
import { ref, computed, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { sweetAlert, processingAlert } from '../../../../sweetalert';
import { useAuthUserStore } from '../../../stores/AuthUserStore';

const authUserStore = useAuthUserStore();

const isSystemAdministrator = computed(() => !!authUserStore.user?.view?.system_administrator);
const allowedPrograms = computed(() => authUserStore.user?.programs || []);

const permissions = ref([]);
const onlineApplicationPermissions = ref({});

const checkPermissions = async () => {
    try {
        const response = await axios.get('/api/check/settings/permissions');
        permissions.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

const checkOnlineApplicationPermissions = async () => {
    try {
        const response = await axios.get('/api/check/online-application/permissions');
        onlineApplicationPermissions.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

// ── Application Settings ──────────────────────────────────────────────────────
// Initialize directly from server-injected window variable (zero API delay)
const _serverSettings = window.__APP_SETTINGS__ || {};
const appSettings = ref({
    maintenance_mode:   !!_serverSettings.maintenance_mode,
    registration_open:  _serverSettings.registration_open  !== undefined ? !!_serverSettings.registration_open  : true,
    allow_google_login: _serverSettings.allow_google_login !== undefined ? !!_serverSettings.allow_google_login : true,
    auto_send_email:    _serverSettings.auto_send_email    !== undefined ? !!_serverSettings.auto_send_email    : true,
});
const savingAppSettings = ref(false);

const fetchAppSettings = async () => {
    try {
        const res = await axios.get('/api/landing-settings/app-settings');
        if (res.data.app_settings) {
            appSettings.value.maintenance_mode   = !!res.data.app_settings.maintenance_mode;
            appSettings.value.registration_open  = !!res.data.app_settings.registration_open;
            appSettings.value.allow_google_login = !!res.data.app_settings.allow_google_login;
            appSettings.value.auto_send_email    = !!res.data.app_settings.auto_send_email;
        }
    } catch (e) {
        console.error('Failed to load app settings:', e);
    }
};

const saveAppSettings = async () => {
    savingAppSettings.value = true;
    try {
        const res = await axios.post('/api/landing-settings/app-settings', {
            maintenance_mode:   appSettings.value.maintenance_mode   ? 1 : 0,
            registration_open:  appSettings.value.registration_open  ? 1 : 0,
            allow_google_login: appSettings.value.allow_google_login ? 1 : 0,
            auto_send_email:    appSettings.value.auto_send_email    ? 1 : 0,
        });
        // Sync confirmed saved values back from the server response
        if (res.data.app_settings) {
            appSettings.value.maintenance_mode   = !!res.data.app_settings.maintenance_mode;
            appSettings.value.registration_open  = !!res.data.app_settings.registration_open;
            appSettings.value.allow_google_login = !!res.data.app_settings.allow_google_login;
            appSettings.value.auto_send_email    = !!res.data.app_settings.auto_send_email;
        }
        sweetAlert('success', res.data.message);
    } catch (e) {
        // On error, re-fetch from server to restore correct state
        await fetchAppSettings();
        sweetAlert('error', e.response?.data?.message || 'Failed to save application settings.');
    } finally {
        savingAppSettings.value = false;
    }
};

// ── Session Timeout Settings ─────────────────────────────────────────────────
const sessionTimeout = ref({
    timeout_minutes: 15,
    warning_minutes: 2,
});
const savingSessionTimeout = ref(false);

const fetchSessionTimeout = async () => {
    try {
        const res = await axios.get('/api/session-timeout-settings');
        sessionTimeout.value.timeout_minutes = res.data.timeout_minutes;
        sessionTimeout.value.warning_minutes = res.data.warning_minutes;
    } catch (e) {
        console.error('Failed to load session timeout settings:', e);
    }
};

const saveSessionTimeout = async () => {
    savingSessionTimeout.value = true;
    try {
        const res = await axios.post('/api/session-timeout-settings', {
            timeout_minutes: Number(sessionTimeout.value.timeout_minutes),
            warning_minutes: Number(sessionTimeout.value.warning_minutes),
        });
        sessionTimeout.value.timeout_minutes = res.data.timeout_minutes;
        sessionTimeout.value.warning_minutes = res.data.warning_minutes;
        sweetAlert('success', res.data.message);
    } catch (e) {
        // On error, re-fetch from server to restore correct state
        await fetchSessionTimeout();
        const errors = e.response?.data?.errors;
        const firstError = errors ? Object.values(errors)[0]?.[0] : null;
        sweetAlert('error', firstError || e.response?.data?.message || 'Failed to save session timeout settings.');
    } finally {
        savingSessionTimeout.value = false;
    }
};

// ── Online Application Period ────────────────────────────────────────────────
const applicationPeriod = ref({
    school_year: '',
    application_start: '',
    application_deadline: '',
});
const periodErrors = ref({ application_start: '', application_deadline: '' });
const savingPeriod = ref(false);

const fetchApplicationPeriod = async () => {
    if (!onlineApplicationPermissions.value.browse) return;

    try {
        const res = await axios.get('/api/application-period');
        applicationPeriod.value.school_year = res.data.school_year || '';
        applicationPeriod.value.application_start = res.data.application_start || '';
        applicationPeriod.value.application_deadline = res.data.application_deadline || '';
    } catch (e) {
        console.error('Failed to load application period:', e);
    }
};

const saveApplicationPeriod = async () => {
    periodErrors.value = { application_start: '', application_deadline: '' };

    if (!applicationPeriod.value.application_start) {
        periodErrors.value.application_start = 'Opening date is required.';
    }
    if (!applicationPeriod.value.application_deadline) {
        periodErrors.value.application_deadline = 'Closing date is required.';
    }
    if (periodErrors.value.application_start || periodErrors.value.application_deadline) {
        return;
    }

    savingPeriod.value = true;
    try {
        const res = await axios.post('/api/application-period', {
            application_start: applicationPeriod.value.application_start,
            application_deadline: applicationPeriod.value.application_deadline,
        });
        if (res.data.school_year !== undefined) {
            applicationPeriod.value.school_year = res.data.school_year || '';
            applicationPeriod.value.application_start = res.data.application_start || '';
            applicationPeriod.value.application_deadline = res.data.application_deadline || '';
        }
        sweetAlert('success', res.data.message);
    } catch (e) {
        const errors = e.response?.data?.errors;
        if (errors) {
            periodErrors.value.application_start = errors.application_start?.[0] || '';
            periodErrors.value.application_deadline = errors.application_deadline?.[0] || '';
        } else {
            await fetchApplicationPeriod();
            sweetAlert('error', e.response?.data?.message || 'Failed to save application period.');
        }
    } finally {
        savingPeriod.value = false;
    }
};

// ── Requirements ──────────────────────────────────────────────────────────────
const requirements = ref([]);
const requirementsLoading = ref(true);
const programs = ref([]);

// Coordinators may only assign / manage programs inside their own scope.
const assignablePrograms = computed(() =>
    isSystemAdministrator.value
        ? programs.value
        : programs.value.filter((prog) => allowedPrograms.value.includes(prog.initials))
);

// A requirement is manageable when it belongs entirely to the caller's programs.
// "All Programs" requirements are System Administrator territory.
const canManageRequirement = (req) => {
    if (isSystemAdministrator.value) return true;
    return Array.isArray(req.programs)
        && req.programs.length > 0
        && req.programs.every((prog) => allowedPrograms.value.includes(prog.initials));
};

const fetchPrograms = async () => {
    try {
        const res = await axios.get('/api/programs', { params: { paginate: 100 } });
        programs.value = res.data.data || res.data;
    } catch (e) {
        console.error('Failed to load programs:', e);
    }
};

const fetchRequirements = async () => {
    requirementsLoading.value = true;
    try {
        const res = await axios.get('/api/requirements');
        requirements.value = res.data.data || res.data;
    } catch (e) {
        console.error('Failed to load requirements:', e);
    } finally {
        requirementsLoading.value = false;
    }
};

const emptyRequirementForm = () => ({
    name:        '',
    description: '',
    notice:      '',
    program_ids: [],
    is_required: true,
    is_active:   true,
});

// Add Requirement modal state
const showAddReqModal  = ref(false);
const newReq           = ref(emptyRequirementForm());
const addReqError      = ref('');

const openAddReqModal = () => {
    newReq.value    = emptyRequirementForm();
    addReqError.value = '';
    showAddReqModal.value = true;
};

const submitAddRequirement = async () => {
    if (!newReq.value.name.trim()) {
        addReqError.value = 'Requirement name is required.';
        return;
    }
    if (!isSystemAdministrator.value && newReq.value.program_ids.length === 0) {
        addReqError.value = 'You must assign the requirement to at least one of your programs.';
        return;
    }
    processingAlert('.btn');
    try {
        const res = await axios.post('/api/requirements', {
            name:        newReq.value.name.trim(),
            description: newReq.value.description.trim() || null,
            notice:      newReq.value.notice.trim() || null,
            program_ids: [...newReq.value.program_ids],
            is_required: newReq.value.is_required ? 1 : 0,
            is_active:   newReq.value.is_active ? 1 : 0,
        });
        Swal.close();
        showAddReqModal.value = false;
        sweetAlert('success', res.data.message);
        fetchRequirements();
    } catch (e) {
        Swal.close();
        const nameError = e.response?.data?.errors?.name?.[0];
        if (nameError) {
            addReqError.value = nameError;
        } else {
            sweetAlert('error', e.response?.data?.message || 'Failed to add requirement.');
        }
    }
};

// Edit Requirement modal state
const showEditReqModal = ref(false);
const editReqForm      = ref({ id: null, ...emptyRequirementForm(), sort_order: 0 });
const editReqError     = ref('');

// View Requirement modal state
const showViewReqModal = ref(false);
const viewReq          = ref(null);

const openViewReqModal = (req) => {
    viewReq.value = req;
    showViewReqModal.value = true;
};

const openEditReqModal = (req) => {
    editReqForm.value = {
        id:          req.id,
        name:        req.name,
        description: req.description || '',
        notice:      req.notice || '',
        program_ids: (req.programs || []).map((p) => p.id),
        is_required: !!req.is_required,
        is_active:   !!req.is_active,
        sort_order:  req.sort_order,
    };
    editReqError.value = '';
    showEditReqModal.value = true;
};

const submitEditRequirement = async () => {
    if (!editReqForm.value.name.trim()) {
        editReqError.value = 'Requirement name is required.';
        return;
    }
    if (!isSystemAdministrator.value && editReqForm.value.program_ids.length === 0) {
        editReqError.value = 'You must assign the requirement to at least one of your programs.';
        return;
    }
    processingAlert('.btn');
    try {
        const res = await axios.put(`/api/requirements/${editReqForm.value.id}`, {
            name:        editReqForm.value.name.trim(),
            description: editReqForm.value.description.trim() || null,
            notice:      editReqForm.value.notice.trim() || null,
            program_ids: [...editReqForm.value.program_ids],
            is_required: editReqForm.value.is_required ? 1 : 0,
            is_active:   editReqForm.value.is_active ? 1 : 0,
            sort_order:  editReqForm.value.sort_order,
        });
        Swal.close();
        showEditReqModal.value = false;
        sweetAlert('success', res.data.message);
        fetchRequirements();
    } catch (e) {
        Swal.close();
        const nameError = e.response?.data?.errors?.name?.[0];
        if (nameError) {
            editReqError.value = nameError;
        } else {
            sweetAlert('error', e.response?.data?.message || 'Failed to update requirement.');
        }
    }
};

const deleteRequirement = (id) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'You are about to delete this requirement!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!',
        allowOutsideClick: false,
        allowEscapeKey: false,
    }).then(async (result) => {
        if (result.isConfirmed) {
            processingAlert('.btn');
            try {
                const res = await axios.delete(`/api/requirements/${id}`);
                Swal.close();
                sweetAlert('success', res.data.message);
                fetchRequirements();
            } catch (e) {
                Swal.close();
                sweetAlert('error', e.response?.data?.message || 'Failed to delete requirement.');
            }
        }
    });
};

const toggleRequirementStatus = (req) => {
    const activating = !req.is_active;
    Swal.fire({
        title: 'Are you sure?',
        text: `You are about to ${activating ? 'activate' : 'deactivate'} this requirement!`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, confirm!',
        allowOutsideClick: false,
        allowEscapeKey: false,
    }).then(async (result) => {
        if (result.isConfirmed) {
            processingAlert('.btn');
            try {
                const res = await axios.put(`/api/requirements/${req.id}`, {
                    name:        req.name,
                    description: req.description,
                    notice:      req.notice,
                    is_required: req.is_required ? 1 : 0,
                    is_active:   activating ? 1 : 0,
                    sort_order:  req.sort_order,
                });
                Swal.close();
                sweetAlert('success', res.data.message);
                fetchRequirements();
            } catch (e) {
                Swal.close();
                sweetAlert('error', e.response?.data?.message || 'Failed to update requirement.');
            }
        }
    });
};

// ── Notifications ─────────────────────────────────────────────────────────────
const notifications = ref([
    { id: 1, event: 'Application Submitted',   email: true,  description: 'Notify applicant when their application is received.' },
    { id: 2, event: 'Application Approved',    email: true,  description: 'Notify applicant when their application is approved.' },
    { id: 3, event: 'Application Rejected',    email: true,  description: 'Notify applicant when their application is rejected.' },
    { id: 4, event: 'Status Reset to Pending', email: true,  description: 'Notify applicant when their status is reset to pending.' },
    { id: 5, event: 'Exam Schedule Assigned',  email: true,  description: 'Notify applicant when assigned to an exam schedule.' },
    { id: 6, event: 'Account Created',         email: true,  description: 'Send credentials when a new account is created.' },
]);
const savingNotifications = ref(false);

const saveNotifications = async () => {
    savingNotifications.value = true;
    try {
        const res = await axios.post('/api/landing-settings/notifications', {
            notifications: notifications.value.map(n => ({ id: n.id, email: n.email ? 1 : 0 })),
        });
        // Sync back descriptions from server response
        if (res.data.notification_settings) {
            const map = Object.fromEntries(res.data.notification_settings.map(n => [n.id, n]));
            notifications.value = notifications.value.map(n => ({
                ...n,
                email: map[n.id] ? !!map[n.id].email : n.email,
            }));
        }
        sweetAlert('success', res.data.message);
    } catch (e) {
        sweetAlert('error', e.response?.data?.message || 'Failed to save notification settings.');
    } finally {
        savingNotifications.value = false;
    }
};

// ── Landing Page Settings ─────────────────────────────────────────────────────
const landingForm = ref({
    school_name:   '',
    title:         '',
    description:   '',
    announcement:  '',
    contact_phone: '',
    contact_email: '',
    contact_address: '',
    office_hours:  '',
    primary_color: '#8B0000',
    sidebar_color: '#8B0000',
    logo_path:     null,
    banner_path:   null,
    hero_steps_title: 'Quick Process',
    hero_steps: [
        'Register and choose your program',
        'Verify your email address',
        'Fill out the application form',
        'Wait for admission review',
    ],
});
const landingLoading      = ref(false);
const savingLanding       = ref(false);
const logoFile            = ref(null);
const bannerFile          = ref(null);
const logoPreview         = ref(null);
const bannerPreview       = ref(null);
const removeLogo          = ref(false);
const removeBanner        = ref(false);
const showLandingPreview  = ref(false);

const storageBase = '/storage/';

const currentLogoUrl = computed(() => {
    if (removeLogo.value)   return null;
    if (logoPreview.value)  return logoPreview.value;
    if (landingForm.value.logo_path) return storageBase + landingForm.value.logo_path;
    return '/images/cnhs_logo.png';
});

const currentBannerUrl = computed(() => {
    if (removeBanner.value)   return null;
    if (bannerPreview.value)  return bannerPreview.value;
    if (landingForm.value.banner_path) return storageBase + landingForm.value.banner_path;
    return null;
});

const fetchLandingSettings = async () => {
    landingLoading.value = true;
    try {
        const res = await axios.get('/api/landing-settings');

        // Landing form — only copy known scalar fields, never nested objects
        const scalarFields = ['school_name','title','description','announcement','contact_phone','contact_email','contact_address','office_hours','primary_color','sidebar_color','logo_path','banner_path'];
        scalarFields.forEach(k => {
            if (res.data[k] !== undefined) landingForm.value[k] = res.data[k];
        });

        // Hero "Quick Process" steps
        if (Array.isArray(res.data.hero_steps) && res.data.hero_steps.length) {
            landingForm.value.hero_steps = res.data.hero_steps.map(s => String(s));
        }
        if (typeof res.data.hero_steps_title === 'string' && res.data.hero_steps_title.trim() !== '') {
            landingForm.value.hero_steps_title = res.data.hero_steps_title;
        }

        // Application Settings loaded separately via fetchAppSettings()

        // Requirements — loaded separately via fetchRequirements()

        // Notifications — merge saved email flags onto local list
        if (res.data.notification_settings && res.data.notification_settings.length) {
            const map = Object.fromEntries(res.data.notification_settings.map(n => [n.id, n]));
            notifications.value = notifications.value.map(n => ({
                ...n,
                email: map[n.id] !== undefined ? !!map[n.id].email : n.email,
            }));
        }
    } catch (e) {
        sweetAlert('error', 'Failed to load landing page settings.');
    } finally {
        landingLoading.value = false;
    }
};

const onLogoChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    logoFile.value    = file;
    removeLogo.value  = false;
    logoPreview.value = URL.createObjectURL(file);
};

const onBannerChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    bannerFile.value    = file;
    removeBanner.value  = false;
    bannerPreview.value = URL.createObjectURL(file);
};

const clearLogo = () => {
    logoFile.value    = null;
    logoPreview.value = null;
    removeLogo.value  = true;
};

const clearBanner = () => {
    bannerFile.value    = null;
    bannerPreview.value = null;
    removeBanner.value  = true;
};

const addHeroStep = () => {
    if (landingForm.value.hero_steps.length < 10) {
        landingForm.value.hero_steps.push('');
    }
};

const removeHeroStep = (index) => {
    if (landingForm.value.hero_steps.length > 1) {
        landingForm.value.hero_steps.splice(index, 1);
    }
};

// Mirrors App\Support\SidebarColor::isLight — used only for the sample preview
const sidebarPreviewIsLight = computed(() => {
    const hex = landingForm.value.sidebar_color || '#8B0000';
    const m = /^#([0-9a-f]{6})$/i.exec(hex);
    if (!m) return false;
    const n = parseInt(m[1], 16);
    const lin = v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    const luminance = 0.2126 * lin((n >> 16) & 255) + 0.7152 * lin((n >> 8) & 255) + 0.0722 * lin(n & 255);
    return luminance > 0.3;
});

// ── Settings filters ─────────────────────────────────────────────────────────
const SETTINGS_FILTERS = [
    { id: 'all',           label: 'All Settings',        icon: 'bi-sliders2-fill' },
    { id: 'branding',      label: 'Branding',            icon: 'bi-palette-fill' },
    { id: 'page-info',     label: 'Page Information',    icon: 'bi-file-text-fill' },
    { id: 'announcement',  label: 'Announcement',        icon: 'bi-megaphone-fill' },
    { id: 'quick-process', label: 'Quick Process Steps', icon: 'bi-list-ordinal' },
];
const activeFilter = ref('all');

// A landing-page field group is visible on "All Settings" or on its own filter
const showSection = (id) => activeFilter.value === 'all' || activeFilter.value === id;

const activeFilterMeta = computed(() =>
    SETTINGS_FILTERS.find(f => f.id === activeFilter.value) || SETTINGS_FILTERS[0]
);

const saveLandingSettings = async () => {
    if (!landingForm.value.school_name.trim()) {
        sweetAlert('error', 'School Name / Title is required.');
        return;
    }
    if (!landingForm.value.title.trim()) {
        sweetAlert('error', 'Hero Title is required.');
        return;
    }
    if (!landingForm.value.description.trim()) {
        sweetAlert('error', 'Description is required.');
        return;
    }

    savingLanding.value = true;
    try {
        const fd = new FormData();
        fd.append('school_name',   landingForm.value.school_name);
        fd.append('title',         landingForm.value.title);
        fd.append('description',   landingForm.value.description);
        fd.append('announcement',  landingForm.value.announcement  ?? '');
        fd.append('contact_phone', landingForm.value.contact_phone ?? '');
        fd.append('contact_email', landingForm.value.contact_email ?? '');
        fd.append('contact_address', landingForm.value.contact_address ?? '');
        fd.append('office_hours',  landingForm.value.office_hours  ?? '');
        fd.append('primary_color', landingForm.value.primary_color ?? '#8B0000');
        fd.append('sidebar_color', landingForm.value.sidebar_color ?? '#8B0000');
        fd.append('hero_steps_title', landingForm.value.hero_steps_title ?? 'Quick Process');
        (landingForm.value.hero_steps ?? []).forEach((step, i) => fd.append(`hero_steps[${i}]`, step ?? ''));
        if (logoFile.value)   fd.append('logo',   logoFile.value);
        if (bannerFile.value) fd.append('banner', bannerFile.value);
        if (removeLogo.value)   fd.append('remove_logo',   '1');
        if (removeBanner.value) fd.append('remove_banner', '1');

        const res = await axios.post('/api/landing-settings', fd);

        // Update form with saved scalar state only
        const saved = res.data.settings || {};
        const scalarFields = ['school_name','title','description','announcement','contact_phone','contact_email','contact_address','office_hours','primary_color','sidebar_color','logo_path','banner_path'];
        scalarFields.forEach(k => {
            if (saved[k] !== undefined) landingForm.value[k] = saved[k];
        });
        if (Array.isArray(saved.hero_steps) && saved.hero_steps.length) {
            landingForm.value.hero_steps = saved.hero_steps.map(s => String(s));
        }
        if (typeof saved.hero_steps_title === 'string' && saved.hero_steps_title.trim() !== '') {
            landingForm.value.hero_steps_title = saved.hero_steps_title;
        }
        logoFile.value    = null;
        bannerFile.value  = null;
        logoPreview.value = null;
        bannerPreview.value = null;
        removeLogo.value  = false;
        removeBanner.value = false;

        sweetAlert('success', res.data.message);
    } catch (e) {
        const msg = e.response?.data?.message || 'Failed to save landing page settings.';
        sweetAlert('error', msg);
    } finally {
        savingLanding.value = false;
    }
};

onMounted(async () => {
    await authUserStore.getAuthUser();
    await checkPermissions();
    await checkOnlineApplicationPermissions();
    if (permissions.value.browse) {
        if (isSystemAdministrator.value) {
            fetchAppSettings();
            fetchLandingSettings();
            fetchSessionTimeout();
        }
        fetchRequirements();
        fetchPrograms();
    }
    if (onlineApplicationPermissions.value.browse) {
        fetchApplicationPeriod();
    }
});
</script>

<template>
    <!-- Page Header -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Settings</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium"><a href="/admin/dashboard">Dashboard</a></li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <!-- ── Filter buttons (landing page sections, System Administrator only) ── -->
            <div v-if="permissions.browse && isSystemAdministrator" class="mb-4">
                <div class="d-flex flex-wrap gap-2">
                    <button v-for="f in SETTINGS_FILTERS" :key="f.id" type="button"
                        :title="f.label"
                        class="btn btn-sm rounded-pill px-3 py-2"
                        :class="activeFilter === f.id ? 'btn-primary' : 'btn-outline-secondary bg-white'"
                        @click="activeFilter = f.id">
                        <i class="bi me-1" :class="f.icon"></i>{{ f.label }}
                    </button>
                </div>
            </div>

            <div class="row g-4">

                <!-- ── 1. Application Settings (System Administrator only) ── -->
                <div v-if="permissions.browse && isSystemAdministrator && activeFilter === 'all'" class="col-12">
                    <div class="card shadow-sm border-0" style="font-size: 13px;">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="mb-0 fw-bold">
                                <i class="bi bi-gear-fill me-2 text-secondary"></i>Application Settings
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center p-3 rounded border">
                                        <div>
                                            <div class="fw-medium">Maintenance Mode</div>
                                            <small class="text-muted">Disable public access to the portal.</small>
                                        </div>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                v-model="appSettings.maintenance_mode" id="toggle-maintenance"
                                                :disabled="!permissions.update">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center p-3 rounded border">
                                        <div>
                                            <div class="fw-medium">Open Registration</div>
                                            <small class="text-muted">Allow new applicants to register.</small>
                                        </div>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                v-model="appSettings.registration_open" id="toggle-registration"
                                                :disabled="!permissions.update">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center p-3 rounded border">
                                        <div>
                                            <div class="fw-medium">Allow Google Login</div>
                                            <small class="text-muted">Let applicants sign in with Google.</small>
                                        </div>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                v-model="appSettings.allow_google_login" id="toggle-google"
                                                :disabled="!permissions.update">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center p-3 rounded border">
                                        <div>
                                            <div class="fw-medium">Auto-Send Emails</div>
                                            <small class="text-muted">Automatically send email notifications.</small>
                                        </div>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                v-model="appSettings.auto_send_email" id="toggle-email"
                                                :disabled="!permissions.update">
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div v-if="permissions.update" class="mt-3">
                                <button class="btn btn-primary btn-sm" :disabled="savingAppSettings"
                                    title="Save application settings"
                                    @click="saveAppSettings">
                                    <span v-if="savingAppSettings"
                                        class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    <i v-else class="bi bi-floppy me-1"></i>
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── 2. Online Application Period ─────────────────────── -->
                <div v-if="onlineApplicationPermissions.browse && activeFilter === 'all'" class="col-12">
                    <div class="card shadow-sm border-0" style="font-size: 13px;">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="mb-0 fw-bold">
                                <i class="bi bi-calendar-event-fill me-2 text-secondary"></i>Online Application Period
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="period_school_year" class="form-label fw-medium">Active School Year</label>
                                    <input type="text" id="period_school_year" class="form-control form-control-sm"
                                        :value="applicationPeriod.school_year || 'No active school year'" readonly>
                                    <small class="text-muted">The period is saved on this school year.</small>
                                </div>

                                <div class="col-md-4">
                                    <label for="application_start" class="form-label fw-medium required">Opening Date</label>
                                    <input type="date" id="application_start" v-model="applicationPeriod.application_start"
                                        :class="{ 'is-invalid': periodErrors.application_start }"
                                        :readonly="!onlineApplicationPermissions.update"
                                        class="form-control form-control-sm">
                                    <span class="invalid-feedback">{{ periodErrors.application_start }}</span>
                                    <small class="text-muted">Month, day, and year the application opens.</small>
                                </div>

                                <div class="col-md-4">
                                    <label for="application_deadline" class="form-label fw-medium required">Closing Date</label>
                                    <input type="date" id="application_deadline" v-model="applicationPeriod.application_deadline"
                                        :class="{ 'is-invalid': periodErrors.application_deadline }"
                                        :readonly="!onlineApplicationPermissions.update"
                                        class="form-control form-control-sm">
                                    <span class="invalid-feedback">{{ periodErrors.application_deadline }}</span>
                                    <small class="text-muted">Month, day, and year the application closes.</small>
                                </div>
                            </div>

                            <div v-if="!applicationPeriod.school_year" class="alert alert-warning mt-3 mb-0 py-2 small">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                No active school year found. Please activate a school year before setting the application period.
                            </div>

                            <div v-if="onlineApplicationPermissions.update" class="mt-3">
                                <button class="btn btn-primary btn-sm" :disabled="savingPeriod || !applicationPeriod.school_year"
                                    title="Save application period"
                                    @click="saveApplicationPeriod">
                                    <span v-if="savingPeriod"
                                        class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    <i v-else class="bi bi-floppy me-1"></i>
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── 3. Requirements ───────────────────────────────────── -->
                <div v-if="permissions.browse && activeFilter === 'all'" class="col-12">
                    <div class="card shadow-sm border-0" style="font-size: 13px;">
                        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold">
                                <i class="bi bi-file-earmark-check-fill me-2 text-secondary"></i>Requirements
                            </h6>
                            <div class="d-flex gap-2">
                                <button v-if="permissions.create" class="btn btn-primary btn-sm" title="Add requirement" @click="openAddReqModal">
                                    <i class="bi bi-plus-circle me-1"></i>New Requirement
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive-sm">
                                <table class="table table-bordered table-hover table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center align-middle">#</th>
                                            <th class="align-middle">Requirement Name</th>
                                            <th class="align-middle">Program / For</th>
                                            <th class="text-center align-middle">Required</th>
                                            <th class="text-center align-middle">Active</th>
                                            <th class="text-center align-middle actions-cell">Action/s</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="requirementsLoading">
                                        <tr>
                                            <td colspan="6" class="text-center">
                                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                                Loading ...
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else-if="requirements.length > 0">
                                        <tr v-for="(req, index) in requirements" :key="req.id">
                                            <td class="text-center align-middle">{{ index + 1 }}</td>
                                            <td class="align-middle">{{ req.name }}</td>
                                            <td class="align-middle">
                                                <template v-if="req.programs?.length">
                                                    <span v-for="prog in req.programs" :key="prog.id"
                                                        class="badge bg-info-subtle text-info me-1">{{ prog.initials }}</span>
                                                </template>
                                                <span v-else class="badge bg-secondary-subtle text-secondary">All Programs</span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <span v-if="req.is_required"
                                                    class="badge bg-success-subtle text-success">Yes</span>
                                                <span v-else
                                                    class="badge bg-secondary-subtle text-secondary">No</span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <span v-if="req.is_active"
                                                    class="badge bg-success-subtle text-success">Active</span>
                                                <span v-else
                                                    class="badge bg-secondary-subtle text-secondary">Inactive</span>
                                            </td>
                                            <td class="text-center align-middle actions-cell">
                                                <button class="btn btn-info btn-sm me-1"
                                                    @click="openViewReqModal(req)"
                                                    title="View">
                                                    <i class="bi bi-eye-fill"></i>
                                                </button>
                                                <button v-if="permissions.update && canManageRequirement(req)" class="btn btn-secondary btn-sm me-1"
                                                    title="Edit requirement"
                                                    @click="openEditReqModal(req)">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button v-if="permissions.update && canManageRequirement(req)" class="btn btn-sm me-1"
                                                    :class="req.is_active ? 'btn-warning' : 'btn-success'"
                                                    @click="toggleRequirementStatus(req)"
                                                    :title="req.is_active ? 'Deactivate' : 'Activate'">
                                                    <i class="bi" :class="req.is_active ? 'bi-eye-slash-fill' : 'bi-eye-fill'"></i>
                                                </button>
                                                <button v-if="permissions.delete && canManageRequirement(req)" class="btn btn-danger btn-sm"
                                                    title="Delete requirement"
                                                    @click="deleteRequirement(req.id)">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="6" class="text-center">No requirements found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── 4. Notifications (System Administrator only) ───────── -->
                <div v-if="permissions.browse && isSystemAdministrator && activeFilter === 'all'" class="col-12">
                    <div class="card shadow-sm border-0" style="font-size: 13px;">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="mb-0 fw-bold">
                                <i class="bi bi-bell-fill me-2 text-secondary"></i>Notifications
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive-sm">
                                <table class="table table-bordered table-hover table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center align-middle">#</th>
                                            <th class="align-middle">Event</th>
                                            <th class="align-middle">Description</th>
                                            <th class="text-center align-middle">Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(notif, index) in notifications" :key="notif.id">
                                            <td class="text-center align-middle">{{ index + 1 }}</td>
                                            <td class="align-middle fw-medium">{{ notif.event }}</td>
                                            <td class="align-middle text-muted">{{ notif.description }}</td>
                                            <td class="text-center align-middle">
                                                <div class="form-check form-switch d-flex justify-content-center mb-0">
                                                    <input class="form-check-input" type="checkbox"
                                                        role="switch" v-model="notif.email">
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top py-3">
                            <button v-if="permissions.update" class="btn btn-primary btn-sm" :disabled="savingNotifications"
                                title="Save notifications"
                                @click="saveNotifications">
                                <span v-if="savingNotifications"
                                    class="spinner-border spinner-border-sm me-1" role="status"></span>
                                <i v-else class="bi bi-floppy me-1"></i>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── 5. Landing Page Settings (System Administrator only) ── -->
                <div v-if="permissions.browse && isSystemAdministrator" class="col-12">
                    <div class="card shadow-sm border-0" style="font-size: 13px;">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="mb-0 fw-bold">
                                <i class="bi bi-window-fullscreen me-2 text-secondary"></i>Landing Page Settings
                            </h6>
                            <small class="text-muted">Branding, page content, announcement, and quick process steps for the public landing page.</small>
                        </div>

                        <div class="card-body">
                            <div v-if="landingLoading" class="text-center py-4">
                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                <span class="ms-2 text-muted">Loading...</span>
                            </div>

                            <div v-else>
                                <!-- Focused-section heading (hidden on "All Settings") -->
                                <div v-if="activeFilter !== 'all'" class="d-flex align-items-center mb-4 pb-2 border-bottom">
                                    <i class="bi me-2 fs-5 text-secondary" :class="activeFilterMeta.icon"></i>
                                    <h6 class="mb-0 fw-bold">{{ activeFilterMeta.label }}</h6>
                                </div>

                                <div class="row g-4">

                                    <!-- Logo -->
                                    <div v-if="showSection('branding')" class="col-md-6">
                                        <label class="form-label fw-medium">Logo</label>
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <img :src="currentLogoUrl || '/images/cnhs_logo.png'"
                                                alt="Logo Preview"
                                                class="rounded border"
                                                style="width:56px;height:56px;object-fit:contain;background:#f8f8f8;">
                                            <div class="d-flex gap-2">
                                                <label class="btn btn-outline-secondary btn-sm mb-0" style="cursor:pointer;">
                                                    <i class="bi bi-upload me-1"></i>Upload
                                                    <input type="file" accept="image/*" class="d-none" @change="onLogoChange">
                                                </label>
                                                <button v-if="currentLogoUrl && currentLogoUrl !== '/images/cnhs_logo.png'"
                                                    type="button" class="btn btn-outline-danger btn-sm"
                                                    title="Remove school logo"
                                                    @click="clearLogo">
                                                    <i class="bi bi-x-lg me-1"></i>Remove
                                                </button>
                                            </div>
                                        </div>
                                        <small class="text-muted">PNG, JPG, or SVG. Max 2 MB.</small>
                                    </div>

                                    <!-- Banner Image -->
                                    <div v-if="showSection('branding')" class="col-md-6">
                                        <label class="form-label fw-medium">Banner Image</label>
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <div class="rounded border d-flex align-items-center justify-content-center"
                                                style="width:100px;height:56px;background:#f8f8f8;overflow:hidden;flex-shrink:0;">
                                                <img v-if="currentBannerUrl" :src="currentBannerUrl"
                                                    alt="Banner Preview"
                                                    style="width:100%;height:100%;object-fit:cover;">
                                                <span v-else class="text-muted" style="font-size:11px;">No banner</span>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <label class="btn btn-outline-secondary btn-sm mb-0" style="cursor:pointer;">
                                                    <i class="bi bi-upload me-1"></i>Upload
                                                    <input type="file" accept="image/*" class="d-none" @change="onBannerChange">
                                                </label>
                                                <button v-if="currentBannerUrl"
                                                    type="button" class="btn btn-outline-danger btn-sm"
                                                    title="Remove banner image"
                                                    @click="clearBanner">
                                                    <i class="bi bi-x-lg me-1"></i>Remove
                                                </button>
                                            </div>
                                        </div>
                                        <small class="text-muted">PNG, JPG, or WebP. Max 4 MB. Displayed as hero background.</small>
                                    </div>

                                    <!-- School Name -->
                                    <div v-if="showSection('branding')" class="col-md-6">
                                        <label class="form-label fw-medium required">School Name / Title</label>
                                        <input type="text" v-model="landingForm.school_name"
                                            class="form-control form-control-sm"
                                            placeholder="e.g., CNHS Online Application">
                                    </div>

                                    <!-- Hero Title -->
                                    <div v-if="showSection('page-info')" class="col-md-6">
                                        <label class="form-label fw-medium required">Hero Title</label>
                                        <input type="text" v-model="landingForm.title"
                                            class="form-control form-control-sm"
                                            placeholder="e.g., Welcome to CNHS Online Application System">
                                    </div>

                                    <!-- Description -->
                                    <div v-if="showSection('page-info')" class="col-12">
                                        <label class="form-label fw-medium required">Description</label>
                                        <textarea v-model="landingForm.description"
                                            class="form-control form-control-sm" rows="2"
                                            placeholder="Short description shown under the hero title."></textarea>
                                    </div>

                                    <!-- Announcement -->
                                    <div v-if="showSection('announcement')" class="col-12">
                                        <label class="form-label fw-medium">Announcement</label>
                                        <input type="text" v-model="landingForm.announcement"
                                            class="form-control form-control-sm"
                                            placeholder="Optional — shown as a banner at the top of the page. Leave blank to hide.">
                                        <small class="text-muted">Leave blank to hide the announcement bar.</small>
                                    </div>

                                    <!-- Quick Process Steps (hero) -->
                                    <div v-if="showSection('quick-process')" class="col-12">
                                        <label class="form-label fw-medium">Quick Process Steps</label>
                                        <input type="text" v-model="landingForm.hero_steps_title"
                                            class="form-control form-control-sm mb-2"
                                            placeholder="e.g., Quick Process">
                                        <div v-for="(step, idx) in landingForm.hero_steps" :key="idx"
                                            class="input-group input-group-sm mb-2">
                                            <span class="input-group-text">
                                                <i class="bi" :class="'bi-' + ((idx + 1) <= 9 ? (idx + 1) + '-circle-fill' : 'circle-fill')"></i>
                                            </span>
                                            <input type="text" v-model="landingForm.hero_steps[idx]"
                                                class="form-control"
                                                :placeholder="'Step ' + (idx + 1)">
                                            <button type="button" class="btn btn-outline-danger"
                                                @click="removeHeroStep(idx)"
                                                :disabled="landingForm.hero_steps.length <= 1"
                                                title="Remove step">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                            title="Add hero step"
                                            @click="addHeroStep"
                                            :disabled="landingForm.hero_steps.length >= 10">
                                            <i class="bi bi-plus-lg me-1"></i>Add Step
                                        </button>
                                        <div><small class="text-muted">
                                            Shown in the hero section of the landing page. 1–10 steps; blank entries are ignored.
                                        </small></div>
                                    </div>

                                    <!-- Contact Phone -->
                                    <div v-if="showSection('page-info')" class="col-md-6">
                                        <label class="form-label fw-medium">Contact Phone</label>
                                        <input type="text" v-model="landingForm.contact_phone"
                                            class="form-control form-control-sm"
                                            placeholder="e.g., (+63) 44-291-2340">
                                    </div>

                                    <!-- Contact Email -->
                                    <div v-if="showSection('page-info')" class="col-md-6">
                                        <label class="form-label fw-medium">Contact Email</label>
                                        <input type="email" v-model="landingForm.contact_email"
                                            class="form-control form-control-sm"
                                            placeholder="e.g., cnhs.edu.ph@gmail.com">
                                    </div>

                                    <!-- Address -->
                                    <div v-if="showSection('page-info')" class="col-12">
                                        <label class="form-label fw-medium">Address</label>
                                        <input type="text" v-model="landingForm.contact_address"
                                            class="form-control form-control-sm"
                                            placeholder="e.g., Quezon Street, F.C. Otic Poblacion (Brgy. II), Carranglan, Nueva Ecija">
                                    </div>

                                    <!-- Office Hours -->
                                    <div v-if="showSection('page-info')" class="col-12">
                                        <label class="form-label fw-medium">Office Hours</label>
                                        <textarea v-model="landingForm.office_hours"
                                            class="form-control form-control-sm" rows="3"
                                            placeholder="Monday – Friday: 7:30 AM – 4:30 PM&#10;Saturday: 7:30 AM – 12:00 PM&#10;Sunday: Closed"></textarea>
                                        <small class="text-muted">One entry per line.</small>
                                    </div>

                                    <!-- Primary Color -->
                                    <div v-if="showSection('branding')" class="col-md-6">
                                        <label class="form-label fw-medium">Primary Color</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="color" v-model="landingForm.primary_color"
                                                class="form-control form-control-color form-control-sm"
                                                style="width:48px;height:34px;padding:2px 4px;cursor:pointer;">
                                            <input type="text" v-model="landingForm.primary_color"
                                                class="form-control form-control-sm"
                                                placeholder="#8B0000"
                                                maxlength="7"
                                                style="width:110px;font-family:monospace;">
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                title="Reset to default color"
                                                @click="landingForm.primary_color = '#8B0000'">
                                                Reset
                                            </button>
                                        </div>
                                        <small class="text-muted">
                                            Controls navbar, buttons, footer, icons, and borders across the landing page.
                                        </small>
                                    </div>

                                    <!-- Color Preview -->
                                    <div v-if="showSection('branding')" class="col-md-6 d-flex align-items-end">
                                        <div class="w-100 rounded border p-2 d-flex gap-2 align-items-center"
                                            style="font-size:12px;">
                                            <div class="rounded"
                                                :style="{ background: landingForm.primary_color, width:'36px', height:'36px', flexShrink:0 }">
                                            </div>
                                            <div>
                                                <div class="fw-semibold" :style="{ color: landingForm.primary_color }">
                                                    Sample Text
                                                </div>
                                                <div class="text-muted">Navbar · Footer · Buttons · Icons</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Side Menu Background Color (admin + applicant portals) -->
                                    <div v-if="showSection('branding')" class="col-md-6">
                                        <label class="form-label fw-medium">Side Menu Background Color</label>
                                        <div class="d-flex align-items-center gap-2">
                                            <input type="color" v-model="landingForm.sidebar_color"
                                                class="form-control form-control-color form-control-sm"
                                                style="width:48px;height:34px;padding:2px 4px;cursor:pointer;">
                                            <input type="text" v-model="landingForm.sidebar_color"
                                                class="form-control form-control-sm"
                                                placeholder="#8B0000"
                                                maxlength="7"
                                                style="width:110px;font-family:monospace;">
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                title="Reset to default color"
                                                @click="landingForm.sidebar_color = '#8B0000'">
                                                Reset
                                            </button>
                                        </div>
                                        <small class="text-muted">
                                            Sets the background of the side menu — including its header — in the Admin and Applicant portals.
                                            Any color works, including white: on light backgrounds the existing menu text and icons automatically turn dark so they stay readable.
                                        </small>
                                    </div>

                                    <!-- Side Menu Preview -->
                                    <div v-if="showSection('branding')" class="col-md-6 d-flex align-items-end">
                                        <div class="w-100 rounded border p-2" style="font-size:12px;">
                                            <div class="rounded overflow-hidden d-flex" style="min-height:80px;">
                                                <div class="d-flex flex-column"
                                                    :style="{
                                                        background: landingForm.sidebar_color || '#8B0000',
                                                        width: '45%',
                                                        color: sidebarPreviewIsLight ? '#1f2937' : '#ffffff',
                                                    }">
                                                    <div class="d-flex align-items-center gap-1 fw-semibold px-2 py-1"
                                                        :style="{ borderBottom: '1px solid ' + (sidebarPreviewIsLight ? 'rgba(31,41,55,.2)' : 'rgba(255,255,255,.2)') }">
                                                        <span style="width:10px;height:10px;border-radius:50%;display:inline-block;flex-shrink:0;"
                                                            :style="{ background: sidebarPreviewIsLight ? '#1f2937' : '#ffffff' }"></span>
                                                        PORTAL
                                                    </div>
                                                    <div class="p-2 d-flex flex-column gap-2">
                                                        <div class="d-flex align-items-center gap-1 fw-semibold">
                                                            <i class="bi bi-grid-fill"></i> Dashboard
                                                        </div>
                                                        <div class="d-flex align-items-center gap-1" :style="{ opacity: .75 }">
                                                            <i class="bi bi-people-fill"></i> Applicants
                                                        </div>
                                                        <div class="d-flex align-items-center gap-1" :style="{ opacity: .75 }">
                                                            <i class="bi bi-gear-fill"></i> Settings
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="bg-light d-flex flex-grow-1 align-items-center justify-content-center text-muted">
                                                    Content
                                                </div>
                                            </div>
                                            <div class="text-muted mt-1 text-center">Side menu background — header included — Admin &amp; Applicant</div>
                                        </div>
                                    </div>

                                </div>

                                <!-- Preview toggle -->
                                <div class="mt-3 mb-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                        title="Toggle landing page preview"
                                        @click="showLandingPreview = !showLandingPreview">
                                        <i class="bi bi-eye me-1"></i>
                                        {{ showLandingPreview ? 'Hide Preview' : 'Preview' }}
                                    </button>
                                </div>

                                <!-- Inline Preview -->
                                <div v-if="showLandingPreview" class="border rounded p-3 mb-3 bg-light">
                                    <p class="text-muted small mb-2 fw-semibold text-uppercase" style="letter-spacing:.05em;">
                                        Preview
                                    </p>
                                    <!-- Announcement bar -->
                                    <div v-if="landingForm.announcement"
                                        class="rounded mb-2 px-3 py-2 text-white text-center small fw-medium"
                                        :style="{ background: landingForm.primary_color || '#8B0000' }">
                                        <i class="bi bi-megaphone-fill me-1"></i>{{ landingForm.announcement }}
                                    </div>
                                    <!-- Navbar brand -->
                                    <div class="d-flex align-items-center gap-2 mb-3 p-2 bg-white rounded border">
                                        <img :src="currentLogoUrl || '/images/cnhs_logo.png'"
                                            style="height:32px;width:auto;object-fit:contain;">
                                        <span class="fw-bold" :style="{ color: landingForm.primary_color || '#8B0000', fontSize: '14px' }">
                                            {{ landingForm.school_name || '—' }}
                                        </span>
                                    </div>
                                    <!-- Hero preview -->
                                    <div class="rounded border p-3 mb-3 position-relative overflow-hidden"
                                        :style="currentBannerUrl
                                            ? 'background:url(' + currentBannerUrl + ') center/cover no-repeat;min-height:100px;'
                                            : 'background:#f8f8f8;'">
                                        <div :style="currentBannerUrl ? 'background:rgba(255,255,255,.85);padding:12px;border-radius:6px;' : ''">
                                            <div class="fw-bold mb-1" :style="{ color: landingForm.primary_color || '#8B0000', fontSize: '15px' }">
                                                {{ landingForm.title || '—' }}
                                            </div>
                                            <div class="text-muted small">{{ landingForm.description || '—' }}</div>
                                        </div>
                                    </div>
                                    <!-- Contact preview -->
                                    <div class="d-flex flex-wrap gap-3">
                                        <div v-if="landingForm.contact_phone" class="small text-muted">
                                            <i class="bi bi-telephone-fill me-1" :style="{ color: landingForm.primary_color || '#8B0000' }"></i>
                                            {{ landingForm.contact_phone }}
                                        </div>
                                        <div v-if="landingForm.contact_email" class="small text-muted">
                                            <i class="bi bi-envelope-fill me-1" :style="{ color: landingForm.primary_color || '#8B0000' }"></i>
                                            {{ landingForm.contact_email }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Save -->
                                <div v-if="permissions.update" class="mt-3">
                                    <button class="btn btn-primary btn-sm" :disabled="savingLanding"
                                        title="Save landing settings"
                                        @click="saveLandingSettings">
                                        <span v-if="savingLanding"
                                            class="spinner-border spinner-border-sm me-1" role="status"></span>
                                        <i v-else class="bi bi-floppy me-1"></i>
                                        Save Changes
                                    </button>
                                    <a href="/" target="_blank" class="btn btn-outline-secondary btn-sm ms-2">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>View Landing Page
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── 6. Session Timeout Settings (System Administrator only) ── -->
                <div v-if="permissions.browse && isSystemAdministrator && activeFilter === 'all'" class="col-12">
                    <div class="card shadow-sm border-0" style="font-size: 13px;">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="mb-0 fw-bold">
                                <i class="bi bi-clock-history me-2 text-secondary"></i>Session Timeout Settings
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="session_timeout_minutes" class="form-label fw-medium required">Idle Time Before Warning (minutes)</label>
                                    <input type="number" id="session_timeout_minutes" class="form-control form-control-sm"
                                        v-model.number="sessionTimeout.timeout_minutes" min="1" max="1440"
                                        :disabled="!permissions.update">
                                    <small class="text-muted">Minutes of inactivity before the session warning modal appears.</small>
                                </div>

                                <div class="col-md-6">
                                    <label for="session_warning_minutes" class="form-label fw-medium required">Countdown Before Logout (minutes)</label>
                                    <input type="number" id="session_warning_minutes" class="form-control form-control-sm"
                                        v-model.number="sessionTimeout.warning_minutes" min="1" max="1440"
                                        :disabled="!permissions.update">
                                    <small class="text-muted">Minutes shown in the countdown before the session is automatically logged out.</small>
                                </div>
                            </div>

                            <div v-if="permissions.update" class="mt-3">
                                <button class="btn btn-primary btn-sm" :disabled="savingSessionTimeout"
                                    title="Save session timeout"
                                    @click="saveSessionTimeout">
                                    <span v-if="savingSessionTimeout"
                                        class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    <i v-else class="bi bi-floppy me-1"></i>
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ── Add Requirement Modal ─────────────────────────────────────────── -->
    <div class="modal fade" :class="{ show: showAddReqModal }"
        :style="{ display: showAddReqModal ? 'block' : 'none' }"
        tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">New Requirement</h6>
                    <button class="btn-close" title="Close" @click="showAddReqModal = false"></button>
                    <div class="form-group mb-3">
                        <label class="form-label required">Requirement Name</label>
                        <input type="text" v-model="newReq.name"
                            :class="{ 'is-invalid': addReqError }"
                            class="form-control form-control-sm"
                            placeholder="e.g., PSA Birth Certificate">
                        <div class="invalid-feedback">{{ addReqError }}</div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Program / For</label>
                        <div class="form-check" v-for="prog in assignablePrograms" :key="prog.id">
                            <input class="form-check-input" type="checkbox" :value="prog.id"
                                v-model="newReq.program_ids" :id="'new-req-program-' + prog.id">
                            <label class="form-check-label" :for="'new-req-program-' + prog.id">
                                {{ prog.initials }} — {{ prog.name }}
                            </label>
                        </div>
                        <small v-if="isSystemAdministrator" class="text-muted">No program checked = required by all programs.</small>
                        <small v-else class="text-muted">Select at least one of your assigned programs.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Required</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        v-model="newReq.is_required" id="new-req-required">
                                    <label class="form-check-label" for="new-req-required">
                                        {{ newReq.is_required ? 'Yes' : 'No' }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Active</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        v-model="newReq.is_active" id="new-req-active">
                                    <label class="form-check-label" for="new-req-active">
                                        {{ newReq.is_active ? 'Yes' : 'No' }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" v-model="newReq.description"
                            class="form-control form-control-sm"
                            placeholder="Helper text shown under the upload (optional)">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Notice</label>
                        <textarea v-model="newReq.notice" rows="2"
                            class="form-control form-control-sm"
                            placeholder="Notice shown above the uploads (optional)"></textarea>
                    </div>
                    <hr>
                    <button class="btn btn-primary btn-sm" title="Submit new requirement" @click="submitAddRequirement">
                        <i class="bi bi-floppy me-1"></i>Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div v-if="showAddReqModal" class="modal-backdrop fade show"></div>

    <!-- ── Edit Requirement Modal ────────────────────────────────────────── -->
    <div class="modal fade" :class="{ show: showEditReqModal }"
        :style="{ display: showEditReqModal ? 'block' : 'none' }"
        tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">Update Requirement</h6>
                    <button class="btn-close" title="Close" @click="showEditReqModal = false"></button>
                    <div class="form-group mb-3">
                        <label class="form-label required">Requirement Name</label>
                        <input type="text" v-model="editReqForm.name"
                            :class="{ 'is-invalid': editReqError }"
                            class="form-control form-control-sm"
                            placeholder="e.g., PSA Birth Certificate">
                        <div class="invalid-feedback">{{ editReqError }}</div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Program / For</label>
                        <div class="form-check" v-for="prog in assignablePrograms" :key="prog.id">
                            <input class="form-check-input" type="checkbox" :value="prog.id"
                                v-model="editReqForm.program_ids" :id="'edit-req-program-' + prog.id">
                            <label class="form-check-label" :for="'edit-req-program-' + prog.id">
                                {{ prog.initials }} — {{ prog.name }}
                            </label>
                        </div>
                        <small v-if="isSystemAdministrator" class="text-muted">No program checked = required by all programs.</small>
                        <small v-else class="text-muted">Select at least one of your assigned programs.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Required</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        v-model="editReqForm.is_required" id="edit-req-required">
                                    <label class="form-check-label" for="edit-req-required">
                                        {{ editReqForm.is_required ? 'Yes' : 'No' }}
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Active</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        v-model="editReqForm.is_active" id="edit-req-active">
                                    <label class="form-check-label" for="edit-req-active">
                                        {{ editReqForm.is_active ? 'Yes' : 'No' }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" v-model="editReqForm.description"
                            class="form-control form-control-sm"
                            placeholder="Helper text shown under the upload (optional)">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Notice</label>
                        <textarea v-model="editReqForm.notice" rows="2"
                            class="form-control form-control-sm"
                            placeholder="Notice shown above the uploads (optional)"></textarea>
                    </div>
                    <hr>
                    <button class="btn btn-primary btn-sm" title="Update requirement" @click="submitEditRequirement">
                        <i class="bi bi-floppy me-1"></i>Update
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div v-if="showEditReqModal" class="modal-backdrop fade show"></div>

    <!-- ── View Requirement Modal ──────────────────────────────────────────── -->
    <div class="modal fade" :class="{ show: showViewReqModal }"
        :style="{ display: showViewReqModal ? 'block' : 'none' }"
        tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content border-0 p-3">
                <div class="modal-body" v-if="viewReq">
                    <h6 class="text-center">Requirement Details</h6>
                    <button class="btn-close" title="Close" @click="showViewReqModal = false"></button>
                    <div class="mb-2">
                        <span class="text-muted">Requirement Name:</span>
                        <div class="fw-semibold">{{ viewReq.name }}</div>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted">Program / For:</span>
                        <div>
                            <template v-if="viewReq.programs?.length">
                                <span v-for="prog in viewReq.programs" :key="prog.id"
                                    class="badge bg-info-subtle text-info me-1">{{ prog.initials }}</span>
                            </template>
                            <span v-else class="badge bg-secondary-subtle text-secondary">All Programs</span>
                        </div>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted">Required:</span>
                        <div>{{ viewReq.is_required ? 'Yes' : 'No' }}</div>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted">Status:</span>
                        <div>
                            <span v-if="viewReq.is_active" class="badge bg-success-subtle text-success">Active</span>
                            <span v-else class="badge bg-secondary-subtle text-secondary">Inactive</span>
                        </div>
                    </div>
                    <div class="mb-2" v-if="viewReq.description">
                        <span class="text-muted">Description:</span>
                        <div>{{ viewReq.description }}</div>
                    </div>
                    <div class="mb-2" v-if="viewReq.notice">
                        <span class="text-muted">Notice:</span>
                        <div>{{ viewReq.notice }}</div>
                    </div>
                    <div class="mb-3" v-if="viewReq.created_at">
                        <span class="text-muted">Added On:</span>
                        <div>{{ viewReq.created_at }}</div>
                    </div>
                    <hr>
                    <button class="btn btn-secondary btn-sm" title="Close" @click="showViewReqModal = false">
                        <i class="bi bi-x-circle me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div v-if="showViewReqModal" class="modal-backdrop fade show"></div>
</template>
