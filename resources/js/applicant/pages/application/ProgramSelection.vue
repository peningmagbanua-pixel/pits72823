<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import Swal from "sweetalert2";
import { sweetAlert, processingAlert } from "@/sweetalert.js";
import { useAuthApplicantStore } from "../../stores/AuthApplicantStore";

const router = useRouter();
const authApplicantStore = useAuthApplicantStore();

const programs = ref([]);
const requirementsByProgram = ref({});
const loading = ref(true);

onMounted(async () => {
    // Already submitted: program selection (and the form) is only reachable
    // again while the application is rejected (reapplication).
    const applicant = authApplicantStore.applicant;
    if (applicant.program && applicant.application_status !== 'rejected') {
        router.push("/applicant/dashboard");
        return;
    }

    loading.value = true;

    try {
        const res = await axios.get('/api/applicant/programs');
        programs.value = res.data.data || res.data;
    } catch (e) {
        console.error('Failed to fetch programs:', e);
    }

    // Requirements are resolved per program on the server; the client only
    // ever passes the public program initials, never a program id.
    try {
        const entries = await Promise.all(
            programs.value.map(async (prog) => {
                const res = await axios.get('/api/applicant/requirements', {
                    params: { program: prog.initials },
                });
                return [prog.initials, res.data];
            })
        );
        requirementsByProgram.value = Object.fromEntries(entries);
    } catch (e) {
        console.error('Failed to fetch requirements:', e);
    }

    loading.value = false;
});

const selectProgram = async (program) => {
    processingAlert('.btn');
    try {
        // The server resolves and stores the actual program id; the form URL
        // stays generic and carries no program identifier.
        await axios.post('/api/applicant/application/select-program', {
            program: program.initials,
        });
        Swal.close();
        router.push({ name: "applicant.application.form" });
    } catch (e) {
        sweetAlert(
            'error',
            e.response?.data?.message || 'Unable to select the program. Please try again.'
        );
    }
};

const getProgramIcon = (initials) => {
    if (initials === 'SPICT') return 'bi-pc-display-horizontal';
    if (initials === 'STE') return 'bi-mortarboard-fill';
    return 'bi-book';
};

const getProgramColor = (initials) => {
    if (initials === 'SPICT') return 'primary';
    if (initials === 'STE') return 'success';
    return 'secondary';
};

const getProgramDescription = (initials) => {
    if (initials === 'SPICT') return 'Focuses on Information and Communications Technology, computer programming, and science integration.';
    if (initials === 'STE') return 'An intensive science and technology program designed for academically advanced students.';
    return '';
};

const getRequiredDocuments = (initials) => {
    return (requirementsByProgram.value[initials] || []).map(r => r.name);
};

const getButtonLabel = (initials) => {
    if (initials === 'SPICT') return 'Apply for SPICT';
    if (initials === 'STE') return 'Apply for STE';
    return `Apply for ${initials}`;
};
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Select Program</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium">
                            <a href="/applicant/dashboard">Home</a>
                        </li>
                        <li class="breadcrumb-item fw-medium">Application</li>
                        <li
                            class="breadcrumb-item active fw-medium"
                            aria-current="page"
                        >
                            Select Program
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Intro -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div
                        class="alert alert-info d-flex align-items-start gap-2 mb-0"
                    >
                        <i class="bi bi-info-circle-fill fs-5 mt-1"></i>
                        <div>
                            <strong>Program Selection</strong><br />
                            <span class="small"
                                >Please select the academic program you wish to
                                apply for. You will be directed to the
                                appropriate enrollment form based on your
                                selection.</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Program Cards -->
            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <div v-else class="row justify-content-center g-4">
                <div v-for="prog in programs" :key="prog.initials" class="col-lg-5 col-md-6">
                    <div
                        class="card border-0 shadow-sm h-100 program-card"
                        @click="selectProgram(prog)"
                        role="button"
                        tabindex="0"
                        @keyup.enter="selectProgram(prog)"
                    >
                        <div class="card-body text-center p-4 p-md-5">
                            <div class="program-icon mb-3" :class="'text-' + getProgramColor(prog.initials)">
                                <i
                                    :class="getProgramIcon(prog.initials)"
                                    style="font-size: 3.5rem"
                                ></i>
                            </div>
                            <span
                                class="badge" :class="'bg-' + getProgramColor(prog.initials) + '-subtle text-' + getProgramColor(prog.initials) + ' mb-2'"
                            >
                                {{ prog.initials }}
                            </span>
                            <h5 class="fw-bold mb-2">
                                {{ prog.name }}
                            </h5>
                            <p class="text-muted small mb-4">
                                {{ getProgramDescription(prog.initials) }}
                            </p>
                            <div class="text-start mb-4">
                                <div
                                    class="small text-muted mb-1 fw-semibold text-uppercase"
                                    style="
                                        font-size: 11px;
                                        letter-spacing: 0.05em;
                                    "
                                >
                                    Required Documents
                                </div>
                                <ul class="list-unstyled small mt-2">
                                    <li class="mb-1" v-for="doc in getRequiredDocuments(prog.initials)" :key="doc">
                                        <i
                                            class="bi bi-check-circle-fill"
                                            :class="'text-' + getProgramColor(prog.initials) + ' me-2'"
                                        ></i
                                        >{{ doc }}
                                    </li>
                                </ul>
                            </div>
                            <button class="btn fw-semibold w-100" :class="'btn-' + getProgramColor(prog.initials)">
                                <i class="bi bi-arrow-right-circle me-2"></i
                                >{{ getButtonLabel(prog.initials) }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.program-card {
    cursor: pointer;
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease;
    border-radius: 12px;
}
.program-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
}
</style>