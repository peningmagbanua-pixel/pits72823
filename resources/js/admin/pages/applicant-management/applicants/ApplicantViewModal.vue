<script setup>
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import { sweetAlert } from '@/sweetalert.js';
import { Modal } from 'bootstrap';

const emit = defineEmits(['status-updated']);

const applicant    = ref(null);
const activeTab    = ref('details');
const activityLogs = ref([]);
const logsLoading    = ref(false);
const statusUpdating = ref(false);

// ── Online STE Declaration (read-only) ──────────────────────────────────────
const declaration = computed(() => applicant.value?.ste_declaration || null);

const declarationStatements = [
    'All information and documents submitted in this application are true, complete, and correct to the best of my knowledge.',
    'I understand that any falsification or misrepresentation of information will result in the immediate disqualification of my application.',
    'I voluntarily apply for the STE Program and commit to comply with all academic standards and policies of the school.',
    'I understand that admission into the STE Program is conditional upon verification of all submitted requirements.',
    'I consent to the collection and processing of my personal data for the purpose of this application, in accordance with the Data Privacy Act of 2012.',
];

const openDeclaration = () => {
    const modalElement = document.getElementById('ste-declaration-view-modal');
    Modal.getOrCreateInstance(modalElement).show();
};

const hideModal = () => {
    const modalElement = document.getElementById('applicant-view-modal');
    const modalInstance = Modal.getInstance(modalElement);
    if (modalInstance) {
        modalInstance.hide();
    }
};

const getApplicant = (data) => {
    applicant.value = data;
    activeTab.value = 'details';
    activityLogs.value = [];
    const modalElement = document.getElementById('applicant-view-modal');
    const modal = Modal.getOrCreateInstance(modalElement);
    modal.show();
};

defineExpose({ getApplicant });

// ── Fetch Activity Logs ──────────────────────────────────────────────────────
const fetchActivityLogs = async () => {
    if (!applicant.value) return;
    logsLoading.value = true;
    try {
        const res = await axios.get(`/api/applicants/${applicant.value.id}/activity-logs`);
        activityLogs.value = res.data.data;
    } catch (e) {
        console.error('Failed to load activity logs:', e);
        activityLogs.value = [];
    } finally {
        logsLoading.value = false;
    }
};

const switchTab = (tab) => {
    activeTab.value = tab;
    if (tab === 'activity' && activityLogs.value.length === 0) {
        fetchActivityLogs();
    }
};

// ── Status Update ────────────────────────────────────────────────────────────
const updateStatus = (newStatus) => {
    const labels = { approved: 'Approve', rejected: 'Reject', pending: 'Reset to Pending' };
    const colors  = { approved: '#198754', rejected: '#dc3545', pending: '#ffc107' };

    Swal.fire({
        title: labels[newStatus] + ' this application?',
        text: "You are about to update this applicant's status!",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: colors[newStatus],
        cancelButtonColor: "#6c757d",
        confirmButtonText: 'Yes, ' + labels[newStatus] + '!',
        allowOutsideClick: false,
        allowEscapeKey: false,
    })
        .then((result) => {
            if (result.isConfirmed) {
                const previousStatus = applicant.value.application_status;
                applicant.value.application_status = newStatus;
                statusUpdating.value = true;

                axios.put(`/api/applicants/${applicant.value.id}/status`, { status: newStatus })
                    .then((response) => {
                        Swal.close();
                        hideModal();
                        sweetAlert('success', response.data.message);
                        emit('status-updated');
                    }).catch((error) => {
                        applicant.value.application_status = previousStatus;
                        statusUpdating.value = false;
                        Swal.close();
                        sweetAlert('error', error.response.data.message);
                    });
            }
        });
};

// ── Action Label Formatting ──────────────────────────────────────────────────
const actionLabel = (action) => ({
    registered:             '📝 Registered',
    registered_via_google:  '🔑 Registered via Google',
    logged_in:              '🔓 Logged In',
    status_approved:        '✅ Application Approved',
    status_rejected:        '❌ Application Rejected',
    status_reset:           '🔄 Status Reset to Pending',
    form_submitted:         '📋 Application Submitted',
}[action] ?? '⚡ ' + action);

const actionBadgeClass = (action) => {
    if (action.includes('approved') || action === 'logged_in') return 'bg-success-subtle text-success';
    if (action.includes('rejected') || action.includes('failed')) return 'bg-danger-subtle text-danger';
    if (action.includes('registered')) return 'bg-primary-subtle text-primary';
    if (action.includes('verification') || action.includes('reset')) return 'bg-warning-subtle text-warning';
    return 'bg-secondary-subtle text-secondary';
};
</script>

