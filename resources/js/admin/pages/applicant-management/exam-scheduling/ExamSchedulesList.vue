<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Bootstrap5Pagination } from 'laravel-vue-pagination';
import axios from "axios";
import Swal from "sweetalert2";
import { Modal } from "bootstrap";
import { escapeHtml, resolveAssetUrl, waitForImages, formatProgramName } from "../../../../constants";
import { departmentLetterhead, departmentLetterheadStyles, departmentPrintFooter, paginatePrintDocument } from "../../../../print/department-letterhead";
import { processingAlert } from "../../../../sweetalert";
import { useAuthUserStore } from '../../../stores/AuthUserStore';

const authUserStore = useAuthUserStore();
const allowedPrograms = computed(() => authUserStore.user?.programs ?? []);
const canAccess = (program) => allowedPrograms.value.includes(program);

const paginate = ref(10);
const search = ref('');
const showDeleted = ref(false);
const schedules = ref({
    data: [],
    links: {},
    meta: {
        total: 0,
        current_page: 1,
        from: 0,
        to: 0,
        last_page: 1,
        per_page: 10
    }
});

const pageMeta = computed(() => schedules.value.meta ?? {
    total: 0,
    current_page: 1,
    from: 0,
    to: 0,
    last_page: 1,
    per_page: paginate.value
});
const schoolYears = ref([]);
const programs = ref([]);
const selectedApplicants = ref([]);
const selectedScheduleId = ref(null);
const loading = ref(true);
const applicants = ref([]);
const applicantsLoading = ref(false);
const submitting = ref(false);
let scheduleModalInstance = null;
let manualAssignModalInstance = null;

const scheduleForm = ref({
    id: null,
    school_year_id: '',
    program_id: '',
    date: "",
    time_slot: "",
    room: "",
    capacity: 50,
});

const isEditing = ref(false);

const activeSchoolYear = computed(() => schoolYears.value.find(sy => sy.is_active) || null);

const formSchoolYearId = computed(() => {
    if (isEditing.value) {
        return scheduleForm.value.school_year_id ?? '';
    }
    return activeSchoolYear.value?.id ?? '';
});

const formSchoolYearLabel = computed(() => {
    if (isEditing.value) {
        const own = scheduleForm.value.school_year_id
            ? schoolYears.value.find(sy => sy.id === scheduleForm.value.school_year_id)
            : null;
        return own?.school_year_name
            || scheduleForm.value.school_year_name
            || '—';
    }
    if (activeSchoolYear.value) {
        return `${activeSchoolYear.value.school_year_name} (Active)`;
    }
    return 'No active school year';
});

const steProgram = computed(() => programs.value.find(p => p.initials === 'STE'));
const spictProgram = computed(() => programs.value.find(p => p.initials === 'SPICT'));
const steProgramId = computed(() => steProgram.value?.id);
const spictProgramId = computed(() => spictProgram.value?.id);

const formPrograms = computed(() => programs.value.filter(p => canAccess(p.initials)));

const fetchPrograms = async () => {
    try {
        const response = await axios.get("/api/programs", {
            params: { paginate: 100 }
        });
        programs.value = response.data.data || response.data;
    } catch (error) {
        console.error("Error fetching programs:", error);
    }
};

const fetchSchoolYears = async () => {
    try {
        const response = await axios.get("/api/school-years", {
            params: { paginate: 100 }
        });
        schoolYears.value = response.data.data || response.data;
    } catch (error) {
        console.error("Error fetching school years:", error);
    }
};

const fetchSchedules = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            paginate: paginate.value,
            page,
            search: search.value,
            deleted: showDeleted.value ? 1 : 0,
        };
        const response = await axios.get("/api/exam-schedules", { params });
        schedules.value = response.data;
    } catch (error) {
        console.error("Error fetching schedules:", error);
    } finally {
        loading.value = false;
    }
};

