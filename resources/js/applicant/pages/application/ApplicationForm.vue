<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';
import { sweetAlert, processingAlert } from '@/sweetalert.js';
import { useAuthApplicantStore } from '../../stores/AuthApplicantStore';
import SignaturePad from '../../components/SignaturePad.vue';

const router = useRouter();
const authApplicantStore = useAuthApplicantStore();

// Selected program, resolved server-side from the program selection step.
// The frontend only ever receives the public initials/name — never an id.
const selectedProgram = ref(null);
const loading = ref(true);
const submitting = ref(false);

// Form fields
const form = ref({
    lastname:                  '',
    firstname:                 '',
    middlename:                '',
    extname:                   '',
    date_of_birth:             '',
    place_of_birth:            '',
    citizenship:               '',
    sex:                       '',
    complete_address:          '',
    parent_guardian_name:      '',
    contact_number:            '',
    elementary_school:         '',
    elementary_school_address: '',
});

// Requirement files (keyed by requirement id)
const requirements = ref([]);
const files        = ref({});

// Online STE Health Declaration
const HEALTH_CONDITIONS = [
    'Asthma', 'Bronchitis', 'Speech delay', 'Visual impairment',
    'Hearing impairment', 'Allergy', 'Surgery', 'Others', 'None',
];
const healthAgreed       = ref(false);
const healthConditions   = ref([]);   // array of selected condition strings
const othersText         = ref('');
// Auto-filled from the STE form's guardian name — display only, not editable.
const healthGuardianName = computed(() => form.value.parent_guardian_name);
const healthDateSigned   = ref('');
const certAgreed         = ref(false);
const healthSignature    = ref('');   // drawn signature (PNG data URL)
const declarationCompleted = ref(false);
const declarationSignedAt  = ref('');
const printDeclaration = () => window.print();

// Signature pad lives in its own nested modal; keep a ref to capture/clear it.
const signaturePadRef = ref(null);

// None / Others mutual-exclusion helpers
const toggleCondition = (condition) => {
    if (condition === 'None') {
        if (healthConditions.value.includes('None')) {
            healthConditions.value = [];
        } else {
            healthConditions.value = ['None'];
            othersText.value = '';
        }
    } else {
        // If None was checked, remove it
        healthConditions.value = healthConditions.value.filter(c => c !== 'None');
        const idx = healthConditions.value.indexOf(condition);
        if (idx === -1) {
            healthConditions.value.push(condition);
        } else {
            healthConditions.value.splice(idx, 1);
            if (condition === 'Others') othersText.value = '';
        }
    }
};
const isChecked      = (c) => healthConditions.value.includes(c);
const showOthersText = computed(() => healthConditions.value.includes('Others'));
const today          = new Date().toISOString().slice(0, 10);

// Validation errors
const errors = ref({});

const isSTE   = computed(() => selectedProgram.value?.initials === 'STE');

// Already filtered server-side for the selected program.
const applicableRequirements = computed(() => requirements.value);

const requirementNotice = computed(() =>
    applicableRequirements.value.find(r => r.notice)?.notice || ''
);

const errorKey = (req) => req.error_key;

const requirementIcon = (req) => {
    if (req.type === 'declaration')                   return 'bi bi-heart-pulse me-1 text-danger';
    if (req.field_key === 'good_moral_file') return 'bi bi-award me-1 text-success';
    return 'bi bi-file-earmark-text me-1 text-primary';
};

// Reapplication (previous application was rejected)
const applicationStatus = ref('');
const rejectionReason   = ref('');

// ---------- Mount: guard + pre-fill ----------
onMounted(async () => {
    // Access guard: the form is only for applicants who have not submitted
    // yet, or whose previous application was rejected (reapplication).
    let application = null;
    let selected = null;
    try {
        const res = await axios.get('/api/applicant/application');
        if (res.data?.submitted) {
            application = res.data;
        }
        selected = res.data?.selected_program || null;
    } catch (_) { /* ignore */ }

    if (application && application.application_status !== 'rejected') {
        router.replace({ name: 'applicant.application.status' });
        return;
    }

    // The program is identified server-side from the program selection step.
    // Without a valid selection (deep link, expired session, deactivated
    // program), send the applicant back to the selection page.
    if (!selected) {
        router.replace({ name: 'applicant.application.select-program' });
        return;
    }
    selectedProgram.value = selected;

    // Fetch active requirements applicable to the selected program
    // (the server filters by the public program initials).
    try {
        const res = await axios.get('/api/applicant/requirements', {
            params: { program: selected.initials },
        });
        requirements.value = res.data;
    } catch (e) {
        console.error('Failed to fetch requirements:', e);
    }

    if (application) {
        // Pre-fill form with existing data if they're returning
        Object.assign(form.value, application);

        if (application.application_status === 'rejected') {
            applicationStatus.value = 'rejected';
            rejectionReason.value = application.rejection_reason || '';
        }
    } else {
        // First visit: derive name fields from the registered account
        const nameParts = (authApplicantStore.applicant?.name || '').trim().split(/\s+/).filter(Boolean);
        form.value.firstname  = nameParts[0] || '';
        form.value.middlename = nameParts.length > 2 ? nameParts.slice(1, -1).join(' ') : '';
        form.value.lastname   = nameParts[nameParts.length - 1] || '';
    }

    loading.value = false;
});

