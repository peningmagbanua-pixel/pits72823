<script setup>
import ApplicantAccountsEdit from './ApplicantAccountsEdit.vue';
import { SORT, SORT_FIELD, STATUS } from '../../../../constants';
import { deletingAlert, sweetAlert } from '../../../../sweetalert';
import { onMounted, ref, watch } from 'vue';
import { Bootstrap5Pagination } from 'laravel-vue-pagination';
import Swal from 'sweetalert2';
import useApplicantAccountApi from '../../../composables/api/useApplicantAccountApi';

const paginate   = ref(10);
const search     = ref('');
const sort_order = ref(SORT.DESC);
const sort_field = ref(SORT_FIELD.CREATED_AT);
const applicants = ref([]);
const status     = ref('');
const editModal  = ref(null);

const editApplicant = (applicant) => {
    editModal.value.getApplicant(applicant);
};

const getApplicants = async (page = 1) => {
    try {
        const response = await useApplicantAccountApi.getApplicantAccounts({
            page,
            paginate: paginate.value,
            search: search.value,
            status: status.value,
            sort_order: sort_order.value,
            sort_field: sort_field.value,
        });
        applicants.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

const clickSort = (field) => {
    if (sort_field.value === field) {
        sort_order.value = sort_order.value === SORT.ASC ? SORT.DESC : SORT.ASC;
    } else {
        sort_field.value = field;
    }
    getApplicants();
};

watch([paginate, search, status], () => {
    getApplicants();
});

const deleteApplicant = (id) => {
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
            useApplicantAccountApi.deleteApplicantAccount(id)
                .then((response) => {
                    Swal.close();
                    sweetAlert('success', response.data.message);
                    getApplicants();
                }).catch((error) => {
                    Swal.close();
                    sweetAlert('error', error.response.data.message);
                });
        }
    });
};

onMounted(() => {
    getApplicants();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Applicant Accounts</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium"><a href="/admin/dashboard">Dashboard</a></li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Applicant Accounts</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label small mb-0">Status:</label>
                    <select v-model="status" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All</option>
                        <option :value="STATUS.ACTIVE">Active</option>
                        <option :value="STATUS.INACTIVE">Inactive</option>
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

                                <div class="col-md-7">

                                </div>

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
                                            <th class="text-center align-middle">Name</th>
                                            <th class="text-center align-middle">Email</th>
                                            <th class="text-center align-middle">Google</th>
                                            <th class="text-center align-middle">Last Login</th>
                                            <th class="text-center align-middle">Status</th>
                                            <th class="text-center align-middle">Action/s</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="applicants.meta && applicants.meta.total > 0">
                                        <tr v-for="(applicant, index) in applicants.data" :key="applicant.id">
                                            <td class="text-center align-middle">{{ index + 1 }}</td>
                                            <td class="text-center align-middle">{{ applicant.created_at }}</td>
                                            <td class="align-middle">
                                                <img :src="applicant.profile || '/images/default.jpeg'" width="30" class="rounded-5 shadow-sm me-2" alt="Profile">
                                                {{ applicant.name }}
                                            </td>
                                            <td class="align-middle">{{ applicant.email }}</td>
                                            <td class="text-center align-middle">
                                                <span v-if="applicant.google_id" class="badge bg-success-subtle text-success">
                                                    <i class="bi bi-google me-1"></i>Linked
                                                </span>
                                                <span v-else class="badge bg-secondary-subtle text-secondary">Not Linked</span>
                                            </td>
                                            <td class="text-center align-middle">{{ applicant.last_login }}</td>
                                            <td class="text-center align-middle">
                                                <span class="badge" :class="applicant.status_color">{{ applicant.status_text }}</span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <button class="btn btn-secondary btn-sm me-1"
                                                    @click="editApplicant(applicant)"
                                                    data-bs-toggle="modal" data-bs-target="#update-applicant-account">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm"
                                                    @click="deleteApplicant(applicant.id)">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="8" class="text-center">No results found</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="applicants.meta && applicants.meta.total > 0" class="row mt-3">
                                <div class="col-md-6">
                                    Showing {{ applicants.meta.from }} to {{ applicants.meta.to }} of
                                    {{ applicants.meta.total.toLocaleString() }} entries
                                </div>
                                <div class="col-md-6">
                                    <Bootstrap5Pagination :data="applicants" :limit="5" size="small"
                                        :show-disabled="true" align="right" @pagination-change-page="getApplicants" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <ApplicantAccountsEdit ref="editModal" @applicant-updated="getApplicants" />
</template>