watch([paginate, search, showDeleted], () => {
    fetchSchedules();
});

const fetchUnscheduledApplicants = async (scheduleId = null) => {
    applicantsLoading.value = true;
    try {
        const params = scheduleId ? { schedule_id: scheduleId } : {};
        const response = await axios.get("/api/unscheduled-applicants", { params });
        applicants.value = response.data;
    } catch (error) {
        console.error("Error fetching applicants:", error);
    } finally {
        applicantsLoading.value = false;
    }
};

const submitSchedule = async () => {
    if (submitting.value) return;

    if (!isEditing.value && !activeSchoolYear.value) {
        Swal.fire(
            "Warning",
            "No active school year found. Please activate a school year first.",
            "warning",
        );
        return;
    }

    const payload = {
        ...scheduleForm.value,
        school_year_id: formSchoolYearId.value,
    };

    submitting.value = true;
    try {
        if (isEditing.value) {
            await axios.put(
                `/api/exam-schedules/${scheduleForm.value.id}`,
                payload,
            );
            Swal.fire("Success", "Schedule updated successfully!", "success");
        } else {
            await axios.post("/api/exam-schedules", payload);
            Swal.fire("Success", "Schedule created successfully!", "success");
        }
        resetForm();
        fetchSchedules();
        if (scheduleModalInstance) {
            scheduleModalInstance.hide();
        }
    } catch (error) {
        const validationErrors = error.response?.data?.errors
            ? Object.values(error.response.data.errors).flat().join(" ")
            : null;

        Swal.fire(
            "Error",
            validationErrors ||
                error.response?.data?.message ||
                "Something went wrong",
            "error",
        );
    } finally {
        submitting.value = false;
    }
};

const openManualAssign = (schedule) => {
    selectedScheduleId.value = schedule.id;
    selectedApplicants.value = [];
    fetchUnscheduledApplicants(schedule.id);
    if (manualAssignModalInstance) {
        manualAssignModalInstance.show();
    }
};

const closeManualAssignModal = () => {
    const el = document.getElementById("manualAssignModal");
    if (!el) return;
    const inst = Modal.getInstance(el) || manualAssignModalInstance;
    if (inst) {
        inst.hide();
    }
    if (el.classList.contains("show")) {
        try {
            inst?.dispose();
        } catch (e) {
            // ignore — instance may already be broken
        }
        el.classList.remove("show");
        el.style.display = "none";
        el.setAttribute("aria-hidden", "true");
        el.removeAttribute("aria-modal");
        el.removeAttribute("role");
        document.querySelectorAll(".modal-backdrop").forEach(b => b.remove());
        document.body.classList.remove("modal-open");
        document.body.style.overflow = "";
        document.body.style.paddingRight = "";
        manualAssignModalInstance = new Modal(el);
    }
};

const assignApplicants = async () => {
    if (selectedApplicants.value.length === 0) {
        Swal.fire(
            "Warning",
            "Please select at least one applicant.",
            "warning",
        );
        return;
    }

    processingAlert('.btn');
    try {
        await axios.post("/api/exam-schedules/manual-assign", {
            applicant_ids: selectedApplicants.value,
            schedule_id: selectedScheduleId.value,
        });
        Swal.close();
        Swal.fire("Success", "Applicants assigned successfully!", "success");
        closeManualAssignModal();
        fetchSchedules();
        fetchUnscheduledApplicants(selectedScheduleId.value);
    } catch (error) {
        Swal.close();
        const validationErrors = error.response?.data?.errors
            ? Object.values(error.response.data.errors).flat().join(" ")
            : null;

        Swal.fire(
            "Error",
            validationErrors ||
                error.response?.data?.message ||
                "Assignment failed.",
            "error",
        );
    }
};

