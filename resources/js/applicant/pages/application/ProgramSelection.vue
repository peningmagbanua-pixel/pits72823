<script setup>
import { useRouter } from "vue-router";
import { onMounted } from "vue";
import { useAuthApplicantStore } from "../../stores/AuthApplicantStore";

const router = useRouter();
const authApplicantStore = useAuthApplicantStore();

onMounted(() => {
    if (authApplicantStore.applicant.program) {
        router.push("/applicant/dashboard");
    }
});

const selectProgram = (program) => {
    router.push({ name: "applicant.application.form", query: { program } });
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
            <div class="row justify-content-center g-4">
                <!-- SPICT -->
                <div class="col-lg-5 col-md-6">
                    <div
                        class="card border-0 shadow-sm h-100 program-card"
                        @click="selectProgram('SPICT')"
                        role="button"
                        tabindex="0"
                        @keyup.enter="selectProgram('SPICT')"
                    >
                        <div class="card-body text-center p-5">
                            <div class="program-icon mb-3 text-primary">
                                <i
                                    class="bi bi-pc-display-horizontal"
                                    style="font-size: 3.5rem"
                                ></i>
                            </div>
                            <span
                                class="badge bg-primary-subtle text-primary mb-2"
                                >SPICT</span
                            >
                            <h5 class="fw-bold mb-2">
                                Special Curriculum Program in Information &
                                Communications Technology (SPICT)
                            </h5>
                            <p class="text-muted small mb-4">
                                Focuses on Information and Communications
                                Technology, computer programming, and science
                                integration.
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
                                    <li class="mb-1">
                                        <i
                                            class="bi bi-check-circle-fill text-success me-2"
                                        ></i
                                        >SF9 / Report Card
                                    </li>
                                </ul>
                            </div>
                            <button class="btn btn-primary w-100 fw-semibold">
                                <i class="bi bi-arrow-right-circle me-2"></i
                                >Apply for SPICT
                            </button>
                        </div>
                    </div>
                </div>

                <!-- STE -->
                <div class="col-lg-5 col-md-6">
                    <div
                        class="card border-0 shadow-sm h-100 program-card"
                        @click="selectProgram('STE')"
                        role="button"
                        tabindex="0"
                        @keyup.enter="selectProgram('STE')"
                    >
                        <div class="card-body text-center p-5">
                            <div class="program-icon mb-3 text-success">
                                <i
                                    class="bi bi-mortarboard-fill"
                                    style="font-size: 3.5rem"
                                ></i>
                            </div>
                            <span
                                class="badge bg-success-subtle text-success mb-2"
                                >STE</span
                            >
                            <h5 class="fw-bold mb-2">
                                Special Curriculum Program in Science,
                                Technology and Engineering
                            </h5>
                            <p class="text-muted small mb-4">
                                An intensive science and technology program
                                designed for academically advanced students.
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
                                    <li class="mb-1">
                                        <i
                                            class="bi bi-check-circle-fill text-success me-2"
                                        ></i
                                        >SF9 / Report Card
                                    </li>
                                    <li class="mb-1">
                                        <i
                                            class="bi bi-check-circle-fill text-success me-2"
                                        ></i
                                        >Certificate of Good Moral Character
                                    </li>
                                    <li class="mb-1">
                                        <i
                                            class="bi bi-check-circle-fill text-success me-2"
                                        ></i
                                        >Health Declaration Form
                                    </li>
                                    <li class="mb-1">
                                        <i
                                            class="bi bi-check-circle-fill text-success me-2"
                                        ></i
                                        >Signed STE Declaration Form
                                    </li>
                                </ul>
                            </div>
                            <button class="btn btn-success w-100 fw-semibold">
                                <i class="bi bi-arrow-right-circle me-2"></i
                                >Apply for STE
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
