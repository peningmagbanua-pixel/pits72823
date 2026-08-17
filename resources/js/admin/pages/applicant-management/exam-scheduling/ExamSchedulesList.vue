<script setup>
import { ref, onMounted, watch } from "vue";
import { Bootstrap5Pagination } from 'laravel-vue-pagination';
import axios from "axios";
import Swal from "sweetalert2";
import { Modal } from "bootstrap";
import { escapeHtml } from "../../../../constants";

const paginate = ref(10);
const search = ref('');
const schedules = ref([]);
const selectedApplicants = ref([]);
const selectedScheduleId = ref(null);
const loading = ref(false);
const applicants = ref([]);
const submitting = ref(false);
let scheduleModalInstance = null;
let manualAssignModalInstance = null;

const scheduleForm = ref({
    id: null,
    program: "STE",
    date: "",
    time_slot: "",
    room: "",
    capacity: 50,
});

const isEditing = ref(false);

const fetchSchedules = async (page = 1) => {
    loading.value = true;
    try {
        const response = await axios.get("/api/exam-schedules", {
            params: {
                paginate: paginate.value,
                page,
                search: search.value,
            }
        });
        schedules.value = response.data;
    } catch (error) {
        console.error("Error fetching schedules:", error);
    } finally {
        loading.value = false;
    }
};

watch([paginate, search], () => {
    fetchSchedules();
});

const fetchUnscheduledApplicants = async () => {
    try {
        const response = await axios.get("/api/unscheduled-applicants");
        applicants.value = response.data;
    } catch (error) {
        console.error("Error fetching applicants:", error);
    }
};

