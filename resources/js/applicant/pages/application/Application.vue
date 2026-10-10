<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";

const router = useRouter();

const applicationData = ref({
    applicant: null,
    schedule: null,
});
const loading = ref(true);

const periodStatus = computed(() => applicationData.value.application_period?.status || "open");

const periodMessage = computed(() => {
    const period = applicationData.value.application_period;
    if (!period) return "";
    if (period.status === "not_open") {
        return "The online application is not yet open. It opens on " + formatDate(period.application_start) + ".";
    }
    return "The online application period has ended. Applications are no longer accepted.";
});

// "2026-06-01" -> "June 1, 2026"
const formatDate = (dateStr) => {
    if (!dateStr) return "";
    const [year, month, day] = String(dateStr).slice(0, 10).split("-").map(Number);
    if (!year || !month || !day) return dateStr;
    return new Date(year, month - 1, day).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};

const fetchApplicationData = async () => {
    try {
        const response = await axios.get("/api/applicant/dashboard");
        applicationData.value = response.data;
    } catch (error) {
        console.error("Error fetching application data:", error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchApplicationData();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">My Application</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item">
                            <a href="/applicant/dashboard">Home</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            Application
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

            <div v-else class="row">
                <div class="col-lg-12">
                    <!-- Notification if scheduled -->
                    <div
                        v-if="applicationData.schedule"
                        class="alert alert-success border-0 shadow-sm mb-4"
                    >
                        <div class="d-flex align-items-center">
                            <i class="bi bi-bell-fill fs-4 me-3"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Notification</h6>
                                <p class="mb-0 small">
                                    You have been scheduled for an exam. Please
                                    see the details below.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0 fw-bold text-secondary">
                                Enrollment Application
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Prompt to fill application form if no program -->
                            <div v-if="!applicationData.applicant?.program && periodStatus === 'open'" class="alert alert-warning border-0 shadow-sm mb-4">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning"></i>
                                    <div>
                                        <h6 class="mb-1 fw-bold text-dark">Enrollment Application Form Pending</h6>
                                        <p class="mb-2 small text-secondary">
                                            Welcome to Carranglan National High School! You have successfully registered your account. The next step is to fill out and submit your enrollment application form.
                                        </p>
                                        <router-link to="/applicant/application/select-program" class="btn btn-warning btn-sm fw-semibold mt-1">
                                            <i class="bi bi-pencil-square me-1"></i>Start Application Form
                                        </router-link>
                                    </div>
                                </div>
                            </div>

                            <!-- Application period not yet open or already closed -->
                            <div v-else-if="!applicationData.applicant?.program"
                                class="border-0 shadow-sm mb-4 alert"
                                :class="periodStatus === 'not_open' ? 'alert-info' : 'alert-secondary'">
                                <div class="d-flex align-items-start">
                                    <i class="fs-4 me-3"
                                        :class="periodStatus === 'not_open' ? 'bi bi-clock-history text-info' : 'bi bi-calendar-x text-secondary'"></i>
                                    <div>
                                        <h6 class="mb-1 fw-bold text-dark">
                                            {{ periodStatus === 'not_open' ? 'Application Not Yet Open' : 'Application Period Closed' }}
                                        </h6>
                                        <p class="mb-0 small text-secondary">
                                            {{ periodMessage }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Rejected: correct and resubmit the application form -->
                            <div v-else-if="applicationData.applicant?.application_status === 'rejected'"
                                class="alert alert-danger border-0 shadow-sm mb-4">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-x-octagon-fill fs-4 me-3 text-danger"></i>
                                    <div>
                                        <h6 class="mb-1 fw-bold text-dark">Your application was rejected.</h6>
                                        <p class="mb-2 small text-secondary">
                                            Please review the rejection reason on the
                                            <router-link to="/applicant/application/status" class="link-danger fw-semibold">Application Status</router-link>
                                            page, correct the missing or incorrect information, and resubmit your application.
                                        </p>
                                        <router-link v-if="periodStatus === 'open'"
                                            to="/applicant/application/select-program"
                                            class="btn btn-danger btn-sm fw-semibold mt-1">
                                            <i class="bi bi-pencil-square me-1"></i>Correct &amp; Resubmit
                                        </router-link>
                                        <p v-else class="mb-0 small text-secondary">{{ periodMessage }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded border mb-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label
                                            class="text-muted small text-uppercase fw-bold"
                                        >Applied Program</label>
                                        <p class="mb-0 fw-medium">
                                            {{ applicationData.applicant?.program }}
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <label
                                            class="text-muted small text-uppercase fw-bold"
                                        >Application Status</label>
                                        <p class="mb-0 fw-medium">
                                            <span
                                                :class="{
                                                    'text-warning':
                                                        applicationData.applicant?.application_status === 'pending',
                                                    'text-success':
                                                        applicationData.applicant?.application_status === 'approved',
                                                    'text-danger':
                                                        applicationData.applicant?.application_status === 'rejected',
                                                }"
                                            >
                                                {{
                                                    applicationData.applicant?.application_status
                                                        ? applicationData.applicant.application_status.charAt(0).toUpperCase() +
                                                          applicationData.applicant.application_status.slice(1)
                                                        : "N/A"
                                                }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4 opacity-50" />

                            <!-- EXAM SCHEDULE SECTION -->
                            <h6 class="fw-bold mb-3 text-secondary">
                                Exam Details
                            </h6>

                            <div
                                v-if="!applicationData.schedule"
                                class="text-center py-4 bg-light rounded border border-dashed"
                            >
                                <i
                                    class="bi bi-calendar-x text-muted fs-1 mb-3 d-block"
                                ></i>
                                <h5 class="fw-bold text-muted">
                                    Waiting for exam schedule
                                </h5>
                                <p class="text-muted mb-0">
                                    Please wait for the admin to assign your
                                    exam details.
                                </p>
                            </div>

                            <div v-else>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="fw-bold small text-muted">Date</th>
                                                <th class="fw-bold small text-muted">Time Slot</th>
                                                <th class="fw-bold small text-muted">Room</th>
                                                <th class="fw-bold small text-muted">Program</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="fw-medium">{{ applicationData.schedule.date }}</td>
                                                <td class="fw-medium">{{ applicationData.schedule.time_slot }}</td>
                                                <td class="fw-medium text-primary">{{ applicationData.schedule.room }}</td>
                                                <td class="fw-medium">{{ applicationData.schedule.program }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Exam Result if available -->
                                <div
                                    v-if="applicationData.applicant?.exam_status"
                                    class="mt-4 p-3 rounded"
                                    :class="{
                                        'bg-info-subtle border-info text-info':
                                            applicationData.applicant.exam_status === 'Completed',
                                        'bg-success-subtle border-success text-success':
                                            applicationData.applicant.exam_status === 'Passed',
                                        'bg-danger-subtle border-danger text-danger':
                                            applicationData.applicant.exam_status === 'Failed',
                                    }"
                                >
                                    <h6 class="fw-bold mb-1">Exam Result Status:</h6>
                                    <p class="mb-0 fw-bold fs-5 text-uppercase">
                                        {{ applicationData.applicant.exam_status }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.border-dashed {
    border-style: dashed !important;
}
</style>