// ---------- File input handlers ----------
const onFileChange = (reqId, e) => {
    files.value[reqId] = e.target.files[0] || null;
};

// Contact number: digits only, max 11 characters.
const onContactNumberInput = (e) => {
    form.value.contact_number = e.target.value.replace(/\D/g, '').slice(0, 11);
};

const canSaveDeclaration = computed(() =>
    healthAgreed.value
    && certAgreed.value
    && healthConditions.value.length > 0
    && !(healthConditions.value.includes('Others') && !othersText.value.trim())
    && healthGuardianName.value.trim()
    && healthDateSigned.value
    && healthSignature.value
);

// ---------- Signature pad (nested modal) ----------
const padError = ref(false);

const openSignaturePad = () => {
    padError.value = false;
    // Always start on a blank canvas; the applied signature is only replaced
    // once the applicant confirms with "Use This Signature".
    signaturePadRef.value?.clear();

    const padElement = document.getElementById('hd-signature-modal');
    Modal.getOrCreateInstance(padElement).show();
};

const clearSignature = () => {
    signaturePadRef.value?.clear();
    padError.value = false;
};

const applySignature = () => {
    const dataURL = signaturePadRef.value?.toDataURL();
    if (!dataURL) {
        padError.value = true;
        return;
    }

    healthSignature.value = dataURL;
    padError.value = false;

    const padElement = document.getElementById('hd-signature-modal');
    const padInstance = Modal.getInstance(padElement);
    if (padInstance) padInstance.hide();
};

const removeSignature = () => {
    healthSignature.value = '';
};

const saveDeclaration = () => {
    if (!canSaveDeclaration.value) return;

    declarationCompleted.value = true;
    declarationSignedAt.value  = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

    const modalElement = document.getElementById('ste-declaration-modal');
    const modalInstance = Modal.getInstance(modalElement);
    if (modalInstance) modalInstance.hide();
};

const hasSteDeclarationErrors = computed(() =>
    Object.keys(errors.value).some((key) => key.startsWith('ste_declaration')));

