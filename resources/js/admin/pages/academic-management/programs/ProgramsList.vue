<script setup>
import ProgramsAdd from "./ProgramsAdd.vue";
import ProgramsEdit from "./ProgramsEdit.vue";
import { SORT, SORT_FIELD } from "../../../../constants";
import { deletingAlert, sweetAlert } from "../../../../sweetalert";
import { onMounted, ref, watch } from "vue";
import { Bootstrap5Pagination } from "laravel-vue-pagination";
import Swal from "sweetalert2";
import useProgramApi from "../../../composables/api/useProgramApi";

const paginate = ref(10);
const search = ref('');
const sort_order = ref(SORT.DESC);
const sort_field = ref(SORT_FIELD.CREATED_AT);
const programs = ref([]);
const permissions = ref([]);
const editModal = ref(null);

const editProgram = (program) => {
    editModal.value.getProgram(program);
};

const getPrograms = async (page = 1) => {
    try {
        const response = await useProgramApi.getPrograms({
            page,
            paginate: paginate.value,
            search: search.value,
            sort_order: sort_order.value,
            sort_field: sort_field.value,
        });
        programs.value = response.data;
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
    getPrograms();
};

watch([paginate, search], () => {
    getPrograms();
});

const deleteProgram = (id) => {
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
            useProgramApi.deleteProgram(id)
                .then((response) => {
                    Swal.close();
                    sweetAlert("success", response.data.message);
                    getPrograms();
                })
                .catch((error) => {
                    Swal.close();
                    if (error.response.data.errors) {
                        actions.setErrors(error.response.data.errors);
                    }
                    sweetAlert("error", error.response.data.message);
                });
        }
    });
};

const checkPermissions = async () => {
    try {
        const response = await useProgramApi.checkPermissions()
        permissions.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

onMounted(() => {
    checkPermissions();
    getPrograms();
});
</script>
<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Programs</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium">
                            <a href="/admin/dashboard">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">
                            Programs
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between mb-2">
                <div v-if="permissions.create" class="d-flex">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#new-program">
                        <i class="bi bi-plus-circle me-1"></i>New Program
                    </button>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12" style="font-size: 13px">
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
                                            <th class="align-middle text-center">#</th>
                                            <th class="align-middle text-center">
                                                <a class="text-decoration-none" @click.prevent="clickSort(SORT_FIELD.CREATED_AT)">
                                                    Date Added
                                                    <span v-if="sort_field === SORT_FIELD.CREATED_AT">
                                                        <i :class="sort_order === SORT.ASC ? 'bi bi-sort-down' : 'bi bi-sort-up'"></i>
                                                    </span>
                                                </a>
                                            </th>
                                            <th class="align-middle text-center">Initials</th>
                                            <th class="align-middle text-center">Name</th>
                                            <th class="align-middle text-center">Description</th>
                                            <th class="align-middle text-center">Status</th>
                                            <th class="align-middle text-center">Action/s</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="programs.meta && programs.meta.total > 0">
                                        <tr v-for="(program, index) in programs.data" :key="program.id">
                                            <td class="align-middle text-center">{{ index + 1 }}</td>
                                            <td class="align-middle text-center">{{ program.created_at }}</td>
                                            <td class="align-middle text-center">{{ program.initials }}</td>
                                            <td class="align-middle">{{ program.name }}</td>
                                            <td class="align-middle">{{ program.description }}</td>
                                            <td class="align-middle text-center">
                                                <span :class="program.status_color">{{ program.status_text }}</span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <button v-if="permissions.update" class="btn btn-secondary btn-sm me-1"
                                                    @click="editProgram(program)" data-bs-toggle="modal"
                                                    data-bs-target="#update-program">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button v-if="permissions.delete" type="button"
                                                    class="btn btn-danger btn-sm"
                                                    @click="deleteProgram(program.id, 'delete')">
                                                    <i class="bi bi-trash-fill"></i>
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
                            <div v-if="programs.meta && programs.meta.total > 0" class="row mt-3">
                                <div class="col-md-6">
                                    Showing {{ programs.meta.from }} to {{ programs.meta.to }} of {{
                                        programs.meta.total.toLocaleString() }} entries
                                </div>

                                <div class="col-md-6">
                                    <Bootstrap5Pagination :data="programs" :limit="5" size="small"
                                        :show-disabled="true" align="right" @pagination-change-page="getPrograms" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <ProgramsAdd @program-added="getPrograms" />
    <ProgramsEdit ref="editModal" @program-updated="getPrograms" />
</template>
