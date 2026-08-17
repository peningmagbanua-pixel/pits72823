<script setup>
import { ref, onMounted, nextTick } from 'vue';
import {
    Chart,
    BarController,
    BarElement,
    DoughnutController,
    ArcElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

Chart.register(BarController, BarElement, DoughnutController, ArcElement, CategoryScale, LinearScale, Tooltip, Legend);

const stats = ref({
    total: 0,
    pending: 0,
    approved: 0,
    rejected: 0,
    recent: [],
    today_count: 0,
    week_count: 0,
    month_count: 0,
    monthly: [],
    spict_count: 0,
    ste_count: 0,
    upcoming_exams: [],
});
const barChartRef = ref(null);
const doughnutChartRef = ref(null);
let barChartInstance = null;
let doughnutChartInstance = null;

const fetchStats = async () => {
    try {
        const response = await axios.get('/api/dashboard/stats');
        stats.value = response.data;
        await nextTick();
        renderCharts();
    } catch (error) {
        console.error(error);
    }
};

const renderCharts = () => {
    if (barChartInstance) barChartInstance.destroy();
    if (doughnutChartInstance) doughnutChartInstance.destroy();

    if (stats.value.monthly?.length && barChartRef.value) {
        barChartInstance = new Chart(barChartRef.value, {
            type: 'bar',
            data: {
                labels: stats.value.monthly.map((m) => m.month),
                datasets: [{
                    label: 'Applications',
                    data: stats.value.monthly.map((m) => m.count),
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

    const totalProgram = stats.value.spict_count + stats.value.ste_count;
    if (totalProgram > 0 && doughnutChartRef.value) {
        doughnutChartInstance = new Chart(doughnutChartRef.value, {
            type: 'doughnut',
            data: {
                labels: ['SPICT', 'STE'],
                datasets: [{
                    data: [stats.value.spict_count, stats.value.ste_count],
                    backgroundColor: ['#0d6efd', '#20c997'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true },
                    },
                },
            },
        });
    }
};

onMounted(() => {
    fetchStats();
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
                        <li class="breadcrumb-item fw-medium"><a href="/admin/dashboard">Home</a></li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-lg-12" style="font-size: 13px;">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0">
                            <table class="table table-bordered table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-center align-middle" style="width: 25%;">
                                            <div class="text-muted small mb-1">Total Applicants</div>
                                            <div class="fs-3 fw-bold">{{ stats.total }}</div>
                                        </td>
                                        <td class="text-center align-middle" style="width: 25%;">
                                            <div class="text-muted small mb-1">Pending</div>
                                            <div class="fs-3 fw-bold">{{ stats.pending }}</div>
                                        </td>
                                        <td class="text-center align-middle" style="width: 25%;">
                                            <div class="text-muted small mb-1">Approved</div>
                                            <div class="fs-3 fw-bold">{{ stats.approved }}</div>
                                        </td>
                                        <td class="text-center align-middle" style="width: 25%;">
                                            <div class="text-muted small mb-1">Rejected</div>
                                            <div class="fs-3 fw-bold">{{ stats.rejected }}</div>
                                            <div class="text-muted small mt-1">
                                                <span class="me-2">Today: {{ stats.today_count }}</span>
                                                <span class="me-2">Week: {{ stats.week_count }}</span>
                                                <span>Month: {{ stats.month_count }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-7">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6 class="mb-3 fw-bold"><i class="bi bi-bar-chart-fill me-1"></i>Monthly Applications</h6>
                            <canvas ref="barChartRef"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h6 class="mb-3 fw-bold"><i class="bi bi-pie-chart-fill me-1"></i>Program Distribution</h6>
                            <div class="d-flex justify-content-center">
                                <canvas ref="doughnutChartRef" style="max-height: 220px; max-width: 220px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-7">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-ui-checks me-1"></i>Recent Applications</h6>
                                <button class="btn btn-primary btn-sm" @click="fetchStats">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                            </div>
                            <div class="table-responsive-sm" style="font-size: 13px;">
                                <table class="table table-bordered table-hover table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center align-middle">#</th>
                                            <th class="text-center align-middle">Name</th>
                                            <th class="text-center align-middle">Program</th>
                                            <th class="text-center align-middle">Status</th>
                                            <th class="text-center align-middle">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="stats.recent && stats.recent.length > 0">
                                        <tr v-for="(app, index) in stats.recent" :key="app.id">
                                            <td class="text-center align-middle">{{ index + 1 }}</td>
                                            <td class="align-middle">{{ app.name }}</td>
                                            <td class="text-center align-middle">
                                                <span v-if="app.program" class="badge"
                                                    :class="app.program === 'STE' ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info'">
                                                    {{ app.program }}
                                                </span>
                                                <span v-else class="badge bg-secondary-subtle text-secondary">&mdash;</span>
                                            </td>
                                            <td class="text-center align-middle">
                                                <span class="badge text-capitalize"
                                                    :class="{
                                                        'bg-success-subtle text-success': app.application_status === 'approved',
                                                        'bg-danger-subtle text-danger': app.application_status === 'rejected',
                                                        'bg-warning-subtle text-warning': app.application_status === 'pending',
                                                    }">
                                                    {{ app.application_status }}
                                                </span>
                                            </td>
                                            <td class="text-center align-middle">{{ app.created_at }}</td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="5" class="text-center">No applications found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h6 class="mb-3 fw-bold"><i class="bi bi-calendar-check me-1"></i>Upcoming Exam Schedules</h6>
                            <div v-if="stats.upcoming_exams && stats.upcoming_exams.length > 0">
                                <div v-for="exam in stats.upcoming_exams" :key="exam.id"
                                    class="d-flex justify-content-between align-items-center p-2 mb-1 rounded border">
                                    <div>
                                        <div class="fw-medium">{{ exam.program }}</div>
                                        <small class="text-muted">{{ exam.date }} &middot; {{ exam.time_slot }} &middot; {{ exam.room }}</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-primary-subtle text-primary">{{ exam.applicants_count }}/{{ exam.capacity }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-muted text-center py-3">
                                No upcoming exam schedules.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