<template>
    <div class="modal fade" id="applicant-view-modal" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 p-1">
                <div class="modal-body" v-if="applicant">

                    <!-- Header -->
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <h6 class="mb-1">
                                <i class="bi bi-person-vcard-fill me-1"></i>
                                {{ applicant.name }}
                            </h6>
                            <div>
                                <span class="badge me-1"
                                    :class="applicant.program === 'STE' ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info'">
                                    {{ applicant.program }}
                                </span>
                                <span class="badge text-capitalize" :class="applicant.status_color">
                                    {{ applicant.application_status }}
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <small class="text-muted">Applied: {{ applicant.created_at }}</small>
                            <button class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <ul class="nav nav-tabs nav-tabs-sm mb-3" style="font-size: 13px;">
                        <li class="nav-item">
                            <button class="nav-link" :class="{ active: activeTab === 'details' }"
                                @click="switchTab('details')">
                                <i class="bi bi-person-fill me-1"></i>Details
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" :class="{ active: activeTab === 'documents' }"
                                @click="switchTab('documents')">
                                <i class="bi bi-folder-fill me-1"></i>Documents
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" :class="{ active: activeTab === 'activity' }"
                                @click="switchTab('activity')">
                                <i class="bi bi-clock-history me-1"></i>Activity Log
                            </button>
                        </li>
                    </ul>

                    <!-- ═══ TAB: Details ═══ -->
                    <div v-show="activeTab === 'details'">

                        <div class="section-label"><i class="bi bi-person-fill me-1"></i> Personal Information</div>
                        <div class="row" style="font-size: 13px;">
                            <div class="col-md-4 form-group mt-3">
                                <label class="form-label">Last Name</label>
                                <div class="fw-semibold">{{ applicant.lastname }}</div>
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label class="form-label">First Name</label>
                                <div class="fw-semibold">{{ applicant.firstname }}</div>
                            </div>
                            <div class="col-md-3 form-group mt-3">
                                <label class="form-label">Middle Name</label>
                                <div class="fw-semibold">{{ applicant.middlename || '—' }}</div>
                            </div>
                            <div class="col-md-1 form-group mt-3">
                                <label class="form-label">Ext.</label>
                                <div class="fw-semibold">{{ applicant.extname || '—' }}</div>
                            </div>
                        </div>
                        <div class="row" style="font-size: 13px;">
                            <div class="col-md-3 form-group mt-3">
                                <label class="form-label">Date of Birth</label>
                                <div class="fw-semibold">{{ applicant.date_of_birth || '—' }}</div>
                            </div>
                            <div class="col-md-4 form-group mt-3">
                                <label class="form-label">Place of Birth</label>
                                <div class="fw-semibold">{{ applicant.place_of_birth || '—' }}</div>
                            </div>
                            <div class="col-md-3 form-group mt-3">
                                <label class="form-label">Citizenship</label>
                                <div class="fw-semibold">{{ applicant.citizenship || '—' }}</div>
                            </div>
                            <div class="col-md-2 form-group mt-3">
                                <label class="form-label">Sex</label>
                                <div class="fw-semibold">{{ applicant.sex || '—' }}</div>
                            </div>
                        </div>
                        <div class="form-group mt-3" style="font-size: 13px;">
                            <label class="form-label">Complete Address</label>
                            <div class="fw-semibold">{{ applicant.complete_address || '—' }}</div>
                        </div>

                        <div class="section-label mt-3"><i class="bi bi-house-heart-fill me-1"></i> Family & School Information</div>
                        <div class="row" style="font-size: 13px;">
                            <div class="col-md-6 form-group mt-3">
                                <label class="form-label">Parent / Guardian</label>
                                <div class="fw-semibold">{{ applicant.parent_guardian_name || '—' }}</div>
                            </div>
                            <div class="col-md-6 form-group mt-3">
                                <label class="form-label">Contact Number</label>
                                <div class="fw-semibold">{{ applicant.contact_number || '—' }}</div>
                            </div>
                        </div>
                        <div class="row" style="font-size: 13px;">
                            <div class="col-md-5 form-group mt-3">
                                <label class="form-label">Elementary School</label>
                                <div class="fw-semibold">{{ applicant.elementary_school || '—' }}</div>
                            </div>
                            <div class="col-md-7 form-group mt-3">
                                <label class="form-label">Elementary School Address</label>
                                <div class="fw-semibold">{{ applicant.elementary_school_address || '—' }}</div>
                            </div>
                        </div>

                        <div class="section-label mt-3"><i class="bi bi-envelope-fill me-1"></i> Account Email</div>
                        <div class="form-group mt-3" style="font-size: 13px;">
                            <label class="form-label">Email</label>
                            <div class="fw-semibold">{{ applicant.email }}</div>
                        </div>
                    </div>

                    <!-- ═══ TAB: Documents ═══ -->
                    <div v-show="activeTab === 'documents'">
                        <div class="section-label"><i class="bi bi-folder-fill me-1"></i> Submitted Documents</div>
                        <div class="row mt-3 g-3" style="font-size: 13px;">
                            <div class="col-md-3">
                                <div class="border rounded p-2 text-center doc-card">
                                    <div class="text-muted small mb-2"><i class="bi bi-file-earmark-text fs-4 d-block mb-1" style="color:#8B0000;"></i>SF9 / Report Card</div>
                                    <template v-if="applicant.sf9_file">
                                        <a :href="applicant.sf9_file" target="_blank" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                    </template>
                                    <span v-else class="text-muted small">Not submitted</span>
                                </div>
                            </div>
                            <div class="col-md-3" v-if="applicant.program === 'STE'">
                                <div class="border rounded p-2 text-center doc-card">
                                    <div class="text-muted small mb-2"><i class="bi bi-award fs-4 d-block mb-1" style="color:#8B0000;"></i>Good Moral Certificate</div>
                                    <template v-if="applicant.good_moral_file">
                                        <a :href="applicant.good_moral_file" target="_blank" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                    </template>
                                    <span v-else class="text-muted small">Not submitted</span>
                                </div>
                            </div>
                            <div class="col-md-3" v-if="applicant.program === 'STE'">
                                <div class="border rounded p-2 text-center doc-card">
                                    <div class="text-muted small mb-2"><i class="bi bi-heart-pulse fs-4 d-block mb-1" style="color:#8B0000;"></i>Health Declaration</div>
                                    <template v-if="applicant.ste_declaration">
                                        <button class="btn btn-outline-danger btn-sm w-100" @click="openDeclaration">
                                            <i class="bi bi-eye me-1"></i>View Declaration
                                        </button>
                                    </template>
                                    <template v-else-if="applicant.health_declaration_file">
                                        <a :href="applicant.health_declaration_file" target="_blank" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                    </template>
                                    <template v-else-if="applicant.ste_declaration_file">
                                        <a :href="applicant.ste_declaration_file" target="_blank" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                    </template>
                                    <span v-else class="text-muted small">Not submitted</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ TAB: Activity Log ═══ -->
                    <div v-show="activeTab === 'activity'">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="section-label mb-0"><i class="bi bi-clock-history me-1"></i> Activity Log</div>
                            <button class="btn btn-outline-secondary btn-sm" @click="fetchActivityLogs" :disabled="logsLoading">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                        </div>

                        <!-- Loading -->
                        <div v-if="logsLoading" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2"></div>Loading activity log…
                        </div>

                        <!-- Empty -->
                        <div v-else-if="activityLogs.length === 0" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-4 d-block mb-1"></i>No activity recorded yet.
                        </div>

                        <!-- Timeline -->
                        <div v-else class="activity-timeline mt-2">
                            <div v-for="log in activityLogs" :key="log.id" class="activity-item">
                                <div class="activity-dot" :class="actionBadgeClass(log.action)"></div>
                                <div class="activity-content">
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <span class="badge activity-badge" :class="actionBadgeClass(log.action)">
                                            {{ actionLabel(log.action) }}
                                        </span>
                                        <small class="text-muted text-nowrap">{{ log.created_at }}</small>
                                    </div>
                                    <div class="activity-desc">{{ log.description }}</div>
                                    <div v-if="log.ip_address" class="activity-meta">
                                        <i class="bi bi-geo me-1"></i>{{ log.ip_address }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ Action Buttons ═══ -->
                    <hr>
                    <div class="d-flex gap-2 flex-wrap">
                        <button v-if="applicant.application_status !== 'approved' && !statusUpdating"
                            class="btn btn-success btn-sm"
                            @click="updateStatus('approved')">
                            <i class="bi bi-check-circle-fill me-1"></i>Approve
                        </button>
                        <button v-if="applicant.application_status !== 'rejected' && !statusUpdating"
                            class="btn btn-danger btn-sm"
                            @click="updateStatus('rejected')">
                            <i class="bi bi-x-circle-fill me-1"></i>Reject
                        </button>
                        <button v-if="applicant.application_status !== 'pending'"
                            class="btn btn-warning btn-sm"
                            @click="updateStatus('pending')">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Pending
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ Read-Only STE Declaration Modal ═══ -->
    <div v-if="declaration" class="modal fade" id="ste-declaration-view-modal" data-bs-backdrop="static" data-bs-keyboard="false">
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
                            :checked="!!declaration.agreements?.[index]"
                            disabled>
                        <label class="form-check-label">{{ statement }}</label>
                    </div>

                    <!-- Section III -->
                    <div class="declaration-section-title">III. Applicant Information</div>
                    <div class="row g-2 mb-1">
                        <div class="col-md-6">
                            <span class="fw-bold">Full Name:</span> {{ declaration.applicant_snapshot?.full_name || applicant.name }}
                        </div>
                        <div class="col-md-3">
                            <span class="fw-bold">Date of Birth:</span> {{ declaration.applicant_snapshot?.date_of_birth || applicant.date_of_birth }}
                        </div>
                        <div class="col-md-3">
                            <span class="fw-bold">Sex:</span> {{ declaration.applicant_snapshot?.sex || applicant.sex }}
                        </div>
                    </div>
                    <div class="row g-2 mb-1">
                        <div class="col-md-12">
                            <span class="fw-bold">Complete Address:</span> {{ declaration.applicant_snapshot?.complete_address || applicant.complete_address }}
                        </div>
                    </div>
                    <div class="row g-2 mb-1">
                        <div class="col-md-6">
                            <span class="fw-bold">Name of Parent / Guardian:</span> {{ declaration.applicant_snapshot?.parent_guardian_name || applicant.parent_guardian_name }}
                        </div>
                        <div class="col-md-3">
                            <span class="fw-bold">Contact Number:</span> {{ declaration.applicant_snapshot?.contact_number || applicant.contact_number }}
                        </div>
                        <div class="col-md-3">
                            <span class="fw-bold">Email:</span> {{ declaration.applicant_snapshot?.email || applicant.email }}
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-12">
                            <span class="fw-bold">Elementary School Graduated From:</span> {{ declaration.applicant_snapshot?.elementary_school || applicant.elementary_school }}
                        </div>
                    </div>

                    <!-- Acknowledgment -->
                    <div class="declaration-section-title mt-3">Digital Acknowledgment</div>
                    <div class="row g-2 mb-1">
                        <div class="col-md-6">
                            <span class="fw-bold">Acknowledged by (Applicant):</span> {{ declaration.acknowledged_name }}
                        </div>
                        <div class="col-md-6">
                            <span class="fw-bold">Acknowledged by (Parent / Guardian):</span> {{ declaration.parent_guardian_name }}
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-12">
                            <span class="fw-bold">Signed on:</span> {{ declaration.signed_at }}
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.section-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #6c757d;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 5px;
    margin-bottom: 10px;
    margin-top: 6px;
}
label {
    font-size: 11px;
    font-weight: 600;
}

