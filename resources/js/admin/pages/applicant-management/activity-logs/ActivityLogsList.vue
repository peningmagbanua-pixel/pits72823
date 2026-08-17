<script setup>
import { ref, onMounted, watch } from 'vue';
import { Bootstrap5Pagination } from 'laravel-vue-pagination';
import { Modal } from 'bootstrap';

const activityLogs = ref([]);
const loading = ref(false);
const paginate = ref(10);
const search = ref('');
const action = ref('');
const sortOrder = ref('desc');

const selectedLog = ref(null);

const actionOptions = [
    { value: 'registered', label: 'Registered' },
    { value: 'registered_via_google', label: 'Registered via Google' },
    { value: 'logged_in', label: 'Logged In' },
    { value: 'logged_out', label: 'Logged Out' },
    { value: 'login_failed', label: 'Login Failed' },
    { value: 'login_failed_inactive', label: 'Login Failed (Inactive)' },
    { value: 'status_approved', label: 'Status Approved' },
    { value: 'status_rejected', label: 'Status Rejected' },
    { value: 'status_reset', label: 'Status Reset to Pending' },
];

const actionLabel = (a) => ({
    registered: '📝 Registered',
    registered_via_google: '🔑 Registered via Google',
    logged_in: '🔓 Logged In',
    logged_out: '🚪 Logged Out',
    login_failed: '❌ Login Failed',
    login_failed_inactive: '⛔ Login Attempt (Inactive)',
    status_approved: '✅ Application Approved',
    status_rejected: '❌ Application Rejected',
    status_reset: '🔄 Status Reset to Pending',
}[a] ?? '⚡ ' + a);

const actionBadgeClass = (a) => {
    if (a.includes('approved') || a === 'logged_in') return 'bg-success-subtle text-success';
    if (a.includes('rejected') || a.includes('failed')) return 'bg-danger-subtle text-danger';
    if (a.includes('registered')) return 'bg-primary-subtle text-primary';
    if (a.includes('reset') || a.includes('inactive')) return 'bg-warning-subtle text-warning';
    return 'bg-secondary-subtle text-secondary';
};

const getActivityLogs = async (page = 1) => {
    loading.value = true;
    try {
        const res = await axios.get('/api/activity-logs', {
            params: {
                page: page,
                paginate: paginate.value,
                search: search.value,
                action: action.value,
                sort_order: sortOrder.value,
            },
        });
        activityLogs.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

watch([paginate, search, action], () => getActivityLogs(1));

const viewLog = (log) => {
    selectedLog.value = log;
    const modalElement = document.getElementById('activity-log-view-modal');
    Modal.getOrCreateInstance(modalElement).show();
};
</script>
<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Activity Logs</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium"><a href="/admin/dashboard">Dashboard</a></li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Activity Logs</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12" style="font-size: 13px;">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-12 col-sm-6 col-md-3">
                                    <label for="action" class="form-label small mb-1">Actions</label>
                                    <select v-model="action" id="action" class="form-select form-select-sm">
                                        <option value="">All</option>
                                        <option v-for="opt in actionOptions" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <div class="col-md-3">
                                    <div class="d-flex justify-content-between align-content-center mb-1">
                                        <div class="d-flex align-items-center">
                                            <select v-model="paginate" id="paginate" class="form-select form-select-sm">
                                                <option value="10">10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                                <option value="75">75</option>
                                                <option value="100">100</option>
                                            </select>
                                            <label for="paginate" class="text-nowrap ms-2 mb-0 form-label">entries per page</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-7"></div>
                                <div class="col-md-2">
                                    <div class="d-flex align-items-center">
                                        <input v-model.lazy="search" type="search" class="form-control form-control-sm"
                                            placeholder="Search applicant">
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive-sm">
                                <table class="table table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th class="text-center align-middle">#</th>
                                            <th class="text-center align-middle">Date &amp; Time</th>
                                            <th class="text-center align-middle">Applicant</th>
                                            <th class="text-center align-middle">Action</th>
                                            <th class="text-center align-middle">Description</th>
                                            <th class="text-center align-middle">IP Address</th>
                                            <th class="text-center align-middle">Action/s</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="activityLogs.meta && activityLogs.meta.total > 0">
                                        <tr v-for="(log, index) in activityLogs.data" :key="log.id">
                                            <td class="text-center align-middle">{{ index + 1 }}</td>
                                            <td class="text-center align-middle text-nowrap">{{ log.created_at }}</td>
                                            <td class="align-middle">
                                                <div class="fw-semibold">{{ log.applicant_name }}</div>
                                                <small class="text-muted">{{ log.applicant_email || '—' }}</small>
                                            </td>
                                            <td class="text-center align-middle">
                                                <span class="badge" :class="actionBadgeClass(log.action)">
                                                    {{ actionLabel(log.action) }}
                                                </span>
                                            </td>
                                            <td class="align-middle">{{ log.description }}</td>
                                            <td class="text-center align-middle text-nowrap">{{ log.ip_address || '—' }}</td>
                                            <td class="text-center align-middle">
                                                <button class="btn btn-info btn-sm" @click="viewLog(log)">
                                                    <i class="bi bi-eye-fill"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="7" class="text-center">No activity logs found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="activityLogs.meta && activityLogs.meta.total > 0" class="row mt-3">
                                <div class="col-md-6">
                                    Showing {{ activityLogs.meta.from }} to {{ activityLogs.meta.to }} of
                                    {{ activityLogs.meta.total.toLocaleString() }} entries
                                </div>
                                <div class="col-md-6">
                                    <Bootstrap5Pagination :data="activityLogs" :limit="5" size="small"
                                        :show-disabled="true" align="right" @pagination-change-page="getActivityLogs" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Activity Log Modal -->
    <div class="modal fade" id="activity-log-view-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" v-if="selectedLog">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-lines-fill me-2 text-info"></i>Activity Log Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="font-size: 13px;">
                    <table class="table table-sm table-bordered mb-3">
                        <tbody>
                            <tr>
                                <th class="text-end w-25 bg-light">Applicant</th>
                                <td>{{ selectedLog.applicant_name }} <small
                                        class="text-muted">({{ selectedLog.applicant_email || 'no email' }})</small></td>
                            </tr>
                            <tr>
                                <th class="text-end bg-light">Action</th>
                                <td>
                                    <span class="badge" :class="actionBadgeClass(selectedLog.action)">
                                        {{ actionLabel(selectedLog.action) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th class="text-end bg-light">Description</th>
                                <td>{{ selectedLog.description }}</td>
                            </tr>
                            <tr>
                                <th class="text-end bg-light">IP Address</th>
                                <td>{{ selectedLog.ip_address || '—' }}</td>
                            </tr>
                            <tr>
                                <th class="text-end bg-light">User Agent</th>
                                <td class="text-break">{{ selectedLog.user_agent || '—' }}</td>
                            </tr>
                            <tr>
                                <th class="text-end bg-light">Date &amp; Time</th>
                                <td>{{ selectedLog.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="selectedLog.metadata && Object.keys(selectedLog.metadata).length > 0">
                        <h6 class="fw-semibold mb-2">Metadata</h6>
                        <pre class="bg-light border rounded p-3 mb-0" style="font-size: 12px;">{{ JSON.stringify(selectedLog.metadata, null, 2) }}</pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>
