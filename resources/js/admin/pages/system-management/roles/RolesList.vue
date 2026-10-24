<script setup>
import RolesAdd from './RolesAdd.vue';
import RolesEdit from './RolesEdit.vue';
import RolesView from './RolesView.vue';
import PermissionsManage from '../permissions/PermissionsManage.vue';
import RolesPermissionMatrix from './RolesPermissionMatrix.vue';
import { SORT, SORT_FIELD } from '../../../../constants';
import { deletingAlert, sweetAlert } from '../../../../sweetalert';
import { onMounted, ref, watch } from 'vue';
import { Bootstrap5Pagination } from 'laravel-vue-pagination';
import Swal from 'sweetalert2';
import useRoleApi from '../../../composables/api/useRoleApi';

const paginate         = ref(10);
const search           = ref('');
const sort_order       = ref(SORT.DESC);
const sort_field       = ref(SORT_FIELD.CREATED_AT);
const roles            = ref([]);
const loading          = ref(true);
const showDeleted      = ref(false);
const editModal        = ref(null);
const viewModal        = ref(null);
const permissionsModal = ref(null);

const editRole = (role) => {
    editModal.value.getRole(role);
};

const viewRole = (role) => {
    viewModal.value.getRole(role);
};

const managePermissions = (role) => {
    permissionsModal.value.getRolePermissions(role);
};

const getRoles = async (page = 1) => {
    loading.value = true;
    try {
        const response = await useRoleApi.getRoles({
            page,
            paginate: paginate.value,
            search: search.value,
            sort_order: sort_order.value,
            sort_field: sort_field.value,
            deleted: showDeleted.value ? 1 : 0,
        });
        roles.value = response.data;
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
    getRoles();
};

watch([paginate, search, showDeleted], () => {
    getRoles();
});

const deleteRole = (id) => {
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
            useRoleApi.deleteRole(id)
                .then((response) => {
                    Swal.close();
                    sweetAlert('success', response.data.message);
                    getRoles();
                }).catch((error) => {
                    Swal.close();
                    sweetAlert('error', error.response.data.message);
                });
        }
    });
};

const restoreRole = (id) => {
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
            useRoleApi.restoreRole(id)
                .then((response) => {
                    Swal.close();
                    sweetAlert('success', response.data.message);
                    getRoles();
                }).catch((error) => {
                    Swal.close();
                    sweetAlert('error', error.response.data.message);
                });
        }
    });
};

const permissions = ref([]);

const checkPermissions = async () => {
    try {
        const response = await useRoleApi.checkPermissions();
        permissions.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

onMounted(() => {
    checkPermissions();
    getRoles();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Roles</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium"><a href="/admin/dashboard">Dashboard</a></li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Roles</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between mb-2">
                <div class="d-flex">
                    <button v-if="permissions.create" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#new-role" title="Add new role">
                        <i class="bi bi-plus-circle me-1"></i>New Role
                    </button>
                </div>
                <div class="d-flex">
                    <button v-if="permissions.browse" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#permission-matrix" title="Permission matrix">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i>Permission Matrix
                    </button>
                </div>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label small mb-0">View:</label>
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
                                            <label for="paginate" class="text-nowrap ms-2 mb-0 form-label">entries per
                                                page</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-7"></div>

                                <div class="col-md-2">
                                    <div class="d-flex align-items-center">
                                        <input v-model.lazy="search" type="search" class="form-control form-control-sm"
                                            placeholder="Search">
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
                                            <th class="text-center align-middle">Role Name</th>
                                            <th class="text-center align-middle actions-cell">Action/s</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="loading">
                                        <tr>
                                            <td colspan="4" class="text-center">
                                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                                Loading ...
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else-if="roles.meta && roles.meta.total > 0">
                                        <tr v-for="(role, index) in roles.data" :key="role.id">
                                            <td class="text-center align-middle">{{ index + 1 }}</td>
                                            <td class="text-center align-middle">{{ role.created_at }}</td>
                                            <td class="align-middle">{{ role.name }}</td>
                                            <td class="text-center align-middle actions-cell">
                                                <template v-if="showDeleted">
                                                    <button v-if="permissions.delete" type="button" class="btn btn-success btn-sm" title="Restore"
                                                        @click="restoreRole(role.id)">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </template>
                                                <template v-else>
                                                    <button v-if="permissions.browse" class="btn btn-info btn-sm me-1"
                                                        @click="viewRole(role)" data-bs-toggle="modal"
                                                        data-bs-target="#view-role" title="View Users">
                                                        <i class="bi bi-eye-fill"></i>
                                                    </button>
                                                    <button v-if="permissions.update" class="btn btn-warning btn-sm me-1"
                                                        @click="managePermissions(role)" data-bs-toggle="modal"
                                                        data-bs-target="#manage-permissions" title="Manage Permissions">
                                                        <i class="bi bi-shield-lock"></i>
                                                    </button>
                                                    <button v-if="permissions.update" class="btn btn-secondary btn-sm me-1" title="Edit"
                                                        @click="editRole(role)" data-bs-toggle="modal"
                                                        data-bs-target="#update-role">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button v-if="permissions.delete" type="button" class="btn btn-danger btn-sm" title="Delete"
                                                        @click="deleteRole(role.id)">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </template>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="4" class="text-center">No results found</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="roles.meta && roles.meta.total > 0" class="row mt-3">
                                <div class="col-md-6">
                                    Showing {{ roles.meta.from }} to {{ roles.meta.to }} of
                                    {{ roles.meta.total.toLocaleString() }} entries
                                </div>
                                <div class="col-md-6">
                                    <Bootstrap5Pagination :data="roles" :limit="5" size="small"
                                        :show-disabled="true" align="right" @pagination-change-page="getRoles" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <RolesAdd @role-added="getRoles" />
    <RolesEdit ref="editModal" @role-updated="getRoles" />
    <RolesView ref="viewModal" />
    <PermissionsManage ref="permissionsModal" @permissions-updated="getRoles" />
    <RolesPermissionMatrix />
</template>
