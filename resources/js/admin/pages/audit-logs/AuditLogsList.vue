<script setup>
import AuditLogsView from './AuditLogsView.vue';
import { SORT, SORT_FIELD } from '../../../constants';
import { onMounted, ref, watch } from "vue";
import { Bootstrap5Pagination } from 'laravel-vue-pagination';
import { useAuthUserStore } from '../../stores/AuthUserStore';
import { ACTION } from '../../../constants';

const authUserStore = useAuthUserStore();
const paginate = ref(10);
const search = ref('');
const action = ref('');
const user = ref('');
const sort_order = ref(SORT.DESC);
const sort_field = ref(SORT_FIELD.CREATED_AT);
const auditLogs = ref([]);
const users = ref([]);
const viewModal = ref(null);

const viewLog = (log) => {
    viewModal.value.getAuditLog(log);
};

const getAuditLogs = (page = 1) => {
    axios.get('/api/audit-logs', {
        params: {
            page: page,
            paginate: paginate.value,
            search: search.value,
            user: user.value,
            action: action.value,
            sort_order: sort_order.value,
            sort_field: sort_field.value
        }
    }).then((response) => {
        auditLogs.value = response.data;
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

watch([paginate, search, action, user], () => {
    getAuditLogs();
});

const getUsers = () => {
    axios.get('/api/list/users')
        .then((response) => {
            users.value = response.data;
        })
}

onMounted(() => {
    getUsers();
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
                           <div class="row g-2">

                                <div v-if="authUserStore.user.view.system_administrator" class="col-12 col-sm-6 col-md-3">
                                    <label for="action" class="form-label small mb-1">Users</label>
                                    <select v-model="user" id="action" class="form-select form-select-sm">
                                        <option value="">All</option>
                                        <option v-for="user in users" :value="user.id" :key="user.id">{{ user.firstname }} {{ user.lastname }}</option>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6 col-md-3">
                                    <label for="action" class="form-label small mb-1">Actions</label>
                                    <select v-model="action" id="action"
                                        class="form-select form-select-sm">
                                        <option value="">All</option>
                                        <option :value="ACTION.CREATED">Created</option>
                                        <option :value="ACTION.UPDATED">Updated</option>
                                        <option :value="ACTION.DELETED">Deleted</option>
                                    </select>
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
                                            <th v-if="authUserStore.user.view.system_administrator" class="text-center align-middle">Action/s</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="auditLogs.meta && auditLogs.meta.total > 0" class="">
                                        <tr v-for="(log, index) in auditLogs.data" :key="log.id">
                                            <td class="text-center align-middle">{{ index + 1 }}</td>
                                            <td class="text-center align-middle">{{ log.created_at }}</td>
                                            <td class="align-middle">
                                                <span><img :src="log.avatar || '/images/default.jpeg'" width="30"
                                                        class="rounded-5 shadow-sm me-2" alt="Profile"></span>
                                                {{ log.user }}
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
                                            <td  v-if="authUserStore.user.view.system_administrator" class="text-center align-middle">
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

                            <div v-if="auditLogs.meta && auditLogs.meta.total > 0" class="row mt-3">
                                <div class="col-md-6">
                                    Showing {{ auditLogs.meta.from }} to {{ auditLogs.meta.to }} of {{
                                    auditLogs.meta.total.toLocaleString() }} entries
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
