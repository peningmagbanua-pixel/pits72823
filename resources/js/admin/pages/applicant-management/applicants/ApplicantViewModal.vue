<script setup>
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import { sweetAlert, processingAlert } from '@/sweetalert.js';
import { Modal } from 'bootstrap';

const emit = defineEmits(['status-updated']);

const props = defineProps({
    canUpdate: {
        type: Boolean,
        default: false,
    },
});

const applicant    = ref(null);
const activeTab    = ref('details');
const activityLogs = ref([]);
const logsLoading    = ref(false);
const statusUpdating = ref(false);

// ── Online Health Declaration (read-only) ──────────────────────────────────
const declaration = computed(() => applicant.value?.ste_declaration || null);

const conditionLabel = (c) => ({
    'Asthma':              'Asthma',
    'Bronchitis':          'Bronchitis',
    'Speech delay':        'Speech delay',
    'Visual impairment':   'Visual impairment',
    'Hearing impairment':  'Hearing impairment',
    'Allergy':             'Allergy',
    'Surgery':             'Surgery',
    'Others':              'Others',
    'None':                'None',
}[c] ?? c);

const openDeclaration = () => {
    const modalElement = document.getElementById('ste-declaration-view-modal');
    Modal.getOrCreateInstance(modalElement).show();
};

const docIcon = (doc) => {
    if (doc.type === 'declaration')     return 'bi bi-heart-pulse';
    if (doc.field_key === 'good_moral_file') return 'bi bi-award';
    return 'bi bi-file-earmark-text';
};

const hideModal = () => {
    const modalElement = document.getElementById('applicant-view-modal');
    const modalInstance = Modal.getOrCreateInstance(modalElement);
    modalInstance.hide();
};

