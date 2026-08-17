<script setup>
import { useRouter } from 'vue-router';
import { useAuthApplicantStore } from '../stores/AuthApplicantStore';
import { logoutAlert } from '../../sweetalert';
const authApplicantStore = useAuthApplicantStore();
const router = useRouter();

const logout = () => {
    logoutAlert();
    axios.post('/applicant/logout');
    router.push('/applicant/login');
    location.reload();
};

</script>
<template>
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i
                            class="bi bi-menu-button"></i></a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto align-items-center">
                
                <li class="nav-item me-1">
                    <div class="dropdown">
                        <button class="btn btn-link nav-link" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false" title="Eye Protection">
                            <i class="bi bi-eye-fill"></i>
                        </button>

                        <div class="dropdown-menu dropdown-menu-end p-3 shadow mt-2 border-0 shadow"
                            style="min-width: 320px;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">
                                    <i class="bi bi-eye-fill me-2"></i>
                                    Eye Protection
                                </h6>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">
                                        <i class="bi bi-shield-fill-check me-2 text-primary"></i>
                                        <span>Blue Light Filter</span>
                                    </label>
                                    <small class="text-muted" id="blueLightValue">0%</small>
                                </div>
                                <input type="range" class="form-range" min="0" max="100" id="blueLightSlider">
                            </div>

                            <hr class="my-3">

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">
                                        <i class="bi bi-brightness-high-fill me-2 text-primary"></i>
                                        <span>Brightness</span>
                                    </label>
                                    <small class="text-muted" id="brightnessValue">100%</small>
                                </div>
                                <input type="range" class="form-range" min="30" max="100" id="brightnessSlider">
                            </div>

                            <hr class="my-3">

                            <div class="mb-3">
                                <button id="resetEyeProtection" class="btn btn-sm btn-outline-secondary w-100">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>
                                    Reset to Default
                                </button>
                            </div>
                        </div>
                    </div>
                </li>

                <li class="nav-item me-1">
                    <button id="theme-toggle-icon" class="btn btn-link nav-link">
                        <i class="bi bi-brightness-high-fill" id="theme-icon"></i>
                    </button>
                </li>
                <li class="nav-item me-3">
                    <a class="nav-link" href="#" data-lte-toggle="fullscreen">
                        <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                        <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
                    </a>
                </li>

                <li class="nav-item me-3 border-start" style="height:24px;"></li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <img :src="authApplicantStore.applicant.profile || '/images/default.jpeg'"
                            class="rounded-circle shadow-sm me-2" alt="Profile" width="32" height="32">
                        <small class="d-none d-md-inline fw-semibold">{{ authApplicantStore.applicant.name }}</small>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end shadow mt-2 p-3 border-0 shadow"
                        style="min-width: 250px;">
                        <li class="text-center mb-3">
                            <img :src="authApplicantStore.applicant.profile || '/images/default.jpeg'"
                                class="rounded-circle shadow-sm mb-2" alt="User Image" width="64" height="64">
                            <p class="mb-0 fw-semibold">{{ authApplicantStore.applicant.name }}</p>
                            <small class="text-muted">{{ authApplicantStore.applicant.email }}</small>
                        </li>

                        <li class="d-flex justify-content-between">
                            <a href="/applicant/profile"
                                class="btn btn-sm btn-link text-decoration-none bg-body text-body w-45">
                                <i class="bi bi-person-fill me-1"></i>Profile
                            </a>
                            <button @click.prevent="logout()"
                                class="btn btn-sm btn-link text-decoration-none bg-body text-body w-45">
                                <i class="bi bi-power me-1"></i>Log out
                            </button>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
</template>
