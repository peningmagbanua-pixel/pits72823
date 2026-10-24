<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import {
    Chart,
    DoughnutController,
    ArcElement,
    Tooltip,
    Legend,
} from 'chart.js';

Chart.register(DoughnutController, ArcElement, Tooltip, Legend);

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
const loading = ref(true);
const doughnutChartRef    = ref(null);
let doughnutChartInstance = null;

// Limit recent to 5
const recentSliced = computed(() => (stats.value.recent || []).slice(0, 5));

// Program distribution percentages for inline bars
const programTotal   = computed(() => stats.value.spict_count + stats.value.ste_count);
const spictPercent   = computed(() => programTotal.value ? Math.round((stats.value.spict_count / programTotal.value) * 100) : 0);
const stePercent     = computed(() => programTotal.value ? Math.round((stats.value.ste_count   / programTotal.value) * 100) : 0);

const fetchStats = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/dashboard/stats');
        stats.value = response.data;
        loading.value = false;
        await nextTick();
        renderCharts();
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const renderCharts = () => {
    if (doughnutChartInstance) { doughnutChartInstance.destroy(); doughnutChartInstance = null; }

    const totalProgram = stats.value.spict_count + stats.value.ste_count;
    if (totalProgram > 0 && doughnutChartRef.value) {
        doughnutChartInstance = new Chart(doughnutChartRef.value, {
            type: 'doughnut',
            data: {
                labels: ['SPICT', 'STE'],
                datasets: [{
                    data: [stats.value.spict_count, stats.value.ste_count],
                    backgroundColor: ['#800000', '#2563eb'],
                    hoverBackgroundColor: ['#600000', '#1d4ed8'],
                    borderWidth: 3,
                    borderColor: '#fff',
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, padding: 16, font: { size: 12 } },
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ` ${ctx.label}: ${ctx.parsed} applicant${ctx.parsed !== 1 ? 's' : ''}`,
                        },
                    },
                },
            },
        });
    }
};