const editSchedule = (schedule) => {
    isEditing.value = true;
    scheduleForm.value = { ...schedule };

    if (schedule.applicants_count > 0) {
        Swal.fire({
            title: "Warning",
            text: "This schedule already has assigned applicants. Changes will affect them.",
            icon: "warning",
            confirmButtonText: "Continue Editing",
            showCancelButton: true,
            cancelButtonText: "Cancel",
        }).then((result) => {
            if (result.isConfirmed && scheduleModalInstance) {
                scheduleModalInstance.show();
            }
        });
        return;
    }

    if (scheduleModalInstance) {
        scheduleModalInstance.show();
    }
};

const closeScheduleModal = () => {
    const el = document.getElementById("scheduleModal");
    if (!el) return;
    const inst = Modal.getInstance(el) || scheduleModalInstance;
    if (inst) {
        inst.hide();
    }
    if (el.classList.contains("show")) {
        try {
            inst?.dispose();
        } catch (e) {
            // ignore — instance may already be broken
        }
        el.classList.remove("show");
        el.style.display = "none";
        el.setAttribute("aria-hidden", "true");
        el.removeAttribute("aria-modal");
        el.removeAttribute("role");
        document.querySelectorAll(".modal-backdrop").forEach(b => b.remove());
        document.body.classList.remove("modal-open");
        document.body.style.overflow = "";
        document.body.style.paddingRight = "";
        scheduleModalInstance = new Modal(el);
    }
};

const deleteSchedule = async (id) => {
    const result = await Swal.fire({
        title: "Delete Schedule?",
        text: "This will remove the schedule from this list. Applicants assigned to it will remain listed in Reports \u2192 Exam Schedule.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Yes, delete it!",
    });

    if (result.isConfirmed) {
        processingAlert('.btn');
        try {
            await axios.delete(`/api/exam-schedules/${id}`);
            Swal.close();
            Swal.fire("Deleted!", "Schedule has been deleted.", "success");
            fetchSchedules();
        } catch (error) {
            Swal.close();
            Swal.fire("Error", "Failed to delete schedule.", "error");
        }
    }
};

const restoreSchedule = async (id) => {
    const result = await Swal.fire({
        title: "Restore Schedule?",
        text: "This will return the schedule to the active scheduling list.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, restore it!",
    });

    if (result.isConfirmed) {
        processingAlert('.btn');
        try {
            await axios.post(`/api/exam-schedules/${id}/restore`);
            Swal.close();
            Swal.fire("Restored!", "Schedule has been restored.", "success");
            fetchSchedules();
        } catch (error) {
            Swal.close();
            Swal.fire("Error", error.response?.data?.message || "Failed to restore schedule.", "error");
        }
    }
};

