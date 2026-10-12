<script setup>
import { computed } from "vue";
import { useRouter, useRoute } from "vue-router";
import Swal from "sweetalert2";
import { logoutAlert } from "../../sweetalert";
import { useAuthApplicantStore } from "../stores/AuthApplicantStore";

const router = useRouter();
const route = useRoute();
const authApplicantStore = useAuthApplicantStore();

const isActive = (path) => route.path.startsWith(path);

// The application form is available before the first submission and,
// afterwards, only while the application is rejected (reapplication).
const canOpenForm = computed(
    () =>
        !authApplicantStore.applicant.program ||
        authApplicantStore.applicant.application_status === "rejected",
);

const logout = () => {
    Swal.fire({
        title: "Log out",
        text: "Are you sure you want to logout?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, log out!",
        cancelButtonText: "Cancel",
        allowOutsideClick: false,
        allowEscapeKey: false,
    }).then((result) => {
        if (result.isConfirmed) {
            logoutAlert();
            axios
                .post("/applicant/logout")
                .then(() => {
                    router.push("/applicant/login");
                    location.reload();
                })
                .catch(() => {
                    Swal.fire(
                        "Error",
                        "Logout failed. please try again.",
                        "error",
                    );
                });
        }
    });
};
</script>
<template>
    <aside class="app-sidebar border-end shadow-sm">
        <div class="sidebar-brand">
            <a href="/applicant/dashboard" class="brand-link">
                <img
                    src="/images/cnhs_logo.png"
                    alt="cnhs-logo"
                    class="brand-image ms-2"
                />
                <span class="brand-text fw-bold">Applicant Portal</span>
            </a>
        </div>
        <div class="sidebar-wrapper">
            <nav class="mt-2">
                <ul
                    class="nav sidebar-menu flex-column"
                    data-lte-toggle="treeview"
                    role="navigation"
                    aria-label="Main navigation"
                    data-accordion="true"
                    id="navigation"
                >
                    <li class="nav-item">
                        <router-link
                            to="/applicant/dashboard"
                            :class="{
                                active: isActive('/applicant/dashboard'),
                            }"
                            class="nav-link"
                        >
                            <i class="nav-icon bi bi-grid-fill"></i>
                            <p>Dashboard</p>
                        </router-link>
                    </li>

                    <li v-if="canOpenForm" class="nav-item">
                        <router-link
                            to="/applicant/application/select-program"
                            :class="{
                                active:
                                    isActive('/applicant/application/form') ||
                                    isActive('/applicant/application/select-program'),
                            }"
                            class="nav-link"
                        >
                            <i class="nav-icon bi bi-pencil-square"></i>
                            <p>Application Form</p>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link
                            to="/applicant/application/status"
                            :class="{
                                active: isActive('/applicant/application/status'),
                            }"
                            class="nav-link"
                        >
                            <i class="nav-icon bi bi-clipboard-check"></i>
                            <p>Application Status</p>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link
                            to="/applicant/activity-logs"
                            :class="{ active: isActive('/applicant/activity-logs') }"
                            class="nav-link"
                        >
                            <i class="nav-icon bi bi-person-lines-fill"></i>
                            <p>Activity Logs</p>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link
                            to="/applicant/profile"
                            :class="{ active: isActive('/applicant/profile') }"
                            class="nav-link"
                        >
                            <i class="nav-icon bi bi-person-fill"></i>
                            <p>Profile</p>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <router-link
                            to="/applicant/faq"
                            :class="{ active: isActive('/applicant/faq') }"
                            class="nav-link"
                        >
                            <i class="nav-icon bi bi-question-circle-fill"></i>
                            <p>CNHS FAQs</p>
                        </router-link>
                    </li>

                    <li class="nav-item">
                        <a
                            type="button"
                            @click.prevent="logout"
                            class="nav-link"
                        >
                            <i class="nav-icon bi bi-power"></i>
                            <p>Log out</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>
</template>