onMounted(() => fetchStats());
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
                        <li class="breadcrumb-item fw-medium">
                            <a href="/admin/dashboard">Home</a>
                        </li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <!-- ── 1. Summary Cards ─────────────────────────────────── -->
            <div class="row g-3 mb-3">

                <div class="col-6 col-md-3">
                    <div class="card dc-card dc-stat-card">
                        <div class="card-body dc-stat">
                            <div class="dc-stat__icon dc-stat__icon--blue">
                                <i class="bi bi-people-fill"></i>
                            </div>
                            <div class="dc-stat__num">{{ stats.total }}</div>
                            <div class="dc-stat__label">Total Applicants</div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card dc-card dc-stat-card">
                        <div class="card-body dc-stat">
                            <div class="dc-stat__icon dc-stat__icon--amber">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div class="dc-stat__num">{{ stats.pending }}</div>
                            <div class="dc-stat__label">Pending</div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card dc-card dc-stat-card">
                        <div class="card-body dc-stat">
                            <div class="dc-stat__icon dc-stat__icon--green">
                                <i class="bi bi-patch-check-fill"></i>
                            </div>
                            <div class="dc-stat__num">{{ stats.approved }}</div>
                            <div class="dc-stat__label">Approved</div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card dc-card dc-stat-card">
                        <div class="card-body dc-stat">
                            <div class="dc-stat__icon dc-stat__icon--red">
                                <i class="bi bi-x-circle-fill"></i>
                            </div>
                            <div class="dc-stat__num">{{ stats.rejected }}</div>
                            <div class="dc-stat__label">Rejected</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── 2. Recent Applications + Program Distribution ───── -->
            <div class="row g-3 mb-3">

                <!-- Recent Applications -->
                <div class="col-lg-8">
                    <div class="card dc-card h-100 d-flex flex-column">
                        <div class="card-header dc-card__header">
                            <span class="dc-card__title">
                                <i class="bi bi-ui-checks me-2" style="color:#800000;"></i>Recent Applications
                            </span>
                            <button class="dc-btn-refresh" @click="fetchStats" title="Refresh stats">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                        </div>
                        <div class="card-body p-0 flex-grow-1">
                            <div class="table-responsive">
                                <table class="table dc-table mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width:36px;">#</th>
                                            <th>Name</th>
                                            <th class="text-center">Program</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Date Applied</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="loading">
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status"></div>
                                                <p class="mt-3 mb-0 text-muted">Loading ...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else-if="recentSliced.length > 0">
                                        <tr v-for="(app, index) in recentSliced" :key="app.id">
                                            <td class="text-center text-muted">{{ index + 1 }}</td>
                                            <td>{{ app.name }}</td>
                                            <td class="text-center">
                                                <span v-if="app.program" class="dc-badge"
                                                    :class="app.program === 'STE' ? 'dc-badge--ste' : 'dc-badge--spict'">
                                                    {{ app.program }}
                                                </span>
                                                <span v-else class="text-muted">—</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="dc-badge" :class="{
                                                    'dc-badge--approved': app.application_status === 'approved',
                                                    'dc-badge--rejected': app.application_status === 'rejected',
                                                    'dc-badge--pending':  app.application_status === 'pending',
                                                }">{{ app.application_status }}</span>
                                            </td>
                                            <td class="text-center text-muted dc-date">{{ app.created_at }}</td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="bi bi-inbox me-1"></i>No applications found.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- View All footer -->
                        <div v-if="stats.recent && stats.recent.length > 5" class="dc-card__footer">
                            <a href="/admin/applications" class="dc-viewall">
                                View all applications <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Program Distribution -->
                <div class="col-lg-4">
                    <div class="card dc-card h-100 d-flex flex-column">
                        <div class="card-header dc-card__header">
                            <span class="dc-card__title">
                                <i class="bi bi-pie-chart-fill me-2" style="color:#800000;"></i>Program Distribution
                            </span>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-center py-3 px-3">

                            <!-- Doughnut -->
                            <div class="dc-doughnut-wrap mx-auto mb-3">
                                <canvas ref="doughnutChartRef" style="max-height:170px;max-width:170px;"></canvas>
                                <div class="dc-doughnut-center">
                                    <div class="dc-doughnut-center__num">{{ programTotal }}</div>
                                    <div class="dc-doughnut-center__lbl">Total</div>
                                </div>
                            </div>

                            <!-- SPICT row -->
                            <div class="dc-prog-row mb-2">
                                <div class="dc-prog-row__header">
                                    <span class="dc-badge dc-badge--spict">SPICT</span>
                                    <span class="dc-prog-row__count">
                                        {{ stats.spict_count }}
                                        <span class="dc-prog-row__pct">({{ spictPercent }}%)</span>
                                    </span>
                                </div>
                                <div class="dc-prog-bar mt-1">
                                    <div class="dc-prog-bar__fill dc-prog-bar__fill--spict"
                                        :style="{ width: spictPercent + '%' }"></div>
                                </div>
                            </div>

                            <!-- STE row -->
                            <div class="dc-prog-row">
                                <div class="dc-prog-row__header">
                                    <span class="dc-badge dc-badge--ste">STE</span>
                                    <span class="dc-prog-row__count">
                                        {{ stats.ste_count }}
                                        <span class="dc-prog-row__pct">({{ stePercent }}%)</span>
                                    </span>
                                </div>
                                <div class="dc-prog-bar mt-1">
                                    <div class="dc-prog-bar__fill dc-prog-bar__fill--ste"
                                        :style="{ width: stePercent + '%' }"></div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <!-- ── 3. Upcoming Exam Schedules ──────────────────────── -->
            <div class="row g-3">
                <div class="col-12">
                    <div class="card dc-card">
                        <div class="card-header dc-card__header">
                            <span class="dc-card__title">
                                <i class="bi bi-calendar-check me-2" style="color:#800000;"></i>Upcoming Exam Schedules
                            </span>
                        </div>
                        <div class="card-body py-2 px-3">

                            <div v-if="loading" class="text-center py-3">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-3 mb-0 text-muted">Loading ...</p>
                            </div>

                            <div v-else-if="stats.upcoming_exams && stats.upcoming_exams.length > 0">
                                <div v-for="(exam, idx) in stats.upcoming_exams" :key="exam.id"
                                    class="dc-exam-row"
                                    :class="{ 'dc-exam-row--last': idx === stats.upcoming_exams.length - 1 }">

                                    <!-- Program badge -->
                                    <span class="dc-badge flex-shrink-0"
                                        :class="exam.program === 'STE' ? 'dc-badge--ste' : 'dc-badge--spict'"
                                        style="min-width:52px;text-align:center;">
                                        {{ exam.program }}
                                    </span>

                                    <!-- Meta info -->
                                    <div class="dc-exam-row__meta">
                                        <span><i class="bi bi-calendar3 me-1 text-muted"></i>{{ exam.date }}</span>
                                        <span><i class="bi bi-clock me-1 text-muted"></i>{{ exam.time_slot }}</span>
                                        <span><i class="bi bi-door-open me-1 text-muted"></i>{{ exam.room }}</span>
                                    </div>

                                    <!-- Capacity -->
                                    <div class="dc-exam-row__cap flex-shrink-0">
                                        <div class="dc-cap-bar">
                                            <div class="dc-cap-bar__fill"
                                                :class="(exam.applicants_count / exam.capacity) >= 1 ? 'dc-cap-bar__fill--full' : ''"
                                                :style="{ width: Math.min((exam.applicants_count / exam.capacity) * 100, 100) + '%' }">
                                            </div>
                                        </div>
                                        <small class="text-muted">{{ exam.applicants_count }}/{{ exam.capacity }}</small>
                                    </div>

                                </div>
                            </div>

                            <div v-else class="text-center py-3 text-muted" style="font-size:13px;">
                                <i class="bi bi-calendar-x me-1"></i>No upcoming exam schedules.
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
/* ── Base card ──────────────────────────────────────────────────── */
.dc-card {
    border: 1px solid #ebebeb;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
    background: #fff;
}
.dc-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 11px 16px;
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    border-radius: 10px 10px 0 0;
    flex-shrink: 0;
}
.dc-card__title {
    font-size: 13px;
    font-weight: 700;
    color: #333;
    display: flex;
    align-items: center;
}
.dc-card__footer {
    padding: 9px 16px;
    border-top: 1px solid #f0f0f0;
    text-align: center;
    flex-shrink: 0;
}
.dc-viewall {
    font-size: 12px;
    color: #800000;
    text-decoration: none;
    font-weight: 600;
}
.dc-viewall:hover { text-decoration: underline; }