const printSchedule = async (schedule) => {
    try {
        const res = await axios.get(`/api/exam-schedules/${schedule.id}/applicants`);
        const { schedule: sched, applicants } = res.data;

        const baseStyle = `
            @page {
                size:A4;
                margin:15mm;
                @top-left { content:""; }
                @top-center { content:""; }
                @top-right { content:""; }
                @bottom-left { content:""; }
                @bottom-center { content:""; }
                @bottom-right { content:""; }
            }
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family:Arial,sans-serif; font-size:13px; padding:0; color:#111; }
            ${departmentLetterheadStyles}
            .report-title { font-family:"Times New Roman",serif; font-size:14px; font-weight:bold; margin:8px 0 2px; text-align:center; text-transform:uppercase; }
            .meta { font-size:11px; color:#444; margin-bottom:12px; text-align:center; }
            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px; }
            .info-table td { border: 1px solid #999; padding: 8px 12px; }
            .info-table td:first-child { font-weight: bold; width: 20%; background: #f5f5f5; }
            table.applicants, .print-table { width: 100%; border-collapse: collapse; }
            table.applicants th, .print-table th, table.applicants td, .print-table td { border: 1px solid #ccc; padding: 6px 10px; vertical-align: middle; }
            table.applicants th, .print-table th { background: #333; color: #fff; font-size: 12px; font-weight: 600; }
            table.applicants td, .print-table td { font-size: 13px; }
            table.applicants tr:nth-child(even) td, .print-table tr:nth-child(even) td { background: #f7f7f7; }
            .tc { text-align: center; }`;

        const headerHtml = `
            ${departmentLetterhead(resolveAssetUrl)}
            <div class="report-title">Exam Schedule - ${escapeHtml(formatProgramName(sched.program))}</div>
            <div class="meta">${escapeHtml(sched.date)} • ${escapeHtml(sched.time_slot)} • Room: ${escapeHtml(sched.room)}</div>`;

        const prefixHtml = `
            ${headerHtml}
            <table class="info-table">
                <tr><td>Program</td><td>${escapeHtml(formatProgramName(sched.program))}</td></tr>
                <tr><td>Date</td><td>${escapeHtml(sched.date)}</td></tr>
                <tr><td>Time</td><td>${escapeHtml(sched.time_slot)}</td></tr>
                <tr><td>Room</td><td>${escapeHtml(sched.room)}</td></tr>
                <tr><td>Capacity</td><td>${escapeHtml(sched.capacity)}</td></tr>
                <tr><td>Assigned</td><td>${applicants.length} / ${escapeHtml(sched.capacity)}</td></tr>
            </table>`;
        const html = `<!DOCTYPE html><html><head><meta charset="UTF-8">
            <title></title>
            <style>${baseStyle}</style>
            </head><body></body></html>`;

        const printWin = window.open('', '_blank');
        if (!printWin) {
            Swal.fire('Pop-up Blocked', 'Please allow pop-ups for this site, then try printing again.', 'warning');
            return;
        }
        printWin.document.write(html);
        printWin.document.close();
        await waitForImages(printWin);
        const tableRows = applicants.length
            ? applicants.map((applicant, index) =>
                `<td class="tc">${index + 1}</td><td>${escapeHtml(applicant.firstname)} ${escapeHtml(applicant.lastname)}</td><td>${escapeHtml(applicant.email)}</td>`
            )
            : ['<td colspan="3" class="tc" style="padding:18px;">No approved applicants assigned.</td>'];
        paginatePrintDocument(printWin, {
            groups: [{ prefixHtml, rows: tableRows }],
            tableHeadHtml: `
                <th class="tc" style="width:45px;">#</th>
                <th>Name</th>
                <th style="width:45%;">Email</th>`,
            footerHtml: departmentPrintFooter(resolveAssetUrl),
        });
        await waitForImages(printWin);
        printWin.focus();
        printWin.print();
    } catch (error) {
        console.error('Print failed:', error);
        Swal.fire('Error', 'Failed to load applicants for printing.', 'error');
    }
};

const resetForm = async () => {
    isEditing.value = false;
    // Refresh so the field always reflects the current active school year
    await fetchSchoolYears();
    scheduleForm.value = {
        id: null,
        school_year_id: activeSchoolYear.value?.id ?? '',
        program_id: formPrograms.value[0]?.id ?? '',
        date: "",
        time_slot: "",
        room: "",
        capacity: 50,
    };
};