// ---------- Submit ----------
const handleSubmit = async () => {
    errors.value = {};
    submitting.value = true;

    processingAlert('#submitBtn', 'Submitting your application…');

    const data = new FormData();
    data.append('lastname',                   form.value.lastname);
    data.append('firstname',                  form.value.firstname);
    data.append('middlename',                 form.value.middlename);
    data.append('extname',                    form.value.extname);
    data.append('date_of_birth',              form.value.date_of_birth);
    data.append('place_of_birth',             form.value.place_of_birth);
    data.append('citizenship',                form.value.citizenship);
    data.append('sex',                        form.value.sex);
    data.append('complete_address',           form.value.complete_address);
    data.append('parent_guardian_name',       form.value.parent_guardian_name);
    data.append('contact_number',             form.value.contact_number);
    data.append('elementary_school',          form.value.elementary_school);
    data.append('elementary_school_address',  form.value.elementary_school_address);

    applicableRequirements.value.forEach((req) => {
        const file = files.value[req.id];
        if (!file || req.type === 'declaration') return;
        data.append(req.input_key, file);
    });

    if (isSTE.value && declarationCompleted.value) {
        data.append('ste_declaration[learner_name]',       form.value.firstname + ' ' + form.value.lastname);
        data.append('ste_declaration[health_agreed]',      healthAgreed.value ? '1' : '0');
        healthConditions.value.forEach(c => data.append('ste_declaration[conditions][]', c));
        data.append('ste_declaration[others_text]',        othersText.value || '');
        data.append('ste_declaration[guardian_name]',      healthGuardianName.value);
        data.append('ste_declaration[date_signed]',        healthDateSigned.value);
        data.append('ste_declaration[signature]',          healthSignature.value);
        data.append('ste_declaration[cert_agreed]',        certAgreed.value ? '1' : '0');
    }

    try {
        const res = await axios.post('/api/applicant/application', data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        Swal.close();
        await Swal.fire({
            icon: 'success',
            title: 'Application Submitted!',
            text: res.data.message,
            confirmButtonColor: '#3085d6',
            allowOutsideClick: false,
            allowEscapeKey: false,
        });

        // Refresh the stored status (now pending) so the sidebar hides the
        // application form again until another rejection occurs.
        await authApplicantStore.getAuthApplicant();

        router.push({ name: 'applicant.application.status' });

    } catch (err) {
        Swal.close();
        if (err.response?.status === 422 && err.response.data.errors) {
            errors.value = err.response.data.errors;
            sweetAlert('error', 'Please correct the errors in the form and try again.');
        } else {
            errors.value = {};
            sweetAlert('error', err.response?.data?.message || 'Something went wrong.');
        }
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Application Form</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium">
                            <a href="/applicant/dashboard">Home</a>
                        </li>
                        <li class="breadcrumb-item fw-medium">
                            <router-link to="/applicant/application/select-program">Application</router-link>
                        </li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Form</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <!-- Loading skeleton -->
            <div v-if="loading" class="d-flex justify-content-center align-items-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading…</span>
                </div>
            </div>

            <form v-else @submit.prevent="handleSubmit" enctype="multipart/form-data">

                <!-- Reapplication notice (previous application was rejected) -->
                <div v-if="applicationStatus === 'rejected'" class="alert alert-danger border-0 shadow-sm mb-4">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                        <div>
                            <h6 class="mb-1 fw-bold text-dark">Your previous application was rejected.</h6>
                            <p class="mb-1 small text-secondary">
                                Please review the rejection reason, correct the missing or incorrect
                                information below, and resubmit your application.
                            </p>
                            <p v-if="rejectionReason" class="mb-0 small text-secondary">
                                <strong>Reason:</strong>
                                <span style="white-space: pre-wrap; word-break: break-word;">{{ rejectionReason }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Program Badge -->
                <div class="row mb-3">
                    <div class="col-lg-12">
                        <span class="badge me-2 fs-6 fw-semibold"
                            :class="isSTE ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'">
                            <i class="bi me-1" :class="isSTE ? 'bi-mortarboard-fill' : 'bi-pc-display-horizontal'"></i>
                            {{ selectedProgram?.initials }} Program
                        </span>
                        <router-link to="/applicant/application/select-program" class="btn btn-sm btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-left me-1"></i>Change Program
                        </router-link>
                    </div>
                </div>

                <!-- ─── Section 1: Personal Information ─── -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-body border-bottom">
                        <h6 class="mb-0">
                            <i class="bi bi-person-fill me-2 text-primary"></i>Personal Information
                        </h6>
                    </div>
                    <div class="card-body">

                        <!-- Row 1: Last / First / Middle / Ext -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="lastname" class="form-label fw-semibold">
                                    Last Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="lastname" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.lastname }"
                                    v-model="form.lastname"
                                    name="lastname" placeholder="Dela Cruz" required>
                                <div class="invalid-feedback" v-if="errors.lastname">{{ errors.lastname[0] }}</div>
                            </div>
                            <div class="col-md-4">
                                <label for="firstname" class="form-label fw-semibold">
                                    First Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="firstname" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.firstname }"
                                    v-model="form.firstname"
                                    name="firstname" placeholder="Juan" required>
                                <div class="invalid-feedback" v-if="errors.firstname">{{ errors.firstname[0] }}</div>
                            </div>
                            <div class="col-md-3">
                                <label for="middlename" class="form-label fw-semibold">Middle Name</label>
                                <input type="text" id="middlename" class="form-control form-control-sm"
                                    v-model="form.middlename"
                                    name="middlename" placeholder="Santos">
                            </div>
                            <div class="col-md-1">
                                <label for="extname" class="form-label fw-semibold">Ext.</label>
                                <input type="text" id="extname" class="form-control form-control-sm"
                                    v-model="form.extname"
                                    name="extname" placeholder="Jr.">
                            </div>
                        </div>

                        <!-- Row 2: DOB / Place of Birth / Citizenship / Sex -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="date_of_birth" class="form-label fw-semibold">
                                    Date of Birth <span class="text-danger">*</span>
                                </label>
                                <input type="date" id="date_of_birth" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.date_of_birth }"
                                    v-model="form.date_of_birth" required>
                                <div class="invalid-feedback" v-if="errors.date_of_birth">{{ errors.date_of_birth[0] }}</div>
                            </div>
                            <div class="col-md-4">
                                <label for="place_of_birth" class="form-label fw-semibold">
                                    Place of Birth <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="place_of_birth" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.place_of_birth }"
                                    v-model="form.place_of_birth" placeholder="City, Province" required>
                                <div class="invalid-feedback" v-if="errors.place_of_birth">{{ errors.place_of_birth[0] }}</div>
                            </div>
                            <div class="col-md-3">
                                <label for="citizenship" class="form-label fw-semibold">
                                    Citizenship <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="citizenship" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.citizenship }"
                                    v-model="form.citizenship" placeholder="Filipino" required>
                                <div class="invalid-feedback" v-if="errors.citizenship">{{ errors.citizenship[0] }}</div>
                            </div>
                            <div class="col-md-2">
                                <label for="sex" class="form-label fw-semibold">
                                    Sex <span class="text-danger">*</span>
                                </label>
                                <select id="sex" class="form-select form-select-sm"
                                    :class="{ 'is-invalid': errors.sex }"
                                    v-model="form.sex" required>
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                                <div class="invalid-feedback" v-if="errors.sex">{{ errors.sex[0] }}</div>
                            </div>
                        </div>

                        <!-- Row 3: Complete Address -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label for="complete_address" class="form-label fw-semibold">
                                    Complete Address <span class="text-danger">*</span>
                                </label>
                                <textarea id="complete_address" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.complete_address }"
                                    v-model="form.complete_address" rows="2"
                                    placeholder="House No., Street, Barangay, Municipality, Province" required></textarea>
                                <div class="invalid-feedback" v-if="errors.complete_address">{{ errors.complete_address[0] }}</div>
                            </div>
                        </div>

                        <!-- Row 4: Guardian / Contact / Email -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="parent_guardian_name" class="form-label fw-semibold">
                                    Name of Parent / Guardian <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="parent_guardian_name" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.parent_guardian_name }"
                                    v-model="form.parent_guardian_name" placeholder="Full name" required>
                                <div class="invalid-feedback" v-if="errors.parent_guardian_name">{{ errors.parent_guardian_name[0] }}</div>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_number" class="form-label fw-semibold">
                                    Contact Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="contact_number" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.contact_number }"
                                    :value="form.contact_number" @input="onContactNumberInput"
                                    inputmode="numeric" pattern="[0-9]*" maxlength="11"
                                    placeholder="09XXXXXXXXX" required>
                                <div class="invalid-feedback" v-if="errors.contact_number">{{ errors.contact_number[0] }}</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label for="email_display" class="form-label fw-semibold">
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" id="email_display" class="form-control form-control-sm"
                                    :value="authApplicantStore.applicant.email" readonly>
                                <small class="text-muted">
                                    <i class="bi bi-lock-fill me-1"></i>Used for notifications (automatically linked to your registered account email)
                                </small>
                            </div>
                        </div>

                        <!-- Row 5: Elementary School / Address -->
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="elementary_school" class="form-label fw-semibold">
                                    Elementary School <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="elementary_school" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.elementary_school }"
                                    v-model="form.elementary_school"
                                    placeholder="e.g. San Jose Elementary School" required>
                                <div class="invalid-feedback" v-if="errors.elementary_school">{{ errors.elementary_school[0] }}</div>
                            </div>
                            <div class="col-md-7">
                                <label for="elementary_school_address" class="form-label fw-semibold">
                                    Elementary School Address <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="elementary_school_address" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.elementary_school_address }"
                                    v-model="form.elementary_school_address"
                                    placeholder="e.g. Barangay, Municipality, Province" required>
                                <div class="invalid-feedback" v-if="errors.elementary_school_address">{{ errors.elementary_school_address[0] }}</div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ─── Section 2: Required Documents ─── -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-body border-bottom">
                        <h6 class="mb-0">
                            <i class="bi bi-folder-fill me-2 text-primary"></i>Required Documents
                            <small class="text-muted ms-2">(PDF, JPG, PNG — max 5MB each)</small>
                        </h6>
                    </div>
                    <div class="card-body">

                        <!-- Requirement Notice -->
                        <div v-if="requirementNotice" class="alert alert-warning d-flex align-items-start gap-2 mb-4">
                            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                            <div class="small">{{ requirementNotice }}</div>
                        </div>

                        <div class="row g-3">

                            <div v-for="req in applicableRequirements" :key="req.id"
                                :class="applicableRequirements.length === 1 ? 'col-md-12' : 'col-md-6'">

                                <!-- Online Health Declaration Form -->
                                <template v-if="req.type === 'declaration'">
                                    <label class="form-label fw-semibold">
                                        <i :class="requirementIcon(req)"></i>{{ req.name }}
                                        <span v-if="req.is_required" class="text-danger">*</span>
                                    </label>
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border rounded p-2"
                                        :class="{ 'border-success': declarationCompleted }">
                                        <small class="text-muted">Complete the Health Declaration Form online.</small>
                                        <button type="button" class="btn btn-warning btn-sm fw-semibold"
                                            data-bs-toggle="modal" data-bs-target="#ste-declaration-modal"
                                            title="Open health declaration">
                                            <i class="bi bi-pencil-square me-1"></i>
                                            {{ declarationCompleted ? 'View / Edit Declaration' : 'Complete Health Declaration Form' }}
                                        </button>
                                    </div>
                                    <div v-if="declarationCompleted" class="alert alert-success mt-2 mb-0 py-2 small">
                                        <i class="bi bi-check-circle-fill me-1"></i>
                                        Declaration completed and signed on <strong>{{ declarationSignedAt }}</strong>.
                                    </div>
                                    <div v-else-if="hasSteDeclarationErrors" class="alert alert-danger mt-2 mb-0 py-2 small">
                                        <i class="bi bi-exclamation-circle-fill me-1"></i>
                                        Please complete the Health Declaration Form before submitting.
                                    </div>
                                </template>

                                <!-- Document Upload -->
                                <template v-else>
                                    <label :for="'requirement_' + req.id" class="form-label fw-semibold">
                                        <i :class="requirementIcon(req)"></i>{{ req.name }}
                                        <span v-if="req.is_required" class="text-danger">*</span>
                                    </label>
                                    <input type="file" :id="'requirement_' + req.id"
                                        class="form-control form-control-sm"
                                        :class="{ 'is-invalid': errors[errorKey(req)] }"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        :required="req.is_required"
                                        @change="onFileChange(req.id, $event)">
                                    <div class="invalid-feedback" v-if="errors[errorKey(req)]">{{ errors[errorKey(req)][0] }}</div>
                                    <small class="text-muted" v-if="req.description">{{ req.description }}</small>
                                </template>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="row">
                    <div class="col-lg-12 d-flex flex-wrap justify-content-end gap-2">
                        <router-link to="/applicant/application/select-program"
                            class="btn btn-outline-secondary btn-sm fw-semibold">
                            <i class="bi bi-arrow-left me-1"></i>Back
                        </router-link>
                        <button type="submit" id="submitBtn"
                            class="btn btn-success btn-sm fw-semibold shadow-sm"
                            :disabled="submitting" title="Submit application">
                            <i class="bi bi-send-fill me-1"></i>Submit Application
                        </button>
                    </div>
                </div>

            </form>

            <!-- ─── Health Declaration Modal ─── -->
            <div class="modal fade" id="ste-declaration-modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content hd-modal">

                        <!-- Government / School Header -->
                        <div class="modal-header hd-header border-0 pb-0">
                            <img class="hd-header__logo" src="/images/deped%20logo.png" alt="Department of Education seal">
                            <div class="hd-header__text">
                                <div class="hd-header__gov">Republic of the Philippines</div>
                                <div class="hd-header__department">Department of Education</div>
                                <div class="hd-header__region">REGION III &ndash; CENTRAL LUZON</div>
                                <div class="hd-header__division">SCHOOLS DIVISION OFFICE OF NUEVA ECIJA</div>
                                <h5 class="hd-header__title mb-0">HEALTH DECLARATION FORM</h5>
                                <p class="hd-header__subtitle mb-0">Special Curricular Program in Science, Technology and Engineering &mdash; Carranglan National High School</p>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" title="Close"></button>
                        </div>
                        <div class="hd-divider"></div>

                        <div class="modal-body hd-body">

                            <!-- ── Section 1: Learner Information ── -->
                            <section class="hd-section">
                                <div class="hd-section__header">
                                    <span class="hd-section__num">1</span>
                                    <span class="hd-section__title">Learner Information</span>
                                </div>
                                <div class="hd-section__body">
                                    <label class="hd-label" for="hd-learner-name">Name of Learner <span class="text-danger">*</span></label>
                                    <input type="text" id="hd-learner-name" class="form-control hd-input"
                                        :value="(form.firstname + ' ' + form.middlename + ' ' + form.lastname).trim()"
                                        readonly>
                                    <small class="hd-help">Auto-filled from your personal information above.</small>
                                </div>
                            </section>

                            <!-- ── Section 2: Health Declaration ── -->
                            <section class="hd-section">
                                <div class="hd-section__header">
                                    <span class="hd-section__num">2</span>
                                    <span class="hd-section__title">Health Declaration</span>
                                </div>
                                <div class="hd-section__body">
                                    <div class="hd-statement">
                                        I hereby declare that my son/daughter is in good health and physically fit to participate in the rigorous academic activities of the Special Curricular Program in Science.
                                    </div>
                                    <label class="hd-agree" :class="{ 'hd-agree--checked': healthAgreed }" for="hd-health-agreed">
                                        <input class="hd-agree__input" type="checkbox" id="hd-health-agreed" v-model="healthAgreed">
                                        <span class="hd-agree__box"><i class="bi bi-check-lg"></i></span>
                                        <span class="hd-agree__text">I agree and confirm the health declaration above. <span class="text-danger">*</span></span>
                                    </label>
                                    <div v-if="!healthAgreed && healthConditions.length > 0" class="hd-error">
                                        <i class="bi bi-exclamation-circle-fill me-1"></i>
                                        You must agree to the health declaration to proceed.
                                    </div>
                                </div>
                            </section>

                            <!-- ── Section 3: Health / Physiological Concerns ── -->
                            <section class="hd-section">
                                <div class="hd-section__header">
                                    <span class="hd-section__num">3</span>
                                    <span class="hd-section__title">Health / Physiological Concerns</span>
                                </div>
                                <div class="hd-section__body">
                                    <p class="hd-help hd-help--block">
                                        Please check all condition/s that apply to your son/daughter and should be taken into consideration.
                                    </p>
                                    <div class="hd-conditions">
                                        <label v-for="condition in HEALTH_CONDITIONS" :key="condition" class="hd-condition-item"
                                            :class="{
                                                'hd-condition-item--checked': isChecked(condition),
                                                'hd-condition-item--none':    condition === 'None',
                                            }"
                                            @click.prevent="toggleCondition(condition)">
                                            <input type="checkbox" class="d-none" :checked="isChecked(condition)" tabindex="-1">
                                            <span class="hd-condition-check">
                                                <i v-if="isChecked(condition)" class="bi bi-check-square-fill"></i>
                                                <i v-else class="bi bi-square"></i>
                                            </span>
                                            <span class="hd-condition-text">{{ condition }}</span>
                                        </label>
                                    </div>
                                    <!-- Others specify -->
                                    <div v-if="showOthersText" class="mt-3">
                                        <label class="hd-label" for="hd-others-text">Please specify other condition/s <span class="text-danger">*</span></label>
                                        <input type="text" id="hd-others-text" class="form-control hd-input"
                                            v-model="othersText"
                                            placeholder="e.g. Diabetes, Epilepsy...">
                                        <div v-if="showOthersText && !othersText.trim()" class="hd-error mt-1">
                                            <i class="bi bi-exclamation-circle-fill me-1"></i>
                                            Please specify the other condition/s.
                                        </div>
                                    </div>
                                    <div v-if="healthAgreed && healthConditions.length === 0" class="hd-error mt-2">
                                        <i class="bi bi-exclamation-circle-fill me-1"></i>
                                        Please select at least one condition, or select "None".
                                    </div>
                                </div>
                            </section>

                            <!-- ── Section 4: Certification and Authorization ── -->
                            <section class="hd-section">
                                <div class="hd-section__header">
                                    <span class="hd-section__num">4</span>
                                    <span class="hd-section__title">Certification and Authorization</span>
                                </div>
                                <div class="hd-section__body">
                                    <div class="hd-statement hd-statement--cert">
                                        I hereby certify that the information provided above is true and correct to the best of my knowledge. I also authorize the Department of Education to use my child's details in relation to issuances concerning applications for Special Curricular Programs. All information provided will be treated with strict confidentiality in accordance with the Data Privacy Act of 2012.
                                    </div>
                                    <label class="hd-agree" :class="{ 'hd-agree--checked': certAgreed }" for="hd-cert-agreed">
                                        <input class="hd-agree__input" type="checkbox" id="hd-cert-agreed" v-model="certAgreed">
                                        <span class="hd-agree__box"><i class="bi bi-check-lg"></i></span>
                                        <span class="hd-agree__text">I agree and confirm the health declaration. <span class="text-danger">*</span></span>
                                    </label>
                                </div>
                            </section>

                            <!-- ── Section 5: Parent / Legal Guardian Information ── -->
                            <section class="hd-section">
                                <div class="hd-section__header">
                                    <span class="hd-section__num">5</span>
                                    <span class="hd-section__title">Parent / Legal Guardian Information</span>
                                </div>
                                <div class="hd-section__body">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label class="hd-label" for="hd-guardian-name">Parent / Legal Guardian Name <span class="text-danger">*</span></label>
                                            <input type="text" id="hd-guardian-name" class="form-control hd-input"
                                                :value="healthGuardianName" readonly
                                                :class="{ 'is-invalid': healthAgreed && !healthGuardianName.trim() }"
                                                placeholder="Full name of parent or guardian">
                                            <div class="invalid-feedback">Guardian name is required.</div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- ── Section 6: Signature and Date ── -->
                            <section class="hd-section">
                                <div class="hd-section__header">
                                    <span class="hd-section__num">6</span>
                                    <span class="hd-section__title">Signature and Date</span>
                                </div>
                                <div class="hd-section__body">
                                    <div class="row g-3">
                                        <div class="col-md-7">
                                            <label class="hd-label">
                                                Parent/Guardian Signature
                                                <span class="text-danger">*</span>
                                            </label>
                                            <div class="hd-signature">
                                                <img v-if="healthSignature" :src="healthSignature"
                                                    class="hd-signature__preview" alt="Drawn signature">
                                                <span v-else class="hd-signature__empty">
                                                    <i class="bi bi-pen me-1"></i>No signature yet.
                                                </span>
                                            </div>
                                            <div class="d-flex gap-2 mt-2 flex-wrap">
                                                <button type="button" class="btn btn-warning btn-sm fw-semibold"
                                                    @click="openSignaturePad" title="Draw signature">
                                                    <i class="bi bi-pencil-square me-1"></i>
                                                    {{ healthSignature ? 'Redraw Signature' : 'Sign Here' }}
                                                </button>
                                                <button v-if="healthSignature" type="button"
                                                    class="btn btn-outline-secondary btn-sm"
                                                    @click="removeSignature" title="Remove signature">
                                                    <i class="bi bi-eraser me-1"></i>Clear
                                                </button>
                                            </div>
                                            <small class="hd-help">Draw your signature; a mouse, finger, or stylus all work.</small>
                                            <div v-if="healthAgreed && !healthSignature" class="hd-error mt-1">
                                                <i class="bi bi-exclamation-circle-fill me-1"></i>
                                                Please draw your signature.
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="hd-label" for="hd-date-signed">Date Signed <span class="text-danger">*</span></label>
                                            <input type="date" id="hd-date-signed" class="form-control hd-input"
                                                v-model="healthDateSigned"
                                                :max="today"
                                                :class="{ 'is-invalid': healthAgreed && !healthDateSigned }">
                                            <div class="invalid-feedback">Date signed is required.</div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <!-- Validation summary -->
                            <div v-if="!canSaveDeclaration && healthAgreed" class="hd-alert">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Please complete all required fields before saving.
                            </div>

                        </div>

                        <!-- Footer: School / Government Office Contact -->
                        <div class="hd-footer">
                            <div class="hd-footer__logos">
                                <img src="/images/DepEd-MATATAG.png" alt="DepEd MATATAG">
                                <img src="/images/bagongpilipinas.jpg" alt="Bagong Pilipinas">
                                <img src="/images/cnhs_logo.png" alt="Carranglan National High School">
                            </div>
                            <div class="hd-footer__contact">
                                <span>Address: Brgy. Rizal, Santa Rosa, Nueva Ecija 3101</span>
                                <span>Telephone No.: (044) 940 3121</span>
                                <span>Email: nueva.ecija@deped.gov.ph</span>
                                <span>Facebook Page: DepEd SDO Nueva Ecija</span>
                                <span>Webpage: www.deped-ne.net.ph</span>
                            </div>
                        </div>

                        <div class="modal-footer hd-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold" @click="printDeclaration" title="Print declaration">
                                <i class="bi bi-printer-fill me-1"></i>Print
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm fw-semibold" data-bs-dismiss="modal" title="Close">
                                <i class="bi bi-x-circle-fill me-1"></i>Close
                            </button>
                            <button type="button" class="btn hd-btn-primary btn-sm fw-semibold"
                                :disabled="!canSaveDeclaration"
                                @click="saveDeclaration" title="Save declaration">
                                <i class="bi bi-check-circle-fill me-1"></i>Submit Health Declaration
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Signature Pad Modal (nested over the declaration modal) ─── -->
            <div class="modal fade" id="hd-signature-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h6 class="modal-title fw-semibold">
                                <i class="bi bi-pen me-2"></i>Draw Your Signature
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" title="Close"></button>
                        </div>
                        <div class="modal-body">
                            <SignaturePad ref="signaturePadRef" />
                            <div v-if="padError" class="hd-error mt-2">
                                <i class="bi bi-exclamation-circle-fill me-1"></i>
                                Please draw your signature before confirming.
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold"
                                @click="clearSignature" title="Clear signature">
                                <i class="bi bi-eraser me-1"></i>Clear
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm fw-semibold"
                                data-bs-dismiss="modal" title="Close">
                                Cancel
                            </button>
                            <button type="button" class="btn btn-warning btn-sm fw-semibold"
                                @click="applySignature" title="Apply signature">
                                <i class="bi bi-check-lg me-1"></i>Use This Signature
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ── Palette ─────────────────────────────────────────────────────── */
/* maroon #8B0000 · white #fff · light #f7f7f7 · light-maroon #fdf5f5 */

