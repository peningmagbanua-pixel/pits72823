<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Bootstrap5Pagination } from 'laravel-vue-pagination';
import { SORT, SORT_FIELD, escapeHtml } from '../../../../constants';
import ApplicantViewModal from '../applicants/ApplicantViewModal.vue';
import { useAuthUserStore } from '../../../stores/AuthUserStore';

const authUserStore = useAuthUserStore();

const applicants   = ref([]);
const loading      = ref(true);
const search       = ref('');
const programFilter = ref('');
const statusFilter  = ref('');
const sortField    = ref(SORT_FIELD.CREATED_AT);
const sortOrder    = ref(SORT.DESC);
const paginate     = ref(10);
const viewModalRef = ref(null);
const permissions   = ref([]);
const applicantPermissions = ref([]);

const checkPermissions = async () => {
    try {
        const response = await axios.get('/api/check/applications/permissions');
        permissions.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

const checkApplicantPermissions = async () => {
    try {
        const response = await axios.get('/api/check/applicants/permissions');
        applicantPermissions.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

const allowedPrograms = computed(() => authUserStore.user?.programs ?? []);
const programs = ref([]);

const fetchPrograms = async () => {
    try {
        const response = await axios.get('/api/programs', { params: { paginate: 100 } });
        programs.value = response.data.data || response.data;
    } catch (e) {
        console.error(e);
    }
};

const programOptions = computed(() => {
    if (allowedPrograms.value.length === 0) return programs.value;
    return programs.value.filter(p => allowedPrograms.value.includes(p.initials));
});

const fetchApplicants = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            paginate: paginate.value,
            page,
            search: search.value,
            sort_field: sortField.value,
            sort_order: sortOrder.value,
        };
        if (programFilter.value) params.program_id = programFilter.value;
        if (statusFilter.value)  params.status = statusFilter.value;

        const res = await axios.get('/api/applicants', { params });
        applicants.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
};

watch([paginate, search, programFilter, statusFilter], () => fetchApplicants(1));

// Staff restricted to a single program always see that program's applications.
watch(allowedPrograms, (progs) => {
    if (progs.length === 1 && !programFilter.value) {
        const program = programs.value.find(p => p.initials === progs[0]);
        programFilter.value = program?.id ?? '';
    }
}, { immediate: true });

const clickSort = (field) => {
    if (sortField.value === field) {
        sortOrder.value = sortOrder.value === SORT.ASC ? SORT.DESC : SORT.ASC;
    } else {
        sortField.value = field;
    }
    fetchApplicants();
};

const printApproved = async () => {
    try {
        const params = {};
        if (programFilter.value) params.program_id = programFilter.value;

        const res = await axios.get('/api/applicants/print-approved', { params });
        const data = res.data;
        const printWindow = window.open('', '_blank');

        let html = `<!DOCTYPE html>
<html><head><title>Approved Applicants</title>
<style>
    body { font-family: Arial, sans-serif; padding: 40px; }
    h2 { text-align: center; margin-bottom: 5px; }
    .subtitle { text-align: center; color: #555; margin-bottom: 30px; }
    h3 { background: #2c3e50; color: #fff; padding: 8px 12px; margin: 20px 0 10px; font-size: 14px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #999; padding: 8px 12px; text-align: left; font-size: 13px; }
    th { background: #2c3e50; color: #fff; font-weight: 600; }
    tr:nth-child(even) { background: #f9f9f9; }
    .text-center { text-align: center; }
    .page-break { page-break-after: always; }
    .school-name { text-align: center; font-size: 16px; font-weight: bold; color: #333; }
    @media print { .no-print { display: none; } }
</style></head><body>
<div class="school-name">Carranglan National High School</div>
<h2>Approved Applicants</h2>`;

        if (data.spict && data.spict.length > 0) {
            html += `<h3>SPICT</h3><table><thead><tr><th class="text-center">#</th><th>Name</th><th>Date Applied</th></tr></thead><tbody>`;
            data.spict.forEach((a, i) => {
                const name = [escapeHtml(a.lastname) + ',', escapeHtml(a.firstname), escapeHtml(a.middlename)].filter(Boolean).join(' ');
                html += `<tr><td class="text-center">${i + 1}</td><td>${name}</td><td>${escapeHtml(a.created_at ? a.created_at.split(' ')[0] : '')}</td></tr>`;
            });
            html += `</tbody></table><div class="page-break"></div>`;
        }

        if (data.ste && data.ste.length > 0) {
            html += `<h3>STE</h3><table><thead><tr><th class="text-center">#</th><th>Name</th><th>Date Applied</th></tr></thead><tbody>`;
            data.ste.forEach((a, i) => {
                const name = [escapeHtml(a.lastname) + ',', escapeHtml(a.firstname), escapeHtml(a.middlename)].filter(Boolean).join(' ');
                html += `<tr><td class="text-center">${i + 1}</td><td>${name}</td><td>${escapeHtml(a.created_at ? a.created_at.split(' ')[0] : '')}</td></tr>`;
            });
            html += `</tbody></table>`;
        }

        if (!data.spict?.length && !data.ste?.length) {
            html += `<p style="text-align:center;margin-top:40px">No approved applicants found.</p>`;
        }

        html += `</body></html>`;
        printWindow.document.write(html);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 500);
    } catch (e) {
        console.error(e);
    }
};

const openViewModal = async (applicant) => {
    const res = await axios.get(`/api/applicants/${applicant.id}`);
    viewModalRef.value.getApplicant(res.data.data);
};

const statusBadge = (status) => ({
    approved: 'bg-success-subtle text-success',
    rejected: 'bg-danger-subtle text-danger',
    pending:  'bg-warning-subtle text-warning',
}[status] ?? 'bg-secondary-subtle text-secondary');

onMounted(async () => {
    await Promise.all([checkPermissions(), checkApplicantPermissions()]);
    await fetchPrograms();
    fetchApplicants();
});
</script>
<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Applications</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium"><a href="/admin/dashboard">Dashboard</a></li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Applications</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <label v-if="allowedPrograms.length !== 1" class="form-label small mb-0">Program:</label>
                    <select v-if="allowedPrograms.length !== 1" v-model="programFilter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All Programs</option>
                        <option v-for="prog in programOptions" :key="prog.id" :value="prog.id">{{ prog.initials }}</option>
                    </select>
                    <label class="form-label small mb-0 ms-2">Status:</label>
                    <select v-model="statusFilter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
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
                                        <input v-model.lazy="search" type="search" class="form-control form-control-sm" placeholder="Search">
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
                                                    Date Applied
                                                    <span v-if="sortField === SORT_FIELD.CREATED_AT">
                                                        <i :class="sortOrder === SORT.ASC ? 'bi bi-sort-down' : 'bi bi-sort-up'"></i>
                                                    </span>
                                                </a>
                                            </th>
                                            <th class="align-middle text-center">
                                                <a class="text-decoration-none" @click.prevent="clickSort('lastname')">
                                                    Name
                                                    <span v-if="sortField === 'lastname'">
                                                        <i :class="sortOrder === SORT.ASC ? 'bi bi-sort-down' : 'bi bi-sort-up'"></i>
                                                    </span>
                                                </a>
                                            </th>
                                            <th class="align-middle text-center">Email</th>
                                            <th class="align-middle text-center">Program</th>
                                            <th class="align-middle text-center">Status</th>
                                            <th class="align-middle text-center">Action/s</th>
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
                                    <tbody v-else-if="applicants.meta && applicants.meta.total > 0">
                                        <tr v-for="(a, index) in applicants.data" :key="a.id">
                                            <td class="align-middle text-center">{{ (applicants.meta.current_page - 1) * paginate + index + 1 }}</td>
                                            <td class="align-middle text-center">{{ a.created_at }}</td>
                                            <td class="align-middle">{{ a.name }}</td>
                                            <td class="align-middle">{{ a.email }}</td>
                                            <td class="align-middle text-center">
                                                <span v-if="a.program" class="badge"
                                                    :class="a.program === 'STE' ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info'">
                                                    {{ a.program }}
                                                </span>
                                                <span v-else class="badge bg-secondary-subtle text-secondary">—</span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="badge text-capitalize" :class="statusBadge(a.application_status)">
                                                    {{ a.application_status }}
                                                </span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <button v-if="applicantPermissions.read" class="btn btn-info btn-sm"
                                                    @click="openViewModal(a)" title="View Application">
                                                    <i class="bi bi-eye-fill"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="7" class="text-center">No applications found.</td>
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
                                        :show-disabled="true" align="right" @pagination-change-page="fetchApplicants" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <ApplicantViewModal ref="viewModalRef" :can-update="permissions.update" @status-updated="fetchApplicants(applicants.meta.current_page)" />
</template>