const getApplicant = (data) => {
    applicant.value = data;
    activeTab.value = 'details';
    activityLogs.value = [];
    statusUpdating.value = false;
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
// All three actions share one confirmation design (same color, same layout)
// so the admin flow stays simple and consistent.
const STATUS_ACTIONS = {
    approved: {
        label: 'Approve',
        message: 'You are about to approve this application.',
    },
    rejected: {
        label: 'Reject',
        message: 'You are about to reject this application.',
    },
    pending: {
        label: 'Reset to Pending',
        message: 'You are about to reset this application back to pending.',
    },
};

const STATUS_CONFIRM_COLOR = '#0d6efd';

// The open applicant modal traps focus inside itself, so focusing the
// SweetAlert textarea would immediately steal focus back and make it
// impossible to type. Release the trap while the dialog is open and
// restore it once the dialog closes.
const setModalFocusTrap = (active) => {
    const modalElement = document.getElementById('applicant-view-modal');
    const modalInstance = modalElement ? Modal.getInstance(modalElement) : null;
    const focusTrap = modalInstance?._focustrap;

    if (!focusTrap) return;

    if (active) {
        if (modalElement.classList.contains('show')) focusTrap.activate();
    } else {
        focusTrap.deactivate();
    }
};

const buildStatusDialog = (newStatus) => {
    const action = STATUS_ACTIONS[newStatus];

    const options = {
        title: action.label + ' this application?',
        text: action.message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: STATUS_CONFIRM_COLOR,
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, ' + action.label + '!',
        allowOutsideClick: false,
        allowEscapeKey: false,
    };

    if (newStatus === 'rejected') {
        options.input = 'textarea';
        options.inputLabel = 'Rejection reason (required)';
        options.inputPlaceholder =
            'Explain what is missing, incorrect, or needs to be corrected in the submitted requirements. This will be sent to the applicant.';
        options.inputAttributes = {
            rows: 5,
            'aria-label': 'Rejection reason',
        };
        options.inputValidator = (value) => {
            if (value && value.trim().length > 0) return null;
            return 'Please provide a rejection reason before rejecting this application.';
        };
        options.willOpen = () => setModalFocusTrap(false);
        options.willClose = () => setModalFocusTrap(true);
    }

    return options;
};

const updateStatus = (newStatus) => {
    // Ignore extra clicks while a status request is already in flight.
    if (statusUpdating.value) return;

    Swal.fire(buildStatusDialog(newStatus))
        .then((result) => {
            if (result.isConfirmed) {
                const previousStatus = applicant.value.application_status;
                applicant.value.application_status = newStatus;
                statusUpdating.value = true;
                processingAlert('.btn');

                const payload = { status: newStatus };
                if (newStatus === 'rejected') {
                    payload.rejection_reason = (result.value || '').trim();
                }

                axios.put(`/api/applicants/${applicant.value.id}/status`, payload)
                    .then((response) => {
                        statusUpdating.value = false;
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
                    <div class="d-flex align-items-start justify-content-between pe-4 mb-2">
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

                        <template v-if="applicant.application_status === 'rejected' && applicant.rejection_reason">
                            <div class="section-label mt-3"><i class="bi bi-exclamation-circle-fill me-1"></i> Rejection Reason</div>
                            <div class="p-2 rounded border-start border-4"
                                style="border-color: #8B0000 !important; background: #f8f9fa; font-size: 13px; white-space: pre-wrap; word-break: break-word;">
                                {{ applicant.rejection_reason }}
                            </div>
                        </template>
                    </div>

                    <!-- ═══ TAB: Documents ═══ -->
                    <div v-show="activeTab === 'documents'">
                        <div class="section-label"><i class="bi bi-folder-fill me-1"></i> Submitted Documents</div>
                        <div class="row mt-3 g-3" style="font-size: 13px;">
                            <div class="col-md-3" v-for="doc in (applicant.documents || [])" :key="doc.requirement_id">
                                <div class="border rounded p-2 text-center doc-card">
                                    <div class="text-muted small mb-2">
                                        <i :class="docIcon(doc) + ' fs-4 d-block mb-1'" style="color:#8B0000;"></i>{{ doc.name }}
                                    </div>
                                    <span v-if="doc.is_orphan" class="badge bg-secondary-subtle text-secondary d-block mb-2">
                                        Requirement removed
                                    </span>
                                    <template v-if="doc.completed">
                                        <button class="btn btn-outline-danger btn-sm w-100" @click="openDeclaration">
                                            <i class="bi bi-eye me-1"></i>View Declaration
                                        </button>
                                    </template>
                                    <template v-else-if="doc.url">
                                        <a :href="doc.url" target="_blank" class="btn btn-outline-danger btn-sm w-100">
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
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
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
                        <button v-if="props.canUpdate && applicant.application_status === 'pending'"
                            class="btn btn-success btn-sm"
                            :disabled="statusUpdating"
                            @click="updateStatus('approved')">
                            <i class="bi bi-check-circle-fill me-1"></i>Approve
                        </button>
                        <button v-if="props.canUpdate && applicant.application_status === 'pending'"
                            class="btn btn-danger btn-sm"
                            :disabled="statusUpdating"
                            @click="updateStatus('rejected')">
                            <i class="bi bi-x-circle-fill me-1"></i>Reject
                        </button>
                        <button v-if="props.canUpdate && applicant.application_status !== 'pending'"
                            class="btn btn-warning btn-sm"
                            :disabled="statusUpdating"
                            @click="updateStatus('pending')">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Pending
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ Read-Only Health Declaration Modal ═══ -->
    <div v-if="declaration" class="modal fade" id="ste-declaration-view-modal" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold mb-1">
                            <i class="bi bi-heart-pulse-fill me-2 text-danger"></i>Health Declaration Form
                        </h5>
                        <p class="text-muted small mb-0">Special Curricular Program in Science — Carranglan National High School</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="font-size:13px;">

                    <!-- Section 1: Learner Information -->
                    <div class="hd-view-section">
                        <div class="hd-view-section__title">
                            <span class="hd-view-section__num">1</span> Learner Information
                        </div>
                        <div class="hd-view-field">
                            <span class="hd-view-label">Name of Learner</span>
                            <span class="hd-view-value">{{ declaration.learner_name || declaration.applicant_snapshot?.full_name || applicant.name }}</span>
                        </div>
                    </div>

                    <!-- Section 2: Health Declaration -->
                    <div class="hd-view-section">
                        <div class="hd-view-section__title">
                            <span class="hd-view-section__num">2</span> Health Declaration
                        </div>
                        <div class="hd-view-statement">
                            I hereby declare that my son/daughter is in good health and physically fit to participate in the rigorous academic activities of the Special Curricular Program in Science.
                        </div>
                        <div class="mt-2 d-flex align-items-center gap-2">
                            <i class="bi"
                                :class="declaration.health_agreed ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'"
                                style="font-size:1rem;"></i>
                            <span :class="declaration.health_agreed ? 'text-success fw-medium' : 'text-danger'">
                                {{ declaration.health_agreed ? 'Agreed and confirmed the health declaration.' : 'Did not agree to the health declaration.' }}
                            </span>
                        </div>
                    </div>

                    <!-- Section 3: Health / Physiological Concerns -->
                    <div class="hd-view-section">
                        <div class="hd-view-section__title">
                            <span class="hd-view-section__num">3</span> Health / Physiological Concerns
                        </div>
                        <p class="text-muted small mb-2">Conditions checked by the applicant's parent/guardian:</p>

                        <div v-if="declaration.conditions && declaration.conditions.length > 0" class="d-flex flex-wrap gap-2 mb-2">
                            <span v-for="c in declaration.conditions" :key="c"
                                class="badge rounded-pill px-3 py-2"
                                :class="c === 'None' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'"
                                style="font-size:12px;">
                                {{ conditionLabel(c) }}
                            </span>
                        </div>
                        <div v-else class="text-muted fst-italic small">No conditions specified.</div>

                        <div v-if="declaration.others_text" class="mt-2">
                            <span class="fw-semibold small">Other condition/s specified:</span>
                            <span class="ms-1 text-dark">{{ declaration.others_text }}</span>
                        </div>
                    </div>

                    <!-- Section 4: Parent / Legal Guardian Certification -->
                    <div class="hd-view-section">
                        <div class="hd-view-section__title">
                            <span class="hd-view-section__num">4</span> Parent / Legal Guardian Certification
                        </div>
                        <div class="hd-view-statement mb-3">
                            I hereby certify that the information provided above is true and correct to the best of my knowledge. I also authorize the Department of Education to use my child's details in relation to issuances concerning applications for Special Curricular Programs. All information provided will be treated with strict confidentiality in accordance with the Data Privacy Act of 2012.
                        </div>
                        <div v-if="'cert_agreed' in declaration" class="mb-3 d-flex align-items-center gap-2">
                            <i class="bi"
                                :class="declaration.cert_agreed ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'"
                                style="font-size:1rem;"></i>
                            <span :class="declaration.cert_agreed ? 'text-success fw-medium' : 'text-danger'">
                                {{ declaration.cert_agreed ? 'Agreed and confirmed the certification and authorization.' : 'Did not agree to the certification and authorization.' }}
                            </span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="hd-view-field">
                                    <span class="hd-view-label">Parent / Legal Guardian Name</span>
                                    <span class="hd-view-value">{{ declaration.guardian_name || declaration.parent_guardian_name || applicant.parent_guardian_name }}</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="hd-view-field">
                                    <span class="hd-view-label">Date Signed</span>
                                    <span class="hd-view-value">{{ declaration.date_signed || declaration.signed_at }}</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="hd-view-field">
                                    <span class="hd-view-label">Submitted At</span>
                                    <span class="hd-view-value">{{ declaration.signed_at || '—' }}</span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="hd-view-field">
                                    <span class="hd-view-label">Signature (Digital)</span>
                                    <!-- Drawn signature captured by the signature pad -->
                                    <span v-if="declaration.signature" class="hd-view-value">
                                        <img :src="declaration.signature" alt="Drawn signature"
                                            style="max-height:60px;border:1px solid #e5e5e5;border-radius:4px;background:#fff;padding:4px 8px;">
                                    </span>
                                    <!-- Legacy declarations only stored the typed name -->
                                    <span v-else class="hd-view-value" style="font-family:'Brush Script MT',cursive;font-size:16px;color:#1a1a5e;">
                                        {{ declaration.guardian_name || declaration.parent_guardian_name || '—' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-0">
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

/* ── Read-only Health Declaration ── */
.hd-view-section {
    border-bottom: 1px solid #f0f0f0;
    padding: 16px 0;
}
.hd-view-section:last-child { border-bottom: none; }
.hd-view-section__title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    font-size: 13px;
    color: #333;
    margin-bottom: 12px;
}
.hd-view-section__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #800000;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
}
.hd-view-statement {
    background: #f8f9fa;
    border-left: 4px solid #800000;
    padding: 10px 14px;
    border-radius: 0 6px 6px 0;
    font-style: italic;
    color: #444;
    line-height: 1.6;
    font-size: 12.5px;
}
.hd-view-field {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.hd-view-label {
    font-size: 11px;
    font-weight: 700;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.hd-view-value {
    font-size: 13px;
    font-weight: 600;
    color: #212529;
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