const autoAssign = async (programId) => {
    const activeSY = schoolYears.value.find(sy => sy.is_active);
    if (!activeSY) {
        Swal.fire('Error', 'No active school year found. Please contact the administrator.', 'error');
        return;
    }

    const program = programs.value.find(p => p.id === programId);
    const programName = program ? program.initials : programId;

    const result = await Swal.fire({
        title: "Auto Assign?",
        text: `Assign all unscheduled ${programName} applicants to available slots in ${activeSY.school_year_name}?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, assign",
    });

    if (result.isConfirmed) {
        processingAlert('.btn');
        try {
            const response = await axios.post(
                "/api/exam-schedules/auto-assign",
                { program_id: programId, school_year_id: activeSY.id },
            );
            Swal.close();
            Swal.fire("Success", response.data.message, "success");
            fetchSchedules();
            fetchUnscheduledApplicants();
        } catch (error) {
            const validationErrors = error.response?.data?.errors
                ? Object.values(error.response.data.errors).flat().join(" ")
                : null;

            Swal.close();
            Swal.fire(
                "Error",
                validationErrors ||
                    error.response?.data?.message ||
                    "Assignment failed.",
                "error",
            );
        }
    }
};

const permissions = ref([]);

const checkPermissions = async () => {
    try {
        const response = await axios.get('/api/check/exam-scheduling/permissions');
        permissions.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

onMounted(async () => {
    checkPermissions();
    await fetchPrograms();
    await fetchSchoolYears();
    fetchSchedules();
    fetchUnscheduledApplicants();

    const scheduleModalElement = document.getElementById("scheduleModal");
    const manualAssignModalElement =
        document.getElementById("manualAssignModal");
    if (scheduleModalElement) {
        scheduleModalInstance = new Modal(scheduleModalElement);
    }
    if (manualAssignModalElement) {
        manualAssignModalInstance = new Modal(manualAssignModalElement);
    }
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Exam Scheduling</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium"><a href="/admin/dashboard">Dashboard</a></li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Exam Scheduling</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <button v-if="permissions.create" class="btn btn-primary btn-sm" @click="resetForm" title="Create schedule" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                        <i class="bi bi-plus-circle me-1"></i>Create Schedule
                    </button>
                    <button v-if="permissions.create && canAccess('STE')" class="btn btn-outline-primary btn-sm" @click="autoAssign(steProgramId)" title="Auto-assign applicants to STE schedules">
                        <i class="bi bi-lightning-fill me-1"></i>Auto Assign STE
                    </button>
                    <button v-if="permissions.create && canAccess('SPICT')" class="btn btn-outline-primary btn-sm" @click="autoAssign(spictProgramId)" title="Auto-assign applicants to SPICT schedules">
                        <i class="bi bi-lightning-fill me-1"></i>Auto Assign SPICT
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
                                            <label for="paginate" class="text-nowrap ms-2 mb-0 form-label">entries per page</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-7"></div>
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
                                            <th class="align-middle text-center">School Year</th>
                                            <th class="align-middle text-center">Program</th>
                                            <th class="align-middle text-center">Date</th>
                                            <th class="align-middle text-center">Time</th>
                                            <th class="align-middle text-center">Room</th>
                                            <th class="align-middle text-center">Capacity</th>
                                            <th class="align-middle text-center">Assigned</th>
                                            <th class="align-middle text-center actions-cell">Action/s</th>
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
                                    <tbody v-else-if="pageMeta.total > 0">
                                        <tr v-for="(schedule, index) in schedules.data" :key="schedule.id">
                                            <td class="align-middle text-center">{{ (pageMeta.current_page - 1) * paginate + index + 1 }}</td>
                                            <td class="align-middle text-center">
                                                <span v-if="schedule.school_year_name">{{ schedule.school_year_name }}</span>
                                                <span v-else class="text-muted">Unassigned</span>
                                            </td>
                                            <td class="align-middle text-center">{{ schedule.program }}</td>
                                            <td class="align-middle text-center">{{ schedule.date }}</td>
                                            <td class="align-middle text-center">{{ schedule.time_slot }}</td>
                                            <td class="align-middle text-center">{{ schedule.room }}</td>
                                            <td class="align-middle text-center">{{ schedule.capacity }}</td>
                                            <td class="align-middle text-center">{{ schedule.applicants_count }} / {{ schedule.capacity }}</td>
                                            <td class="align-middle text-center actions-cell">
                                                <template v-if="showDeleted">
                                                    <button v-if="permissions.delete" class="btn btn-success btn-sm" @click="restoreSchedule(schedule.id)" title="Restore">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </button>
                                                </template>
                                                <template v-else>
                                                    <button v-if="permissions.create" class="btn btn-secondary btn-sm me-1" @click="openManualAssign(schedule)" title="Assign Applicants">
                                                        <i class="bi bi-person-plus"></i>
                                                    </button>
                                                    <button v-if="permissions.update" class="btn btn-secondary btn-sm me-1" @click="editSchedule(schedule)" title="Edit">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button class="btn btn-outline-secondary btn-sm me-1" @click="printSchedule(schedule)" title="Print Approved Applicants">
                                                        <i class="bi bi-printer"></i>
                                                    </button>
                                                    <button v-if="permissions.delete" class="btn btn-danger btn-sm" @click="deleteSchedule(schedule.id)" title="Delete">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </template>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="9" class="text-center">No schedules found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="pageMeta.total > 0" class="row mt-3">
                                <div class="col-md-6">
                                    Showing {{ pageMeta.from }} to {{ pageMeta.to }} of
                                    {{ pageMeta.total.toLocaleString() }} entries
                                </div>
                                <div class="col-md-6">
                                    <Bootstrap5Pagination :data="schedules" :limit="5" size="small"
                                        :show-disabled="true" align="right" @pagination-change-page="fetchSchedules" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div class="modal fade" id="scheduleModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">{{ isEditing ? 'Update Schedule' : 'New Schedule' }}</h6>
                    <button type="button" class="btn-close" title="Close" @click="closeScheduleModal" data-bs-dismiss="modal" aria-label="Close"></button>
                    <form @submit.prevent="submitSchedule">
                        <div class="mb-3">
                            <label class="form-label required">School Year</label>
                            <select :value="formSchoolYearId" class="form-select form-select-sm" disabled>
                                <option :value="formSchoolYearId">{{ formSchoolYearLabel }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Program</label>
                            <select v-model="scheduleForm.program_id" class="form-select form-select-sm" required>
                                <option v-for="prog in formPrograms" :key="prog.id" :value="prog.id">
                                    {{ prog.initials }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Date</label>
                            <input v-model="scheduleForm.date" type="date" class="form-control form-control-sm" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Time Slot</label>
                            <input v-model="scheduleForm.time_slot" type="text" class="form-control form-control-sm" placeholder="e.g., 8:00 AM - 10:00 AM" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Room</label>
                            <input v-model="scheduleForm.room" type="text" class="form-control form-control-sm" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Capacity</label>
                            <input v-model="scheduleForm.capacity" type="number" class="form-control form-control-sm" min="1" required />
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary btn-sm" title="Cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm" :disabled="submitting" title="Save schedule">
                                <span v-if="submitting" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                <i v-else class="bi bi-floppy me-1"></i>{{ isEditing ? 'Update' : 'Submit' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Assign Modal -->
    <div class="modal fade" id="manualAssignModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">Manual Applicant Assignment</h6>
                    <button type="button" class="btn-close" title="Close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="table-responsive" style="max-height: 400px">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th style="width: 40px"></th>
                                    <th>Name</th>
                                    <th>Program</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="applicantsLoading">
                                    <td colspan="4" class="text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                        Loading ...
                                    </td>
                                </tr>
                                <template v-else>
                                    <tr v-for="applicant in applicants" :key="applicant.id">
                                        <td>
                                            <input type="checkbox" v-model="selectedApplicants" :value="applicant.id" class="form-check-input" />
                                        </td>
                                        <td>{{ applicant.firstname }} {{ applicant.lastname }}</td>
                                        <td>{{ applicant.program }}</td>
                                        <td>{{ applicant.email }}</td>
                                    </tr>
                                    <tr v-if="applicants.length === 0">
                                        <td colspan="4" class="text-center py-3">No unscheduled applicants found.</td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">{{ selectedApplicants.length }} selected</span>
                        <div>
                            <button type="button" class="btn btn-secondary btn-sm me-1" title="Cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" @click="assignApplicants" title="Assign applicants">Assign Selected</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>