/* ── Modal shell ─────────────────────────────────────────────────── */
.hd-modal {
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, .18);
    background: #fff;
}

/* ── Government header ───────────────────────────────────────────── */
.hd-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    background: #fff;
    padding: 20px 24px 14px;
}
.hd-header__text { text-align: center; flex: 1; min-width: 0; }
.hd-header__logo {
    width: 76px;
    height: 76px;
    flex-shrink: 0;
    object-fit: contain;
}
.hd-header__department { font-size: 13px; font-weight: 700; }
.modal-header .btn-close { margin: 0; flex-shrink: 0; }
.hd-header__gov {
    font-size: 12px;
    letter-spacing: .04em;
    color: #444;
    text-transform: uppercase;
}
.hd-header__region {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: .06em;
    color: #8B0000;
    margin-top: 6px;
}
.hd-header__division { font-size: 11px; }
.hd-header__title {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: .04em;
    color: #1a1a1a;
    margin-top: 6px;
}
.hd-header__subtitle {
    font-size: 12.5px;
    color: #555;
    margin-top: 4px;
}
.hd-divider {
    height: 4px;
    background: #8B0000;
    margin: 0 24px;
    border-radius: 2px;
}

/* ── Modal body ──────────────────────────────────────────────────── */
.hd-body {
    font-size: 13.5px;
    line-height: 1.6;
    padding: 20px 24px;
    background: #f7f7f7;
}

