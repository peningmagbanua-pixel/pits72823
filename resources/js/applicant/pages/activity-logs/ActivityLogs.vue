<script setup>
import { ref, onMounted, watch } from 'vue';
import { Bootstrap5Pagination } from 'laravel-vue-pagination';
import { Modal } from 'bootstrap';

const activityLogs = ref([]);
const loading = ref(false);
const paginate = ref(10);
const action = ref('');
const sortOrder = ref('desc');

const selectedLog = ref(null);

const actionOptions = [
    { value: 'registered', label: 'Registered' },
    { value: 'registered_via_google', label: 'Registered via Google' },
    { value: 'logged_in', label: 'Logged In' },
    { value: 'logged_out', label: 'Logged Out' },
    { value: 'login_failed', label: 'Login Failed' },
    { value: 'login_failed_inactive', label: 'Login Attempt (Inactive)' },
    { value: 'viewed_dashboard', label: 'Viewed Dashboard' },
    { value: 'viewed_application', label: 'Viewed Application' },
    { value: 'application_submitted', label: 'Application Submitted' },
    { value: 'profile_updated', label: 'Profile Updated' },
    { value: 'status_approved', label: 'Application Approved' },
    { value: 'status_rejected', label: 'Application Rejected' },
    { value: 'status_reset', label: 'Status Reset to Pending' },
];

const actionLabel = (a) => ({
    registered:             'Registered',
    registered_via_google:  'Registered via Google',
    logged_in:              'Logged In',
    logged_out:             'Logged Out',
    login_failed:           'Login Failed',
    login_failed_inactive:  'Login Attempt (Inactive)',
    viewed_dashboard:       'Viewed Dashboard',
    viewed_application:     'Viewed Application',
    application_submitted:  'Application Submitted',
    profile_updated:        'Profile Updated',
    status_approved:        'Application Approved',
    status_rejected:        'Application Rejected',
    status_reset:           'Status Reset to Pending',
}[a] ?? a);

const actionBadgeClass = (a) => {
    if (a.includes('approved') || a === 'logged_in') return 'bg-success-subtle text-success';
    if (a.includes('rejected') || a.includes('failed')) return 'bg-danger-subtle text-danger';
    if (a.includes('registered')) return 'bg-primary-subtle text-primary';
    if (a.includes('reset') || a.includes('inactive')) return 'bg-warning-subtle text-warning';
    if (a.includes('viewed') || a.includes('submitted') || a.includes('updated')) return 'bg-info-subtle text-info';
    return 'bg-secondary-subtle text-secondary';
};

const getActivityLogs = async (page = 1) => {
    loading.value = true;
    try {
        const res = await axios.get('/api/applicant/activity-logs', {
            params: {
                page,
                paginate: paginate.value,
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

watch([paginate, action], () => getActivityLogs(1));

const viewLog = (log) => {
    selectedLog.value = log;
    const modalElement = document.getElementById('applicant-activity-log-view-modal');
    Modal.getOrCreateInstance(modalElement).show();
};

onMounted(() => getActivityLogs());
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
                        <li class="breadcrumb-item fw-medium"><a href="/applicant/dashboard">Dashboard</a></li>
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
                                    <label for="action-filter" class="form-label small mb-1">Action</label>
                                    <select v-model="action" id="action-filter" class="form-select form-select-sm">
                                        <option value="">All</option>
                                        <option v-for="opt in actionOptions" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-1">
                                <div class="col-md-3">
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

                            <div class="table-responsive-sm">
                                <table class="table table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th class="text-center align-middle">#</th>
                                            <th class="text-center align-middle">Date &amp; Time</th>
                                            <th class="text-center align-middle">Action</th>
                                            <th class="text-center align-middle">Description</th>
                                            <th class="text-center align-middle actions-cell">Action/s</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="activityLogs.data && activityLogs.data.length > 0">
                                        <tr v-for="(log, index) in activityLogs.data" :key="log.id">
                                            <td class="text-center align-middle">
                                                {{ (activityLogs.current_page - 1) * paginate + index + 1 }}
                                            </td>
                                            <td class="text-center align-middle text-nowrap">{{ log.created_at }}</td>
                                            <td class="text-center align-middle">
                                                <span class="badge" :class="actionBadgeClass(log.action)">
                                                    {{ actionLabel(log.action) }}
                                                </span>
                                            </td>
                                            <td class="align-middle">{{ log.description }}</td>
                                            <td class="text-center align-middle actions-cell">
                                                <button class="btn btn-info btn-sm" @click="viewLog(log)" title="View log">
                                                    <i class="bi bi-eye-fill"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <span v-if="loading">
                                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                                    Loading...
                                                </span>
                                                <span v-else>No activity logs found.</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-if="activityLogs.total > 0" class="row mt-3">
                                <div class="col-md-6">
                                    Showing {{ activityLogs.from }} to {{ activityLogs.to }} of
                                    {{ activityLogs.total.toLocaleString() }} entries
                                </div>
                                <div class="col-md-6">
                                    <Bootstrap5Pagination
                                        :data="activityLogs"
                                        :limit="5"
                                        size="small"
                                        :show-disabled="true"
                                        align="right"
                                        @pagination-change-page="getActivityLogs"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Activity Log Modal -->
    <div class="modal fade" id="applicant-activity-log-view-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" v-if="selectedLog">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-lines-fill me-2 text-info"></i>Activity Log Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" title="Close"></button>
                </div>
                <div class="modal-body" style="font-size: 13px;">
                    <div class="table-responsive-sm">
                        <table class="table table-sm table-bordered mb-3">
                            <tbody>
                                <tr>
                                    <th class="text-end w-25 bg-light">Action</th>
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
                                    <th class="text-end bg-light">Date &amp; Time</th>
                                    <td>{{ selectedLog.created_at }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="selectedLog.metadata && Object.keys(selectedLog.metadata).length > 0">
                        <h6 class="fw-semibold mb-2">Metadata</h6>
                        <pre class="bg-light border rounded p-3 mb-0" style="font-size: 12px;">{{ JSON.stringify(selectedLog.metadata, null, 2) }}</pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" title="Close">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>
