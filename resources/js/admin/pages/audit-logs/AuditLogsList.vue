<script setup>
import AuditLogsView from './AuditLogsView.vue';
import { SORT, SORT_FIELD, ACTION } from '../../../constants';
import { onMounted, ref, watch } from "vue";
import { Bootstrap5Pagination } from 'laravel-vue-pagination';

const paginate = ref(10);
const search = ref('');
const action = ref('');
const user = ref('');
const module = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const sort_order = ref(SORT.DESC);
const sort_field = ref(SORT_FIELD.CREATED_AT);
const auditLogs = ref([]);
const loading = ref(true);
const users = ref([]);
const viewModal = ref(null);
const permissions = ref({ read: false });

const checkPermissions = async () => {
    const response = await axios.get('/api/check/audit-logs/permissions');
    permissions.value = response.data;
};

const actionOptions = [
    { value: ACTION.CREATED, label: ACTION.CREATED },
    { value: ACTION.UPDATED, label: ACTION.UPDATED },
    { value: ACTION.DELETED, label: ACTION.DELETED },
    { value: 'registered', label: 'Registered' },
    { value: 'registered_via_google', label: 'Registered via Google' },
    { value: 'logged_in', label: 'Logged In' },
    { value: 'logged_in_via_google', label: 'Logged In via Google' },
    { value: 'logged_out', label: 'Logged Out' },
    { value: 'login_failed', label: 'Login Failed' },
    { value: 'login_failed_inactive', label: 'Login Attempt (Inactive)' },
    { value: 'viewed_dashboard', label: 'Viewed Dashboard' },
    { value: 'viewed_application', label: 'Viewed Application' },
    { value: 'application_submitted', label: 'Application Submitted' },
    { value: 'profile_updated', label: 'Profile Updated' },
    { value: 'viewed_applicants', label: 'Viewed Applicants' },
    { value: 'status_approved', label: 'Application Approved' },
    { value: 'status_rejected', label: 'Application Rejected' },
    { value: 'status_reset', label: 'Status Reset to Pending' },
    { value: 'approved_application', label: 'Approved Application' },
    { value: 'rejected_application', label: 'Rejected Application' },
    { value: 'reset_application_status', label: 'Reset Application Status' },
    { value: 'created_program', label: 'Created Program' },
    { value: 'updated_program', label: 'Updated Program' },
    { value: 'deleted_program', label: 'Deleted Program' },
    { value: 'created_school_year', label: 'Created School Year' },
    { value: 'updated_school_year', label: 'Updated School Year' },
    { value: 'deleted_school_year', label: 'Deleted School Year' },
    { value: 'created_exam_schedule', label: 'Created Exam Schedule' },
    { value: 'updated_exam_schedule', label: 'Updated Exam Schedule' },
    { value: 'deleted_exam_schedule', label: 'Deleted Exam Schedule' },
    { value: 'auto_assigned_exam_schedule', label: 'Auto-Assigned Exam Schedule' },
    { value: 'manual_assigned_exam_schedule', label: 'Manual-Assigned Exam Schedule' },
    { value: 'updated_applicant_account', label: 'Updated Applicant Account' },
    { value: 'deleted_applicant_account', label: 'Deleted Applicant Account' },
];

const moduleOptions = [
    'Applicant Accounts',
    'Applicants',
    'Applications',
    'Authentication',
    'Dashboard',
    'Exam Scheduling',
    'Permission Titles',
    'Profile',
    'Programs',
    'Role Permissions',
    'Roles',
    'School Years',
    'Users',
];

const viewLog = (log) => {
    viewModal.value.getAuditLog(log);
};

const getAuditLogs = (page = 1) => {
    loading.value = true;
    axios.get('/api/audit-logs', {
        params: {
            page: page,
            paginate: paginate.value,
            search: search.value,
            user: user.value,
            action: action.value,
            module: module.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
            sort_order: sort_order.value,
            sort_field: sort_field.value
        }
    }).then((response) => {
        auditLogs.value = response.data;
    }).finally(() => {
        loading.value = false;
    });
}

const clickSort = (field) => {
    if (sort_field.value === field) {
        sort_order.value = sort_order.value === SORT.ASC ? SORT.DESC : SORT.ASC;
    } else {
        sort_field.value = field;
    }
    getAuditLogs();
};

watch([paginate, search, action, user, module, dateFrom, dateTo], () => {
    getAuditLogs();
});

const getUsers = () => {
    axios.get('/api/list/users')
        .then((response) => {
            users.value = response.data;
        });
}

