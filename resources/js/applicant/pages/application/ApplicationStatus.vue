<script setup>
import { ref, onMounted, computed } from 'vue';

const loading       = ref(true);
const application   = ref(null);
const hasApplication = ref(false);

const statusColor = computed(() => {
    const s = application.value?.application_status;
    if (s === 'approved') return 'bg-success-subtle text-success';
    if (s === 'rejected') return 'bg-danger-subtle text-danger';
    return 'bg-warning-subtle text-warning';
});

const statusIcon = computed(() => {
    const s = application.value?.application_status;
    if (s === 'approved') return 'bi-check-circle-fill text-success';
    if (s === 'rejected') return 'bi-x-circle-fill text-danger';
    return 'bi-clock-fill text-warning';
});

onMounted(async () => {
    try {
        const res = await axios.get('/api/applicant/application');
        if (res.data?.submitted) {
            application.value = res.data;
            hasApplication.value = true;
        }
    } catch (_) {
        application.value = null;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Application Status</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium">
                            <a href="/applicant/dashboard">Home</a>
                        </li>
                        <li class="breadcrumb-item fw-medium">Application</li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Status</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <!-- Loading -->
            <div v-if="loading" class="d-flex justify-content-center align-items-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading…</span>
                </div>
            </div>

            <template v-if="hasApplication">

                <!-- Status Banner -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center gap-4 p-4">
                                <div>
                                    <i class="bi fs-1" :class="statusIcon"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge" :class="application.program === 'STE' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'">
                                            {{ application.program }}
                                        </span>
                                        <span class="badge text-capitalize" :class="statusColor">
                                            {{ application.application_status }}
                                        </span>
                                    </div>
                                    <h5 class="mb-1 fw-bold">
                                        <span v-if="application.application_status === 'approved'">Congratulations! Your application has been approved.</span>
                                        <span v-else-if="application.application_status === 'rejected'">Your application has been rejected.</span>
                                        <span v-else>Your application is currently under review.</span>
                                    </h5>
                                    <p class="text-muted small mb-0">
                                        <span v-if="application.application_status === 'approved'">
                                            You will receive an email at <strong>{{ application.email }}</strong> with your account credentials soon.
                                        </span>
                                        <span v-else-if="application.application_status === 'rejected'">
                                            Please contact the school for more information.
                                        </span>
                                        <span v-else>
                                            The administrator is reviewing your application. You will be notified via email at <strong>{{ application.email }}</strong>.
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal Info -->
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-body border-bottom">
                                <h6 class="mb-0">
                                    <i class="bi bi-person-fill me-2 text-primary"></i>Personal Information
                                </h6>
                            </div>
                            <div class="card-body" style="font-size: 13px;">
                                <div class="row">
                                    <div class="col-md-4 form-group mt-3">
                                        <label class="form-label">Last Name</label>
                                        <div class="fw-semibold">{{ application.lastname ?? '—' }}</div>
                                    </div>
                                    <div class="col-md-4 form-group mt-3">
                                        <label class="form-label">First Name</label>
                                        <div class="fw-semibold">{{ application.firstname ?? '—' }}</div>
                                    </div>
                                    <div class="col-md-3 form-group mt-3">
                                        <label class="form-label">Middle Name</label>
                                        <div class="fw-semibold">{{ application.middlename || '—' }}</div>
                                    </div>
                                    <div class="col-md-1 form-group mt-3">
                                        <label class="form-label">Ext.</label>
                                        <div class="fw-semibold">{{ application.extname || '—' }}</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 form-group mt-3">
                                        <label class="form-label">Date of Birth</label>
                                        <div class="fw-semibold">{{ application.date_of_birth || '—' }}</div>
                                    </div>
                                    <div class="col-md-4 form-group mt-3">
                                        <label class="form-label">Place of Birth</label>
                                        <div class="fw-semibold">{{ application.place_of_birth || '—' }}</div>
                                    </div>
                                    <div class="col-md-3 form-group mt-3">
                                        <label class="form-label">Citizenship</label>
                                        <div class="fw-semibold">{{ application.citizenship || '—' }}</div>
                                    </div>
                                    <div class="col-md-2 form-group mt-3">
                                        <label class="form-label">Sex</label>
                                        <div class="fw-semibold">{{ application.sex || '—' }}</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 form-group mt-3">
                                        <label class="form-label">Complete Address</label>
                                        <div class="fw-semibold">{{ application.complete_address || '—' }}</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-5 form-group mt-3">
                                        <label class="form-label">Parent / Guardian</label>
                                        <div class="fw-semibold">{{ application.parent_guardian_name || '—' }}</div>
                                    </div>
                                    <div class="col-md-4 form-group mt-3">
                                        <label class="form-label">Contact Number</label>
                                        <div class="fw-semibold">{{ application.contact_number || '—' }}</div>
                                    </div>
                                    <div class="col-md-3 form-group mt-3">
                                        <label class="form-label">Email Address</label>
                                        <div class="fw-semibold">{{ application.email }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submitted Documents -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-body border-bottom">
                                <h6 class="mb-0">
                                    <i class="bi bi-folder-fill me-2 text-primary"></i>Submitted Documents
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3" style="font-size: 13px;">

                                    <!-- SF9 -->
                                    <div class="col-md-3">
                                        <div class="border rounded p-3 text-center" style="min-height: 100px;">
                                            <div class="text-muted small mb-2">SF9 / Report Card</div>
                                            <template v-if="application.sf9_file">
                                                <a :href="application.sf9_file" target="_blank" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </a>
                                            </template>
                                            <span v-else class="text-muted small">Not submitted</span>
                                        </div>
                                    </div>

                                    <!-- Good Moral -->
                                    <div class="col-md-3" v-if="application.program === 'STE'">
                                        <div class="border rounded p-3 text-center" style="min-height: 100px;">
                                            <div class="text-muted small mb-2">Good Moral Certificate</div>
                                            <template v-if="application.good_moral_file">
                                                <a :href="application.good_moral_file" target="_blank" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </a>
                                            </template>
                                            <span v-else class="text-muted small">Not submitted</span>
                                        </div>
                                    </div>

                                    <!-- Health Declaration -->
                                    <div class="col-md-3" v-if="application.program === 'STE'">
                                        <div class="border rounded p-3 text-center" style="min-height: 100px;">
                                            <div class="text-muted small mb-2">Health Declaration Form</div>
                                            <template v-if="application.ste_declaration">
                                                <span class="badge bg-success-subtle text-success">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Completed Online
                                                </span>
                                            </template>
                                            <template v-else-if="application.health_declaration_file">
                                                <a :href="application.health_declaration_file" target="_blank" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </a>
                                            </template>
                                            <span v-else class="text-muted small">Not submitted</span>
                                        </div>
                                    </div>

                                    <!-- STE Declaration -->
                                    <div class="col-md-3" v-if="application.program === 'STE'">
                                        <div class="border rounded p-3 text-center" style="min-height: 100px;">
                                            <div class="text-muted small mb-2">STE Declaration Form</div>
                                            <template v-if="application.ste_declaration">
                                                <span class="badge bg-success-subtle text-success">
                                                    <i class="bi bi-check-circle-fill me-1"></i>Submitted
                                                </span>
                                            </template>
                                            <template v-else-if="application.ste_declaration_file">
                                                <a :href="application.ste_declaration_file" target="_blank" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </a>
                                            </template>
                                            <span v-else class="text-muted small">Not submitted</span>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </template>

            <!-- No application yet -->
            <template v-else>
                <div class="row justify-content-center">
                    <div class="col-lg-6 col-md-8">
                        <div class="card border-0 shadow-sm text-center py-5">
                            <div class="card-body">
                                <div class="mb-4">
                                    <i class="bi bi-file-earmark-text fs-1 text-muted"></i>
                                </div>
                                <h5 class="fw-bold mb-2">No Application Submitted Yet</h5>
                                <p class="text-muted small mb-4">
                                    You haven't submitted an enrollment application yet.
                                    Please fill out the application form to get started.
                                </p>
                                <router-link to="/applicant/application/select-program"
                                    class="btn btn-primary fw-semibold">
                                    <i class="bi bi-pencil-square me-1"></i>Go to Application Form
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

        </div>
    </div>
</template>

<style scoped>
label {
    font-size: 11px;
    font-weight: 600;
    color: #6c757d;
}
</style>
