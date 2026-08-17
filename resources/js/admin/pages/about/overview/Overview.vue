<script setup>
import { ref } from "vue";

const features = ref([
    {
        name: "Application Processing",
        icon: "bi-file-earmark-check",
        color: "#0d6efd",
        description:
            "Streamlined online submission and review of student applications with real-time status tracking.",
    },
    {
        name: "Secure & Reliable",
        icon: "bi-shield-lock-fill",
        color: "#198754",
        description:
            "Role-based access control and data protection to ensure applicant information stays safe.",
    },
    {
        name: "Reports & Analytics",
        icon: "bi-bar-chart-line-fill",
        color: "#ffc107",
        description:
            "Generate reports on application trends, enrollment numbers, and program statistics.",
    },
    {
        name: "Accessible Anywhere",
        icon: "bi-cloud-arrow-up-fill",
        color: "#0dcaf0",
        description:
            "Web-based platform accessible from any device with an internet connection.",
    },
    {
        name: "Admin Dashboard",
        icon: "bi-speedometer2",
        color: "#6f42c1",
        description:
            "Centralized dashboard for managing applicants, academic data, and system settings.",
    },
    {
        name: "Audit Logging",
        icon: "bi-journal-check",
        color: "#fd7e14",
        description:
            "Track all system activities with detailed audit logs for transparency and accountability.",
    },
]);
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Overview</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium">
                            <a href="/admin/dashboard">Dashboard</a>
                        </li>
                        <li
                            class="breadcrumb-item active fw-medium"
                            aria-current="page"
                        >
                            Overview
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid py-4">
            <div class="mb-4 text-center">
                <p class="text-muted">
                    The
                    <strong class="text-danger"
                        >Online Application System</strong
                    >
                    simplifies and automates the student application and
                    enrollment process.
                </p>
            </div>

            <div class="row g-4">
                <div
                    v-for="feature in features"
                    :key="feature.name"
                    class="col-md-4"
                >
                    <div
                        class="card h-100 shadow-sm border-0 text-center"
                        style="transition: all 0.3s"
                    >
                        <div class="card-body">
                            <i
                                :class="feature.icon"
                                :style="{
                                    color: feature.color,
                                    fontSize: '3rem',
                                }"
                            ></i>
                            <h5 class="mt-3 mb-2">{{ feature.name }}</h5>
                            <p class="text-muted small mb-0">
                                {{ feature.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
