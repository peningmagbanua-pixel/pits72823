<script setup>
import { ref, onMounted } from 'vue';
import { escapeHtml, resolveAssetUrl, waitForImages, formatProgramName } from '../../../constants';
import Swal from 'sweetalert2';
import useSchoolYearApi from '../../composables/api/useSchoolYearApi';

// ── State ──────────────────────────────────────────────────────────────────
const schoolYears    = ref([]);
const selectedYear   = ref('');
const loadingYears   = ref(false);
const generated      = ref(false);

const activeReport   = ref('');   // 'approved' | 'exam' | ''
const reportRows     = ref([]);
const loadingReport  = ref(false);
const printing       = ref(false);

// ── Load school years ──────────────────────────────────────────────────────
const fetchSchoolYears = async () => {
    loadingYears.value = true;
    try {
        const res = await useSchoolYearApi.getSchoolYears({
            paginate: 100,
            sort_field: 'name',
            sort_order: 'asc',
        });
        schoolYears.value = res.data.data ?? [];
    } catch (e) {
        console.error(e);
    } finally {
        loadingYears.value = false;
    }
};

const selectedYearName = () => {
    const sy = schoolYears.value.find(sy => sy.id === selectedYear.value);
    if (!sy) return 'All School Years';
    return sy.school_year_start && sy.school_year_end
        ? `${sy.school_year_start}-${sy.school_year_end}`
        : (sy.school_year_name || 'All School Years');
};

// ── Generate ───────────────────────────────────────────────────────────────
const generate = () => {
    generated.value   = true;
    activeReport.value = '';
    reportRows.value  = [];
};

// ── Load approved applicants ───────────────────────────────────────────────
const loadApproved = async () => {
    activeReport.value  = 'approved';
    loadingReport.value = true;
    reportRows.value    = [];
    try {
        const res = await axios.get('/api/reports/print-approved', {
            params: { school_year_id: selectedYear.value || null },
        });
        reportRows.value = res.data.applicants ?? [];
    } catch (e) {
        Swal.fire('Error', e.response?.data?.message || 'Failed to load data.', 'error');
    } finally {
        loadingReport.value = false;
    }
};

// ── Load exam schedule ─────────────────────────────────────────────────────
const loadExamSchedule = async () => {
    activeReport.value  = 'exam';
    loadingReport.value = true;
    reportRows.value    = [];
    try {
        const res = await axios.get('/api/reports/print-exam-schedule', {
            params: { school_year_id: selectedYear.value || null },
        });
        // Flatten schedule → applicant rows
        const rows = [];
        (res.data.schedules ?? []).forEach(sched => {
            (sched.applicants ?? []).forEach(a => {
                rows.push({
                    lastname:   a.lastname,
                    firstname:  a.firstname,
                    middlename: a.middlename,
                    extname:    a.extname,
                    program:    a.program,
                    date:       sched.date,
                    time_slot:  sched.time_slot,
                    room:       sched.room,
                    date_added: a.date_added,
                });
            });
        });
        reportRows.value = rows;
    } catch (e) {
        Swal.fire('Error', e.response?.data?.message || 'Failed to load data.', 'error');
    } finally {
        loadingReport.value = false;
    }
};

// ── Helpers ────────────────────────────────────────────────────────────────
const formatName = (a) => {
    const mid = a.middlename ? ` ${a.middlename.charAt(0)}.` : '';
    const ext = a.extname    ? ` ${a.extname}` : '';
    return `${a.lastname}, ${a.firstname}${mid}${ext}`;
};

// ── Print ──────────────────────────────────────────────────────────────────
const groupByProgram = (rows) => {
    const groups = new Map();
    rows.forEach(r => {
        const key = String(r.program ?? '').trim() || 'Other';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(r);
    });

    const preferred = ['SPICT', 'STE'];
    const rank = (key) => {
        const i = preferred.indexOf(key.toUpperCase());
        return i === -1 ? preferred.length : i;
    };

    return [...groups.entries()]
        .sort(([a], [b]) => rank(a) - rank(b) || a.localeCompare(b));
};