/* ── Section cards (Google-Forms style) ──────────────────────────── */
.hd-section {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-left: 4px solid #8B0000;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
    padding: 18px 20px;
    margin-bottom: 16px;
}
.hd-section:last-of-type { margin-bottom: 0; }

.hd-section__header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}
.hd-section__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #8B0000;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
}
.hd-section__title {
    font-weight: 700;
    font-size: 14.5px;
    color: #1a1a1a;
    letter-spacing: .02em;
}
.hd-section__body { padding-left: 36px; }

/* ── Inputs ──────────────────────────────────────────────────────── */
.hd-label {
    display: block;
    font-weight: 600;
    font-size: 12.5px;
    margin-bottom: 4px;
    color: #333;
}
.hd-input {
    font-size: 13.5px;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 8px 12px;
    background: #fff;
}
.hd-input:focus {
    border-color: #8B0000;
    box-shadow: 0 0 0 .18rem rgba(139, 0, 0, .12);
}
.hd-input[readonly] {
    background: #f7f7f7;
    color: #333;
}
.hd-help {
    display: block;
    font-size: 12px;
    color: #666;
    margin-top: 4px;
}
.hd-help--block { margin-bottom: 10px; }

/* ── Statement blocks ────────────────────────────────────────────── */
.hd-statement {
    background: #f7f7f7;
    border-left: 4px solid #8B0000;
    padding: 12px 16px;
    border-radius: 0 6px 6px 0;
    font-style: italic;
    color: #444;
    line-height: 1.7;
}
.hd-statement--cert {
    background: #fdf5f5;
    border-left-color: #8B0000;
    color: #333;
    font-style: normal;
    border: 1px solid #f0dcdc;
    border-left: 4px solid #8B0000;
    border-radius: 0 6px 6px 0;
}

