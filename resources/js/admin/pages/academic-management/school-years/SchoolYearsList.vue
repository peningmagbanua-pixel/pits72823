<script setup>
import SchoolYearsAdd from './SchoolYearsAdd.vue';
import SchoolYearsEdit from './SchoolYearsEdit.vue';
import { SORT, SORT_FIELD } from '../../../../constants';
import { deletingAlert, sweetAlert } from '../../../../sweetalert';
import { onMounted, ref, watch } from 'vue';
import { Bootstrap5Pagination } from 'laravel-vue-pagination';
import Swal from 'sweetalert2';
import useSchoolYearApi from '../../../composables/api/useSchoolYearApi';

const paginate    = ref(10);
const search      = ref('');
const sort_order  = ref(SORT.DESC);
const sort_field  = ref(SORT_FIELD.CREATED_AT);
const school_years = ref([]);
const loading = ref(true);
const permissions = ref([]);
const showDeleted = ref(false);
const editModal   = ref(null);

const editSchoolYear = (school_year) => {
    editModal.value.getSchoolYear(school_year);
};

const getSchoolYears = async (page = 1) => {
    loading.value = true;
    try {
        const response = await useSchoolYearApi.getSchoolYears({
            page,
            paginate: paginate.value,
            search: search.value,
            sort_order: sort_order.value,
            sort_field: sort_field.value,
            deleted: showDeleted.value ? 1 : 0,
        });
        school_years.value = response.data;
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
    getSchoolYears();
};

watch([paginate, search, showDeleted], () => {
    getSchoolYears();
});

const deleteSchoolYear = (id) => {
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
            useSchoolYearApi.deleteSchoolYear(id)
                .then((response) => {
                    Swal.close();
                    sweetAlert('success', response.data.message);
                    getSchoolYears();
                }).catch((error) => {
                    Swal.close();
                    sweetAlert('error', error.response.data.message);
                });
        }
    });
};

const restoreSchoolYear = (id) => {
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
            useSchoolYearApi.restoreSchoolYear(id)
                .then((response) => {
                    Swal.close();
                    sweetAlert('success', response.data.message);
                    getSchoolYears();
                }).catch((error) => {
                    Swal.close();
                    sweetAlert('error', error.response.data.message);
                });
        }
    });
};

const checkPermissions = async () => {
    try {
        const response = await useSchoolYearApi.checkPermissions();
        permissions.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

onMounted(() => {
    checkPermissions();
    getSchoolYears();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">School Years</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium"><a href="/admin/dashboard">Dashboard</a></li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">School Years</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between mb-2">
                <div v-if="permissions.create" class="d-flex">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#new-school-year">
                        <i class="bi bi-plus-circle me-1"></i>New School Year
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
                                            <th class="align-middle text-center">#</th>
                                            <th class="align-middle text-center">
                                                <a class="text-decoration-none" @click.prevent="clickSort(SORT_FIELD.CREATED_AT)">
                                                    Date Added
                                                    <span v-if="sort_field === SORT_FIELD.CREATED_AT">
                                                        <i :class="sort_order === SORT.ASC ? 'bi bi-sort-down' : 'bi bi-sort-up'"></i>
                                                    </span>
                                                </a>
                                            </th>
                                            <th class="align-middle text-center">Name</th>
                                            <th class="align-middle text-center">Start</th>
                                            <th class="align-middle text-center">End</th>
                                            <th class="align-middle text-center">Status</th>
                                            <th class="align-middle text-center actions-cell">Action/s</th>
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
                                    <tbody v-else-if="school_years.meta && school_years.meta.total > 0">
                                        <tr v-for="(school_year, index) in school_years.data" :key="school_year.id">
                                            <td class="align-middle text-center">{{ index + 1 }}</td>
                                            <td class="align-middle text-center">{{ school_year.created_at }}</td>
                                            <td class="align-middle text-center">{{ school_year.school_year_name }}</td>
                                            <td class="align-middle text-center">{{ school_year.school_year_start }}</td>
                                            <td class="align-middle text-center">{{ school_year.school_year_end }}</td>
                                            <td class="align-middle text-center">
                                                <span :class="school_year.status_color">{{ school_year.status_text }}</span>
                                            </td>
                                            <td class="align-middle text-center actions-cell">
                                                <template v-if="showDeleted">
                                                    <button v-if="permissions.delete" type="button"
                                                        class="btn btn-success btn-sm"
                                                        @click="restoreSchoolYear(school_year.id)">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </template>
                                                <template v-else>
                                                    <button v-if="permissions.update" class="btn btn-secondary btn-sm me-1"
                                                        @click="editSchoolYear(school_year)"
                                                        data-bs-toggle="modal" data-bs-target="#update-school-year">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button v-if="permissions.delete" type="button"
                                                        class="btn btn-danger btn-sm"
                                                        @click="deleteSchoolYear(school_year.id)">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </template>
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
                            <div v-if="school_years.meta && school_years.meta.total > 0" class="row mt-3">
                                <div class="col-md-6">
                                    Showing {{ school_years.meta.from }} to {{ school_years.meta.to }} of
                                    {{ school_years.meta.total.toLocaleString() }} entries
                                </div>
                                <div class="col-md-6">
                                    <Bootstrap5Pagination :data="school_years" :limit="5" size="small"
                                        :show-disabled="true" align="right" @pagination-change-page="getSchoolYears" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <SchoolYearsAdd @school-year-added="getSchoolYears" />
    <SchoolYearsEdit ref="editModal" @school-year-updated="getSchoolYears" />
</template>
