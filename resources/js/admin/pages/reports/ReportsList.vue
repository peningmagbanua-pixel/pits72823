<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import {
    Chart,
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';
import Swal from 'sweetalert2';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const reportData = ref(null);
const programFilter = ref('');
const statusFilter = ref('');

const chartRefs = {
    monthly: ref(null),
};
let chartInstances = { monthly: null };

const genderTotal = computed(() => (reportData.value?.sex.male ?? 0) + (reportData.value?.sex.female ?? 0));
const malePercent = computed(() => (genderTotal.value ? Math.round((reportData.value.sex.male / genderTotal.value) * 100) : 0));
const femalePercent = computed(() => (genderTotal.value ? Math.round((reportData.value.sex.female / genderTotal.value) * 100) : 0));

const examTotal = computed(() => (reportData.value?.exam.scheduled ?? 0) + (reportData.value?.exam.unscheduled ?? 0));
const scheduledPercent = computed(() => (examTotal.value ? Math.round((reportData.value.exam.scheduled / examTotal.value) * 100) : 0));
const unscheduledPercent = computed(() => (examTotal.value ? Math.round((reportData.value.exam.unscheduled / examTotal.value) * 100) : 0));

const fetchReports = async () => {
    try {
        const params = {};
        if (programFilter.value) params.program = programFilter.value;
        if (statusFilter.value) params.status = statusFilter.value;

        const response = await axios.get('/api/reports', { params });
        reportData.value = response.data;
        await nextTick();
        renderCharts();
    } catch (error) {
        Swal.fire('Error', error.response?.data?.message || 'Failed to load reports', 'error');
    }
};

const renderCharts = () => {
    Object.keys(chartInstances).forEach((key) => {
        if (chartInstances[key]) {
            chartInstances[key].destroy();
            chartInstances[key] = null;
        }
    });

    if (!reportData.value) return;

    if (reportData.value.monthly?.length && chartRefs.monthly.value) {
        chartInstances.monthly = new Chart(chartRefs.monthly.value, {
            type: 'bar',
            data: {
                labels: reportData.value.monthly.map((m) => m.month),
                datasets: [{
                    label: 'Applications',
                    data: reportData.value.monthly.map((m) => m.count),
                    backgroundColor: '#0d6efd',
                    borderRadius: 4,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                },
            },
        });
    }
};

onMounted(() => {
    fetchReports();
});
</script>
<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Reports</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium"><a href="/admin/dashboard">Dashboard</a></li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Reports</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-2 mb-3">
                <label class="form-label small mb-0">Program:</label>
                <select v-model="programFilter" class="form-select form-select-sm" style="width: auto;">
                    <option value="">All Programs</option>
                    <option value="SPICT">SPICT</option>
                    <option value="STE">STE</option>
                </select>
                <label class="form-label small mb-0 ms-2">Status:</label>
                <select v-model="statusFilter" class="form-select form-select-sm" style="width: auto;">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <button class="btn btn-primary btn-sm ms-2" @click="fetchReports">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
            </div>

            <div v-if="reportData">
                <div class="row mb-4">
                    <div class="col-lg-12" style="font-size: 13px;">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-0">
                                <table class="table table-bordered table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-center align-middle" style="width: 25%;">
                                                <div class="text-muted small mb-1">Total Applicants</div>
                                                <div class="fs-3 fw-bold">{{ reportData.summary.total }}</div>
                                            </td>
                                            <td class="text-center align-middle" style="width: 25%;">
                                                <div class="text-muted small mb-1">Approval Rate</div>
                                                <div class="fs-3 fw-bold">{{ reportData.rates.approval_rate }}%</div>
                                            </td>
                                            <td class="text-center align-middle" style="width: 25%;">
                                                <div class="text-muted small mb-1">Rejection Rate</div>
                                                <div class="fs-3 fw-bold">{{ reportData.rates.rejection_rate }}%</div>
                                            </td>
                                            <td class="text-center align-middle" style="width: 25%;">
                                                <div class="text-muted small mb-1">Approved</div>
                                                <div class="fs-3 fw-bold">{{ reportData.summary.approved }}</div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12" style="font-size: 13px;">
                        <div class="card shadow-sm border-0">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="mb-3 fw-bold"><i class="bi bi-bar-chart-fill me-1"></i>Monthly Applications</h6>
                                        <canvas ref="chartRefs.monthly"></canvas>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-3 fw-bold"><i class="bi bi-journal-bookmark-fill me-1"></i>Program Breakdown</h6>
                                        <div class="table-responsive-sm">
                                            <table class="table table-bordered table-hover table-sm mb-0">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center align-middle">Program</th>
                                                        <th class="text-center align-middle">Total</th>
                                                        <th class="text-center align-middle">Approved</th>
                                                        <th class="text-center align-middle">Pending</th>
                                                        <th class="text-center align-middle">Rejected</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(prog, name) in reportData.programs" :key="name">
                                                        <td class="align-middle fw-medium">{{ name }}</td>
                                                        <td class="text-center align-middle">{{ prog.total }}</td>
                                                        <td class="text-center align-middle">
                                                            <span class="badge bg-success-subtle text-success">{{ prog.approved }}</span>
                                                        </td>
                                                        <td class="text-center align-middle">
                                                            <span class="badge bg-warning-subtle text-warning">{{ prog.pending }}</span>
                                                        </td>
                                                        <td class="text-center align-middle">
                                                            <span class="badge bg-danger-subtle text-danger">{{ prog.rejected }}</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="mb-3 fw-bold"><i class="bi bi-gender-ambiguous me-1"></i>Gender Distribution</h6>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>Male</span>
                                                <span>{{ reportData.sex.male }}</span>
                                            </div>
                                            <div class="progress" style="height: 14px;">
                                                <div class="progress-bar bg-primary" role="progressbar"
                                                    :style="{ width: malePercent + '%' }"></div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>Female</span>
                                                <span>{{ reportData.sex.female }}</span>
                                            </div>
                                            <div class="progress" style="height: 14px;">
                                                <div class="progress-bar bg-danger" role="progressbar"
                                                    :style="{ width: femalePercent + '%' }"></div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 align-items-center flex-wrap">
                                            <span class="badge bg-primary-subtle text-primary">Male: {{ reportData.sex.male }}</span>
                                            <span class="badge bg-danger-subtle text-danger">Female: {{ reportData.sex.female }}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-3 fw-bold"><i class="bi bi-calendar-check me-1"></i>Exam Status</h6>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>Scheduled</span>
                                                <span>{{ reportData.exam.scheduled }}</span>
                                            </div>
                                            <div class="progress" style="height: 14px;">
                                                <div class="progress-bar bg-success" role="progressbar"
                                                    :style="{ width: scheduledPercent + '%' }"></div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between small mb-1">
                                                <span>Unscheduled</span>
                                                <span>{{ reportData.exam.unscheduled }}</span>
                                            </div>
                                            <div class="progress" style="height: 14px;">
                                                <div class="progress-bar bg-secondary" role="progressbar"
                                                    :style="{ width: unscheduledPercent + '%' }"></div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2 align-items-center flex-wrap">
                                            <span class="badge bg-success-subtle text-success">Scheduled: {{ reportData.exam.scheduled }}</span>
                                            <span class="badge bg-secondary-subtle text-secondary">Unscheduled: {{ reportData.exam.unscheduled }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
