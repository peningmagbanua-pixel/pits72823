<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";

const dashboardData = ref({
    applicant: null,
    school_year: null,
});
const loading = ref(true);


const fetchDashboardData = async () => {
    try {
        const response = await axios.get("/api/applicant/dashboard");
        dashboardData.value = response.data;
    } catch (error) {
        console.error("Error fetching dashboard data:", error);
    } finally {
        loading.value = false;
    }
};


const daysRemaining = (dateStr) => {
    if (!dateStr) return null;
    const now = new Date();
    const target = new Date(dateStr);
    const diff = Math.ceil((target - now) / (1000 * 60 * 60 * 24));
    return diff;
};

// "2026-10-09" (or an ISO timestamp) -> "October 9, 2026"
const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const [year, month, day] = String(dateStr).slice(0, 10).split('-').map(Number);
    if (!year || !month || !day) return dateStr;
    return new Date(year, month - 1, day).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const deadlineDays = computed(() => daysRemaining(dashboardData.value.school_year?.application_deadline));

onMounted(() => {
    fetchDashboardData();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Dashboard</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item">
                            <a href="/applicant/dashboard">Home</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            Dashboard
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <div v-else>
                <!-- Welcome Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body text-center py-4">
                        <img src="/images/cnhs_logo.png" alt="CNHS Logo" class="mb-3" style="width: 80px;">
                        <h4 class="fw-bold text-secondary mb-1">Welcome{{ dashboardData.applicant?.name ? ', ' + dashboardData.applicant.name : '' }}!</h4>
                        <p class="text-muted mb-0">Carranglan National High School — Online Application Portal</p>
                    </div>
                </div>

                <!-- My Exam Schedule -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fw-bold text-secondary">
                            <i class="bi bi-file-earmark-person-fill me-2 text-success"></i>
                            My Exam Schedule
                        </h5>
                    </div>
                    <div class="card-body">
                        <div v-if="dashboardData.schedule" class="row g-3">
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded border text-center h-100">
                                    <small class="text-muted text-uppercase fw-bold">Program</small>
                                    <p class="fw-bold mb-0 mt-2">
                                        {{ dashboardData.schedule.program }}
                                        <span class="badge bg-success-subtle text-success ms-1">Scheduled</span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded border text-center h-100">
                                    <small class="text-muted text-uppercase fw-bold">Exam Date</small>
                                    <p class="fs-5 fw-bold mb-0 mt-2">{{ formatDate(dashboardData.schedule.date) || 'TBA' }}</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded border text-center h-100">
                                    <small class="text-muted text-uppercase fw-bold">Time Slot</small>
                                    <p class="fs-5 fw-bold mb-0 mt-2">{{ dashboardData.schedule.time_slot || 'TBA' }}</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded border text-center h-100">
                                    <small class="text-muted text-uppercase fw-bold">Room</small>
                                    <p class="fs-5 fw-bold mb-0 mt-2">{{ dashboardData.schedule.room || 'TBA' }}</p>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-success mb-0 py-2 small">
                                    <i class="bi bi-check-circle-fill me-1"></i>
                                    You have been scheduled for the admission examination. Please arrive on time and bring your SF9 and a valid ID.
                                </div>
                            </div>
                        </div>
                        <div v-else-if="dashboardData.applicant?.application_status === 'approved'" class="text-center py-3">
                            <i class="bi bi-hourglass-split text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mb-0 mt-2 small">
                                You have not been assigned to an exam schedule yet. Your exam schedule will appear here once assigned.
                            </p>
                        </div>
                        <div v-else class="text-center py-3">
                            <p class="text-muted mb-0 small">
                                Your exam schedule will appear here once you have submitted your application and been assigned to a schedule.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- School Year & Key Dates -->
                <div v-if="dashboardData.school_year" class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3">
                                <h5 class="card-title mb-0 fw-bold text-secondary">
                                    <i class="bi bi-calendar-event me-2"></i>
                                    {{ dashboardData.school_year.name }} — Dates to Remember
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded border text-center h-100">
                                            <small class="text-muted text-uppercase fw-bold">Application Start</small>
                                            <p class="fs-5 fw-bold mb-1 mt-2">{{ formatDate(dashboardData.school_year.application_start) || 'TBA' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded border text-center h-100">
                                            <small class="text-muted text-uppercase fw-bold">Application Deadline</small>
                                            <p class="fs-5 fw-bold mb-1 mt-2">{{ formatDate(dashboardData.school_year.application_deadline) || 'TBA' }}</p>
                                            <span v-if="deadlineDays !== null" :class="deadlineDays <= 0 ? 'text-danger' : 'text-warning'" class="fw-semibold small">
                                                {{ deadlineDays > 0 ? deadlineDays + ' days remaining' : deadlineDays === 0 ? 'Last day today' : 'Deadline passed' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guidelines -->
                <div v-if="dashboardData.school_year?.guidelines" class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fw-bold text-secondary">
                            <i class="bi bi-info-circle me-2"></i>
                            Qualification Guidelines
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="p-3 bg-light rounded border">
                            <p class="mb-0 text-secondary" style="white-space: pre-line;">{{ dashboardData.school_year.guidelines }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
