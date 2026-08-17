<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import { Modal } from 'bootstrap';
import { sweetAlert, processingAlert } from '@/sweetalert.js';
import { useAuthApplicantStore } from '../../stores/AuthApplicantStore';

const route  = useRoute();
const router = useRouter();
const authApplicantStore = useAuthApplicantStore();

const program = ref(route.query.program || '');
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

// File refs
const sf9File              = ref(null);
const goodMoralFile        = ref(null);

// Online STE Declaration
const declarationStatements = [
    'All information and documents submitted in this application are true, complete, and correct to the best of my knowledge.',
    'I understand that any falsification or misrepresentation of information will result in the immediate disqualification of my application.',
    'I voluntarily apply for the STE Program and commit to comply with all academic standards and policies of the school.',
    'I understand that admission into the STE Program is conditional upon verification of all submitted requirements.',
    'I consent to the collection and processing of my personal data for the purpose of this application, in accordance with the Data Privacy Act of 2012.',
];
const declarationAgreements = ref(Array(5).fill(false));
const declaration          = ref({ acknowledged_name: '', parent_guardian_name: '', parent_agreement: false });
const declarationCompleted = ref(false);
const declarationSignedAt  = ref('');

// Validation errors
const errors = ref({});

const isSTE   = computed(() => program.value === 'STE');
const isSPICT = computed(() => program.value === 'SPICT');

// ---------- Mount: guard + pre-fill ----------
onMounted(async () => {
    // If no program in query, redirect back to selection
    if (!['SPICT', 'STE'].includes(program.value)) {
        router.replace({ name: 'applicant.application.select-program' });
        return;
    }

    try {
        const res = await axios.get('/api/applicant/application');
        if (res.data?.submitted) {
            // Pre-fill form with existing data if they're returning
            Object.assign(form.value, res.data);
        } else {
            // First visit: derive name fields from the registered account
            const nameParts = (authApplicantStore.applicant?.name || '').trim().split(/\s+/).filter(Boolean);
            form.value.firstname  = nameParts[0] || '';
            form.value.middlename = nameParts.length > 2 ? nameParts.slice(1, -1).join(' ') : '';
            form.value.lastname   = nameParts[nameParts.length - 1] || '';
        }
    } catch (_) { /* ignore */ }

    loading.value = false;
});

// ---------- File input handlers ----------
const onSf9Change              = (e) => { sf9File.value       = e.target.files[0] || null; };
const onGoodMoralChange        = (e) => { goodMoralFile.value = e.target.files[0] || null; };

// ---------- Online STE Declaration ----------
const registeredName = computed(() => (authApplicantStore.applicant?.name || '').trim());

const nameTokens = (value) => (value || '')
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, ' ')
    .split(/\s+/)
    .filter(Boolean);

const namesMatch = (typed, expected) => {
    const typedTokens    = nameTokens(typed);
    const expectedTokens = nameTokens(expected);
    return typedTokens.length > 0 && expectedTokens.length > 0
        && expectedTokens.some((word) => typedTokens.includes(word));
};

const nameMismatch = computed(() =>
    !declaration.value.acknowledged_name ? 'The applicant acknowledgment is required.'
    : !namesMatch(declaration.value.acknowledged_name, registeredName.value) ? 'The typed name does not match your registered name.'
    : '');

const parentNameMismatch = computed(() =>
    !declaration.value.parent_guardian_name ? 'The parent/guardian acknowledgment is required.'
    : !namesMatch(declaration.value.parent_guardian_name, form.value.parent_guardian_name) ? 'The typed name does not match the parent/guardian name entered in the form.'
    : '');

const canSaveDeclaration = computed(() =>
    declarationAgreements.value.every(Boolean)
    && declaration.value.parent_agreement
    && !nameMismatch.value
    && !parentNameMismatch.value
    && form.value.date_of_birth
    && form.value.sex
    && form.value.complete_address.trim()
    && form.value.parent_guardian_name.trim()
    && form.value.contact_number.trim()
    && form.value.elementary_school.trim()
);

const saveDeclaration = () => {
    if (!canSaveDeclaration.value) return;

    declarationCompleted.value = true;
    declarationSignedAt.value = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

    const modalElement = document.getElementById('ste-declaration-modal');
    const modalInstance = Modal.getInstance(modalElement);
    if (modalInstance) {
        modalInstance.hide();
    }
};

const hasSteDeclarationErrors = computed(() =>
    Object.keys(errors.value).some((key) => key.startsWith('ste_declaration')));