const openPrintWindow = async () => {
    const monthYear = new Date().toLocaleString('en-PH', { month: 'long', year: 'numeric' });
    const title     = activeReport.value === 'approved'
        ? 'Approved Applicants Report'
        : 'Exam Schedule Report';

    const baseStyle = `
        @page {
            margin:15mm;
            @top-left { content:""; }
            @top-center { content:""; }
            @top-right { content:""; }
            @bottom-left { content:""; }
            @bottom-center { content:""; }
            @bottom-right { content:""; }
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:Arial,sans-serif; font-size:12px; padding:0; color:#111; }
        .header { display:flex; align-items:center; justify-content:center; gap:10px; text-align:center; margin-bottom:14px; }
        .logo { width:80px; height:80px; flex-shrink:0; }
        .logo2 { height:64px; width:auto; flex-shrink:0; }
        .header-text { }
        .school { font-size:15px; font-weight:bold; }
        .address { font-size:11px; color:#555; margin-top:2px; }
        .report-title { font-size:14px; font-weight:bold; margin-top:8px; text-transform:uppercase; letter-spacing:.5px; }
        .meta { font-size:11px; color:#666; margin-top:3px; }
        hr { border:none; border-top:2px solid #333; margin:8px 0 14px; }
        table { width:100%; border-collapse:collapse; }
        th { background:#333; color:#fff; padding:7px 10px; font-size:11px; font-weight:600; border:1px solid #333; }
        td { border:1px solid #ccc; padding:6px 10px; vertical-align:middle; }
        tr:nth-child(even) td { background:#f7f7f7; }
        h3 { background:#2c3e50; color:#fff; padding:8px 12px; margin:20px 0 10px; font-size:14px; }
        .page-break { page-break-after: always; }
        .tc { text-align:center; }
        .footer { margin-top:18px; font-size:11px; color:#666; text-align:right; }`;

    const headerHtml = `
        <div class="header">
            <img class="logo" src="${resolveAssetUrl('cnhs_logo.png')}" alt="CNHS Logo">
            <div class="header-text">
                <div class="school">Carranglan National High School</div>
                <div class="address">Quezon Street, F.C. Otic Poblacion (Brgy. II), Carranglan, Nueva Ecija</div>
                <div class="report-title">${escapeHtml(title)}</div>
                <div class="meta">${escapeHtml(monthYear)}</div>
            </div>
            <img class="logo2" src="${resolveAssetUrl('deped.webp')}" alt="DepEd Logo">
        </div>
        <hr>`;

    const isApproved = activeReport.value === 'approved';
    const thead = isApproved
        ? `
            <th class="tc" style="width:45px;">#</th>
            <th class="tc" style="width:90px;">Date Added</th>
            <th>Applicant Name</th>
            <th class="tc" style="width:45%;">Gmail Account</th>`
        : `
            <th class="tc" style="width:45px;">#</th>
            <th class="tc" style="width:90px;">Date Added</th>
            <th>Applicant Name</th>
            <th class="tc" style="width:90px;">Exam Date</th>
            <th class="tc" style="width:90px;">Time</th>
            <th class="tc" style="width:90px;">Room</th>`;

    const colspan = isApproved ? 4 : 6;

    const buildRow = isApproved
        ? (a, i) => `<tr>
                <td class="tc">${i + 1}</td>
                <td class="tc">${escapeHtml(a.date_added ?? '')}</td>
                <td>${escapeHtml(formatName(a))}</td>
                <td class="tc">${escapeHtml(a.email ?? '')}</td>
            </tr>`
        : (a, i) => `<tr>
                <td class="tc">${i + 1}</td>
                <td class="tc">${escapeHtml(a.date_added ?? '')}</td>
                <td>${escapeHtml(formatName(a))}</td>
                <td class="tc">${escapeHtml(a.date ?? '')}</td>
                <td class="tc">${escapeHtml(a.time_slot ?? '')}</td>
                <td class="tc">${escapeHtml(a.room ?? '')}</td>
            </tr>`;

    const groups = groupByProgram(reportRows.value);

    const sections = groups.length
        ? groups.map(([program, rows], idx) => `
            ${idx > 0 ? `<div class="page-break"></div>${headerHtml}` : ''}
            <h3>${escapeHtml(formatProgramName(program))}</h3>
            <table>
                <thead><tr>${thead}</tr></thead>
                <tbody>${rows.map((a, i) => buildRow(a, i)).join('')}</tbody>
            </table>`).join('')
        : `<table><tbody><tr><td colspan="${colspan}" style="text-align:center;padding:18px;">No records found.</td></tr></tbody></table>`;

    const tableHtml = `${headerHtml}${sections}
        <div class="footer">Total: ${reportRows.value.length} record(s)</div>`;

    const html = `<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>${escapeHtml(title)}</title>
        <style>${baseStyle}</style>
        </head><body>
        ${tableHtml}
        </body></html>`;

    const win = window.open('', '_blank');
    if (!win) {
        Swal.fire('Pop-up Blocked', 'Please allow pop-ups for this site, then try printing again.', 'warning');
        return;
    }

    win.document.write(html);
    win.document.close();
    await waitForImages(win);
    win.focus();
    win.print();
};