/* ── Stat cards — fixed equal height ───────────────────────────── */
.dc-stat-card { min-height: 110px; }
.dc-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 16px 10px;
    gap: 4px;
}
.dc-stat__icon {
    width: 40px;
    height: 40px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    margin-bottom: 2px;
    flex-shrink: 0;
}
.dc-stat__icon--blue  { background: #eff6ff; color: #2563eb; }
.dc-stat__icon--amber { background: #fffbeb; color: #d97706; }
.dc-stat__icon--green { background: #f0fdf4; color: #16a34a; }
.dc-stat__icon--red   { background: #fef2f2; color: #dc2626; }
.dc-stat__num   { font-size: 1.75rem; font-weight: 800; line-height: 1; color: #111; }
.dc-stat__label { font-size: 11px; color: #777; font-weight: 500; }

/* ── Refresh button ─────────────────────────────────────────────── */
.dc-btn-refresh {
    font-size: 12px;
    padding: 3px 10px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    color: #555;
    cursor: pointer;
    transition: border-color .15s, color .15s;
}
.dc-btn-refresh:hover { border-color: #800000; color: #800000; }

/* ── Table ──────────────────────────────────────────────────────── */
.dc-table { font-size: 13px; }
.dc-table thead th {
    background: #fafafa;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #666;
    padding: 9px 14px;
    border-bottom: 1px solid #f0f0f0;
    border-top: none;
}
.dc-table tbody td {
    padding: 9px 14px;
    vertical-align: middle;
    border-color: #f5f5f5;
}
.dc-table tbody tr:last-child td { border-bottom: none; }
.dc-table tbody tr:hover td      { background: #fafafa; }
.dc-date { font-size: 12px; white-space: nowrap; }

/* ── Badges ─────────────────────────────────────────────────────── */
.dc-badge {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: capitalize;
}
.dc-badge--spict    { background: #fce8e8; color: #800000; }
.dc-badge--ste      { background: #eff6ff; color: #2563eb; }
.dc-badge--approved { background: #f0fdf4; color: #15803d; }
.dc-badge--pending  { background: #fffbeb; color: #b45309; }
.dc-badge--rejected { background: #fef2f2; color: #b91c1c; }

/* ── Doughnut ───────────────────────────────────────────────────── */
.dc-doughnut-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.dc-doughnut-center {
    position: absolute;
    text-align: center;
    pointer-events: none;
}
.dc-doughnut-center__num { font-size: 1.35rem; font-weight: 800; color: #222; line-height: 1.1; }
.dc-doughnut-center__lbl { font-size: 10px; color: #999; text-transform: uppercase; letter-spacing: .06em; }

/* ── Program breakdown rows ─────────────────────────────────────── */
.dc-prog-row__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.dc-prog-row__count { font-size: 13px; font-weight: 700; color: #333; }
.dc-prog-row__pct   { font-size: 11px; font-weight: 400; color: #999; }
.dc-prog-bar {
    height: 7px;
    border-radius: 4px;
    background: #f0f0f0;
    overflow: hidden;
}
.dc-prog-bar__fill {
    height: 100%;
    border-radius: 4px;
    transition: width .4s ease;
}
.dc-prog-bar__fill--spict { background: #800000; }
.dc-prog-bar__fill--ste   { background: #2563eb; }

/* ── Exam rows ──────────────────────────────────────────────────── */
.dc-exam-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 9px 0;
    border-bottom: 1px solid #f5f5f5;
    font-size: 13px;
}
.dc-exam-row--last   { border-bottom: none; }
.dc-exam-row__meta {
    flex: 1;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    color: #555;
    font-size: 12px;
}
.dc-exam-row__cap {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 3px;
    min-width: 80px;
}

/* ── Capacity bar ───────────────────────────────────────────────── */
.dc-cap-bar {
    width: 80px;
    height: 5px;
    border-radius: 3px;
    background: #e5e5e5;
    overflow: hidden;
}
.dc-cap-bar__fill {
    height: 100%;
    border-radius: 3px;
    background: #800000;
    transition: width .4s ease;
}
.dc-cap-bar__fill--full { background: #dc2626; }

/* ── Dark mode ──────────────────────────────────────────────────── */
[data-bs-theme="dark"] .dc-card            { background: #1c1c1c; border-color: #2a2a2a; box-shadow: none; }
[data-bs-theme="dark"] .dc-card__header    { background: #1c1c1c; border-bottom-color: #2a2a2a; }
[data-bs-theme="dark"] .dc-card__footer    { border-top-color: #2a2a2a; }
[data-bs-theme="dark"] .dc-card__title     { color: #ddd; }
[data-bs-theme="dark"] .dc-viewall         { color: #f5a5a5; }
[data-bs-theme="dark"] .dc-stat__num       { color: #eee; }
[data-bs-theme="dark"] .dc-stat__label     { color: #888; }
[data-bs-theme="dark"] .dc-stat__icon--blue  { background: #172554; color: #93c5fd; }
[data-bs-theme="dark"] .dc-stat__icon--amber { background: #2d1a00; color: #fcd34d; }
[data-bs-theme="dark"] .dc-stat__icon--green { background: #052e16; color: #6ee7b7; }
[data-bs-theme="dark"] .dc-stat__icon--red   { background: #2d0000; color: #fca5a5; }
[data-bs-theme="dark"] .dc-table thead th    { background: #222; color: #aaa; border-bottom-color: #333; }
[data-bs-theme="dark"] .dc-table tbody td    { border-color: #2a2a2a; }
[data-bs-theme="dark"] .dc-table tbody tr:hover td { background: #242424; }
[data-bs-theme="dark"] .dc-badge--spict    { background: #3d0000; color: #f5a5a5; }
[data-bs-theme="dark"] .dc-badge--ste      { background: #172554; color: #93c5fd; }
[data-bs-theme="dark"] .dc-badge--approved { background: #052e16; color: #6ee7b7; }
[data-bs-theme="dark"] .dc-badge--pending  { background: #2d1a00; color: #fcd34d; }
[data-bs-theme="dark"] .dc-badge--rejected { background: #2d0000; color: #fca5a5; }
[data-bs-theme="dark"] .dc-exam-row        { border-bottom-color: #2a2a2a; }
[data-bs-theme="dark"] .dc-exam-row__meta  { color: #999; }
[data-bs-theme="dark"] .dc-cap-bar         { background: #333; }
[data-bs-theme="dark"] .dc-prog-bar        { background: #2a2a2a; }
[data-bs-theme="dark"] .dc-btn-refresh     { background: #1c1c1c; border-color: #333; color: #aaa; }
[data-bs-theme="dark"] .dc-btn-refresh:hover { border-color: #f5a5a5; color: #f5a5a5; }
[data-bs-theme="dark"] .dc-doughnut-center__num { color: #eee; }
[data-bs-theme="dark"] .dc-prog-row__count { color: #ddd; }

/* ── Mobile (≤767.98px): compact cards, readable table ───────────── */
@media (max-width: 767.98px) {
    .dc-card__header { padding: 10px 12px; }

    /* Stat cards: slightly smaller so 2 fit comfortably per row */
    .dc-stat-card { min-height: 92px; }
    .dc-stat { padding: 12px 6px; gap: 2px; }
    .dc-stat__icon { width: 32px; height: 32px; font-size: 1rem; border-radius: 8px; }
    .dc-stat__num   { font-size: 1.4rem; }
    .dc-stat__label { font-size: 10px; }

    /* Keep the table flush inside the card (overrides the global
       mobile .card-body padding rule) */
    .dc-card .card-body.p-0 { padding: 0 !important; }

    /* Table: scroll horizontally instead of crushing the columns */
    .dc-table { min-width: 540px; }
    .dc-table thead th,
    .dc-table tbody td { padding: 8px 10px; }

    /* Exam rows: wrap the meta info onto its own line on narrow screens */
    .dc-exam-row { flex-wrap: wrap; gap: 8px; }
    .dc-exam-row__meta { flex: 0 0 100%; order: 3; gap: 8px; }
    .dc-exam-row__cap { min-width: auto; margin-left: auto; }
    .dc-cap-bar { width: 64px; }
}
</style>