onMounted(async () => {
    await checkPermissions();
    if (permissions.value.read) {
        getUsers();
    }
    getAuditLogs();
});
</script>
<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Audit Logs</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium"><a href="/admin/dashboard">Dashboard</a></li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Audit Logs</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12" style="font-size: 13px;">
                    <div class="card shadow-sm border-0 mb-2">
                        <div class="card-body">
                           <div class="d-flex flex-wrap align-items-end gap-2">

                                <div v-if="permissions.read">
                                    <label for="user-select" class="form-label small mb-1">Users</label>
                                    <select v-model="user" id="user-select" class="form-select form-select-sm" style="max-width: 150px;">
                                        <option value="">All</option>
                                        <option v-for="option in users" :value="option.id" :key="option.id">{{ option.firstname }} {{ option.lastname }}</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="action-select" class="form-label small mb-1">Actions</label>
                                    <select v-model="action" id="action-select"
                                        class="form-select form-select-sm" style="max-width: 200px;">
                                        <option value="">All</option>
                                        <option v-for="opt in actionOptions" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label for="module-select" class="form-label small mb-1">Module</label>
                                    <select v-model="module" id="module-select"
                                        class="form-select form-select-sm" style="max-width: 180px;">
                                        <option value="">All</option>
                                        <option v-for="opt in moduleOptions" :key="opt" :value="opt">
                                            {{ opt }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label for="date-from" class="form-label small mb-1">Date From</label>
                                    <input v-model="dateFrom" id="date-from" type="date"
                                        class="form-control form-control-sm" style="max-width: 150px;">
                                </div>

                                <div>
                                    <label for="date-to" class="form-label small mb-1">Date To</label>
                                    <input v-model="dateTo" id="date-to" type="date"
                                        class="form-control form-control-sm" style="max-width: 150px;">
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
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

                                <div class="col-md-7">
                              
                                </div>

                                <div class="col-md-2">
                                    <div class="d-flex align-items-center">
                                        <input v-model.lazy="search" type="search" class="form-control form-control-sm" placeholder="Search">
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive-sm">
                                <table class="table table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th class="text-center align-middle">#</th>
                                            <th class="text-center align-middle">
                                               <a class="text-decoration-none" @click.prevent="clickSort(SORT_FIELD.CREATED_AT)">
                                                    Date Added
                                                    <span v-if="sort_field === SORT_FIELD.CREATED_AT">
                                                        <i :class="sort_order === SORT.ASC ? 'bi bi-sort-down' : 'bi bi-sort-up'"></i>
                                                    </span>
                                                </a>
                                            </th>
                                            <th class="text-center align-middle">User</th>
                                            <th class="text-center align-middle">Action</th>
                                            <th class="text-center align-middle">Module</th>
                                            <th class="text-center align-middle">Description</th>
                                            <th class="text-center align-middle">Action/s</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="loading">
                                        <tr>
                                            <td colspan="7" class="text-center">
                                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                                Loading ...
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else-if="auditLogs.data && auditLogs.data.length > 0">
                                        <tr v-for="(log, index) in auditLogs.data" :key="log.id">
                                            <td class="text-center align-middle">{{ (auditLogs.current_page - 1) * paginate + index + 1 }}</td>
                                            <td class="text-center align-middle">{{ log.created_at }}</td>
                                            <td class="align-middle">
                                                <span><img :src="log.avatar || '/images/default.jpeg'" width="30"
                                                        class="rounded-5 shadow-sm me-2" alt="Profile"></span>
                                                <div class="d-inline-flex flex-column align-items-start">
                                                    <span>{{ log.user }}</span>
                                                    <small class="text-muted">{{ log.actor_email || '—' }}</small>
                                                </div>
                                            </td>
                                            <td class="text-center align-middle">
                                                <span :class="log.action_color">
                                                    {{ log.action_text }}
                                                </span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <span class="badge bg-info-subtle text-info">
                                                    {{ log.module }}
                                                </span>
                                            </td>
                                            <td class="align-middle">{{ log.description }}</td>
                                            <td class="text-center align-middle">
                                                <button class="btn btn-info btn-sm"
                                                    @click="viewLog(log)" data-bs-toggle="modal"
                                                    data-bs-target="#view-audit-log">
                                                    <i class="bi bi-eye-fill"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="7" class="text-center">No results found</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div v-if="auditLogs.total > 0" class="row mt-3">
                                <div class="col-md-6">
                                    Showing {{ auditLogs.from }} to {{ auditLogs.to }} of {{
                                    auditLogs.total.toLocaleString() }} entries
                                </div>

                                <div class="col-md-6">
                                    <Bootstrap5Pagination :data="auditLogs" :limit="5" size="small" :show-disabled="true"
                                        align="right" @pagination-change-page="getAuditLogs" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <AuditLogsView ref="viewModal" />
</template>