const printReport = async () => {
    printing.value = true;
    try {
        await openPrintWindow();
    } finally {
        printing.value = false;
    }
};

onMounted(() => {
    fetchSchoolYears();
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
                        <li class="breadcrumb-item fw-medium">
                            <a href="/admin/dashboard">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Reports</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid" style="font-size: 13px;">

            <!-- ── Row 1: School Year + Generate ── -->
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <label for="sy-select" class="form-label mb-0 text-nowrap fw-medium">School Year</label>
                        <select id="sy-select" v-model="selectedYear"
                            class="form-select form-select-sm"
                            style="max-width: 160px;"
                            :disabled="loadingYears">
                            <option value="">All School Years</option>
                            <option v-for="sy in schoolYears" :key="sy.id" :value="sy.id">
                                {{ sy.school_year_start && sy.school_year_end ? `${sy.school_year_start}-${sy.school_year_end}` : sy.school_year_name }}
                            </option>
                        </select>
                        <button class="btn btn-primary btn-sm" @click="generate">
                            <i class="bi bi-lightning-fill me-1"></i>Generate
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Row 2: Report type buttons (shown after Generate) ── -->
            <template v-if="generated">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <button class="btn btn-sm btn-outline-success"
                        :class="{ active: activeReport === 'approved' }"
                        :disabled="loadingReport"
                        @click="loadApproved">
                        <i class="bi bi-person-check me-1"></i>Approved Applicants
                    </button>
                    <button class="btn btn-sm btn-outline-primary"
                        :class="{ active: activeReport === 'exam' }"
                        :disabled="loadingReport"
                        @click="loadExamSchedule">
                        <i class="bi bi-calendar-check me-1"></i>Exam Schedule
                    </button>
                </div>

                <!-- ── Loading ── -->
                <div v-if="loadingReport" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Loading...
                </div>

                <!-- ── Approved Applicants Table ── -->
                <template v-else-if="activeReport === 'approved'">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-medium">
                            Approved Applicants
                            <span class="text-muted ms-1">{{ selectedYearName() }}</span>
                        </span>
                        <button class="btn btn-sm btn-outline-secondary" :disabled="printing" @click="printReport">
                            <span v-if="printing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            <i v-else class="bi bi-printer me-1"></i>Print
                        </button>
                    </div>
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0">
                            <div class="table-responsive-sm">
                                <table class="table table-bordered table-hover table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center align-middle">#</th>
                                            <th class="align-middle">Applicant Name</th>
                                            <th class="text-center align-middle">Gmail Account</th>
                                            <th class="text-center align-middle">Program</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="reportRows.length > 0">
                                        <tr v-for="(a, i) in reportRows" :key="a.id">
                                            <td class="text-center align-middle">{{ i + 1 }}</td>
                                            <td class="align-middle">{{ formatName(a) }}</td>
                                            <td class="text-center align-middle">{{ a.email ?? '' }}</td>
                                            <td class="text-center align-middle">{{ a.program ?? '' }}</td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">No approved applicants found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 text-muted">Total: {{ reportRows.length }} record(s)</div>
                </template>

                <!-- ── Exam Schedule Table ── -->
                <template v-else-if="activeReport === 'exam'">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-medium">
                            Exam Schedule
                            <span class="text-muted ms-1">{{ selectedYearName() }}</span>
                        </span>
                        <button class="btn btn-sm btn-outline-secondary" :disabled="printing" @click="printReport">
                            <span v-if="printing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            <i v-else class="bi bi-printer me-1"></i>Print
                        </button>
                    </div>
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0">
                            <div class="table-responsive-sm">
                                <table class="table table-bordered table-hover table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-center align-middle">#</th>
                                            <th class="align-middle">Applicant Name</th>
                                            <th class="text-center align-middle">Program</th>
                                            <th class="text-center align-middle">Exam Date</th>
                                            <th class="text-center align-middle">Time</th>
                                            <th class="text-center align-middle">Room</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="reportRows.length > 0">
                                        <tr v-for="(a, i) in reportRows" :key="i">
                                            <td class="text-center align-middle">{{ i + 1 }}</td>
                                            <td class="align-middle">{{ formatName(a) }}</td>
                                            <td class="text-center align-middle">{{ a.program ?? '' }}</td>
                                            <td class="text-center align-middle">{{ a.date ?? '' }}</td>
                                            <td class="text-center align-middle">{{ a.time_slot ?? '' }}</td>
                                            <td class="text-center align-middle">{{ a.room ?? '' }}</td>
                                        </tr>
                                    </tbody>
                                    <tbody v-else>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No exam schedules found.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 text-muted">Total: {{ reportRows.length }} record(s)</div>
                </template>

            </template>

        </div>
    </div>
</template>
