<script setup>
import { useRouter, useRoute } from "vue-router";
import Swal from "sweetalert2";
import { useAuthUserStore } from "../stores/AuthUserStore";
import { logoutAlert } from "../../sweetalert";

const authUserStore = useAuthUserStore();
const router = useRouter();
const route = useRoute();

const isActive = (path) => route.path.startsWith(path);

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
                .post("/admin/logout")
                .then(() => {
                    router.push("/admin/login");
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
    <aside
        class="app-sidebar border-end shadow-sm"
        style="background-color: maroon !important"
    >
        <div class="sidebar-brand">
            <a href="/admin/dashboard" class="brand-link">
                <img
                    src="/images/cnhs_logo.png"
                    alt="cnhs-logo"
                    class="brand-image ms-2"
                />
                <span class="brand-text fw-bold text-white">ADMIN PORTAL</span>
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
                    <!-- MAIN MENU -->
                    <li class="nav-header">
                        <i class="bi bi-house-fill me-1"></i> Main Menu
                    </li>
                    <li class="nav-item">
                        <router-link
                            to="/admin/dashboard"
                            :class="{ active: isActive('/admin/dashboard') }"
                            class="nav-link"
                        >
                            <i class="nav-icon bi bi-grid-fill"></i>
                            <p>Dashboard</p>
                        </router-link>
                    </li>

                    <template v-if="authUserStore.user.view.admissions">
                        <hr class="sidebar-divider" />

                        <li class="nav-header">
                            <i class="bi bi-person-lines-fill me-1"></i>
                            Admissions
                        </li>
                        <li
                            v-if="authUserStore.user.view.applicant_accounts"
                            class="nav-item"
                        >
                            <router-link
                                to="/admin/applicant-accounts"
                                :class="{
                                    active: isActive(
                                        '/admin/applicant-accounts',
                                    ),
                                }"
                                class="nav-link"
                            >
                                <i class="nav-icon bi bi-people-fill"></i>
                                <p>Applicant Accounts</p>
                            </router-link>
                        </li>
                        <li
                            v-if="authUserStore.user.view.applications"
                            class="nav-item"
                        >
                            <router-link
                                to="/admin/applications"
                                :class="{
                                    active: isActive('/admin/applications'),
                                }"
                                class="nav-link"
                            >
                                <i class="nav-icon bi bi-ui-checks"></i>
                                <p>Applications</p>
                            </router-link>
                        </li>
                        <li
                            v-if="authUserStore.user.view.applications"
                            class="nav-item"
                        >
                            <router-link
                                to="/admin/exam-scheduling"
                                :class="{
                                    active: isActive('/admin/exam-scheduling'),
                                }"
                                class="nav-link"
                            >
                                <i class="nav-icon bi bi-calendar-check"></i>
                                <p>Exam Scheduling</p>
                            </router-link>
                        </li>
                    </template>

                    <template v-if="authUserStore.user.view.reports_monitoring">
                        <hr class="sidebar-divider" />

                        <li class="nav-header">
                            <i class="bi bi-bar-chart-fill me-1"></i> Reports &
                            Monitoring
                        </li>
                        <li
                            v-if="authUserStore.user.view.reports"
                            class="nav-item"
                        >
                            <router-link
                                to="/admin/reports"
                                :class="{
                                    active: isActive('/admin/reports'),
                                }"
                                class="nav-link"
                            >
                                <i
                                    class="nav-icon bi bi-file-earmark-bar-graph-fill"
                                ></i>
                                <p>Reports</p>
                            </router-link>
                        </li>
                        <li class="nav-item">
                            <router-link
                                to="/admin/audit-logs"
                                :class="{
                                    active: isActive('/admin/audit-logs'),
                                }"
                                class="nav-link"
                            >
                                <i class="nav-icon bi bi-clock-history"></i>
                                <p>Audit Logs</p>
                            </router-link>
                        </li>
                        <li class="nav-item">
                            <router-link
                                to="/admin/activity-logs"
                                :class="{
                                    active: isActive('/admin/activity-logs'),
                                }"
                                class="nav-link"
                            >
                                <i class="nav-icon bi bi-person-lines-fill"></i>
                                <p>Activity Logs</p>
                            </router-link>
                        </li>
                    </template>

                    <template v-if="authUserStore.user.view.system_management">
                        <hr class="sidebar-divider" />

                        <!-- SYSTEM ADMINISTRATION -->
                        <li class="nav-header">
                            <i class="bi bi-gear-wide-connected me-1"></i>
                            System Administration
                        </li>

                        <li class="nav-item">
                            <a
                                href="#"
                                class="nav-link"
                                :class="{
                                    'menu-open active': isActive(
                                        '/admin/academic-setup',
                                    ),
                                }"
                            >
                                <i class="nav-icon bi bi-mortarboard-fill"></i>
                                <p>
                                    Academic Setup
                                    <i
                                        class="nav-arrow bi bi-chevron-right"
                                    ></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <router-link
                                        to="/admin/academic-setup/school-years"
                                        :class="{
                                            active: isActive(
                                                '/admin/academic-setup/school-years',
                                            ),
                                        }"
                                        class="nav-link"
                                    >
                                        <i
                                            class="nav-icon bi bi-calendar-range"
                                        ></i>
                                        <p>School Years</p>
                                    </router-link>
                                </li>
                                <li class="nav-item">
                                    <router-link
                                        to="/admin/academic-setup/programs"
                                        :class="{
                                            active: isActive(
                                                '/admin/academic-setup/programs',
                                            ),
                                        }"
                                        class="nav-link"
                                    >
                                        <i
                                            class="nav-icon bi bi-journal-bookmark-fill"
                                        ></i>
                                        <p>Programs</p>
                                    </router-link>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <a
                                href="#"
                                class="nav-link"
                                :class="{
                                    'menu-open active': isActive(
                                        '/admin/system-management',
                                    ),
                                }"
                            >
                                <i class="nav-icon bi bi-sliders"></i>
                                <p>
                                    System Management
                                    <i
                                        class="nav-arrow bi bi-chevron-right"
                                    ></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <router-link
                                        to="/admin/system-management/users"
                                        :class="{
                                            active: isActive(
                                                '/admin/system-management/users',
                                            ),
                                        }"
                                        class="nav-link"
                                    >
                                        <i
                                            class="nav-icon bi bi-people-fill"
                                        ></i>
                                        <p>Users</p>
                                    </router-link>
                                </li>
                                <li class="nav-item">
                                    <router-link
                                        to="/admin/system-management/roles"
                                        :class="{
                                            active: isActive(
                                                '/admin/system-management/roles',
                                            ),
                                        }"
                                        class="nav-link"
                                    >
                                        <i
                                            class="nav-icon bi bi-person-fill-gear"
                                        ></i>
                                        <p>Roles</p>
                                    </router-link>
                                </li>
                                <li class="nav-item">
                                    <router-link
                                        to="/admin/system-management/permission-titles"
                                        :class="{
                                            active: isActive(
                                                '/admin/system-management/permission-titles',
                                            ),
                                        }"
                                        class="nav-link"
                                    >
                                        <i
                                            class="nav-icon bi bi-shield-lock-fill"
                                        ></i>
                                        <p>Permission Titles</p>
                                    </router-link>
                                </li>
                                <li class="nav-item">
                                    <router-link
                                        to="/admin/system-management/settings"
                                        :class="{
                                            active: isActive(
                                                '/admin/system-management/settings',
                                            ),
                                        }"
                                        class="nav-link"
                                    >
                                        <i class="nav-icon bi bi-gear-fill"></i>
                                        <p>Settings</p>
                                    </router-link>
                                </li>
                            </ul>
                        </li>

                        <hr class="sidebar-divider" />

                        <!-- SYSTEM INFORMATION -->
                        <li class="nav-header">
                            <i class="bi bi-info-circle-fill me-1"></i> System
                            Information
                        </li>

                        <li class="nav-item">
                            <a
                                href="#"
                                class="nav-link"
                                :class="{
                                    'menu-open active':
                                        isActive('/admin/about'),
                                }"
                            >
                                <i class="nav-icon bi bi-info-circle"></i>
                                <p>
                                    About
                                    <i
                                        class="nav-arrow bi bi-chevron-right"
                                    ></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <router-link
                                        to="/admin/about/overview"
                                        :class="{
                                            active: isActive(
                                                '/admin/about/overview',
                                            ),
                                        }"
                                        class="nav-link"
                                    >
                                        <i
                                            class="nav-icon bi bi-speedometer2"
                                        ></i>
                                        <p>Overview</p>
                                    </router-link>
                                </li>
                                <li class="nav-item">
                                    <router-link
                                        to="/admin/about/team"
                                        :class="{
                                            active: isActive(
                                                '/admin/about/team',
                                            ),
                                        }"
                                        class="nav-link"
                                    >
                                        <i
                                            class="nav-icon bi bi-people-fill"
                                        ></i>
                                        <p>Team</p>
                                    </router-link>
                                </li>
                                <li class="nav-item">
                                    <router-link
                                        to="/admin/about/tech-stack"
                                        :class="{
                                            active: isActive(
                                                '/admin/about/tech-stack',
                                            ),
                                        }"
                                        class="nav-link"
                                    >
                                        <i class="nav-icon bi bi-stack"></i>
                                        <p>Tech Stack</p>
                                    </router-link>
                                </li>
                            </ul>
                        </li>
                    </template>

                    <hr class="sidebar-divider" />

                    <!-- ACCOUNT -->
                    <li class="nav-header">
                        <i class="bi bi-person-circle me-1"></i> Account
                    </li>
                    <li class="nav-item">
                        <router-link
                            to="/admin/profile"
                            :class="{ active: isActive('/admin/profile') }"
                            class="nav-link"
                        >
                            <i class="nav-icon bi bi-person-fill"></i>
                            <p>My Profile</p>
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

<style scoped>
.nav-header {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.55;
    padding: 0.6rem 1rem 0.3rem;
    margin-top: 0.25rem;
}

.nav-header i {
    font-size: 0.75rem;
}

.sidebar-divider {
    margin: 0.5rem 1rem;
    opacity: 0.1;
}
</style>