/* ── Agree checkbox ──────────────────────────────────────────────── */
.hd-agree {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    padding: 10px 12px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    user-select: none;
}
.hd-agree:hover { border-color: #8B0000; background: #fdf5f5; }
.hd-agree--checked { border-color: #8B0000; background: #fdf5f5; }
.hd-agree__input { position: absolute; opacity: 0; pointer-events: none; }
.hd-agree__box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: 2px solid #999;
    border-radius: 4px;
    background: #fff;
    color: transparent;
    font-size: 15px;
    flex-shrink: 0;
    transition: all .15s;
}
.hd-agree--checked .hd-agree__box {
    background: #8B0000;
    border-color: #8B0000;
    color: #fff;
}
.hd-agree__text { font-size: 13.5px; color: #333; }

/* ── Conditions grid ─────────────────────────────────────────────── */
.hd-conditions {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}
.hd-condition-item {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 52px;
    padding: 10px 14px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    transition: border-color .15s, background .15s, box-shadow .15s;
    user-select: none;
    font-size: 13.5px;
    color: #333;
}
.hd-condition-item:hover {
    border-color: #8B0000;
    background: #fdf5f5;
}
.hd-condition-item--checked {
    border-color: #8B0000;
    background: #fdf5f5;
    font-weight: 600;
    color: #1a1a1a;
}
.hd-condition-item--none.hd-condition-item--checked {
    border-color: #198754;
    background: #f0fdf4;
    color: #146c43;
}
.hd-condition-check { flex-shrink: 0; font-size: 18px; color: #999; }
.hd-condition-item--checked .hd-condition-check { color: #8B0000; }
.hd-condition-item--none.hd-condition-item--checked .hd-condition-check { color: #198754; }

/* ── Signature ────────────────────────────────────────────────────── */
.hd-signature {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 76px;
    padding: 6px;
    background: #fbfbff;
    border: 1.5px solid #e5e5e5;
    border-bottom: 2px solid #8B0000;
    border-radius: 4px;
    overflow: hidden;
}
.hd-signature__preview {
    max-width: 100%;
    height: 58px;
    object-fit: contain;
}
.hd-signature__empty {
    font-size: 12.5px;
    font-style: italic;
    color: #999;
}

/* ── Errors / alerts ─────────────────────────────────────────────── */
.hd-error {
    font-size: 12px;
    color: #dc3545;
}
.hd-alert {
    background: #fff8e1;
    border: 1px solid #ffe082;
    color: #8a6d00;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 12.5px;
    margin-top: 16px;
}

/* ── Footer: contact + logos ─────────────────────────────────────── */
.hd-footer {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 16px;
    flex-wrap: wrap;
    background: #fff;
    border-top: 2px solid #111;
    padding: 8px 24px;
}
.hd-footer__logos {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 auto;
}
.hd-footer__logos img {
    width: 48px;
    height: 44px;
    object-fit: contain;
}
.hd-footer__contact {
    display: grid;
    grid-template-columns: repeat(2, max-content);
    gap: 2px 10px;
    font-size: 8px;
    line-height: 1.2;
    color: #111;
}

/* ── Actions ─────────────────────────────────────────────────────── */
.hd-actions {
    background: #fff;
    border-top: 1px solid #e5e5e5;
    padding: 12px 24px;
}
.hd-btn-primary {
    background: #8B0000;
    border-color: #8B0000;
    color: #fff;
}
.hd-btn-primary:hover,
.hd-btn-primary:focus {
    background: #6d0000;
    border-color: #6d0000;
    color: #fff;
}
.hd-btn-primary:disabled {
    background: #c9a3a3;
    border-color: #c9a3a3;
    color: #fff;
}

/* ── Responsive ──────────────────────────────────────────────────── */
@media (max-width: 767.98px) {
    .hd-conditions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .hd-footer__contact { grid-template-columns: 1fr; }
    .hd-header { gap: 10px; }
    .hd-header__logo { width: 64px; height: 64px; }
}
@media (max-width: 575.98px) {
    .hd-header { padding: 16px 16px 12px; gap: 8px; }
    .hd-header__logo { width: 54px; height: 54px; }
    .hd-header__gov { font-size: 11px; }
    .hd-header__region { font-size: 12px; }
    .hd-header__title { font-size: 18px; }
    .hd-divider { margin: 0 16px; }
    .hd-body { padding: 16px; }
    .hd-section { padding: 14px 16px; }
    .hd-section__body { padding-left: 0; }
    .hd-conditions { grid-template-columns: 1fr; }
    .hd-footer { padding: 12px 16px; }
    .hd-actions { padding: 10px 16px; }
}

/* ── Print (A4) ──────────────────────────────────────────────────── */
@media print {
    @page { size: A4; margin: 12mm 15mm 29mm; }
    :global(body *) { visibility: hidden !important; }
    :global(#ste-declaration-modal),
    :global(#ste-declaration-modal *) { visibility: visible !important; }
    :global(#ste-declaration-modal) {
        position: absolute !important;
        inset: 0 auto auto 0 !important;
        display: block !important;
        width: 100% !important;
        height: auto !important;
        overflow: visible !important;
        background: #fff !important;
    }
    :global(#ste-declaration-modal .modal-dialog) {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
    }
    :global(#ste-declaration-modal .modal-content),
    :global(#ste-declaration-modal .modal-body) {
        max-height: none !important;
        height: auto !important;
        overflow: visible !important;
    }
    .hd-modal {
        border: none;
        border-radius: 0;
        box-shadow: none;
        overflow: visible;
    }
    .hd-header {
        display: block;
        padding: 0 0 6px;
        text-align: center;
        border-bottom: 2px solid #111 !important;
    }
    .hd-header__logo {
        display: block;
        width: 46px;
        height: 46px;
        margin: 0 auto;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .hd-header__text { text-align: center; }
    .hd-header__gov { font-size: 11px; line-height: 1.05; text-transform: none; letter-spacing: normal; }
    .hd-header__department { font-size: 13px; line-height: 1.05; }
    .hd-header__region,
    .hd-header__division { font-size: 11px; line-height: 1.05; margin-top: 0; color: #111; }
    .hd-header__title { font-size: 14px; line-height: 1.05; margin-top: 4px; }
    .hd-header__subtitle { font-size: 11px; line-height: 1.05; margin-top: 2px; }
    .hd-divider { display: none; }
    .hd-body { background: #fff; padding: 12px 0; overflow: visible; }
    .hd-section {
        break-inside: avoid;
        page-break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    .hd-actions,
    .btn-close { display: none !important; }
    .hd-footer { border-top: 2px solid #111; padding: 5px 0 0; margin: 0; break-inside: avoid; page-break-inside: avoid; }
    .hd-footer__logos img {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