const submitSchedule = async () => {
    if (submitting.value) return;
    submitting.value = true;
    try {
        if (isEditing.value) {
            await axios.put(
                `/api/exam-schedules/${scheduleForm.value.id}`,
                scheduleForm.value,
            );
            Swal.fire("Success", "Schedule updated successfully!", "success");
        } else {
            await axios.post("/api/exam-schedules", scheduleForm.value);
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
    if (manualAssignModalInstance) {
        manualAssignModalInstance.show();
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

    try {
        await axios.post("/api/exam-schedules/manual-assign", {
            applicant_ids: selectedApplicants.value,
            schedule_id: selectedScheduleId.value,
        });
        Swal.fire("Success", "Applicants assigned successfully!", "success");
        if (manualAssignModalInstance) {
            manualAssignModalInstance.hide();
        }
        fetchSchedules();
        fetchUnscheduledApplicants();
    } catch (error) {
        const validationErrors = error.response?.data?.errors
            ? Object.values(error.response.data.errors).flat().join(" ")
            : null;

        Swal.fire(
            "Error",
            validationErrors ||
                error.response?.data?.message ||
                "Assignment failed.",
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

const deleteSchedule = async (id) => {
    const result = await Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Yes, delete it!",
    });

    if (result.isConfirmed) {
        try {
            await axios.delete(`/api/exam-schedules/${id}`);
            Swal.fire("Deleted!", "Schedule has been deleted.", "success");
            fetchSchedules();
        } catch (error) {
            Swal.fire("Error", "Failed to delete schedule.", "error");
        }
    }
};

const printSchedule = async (schedule) => {
    try {
        const res = await axios.get(`/api/exam-schedules/${schedule.id}/applicants`);
        const { schedule: sched, applicants } = res.data;

        const rows = applicants.map((a, i) =>
            `<tr><td>${i + 1}</td><td>${escapeHtml(a.firstname)} ${escapeHtml(a.lastname)}</td><td>${escapeHtml(a.email)}</td><td>${escapeHtml(a.program)}</td></tr>`
        ).join('');

        const printWin = window.open('', '_blank');
        printWin.document.write(`
            <!DOCTYPE html>
            <html><head>
                <title>Approved Applicants - ${escapeHtml(sched.program)}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 40px; }
                    h2 { text-align: center; margin-bottom: 5px; }
                    .subtitle { text-align: center; color: #555; margin-bottom: 30px; }
                    .info-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                    .info-table td { border: 1px solid #999; padding: 8px 12px; }
                    .info-table td:first-child { font-weight: bold; width: 20%; background: #f5f5f5; }
                    table.applicants { width: 100%; border-collapse: collapse; }
                    table.applicants th, table.applicants td { border: 1px solid #999; padding: 8px 12px; text-align: left; }
                    table.applicants th { background: #2c3e50; color: #fff; }
                    table.applicants tr:nth-child(even) { background: #f9f9f9; }
                    .print-btn { text-align: center; margin-bottom: 20px; }
                    @media print { .print-btn { display: none; } }
                </style>
            </head><body>
                <div class="print-btn"><button onclick="window.print()">Print</button></div>
                <h2>Carranglan National High School</h2>
                <p class="subtitle">Approved Applicants — ${escapeHtml(sched.program)} (${escapeHtml(sched.date)})</p>
                <table class="info-table">
                    <tr><td>Program</td><td>${escapeHtml(sched.program)}</td></tr>
                    <tr><td>Date</td><td>${escapeHtml(sched.date)}</td></tr>
                    <tr><td>Time</td><td>${escapeHtml(sched.time_slot)}</td></tr>
                    <tr><td>Room</td><td>${escapeHtml(sched.room)}</td></tr>
                    <tr><td>Capacity</td><td>${escapeHtml(sched.capacity)}</td></tr>
                    <tr><td>Assigned</td><td>${applicants.length} / ${escapeHtml(sched.capacity)}</td></tr>
                </table>
                <table class="applicants">
                    <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Program</th></tr></thead>
                    <tbody>${rows || '<tr><td colspan="4" style="text-align:center">No approved applicants assigned.</td></tr>'}</tbody>
                </table>
            </body></html>
        `);
        printWin.document.close();
    } catch (error) {
        console.error('Print failed:', error);
        Swal.fire('Error', 'Failed to load applicants for printing.', 'error');
    }
};

const resetForm = () => {
    isEditing.value = false;
    scheduleForm.value = {
        id: null,
        program: "STE",
        date: "",
        time_slot: "",
        room: "",
        capacity: 50,
    };
};

const autoAssign = async (program) => {
    const result = await Swal.fire({
        title: "Auto Assign?",
        text: `Assign all unscheduled ${program} applicants to available slots?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, assign",
    });

    if (result.isConfirmed) {
        try {
            const response = await axios.post(
                "/api/exam-schedules/auto-assign",
                { program },
            );
            Swal.fire("Success", response.data.message, "success");
            fetchSchedules();
            fetchUnscheduledApplicants();
        } catch (error) {
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
    }
};

onMounted(() => {
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
                    <button class="btn btn-primary btn-sm" @click="resetForm" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                        <i class="bi bi-plus-circle me-1"></i>Create Schedule
                    </button>
                    <button class="btn btn-outline-primary btn-sm" @click="autoAssign('STE')">
                        <i class="bi bi-lightning-fill me-1"></i>Auto Assign STE
                    </button>
                    <button class="btn btn-outline-primary btn-sm" @click="autoAssign('SPICT')">
                        <i class="bi bi-lightning-fill me-1"></i>Auto Assign SPICT
                    </button>
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
                                            <th class="align-middle text-center">Program</th>
                                            <th class="align-middle text-center">Date</th>
                                            <th class="align-middle text-center">Time</th>
                                            <th class="align-middle text-center">Room</th>
                                            <th class="align-middle text-center">Capacity</th>
                                            <th class="align-middle text-center">Assigned</th>
                                            <th class="align-middle text-center">Action/s</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="schedules.total > 0">
                                        <tr v-for="(schedule, index) in schedules.data" :key="schedule.id">
                                            <td class="align-middle text-center">{{ (schedules.current_page - 1) * paginate + index + 1 }}</td>
                                            <td class="align-middle text-center">{{ schedule.program }}</td>
                                            <td class="align-middle text-center">{{ schedule.date }}</td>
                                            <td class="align-middle text-center">{{ schedule.time_slot }}</td>
                                            <td class="align-middle text-center">{{ schedule.room }}</td>
                                            <td class="align-middle text-center">{{ schedule.capacity }}</td>
                                            <td class="align-middle text-center">{{ schedule.applicants_count }} / {{ schedule.capacity }}</td>
                                            <td class="align-middle text-center">
                                                <button class="btn btn-secondary btn-sm me-1" @click="openManualAssign(schedule)" title="Assign Applicants">
                                                    <i class="bi bi-person-plus"></i>
                                                </button>
                                                <button class="btn btn-secondary btn-sm me-1" @click="editSchedule(schedule)">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary btn-sm me-1" @click="printSchedule(schedule)" title="Print Approved Applicants">
                                                    <i class="bi bi-printer"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" @click="deleteSchedule(schedule.id)">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="8" class="text-center">No schedules found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="schedules.total > 0" class="row mt-3">
                                <div class="col-md-6">
                                    Showing {{ schedules.from }} to {{ schedules.to }} of
                                    {{ schedules.total.toLocaleString() }} entries
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <form @submit.prevent="submitSchedule">
                        <div class="mb-3">
                            <label class="form-label required">Program</label>
                            <select v-model="scheduleForm.program" class="form-select form-select-sm" required>
                                <option value="STE">STE</option>
                                <option value="SPICT">SPICT</option>
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
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm" :disabled="submitting">
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                            </tbody>
                        </table>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">{{ selectedApplicants.length }} selected</span>
                        <div>
                            <button type="button" class="btn btn-secondary btn-sm me-1" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary btn-sm" @click="assignApplicants">Assign Selected</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