/* ── Read-only declaration ── */
.declaration-body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 13px;
    line-height: 1.7;
}
.declaration-section-title {
    font-weight: bold;
    font-size: 13px;
    margin: 14px 0 8px 0;
    text-transform: uppercase;
    border-bottom: 1px solid #000;
    padding-bottom: 4px;
}

/* ── Document cards ── */
.doc-card {
    min-height: 100px;
    transition: box-shadow .2s;
}
.doc-card:hover {
    box-shadow: 0 4px 12px rgba(139,0,0,.1);
}

/* ── Activity Timeline ── */
.activity-timeline {
    position: relative;
    padding-left: 20px;
}
.activity-timeline::before {
    content: '';
    position: absolute;
    left: 7px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}
.activity-item {
    position: relative;
    margin-bottom: 16px;
    padding-left: 16px;
}
.activity-dot {
    position: absolute;
    left: -14px;
    top: 6px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 1px #dee2e6;
    background: #6c757d;
}
.activity-dot.bg-success-subtle { background: #198754; }
.activity-dot.bg-danger-subtle  { background: #dc3545; }
.activity-dot.bg-primary-subtle { background: #0d6efd; }
.activity-dot.bg-warning-subtle { background: #ffc107; }
.activity-content {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 10px 14px;
}
.activity-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 4px 8px;
}
.activity-desc {
    font-size: 12px;
    color: #495057;
    margin-top: 4px;
}
.activity-meta {
    font-size: 11px;
    color: #adb5bd;
    margin-top: 3px;
}
</style>