// ---------- Submit ----------
const handleSubmit = async () => {
    errors.value = {};
    submitting.value = true;

    processingAlert('#submitBtn', 'Submitting your application…');

    const data = new FormData();
    data.append('program',                    program.value);
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

    if (sf9File.value)        data.append('sf9_file',              sf9File.value);
    if (goodMoralFile.value)  data.append('good_moral_file',       goodMoralFile.value);

    if (isSTE.value && declarationCompleted.value) {
        data.append('ste_declaration[acknowledged_name]',    declaration.value.acknowledged_name);
        data.append('ste_declaration[parent_guardian_name]', declaration.value.parent_guardian_name);
        declarationAgreements.value.forEach((checked) => data.append('ste_declaration[agreements][]', checked ? 1 : 0));
        data.append('ste_declaration[parent_agreement]', declaration.value.parent_agreement ? 1 : 0);
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

        router.push({ name: 'applicant.application.status' });

    } catch (err) {
        Swal.close();
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors || {};
            sweetAlert('error', 'Please correct the errors in the form and try again.');
        } else {
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

                <!-- Program Badge -->
                <div class="row mb-3">
                    <div class="col-lg-12">
                        <span class="badge me-2 fs-6 fw-semibold"
                            :class="isSTE ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'">
                            <i class="bi me-1" :class="isSTE ? 'bi-mortarboard-fill' : 'bi-pc-display-horizontal'"></i>
                            {{ program }} Program
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
                                    v-model="form.contact_number" placeholder="09XXXXXXXXX" required>
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

                        <!-- Grade Requirements Notice -->
                        <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
                            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                            <div class="small">
                                <strong>SF9 Grade Requirements:</strong>
                                At least <strong>85%</strong> in English, Science, and Mathematics;
                                at least <strong>83%</strong> in other subjects;
                                no grade lower than <strong>80%</strong>.
                            </div>
                        </div>

                        <div class="row g-3">

                            <!-- SF9 — Required for Both -->
                            <div class="col-md-6" :class="isSPICT ? 'col-md-12' : 'col-md-6'">
                                <label for="sf9_file" class="form-label fw-semibold">
                                    <i class="bi bi-file-earmark-text me-1 text-primary"></i>
                                    SF9 / Report Card <span class="text-danger">*</span>
                                </label>
                                <input type="file" id="sf9_file" class="form-control form-control-sm"
                                    :class="{ 'is-invalid': errors.sf9_file }"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    @change="onSf9Change" required>
                                <div class="invalid-feedback" v-if="errors.sf9_file">{{ errors.sf9_file[0] }}</div>
                                <small class="text-muted">Current school year report card</small>
                            </div>

                            <!-- STE-only documents -->
                            <template v-if="isSTE">

                                <div class="col-md-6">
                                    <label for="good_moral_file" class="form-label fw-semibold">
                                        <i class="bi bi-award me-1 text-success"></i>
                                        Certificate of Good Moral Character <span class="text-danger">*</span>
                                    </label>
                                    <input type="file" id="good_moral_file" class="form-control form-control-sm"
                                        :class="{ 'is-invalid': errors.good_moral_file }"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        @change="onGoodMoralChange" required>
                                    <div class="invalid-feedback" v-if="errors.good_moral_file">{{ errors.good_moral_file[0] }}</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-heart-pulse me-1 text-danger"></i>
                                        Health Declaration Form <span class="text-danger">*</span>
                                    </label>
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border rounded p-2"
                                        :class="{ 'border-success': declarationCompleted }">
                                        <small class="text-muted">Complete the Health Declaration Form online.</small>
                                        <button type="button" class="btn btn-warning btn-sm fw-semibold"
                                            data-bs-toggle="modal" data-bs-target="#ste-declaration-modal">
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
                                </div>

                            </template>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="row">
                    <div class="col-lg-12 d-flex justify-content-end gap-2">
                        <router-link to="/applicant/application/select-program"
                            class="btn btn-outline-secondary btn-sm fw-semibold">
                            <i class="bi bi-arrow-left me-1"></i>Back
                        </router-link>
                        <button type="submit" id="submitBtn"
                            class="btn btn-success btn-sm fw-semibold shadow-sm"
                            :disabled="submitting">
                            <span v-if="!submitting">
                                <i class="bi bi-send-fill me-1"></i>Submit Application
                            </span>
                            <span v-else>
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Submitting…
                            </span>
                        </button>
                    </div>
                </div>

            </form>

            <!-- ─── Online STE Declaration Modal ─── -->
            <div class="modal fade" id="ste-declaration-modal" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">
                                <i class="bi bi-file-earmark-check me-2 text-warning"></i>STE Program — Applicant Declaration Form
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body declaration-body">

                            <!-- School Header -->
                            <div class="declaration-header text-center border-bottom pb-3 mb-3">
                                <div class="text-uppercase fw-bold">Republic of the Philippines</div>
                                <div class="text-uppercase fw-bold">Department of Education</div>
                                <div class="text-uppercase fw-bold mt-1">Carranglan National High School</div>
                                <div class="mt-1">Carranglan, Nueva Ecija</div>
                                <div class="text-uppercase fw-bold mt-2" style="letter-spacing: 1px;">STE Program — Applicant Declaration Form</div>
                            </div>

                            <!-- Section I -->
                            <div class="declaration-section-title">I. Grade Requirements</div>
                            <p>
                                I hereby acknowledge that I am aware of and meet the following minimum grade requirements for admission into the
                                <strong>Science and Technology Education (STE) Program</strong> of Carranglan National High School:
                            </p>
                            <ul>
                                <li>At least <strong>85%</strong> in English, Science, and Mathematics for the current school year.</li>
                                <li>At least <strong>83%</strong> in all other learning areas.</li>
                                <li>No grade lower than <strong>80%</strong> in any subject.</li>
                            </ul>

                            <!-- Section II -->
                            <div class="declaration-section-title">II. Declaration</div>
                            <p>I, the undersigned applicant, solemnly declare and affirm the following:</p>
                            <div v-for="(statement, index) in declarationStatements" :key="index" class="form-check mb-2">
                                <input class="form-check-input" type="checkbox"
                                    :id="'ste-decl-agree-' + index"
                                    v-model="declarationAgreements[index]">
                                <label class="form-check-label" :for="'ste-decl-agree-' + index">
                                    {{ statement }}
                                </label>
                            </div>

                            <!-- Section III -->
                            <div class="declaration-section-title">III. Applicant Information</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="decl-full-name" class="form-label fw-semibold">Full Name</label>
                                    <input type="text" id="decl-full-name" class="form-control form-control-sm"
                                        :value="registeredName" readonly>
                                    <small class="text-muted">From your registered account</small>
                                </div>
                                <div class="col-md-3">
                                    <label for="decl-dob" class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" id="decl-dob" class="form-control form-control-sm"
                                        v-model="form.date_of_birth" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="decl-sex" class="form-label fw-semibold">Sex <span class="text-danger">*</span></label>
                                    <select id="decl-sex" class="form-select form-select-sm" v-model="form.sex" required>
                                        <option value="">Select</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-12">
                                    <label for="decl-address" class="form-label fw-semibold">Complete Address <span class="text-danger">*</span></label>
                                    <input type="text" id="decl-address" class="form-control form-control-sm"
                                        v-model="form.complete_address" placeholder="House No., Street, Barangay, Municipality, Province" required>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label for="decl-guardian" class="form-label fw-semibold">Name of Parent / Guardian <span class="text-danger">*</span></label>
                                    <input type="text" id="decl-guardian" class="form-control form-control-sm"
                                        v-model="form.parent_guardian_name" placeholder="Full name" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="decl-contact" class="form-label fw-semibold">Contact Number <span class="text-danger">*</span></label>
                                    <input type="text" id="decl-contact" class="form-control form-control-sm"
                                        v-model="form.contact_number" placeholder="09XXXXXXXXX" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="decl-email" class="form-label fw-semibold">Email</label>
                                    <input type="email" id="decl-email" class="form-control form-control-sm"
                                        :value="authApplicantStore.applicant.email" readonly>
                                    <small class="text-muted">From your registered account</small>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-12">
                                    <label for="decl-elem" class="form-label fw-semibold">Elementary School Graduated From <span class="text-danger">*</span></label>
                                    <input type="text" id="decl-elem" class="form-control form-control-sm"
                                        v-model="form.elementary_school"
                                        placeholder="e.g. San Jose Elementary School" required>
                                </div>
                            </div>

                            <!-- Digital Acknowledgment -->
                            <div class="declaration-section-title mt-3">Digital Acknowledgment</div>
                            <p class="mb-2">
                                By typing your full name below, you acknowledge that you have read, understood, and agreed to the declaration above.
                            </p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="ste-decl-applicant-name" class="form-label fw-semibold">
                                        Signature of Applicant (Type Full Name) <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="ste-decl-applicant-name" class="form-control form-control-sm"
                                        :class="{ 'is-invalid': nameMismatch }"
                                        v-model="declaration.acknowledged_name"
                                        placeholder="Type your full name" required>
                                    <div class="invalid-feedback">{{ nameMismatch }}</div>
                                    <small class="text-muted">Must match your registered name.</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="ste-decl-guardian-name" class="form-label fw-semibold">
                                        Signature of Parent / Guardian (Type Full Name) <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="ste-decl-guardian-name" class="form-control form-control-sm"
                                        :class="{ 'is-invalid': parentNameMismatch }"
                                        v-model="declaration.parent_guardian_name"
                                        placeholder="Type the full name of your parent / guardian" required>
                                    <div class="invalid-feedback">{{ parentNameMismatch }}</div>
                                    <small class="text-muted">Must match the parent / guardian name entered in the form.</small>
                                </div>
                            </div>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="ste-decl-parent-agreement"
                                    v-model="declaration.parent_agreement">
                                <label class="form-check-label" for="ste-decl-parent-agreement">
                                    I, the parent / guardian named above, acknowledge and give consent to this application and its declaration.
                                </label>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-warning btn-sm fw-semibold"
                                :disabled="!canSaveDeclaration"
                                @click="saveDeclaration">
                                <i class="bi bi-check-lg me-1"></i>Save Declaration
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.declaration-body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 13px;
    line-height: 1.7;
}
.declaration-header {
    color: #000;
}
.declaration-section-title {
    font-weight: bold;
    font-size: 13px;
    margin: 14px 0 8px 0;
    text-transform: uppercase;
    border-bottom: 1px solid #000;
    padding-bottom: 4px;
}
</style>
