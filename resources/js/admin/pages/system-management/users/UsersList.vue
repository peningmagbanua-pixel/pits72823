<script setup>
import UsersAdd from './UsersAdd.vue';
import UsersEdit from './UsersEdit.vue';
import { SORT, SORT_FIELD, STATUS } from '../../../../constants';
import { deletingAlert, sweetAlert } from '../../../../sweetalert';
import { onMounted, ref, watch } from 'vue';
import { Bootstrap5Pagination } from 'laravel-vue-pagination';
import Swal from 'sweetalert2';
import useUserApi from '../../../composables/api/useUserApi';

const paginate   = ref(10);
const search     = ref('');
const sort_order = ref(SORT.DESC);
const sort_field = ref(SORT_FIELD.CREATED_AT);
const users      = ref([]);
const loading    = ref(true);
const roles      = ref([]);
const role       = ref('');
const status     = ref('');
const showDeleted = ref(false);
const editModal  = ref(null);

const editUser = (user) => {
    editModal.value.getUser(user);
};

const getUsers = async (page = 1) => {
    loading.value = true;
    try {
        const response = await useUserApi.getUsers({
            page,
            paginate: paginate.value,
            search: search.value,
            role: role.value,
            status: status.value,
            sort_order: sort_order.value,
            sort_field: sort_field.value,
            deleted: showDeleted.value ? 1 : 0,
        });
        users.value = response.data;
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const clickSort = (field) => {
    if (sort_field.value === field) {
        sort_order.value = sort_order.value === SORT.ASC ? SORT.DESC : SORT.ASC;
    } else {
        sort_field.value = field;
    }
    getUsers();
};

watch([paginate, search, role, status, showDeleted], () => {
    getUsers();
});

const deleteUser = (id) => {
    Swal.fire({
        title: "Are you sure?",
        text: "You are about to delete this record!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!",
        allowOutsideClick: false,
        allowEscapeKey: false,
    }).then((result) => {
        if (result.isConfirmed) {
            deletingAlert();
            useUserApi.deleteUser(id)
                .then((response) => {
                    Swal.close();
                    sweetAlert('success', response.data.message);
                    getUsers();
                }).catch((error) => {
                    Swal.close();
                    sweetAlert('error', error.response.data.message);
                });
        }
    });
};

const restoreUser = (id) => {
    Swal.fire({
        title: "Are you sure?",
        text: "You are about to restore this record!",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, restore it!",
        allowOutsideClick: false,
        allowEscapeKey: false,
    }).then((result) => {
        if (result.isConfirmed) {
            deletingAlert();
            useUserApi.restoreUser(id)
                .then((response) => {
                    Swal.close();
                    sweetAlert('success', response.data.message);
                    getUsers();
                }).catch((error) => {
                    Swal.close();
                    sweetAlert('error', error.response.data.message);
                });
        }
    });
};

const getRoles = () => {
    useUserApi.getAllRoles().then((response) => {
        roles.value = response.data;
    });
};

const permissions = ref([]);

const checkPermissions = async () => {
    try {
        const response = await useUserApi.checkPermissions();
        permissions.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

onMounted(() => {
    checkPermissions();
    getRoles();
    getUsers();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Users</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium"><a href="/admin/dashboard">Dashboard</a></li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Users</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between mb-2">
                <div class="d-flex">
                    <button v-if="permissions.create" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#new-user">
                        <i class="bi bi-plus-circle me-1"></i>New User
                    </button>
                </div>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label small mb-0">Role:</label>
                    <select v-model="role" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All</option>
                        <option v-for="r in roles" :value="r.id" :key="r.id">{{ r.name }}</option>
                    </select>
                    <label class="form-label small mb-0 ms-2">Status:</label>
                    <select v-model="status" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All</option>
                        <option :value="STATUS.ACTIVE">Active</option>
                        <option :value="STATUS.INACTIVE">Inactive</option>
                    </select>
                    <label class="form-label small mb-0 ms-2">View:</label>
                    <select v-model="showDeleted" class="form-select form-select-sm" style="width: auto;">
                        <option :value="false">Active</option>
                        <option :value="true">Deleted</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12" style="font-size: 13px;">
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
                                            <th class="text-center align-middle">Name</th>
                                            <th class="text-center align-middle">Email</th>
                                            <th class="text-center align-middle">Role</th>
                                            <th class="text-center align-middle">Programs</th>
                                            <th class="text-center align-middle">Last Login</th>
                                            <th class="text-center align-middle">Status</th>
                                            <th class="text-center align-middle">Action/s</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="loading">
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                                Loading ...
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else-if="users.meta && users.meta.total > 0">
                                        <tr v-for="(user, index) in users.data" :key="user.id">
                                            <td class="text-center align-middle">{{ index + 1 }}</td>
                                            <td class="text-center align-middle">{{ user.created_at }}</td>
                                            <td class="align-middle">
                                                <img :src="user.avatar || '/images/default.jpeg'" width="30" class="rounded-5 shadow-sm me-2" alt="Profile">
                                                {{ user.name }}
                                            </td>
                                            <td class="align-middle">{{ user.email }}</td>
                                            <td class="text-center align-middle">
                                                <template v-if="user.roles && user.roles.length">
                                                    <span v-for="(r, i) in user.roles" :key="i" class="badge bg-primary-subtle text-primary me-1">{{ r }}</span>
                                                </template>
                                                <span v-else class="badge bg-secondary-subtle">No Role</span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <template v-if="user.is_system_administrator">
                                                    <span class="badge bg-primary-subtle text-primary">All Programs</span>
                                                </template>
                                                <template v-else-if="user.program_access && user.program_access.length">
                                                    <span v-for="(p, i) in user.program_access" :key="i"
                                                        class="badge bg-info-subtle text-info me-1">{{ p }}</span>
                                                </template>
                                                <template v-else>
                                                    <span class="badge bg-secondary-subtle">No Program</span>
                                                </template>
                                            </td>
                                            <td class="text-center align-middle">{{ user.last_login }}</td>
                                            <td class="text-center align-middle">
                                                <span class="badge" :class="user.status_color">{{ user.status_text }}</span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <template v-if="showDeleted">
                                                    <button v-if="permissions.delete" type="button" class="btn btn-success btn-sm"
                                                        @click="restoreUser(user.id)">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </template>
                                                <template v-else>
                                                    <button v-if="permissions.update" class="btn btn-secondary btn-sm me-1"
                                                        @click="editUser(user)" data-bs-toggle="modal" data-bs-target="#update-user">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button v-if="permissions.delete" type="button" class="btn btn-danger btn-sm"
                                                        @click="deleteUser(user.id)">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </template>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="9" class="text-center">No results found</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="users.meta && users.meta.total > 0" class="row mt-3">
                                <div class="col-md-6">
                                    Showing {{ users.meta.from }} to {{ users.meta.to }} of
                                    {{ users.meta.total.toLocaleString() }} entries
                                </div>
                                <div class="col-md-6">
                                    <Bootstrap5Pagination :data="users" :limit="5" size="small"
                                        :show-disabled="true" align="right" @pagination-change-page="getUsers" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <UsersAdd @user-added="getUsers" />
    <UsersEdit ref="editModal" @user-updated="getUsers" />
</template>
