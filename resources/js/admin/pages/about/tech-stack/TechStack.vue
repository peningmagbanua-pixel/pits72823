<script setup>
import { ref } from "vue";

const techStack = ref({
    frontend: [
        {
            name: "HTML5",
            icon: "bi-filetype-html",
            color: "#E34F26",
            description: "Markup language for structuring web content",
        },
        {
            name: "CSS3",
            icon: "bi-filetype-css",
            color: "#1572B6",
            description: "Styling and layout of web pages",
        },
        {
            name: "Bootstrap 5",
            icon: "bi-bootstrap-fill",
            color: "#7952B3",
            description: "Frontend framework for responsive design",
        },
        {
            name: "JavaScript",
            icon: "bi-filetype-js",
            color: "#F1E05A",
            description: "Programming language for web interactivity",
        },
        {
            name: "Vue.js 3",
            icon: "bi-code-square",
            color: "#42B883",
            description: "Progressive JavaScript framework",
        },
    ],
    backend: [
        {
            name: "Laravel 12",
            icon: "bi-box-seam",
            color: "#FF2D20",
            description: "PHP web application framework",
        },
        {
            name: "MySQL",
            icon: "bi-database-fill",
            color: "#4479A1",
            description: "Relational database management system",
        },
    ],
    tools: [
        {
            name: "ngrok",
            icon: "bi-arrow-left-right",
            color: "#1DA1F2",
            description: "Secure tunneling for local development",
        },
    ],
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Tech Stack</h3>
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
                            Tech Stack
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0 bg-gradient">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-stack" style="font-size: 4rem"></i>
                            <h2 class="mt-3 mb-2">Technology Stack</h2>
                            <p class="mb-0">
                                Built with modern web technologies
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-body border-bottom">
                            <h6 class="mb-0">
                                <i
                                    class="bi bi-palette-fill text-primary me-2"
                                ></i>
                                Frontend Technologies
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div
                                    v-for="tech in techStack.frontend"
                                    :key="tech.name"
                                    class="col-md-4"
                                >
                                    <div
                                        class="card h-100 border bg-gradient"
                                        style="transition: all 0.3s"
                                    >
                                        <div class="card-body text-center">
                                            <i
                                                :class="tech.icon"
                                                :style="{
                                                    color: tech.color,
                                                    fontSize: '3rem',
                                                }"
                                            ></i>
                                            <h5 class="mt-3 mb-2">
                                                {{ tech.name }}
                                            </h5>
                                            <p class="text-muted small mb-0">
                                                {{ tech.description }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-body border-bottom">
                            <h6 class="mb-0">
                                <i class="bi bi-server text-success me-2"></i>
                                Backend Technologies
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div
                                    v-for="tech in techStack.backend"
                                    :key="tech.name"
                                    class="col-md-4"
                                >
                                    <div
                                        class="card h-100 border bg-gradient"
                                        style="transition: all 0.3s"
                                    >
                                        <div class="card-body text-center">
                                            <i
                                                :class="tech.icon"
                                                :style="{
                                                    color: tech.color,
                                                    fontSize: '3rem',
                                                }"
                                            ></i>
                                            <h5 class="mt-3 mb-2">
                                                {{ tech.name }}
                                            </h5>
                                            <p class="text-muted small mb-0">
                                                {{ tech.description }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-body border-bottom">
                            <h6 class="mb-0">
                                <i class="bi bi-tools text-warning me-2"></i>
                                Tools & Services
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div
                                    v-for="tech in techStack.tools"
                                    :key="tech.name"
                                    class="col-md-4"
                                >
                                    <div
                                        class="card h-100 border bg-gradient"
                                        style="transition: all 0.3s"
                                    >
                                        <div class="card-body text-center">
                                            <i
                                                :class="tech.icon"
                                                :style="{
                                                    color: tech.color,
                                                    fontSize: '3rem',
                                                }"
                                            ></i>
                                            <h5 class="mt-3 mb-2">
                                                {{ tech.name }}
                                            </h5>
                                            <p class="text-muted small mb-0">
                                                {{ tech.description }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-body border-bottom">
                            <h6 class="mb-0">
                                <i class="bi bi-diagram-3 text-info me-2"></i>
                                System Architecture
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center py-4">
                                <div class="row justify-content-between">
                                    <div class="col-md-3">
                                        <div
                                            class="p-3 bg-body-tertiary rounded"
                                        >
                                            <i
                                                class="bi bi-browser-chrome text-primary"
                                                style="font-size: 2rem"
                                            ></i>
                                            <h6 class="mt-2">Client</h6>
                                            <small class="text-muted"
                                                >Vue.js SPA</small
                                            >
                                        </div>
                                    </div>
                                    <div
                                        class="col-md-1 d-flex align-items-center justify-content-center"
                                    >
                                        <i
                                            class="bi bi-arrow-right text-muted"
                                            style="font-size: 2rem"
                                        ></i>
                                    </div>
                                    <div class="col-md-3">
                                        <div
                                            class="p-3 bg-body-tertiary rounded"
                                        >
                                            <i
                                                class="bi bi-hdd-rack text-success"
                                                style="font-size: 2rem"
                                            ></i>
                                            <h6 class="mt-2">Server</h6>
                                            <small class="text-muted"
                                                >Laravel API</small
                                            >
                                        </div>
                                    </div>
                                    <div
                                        class="col-md-1 d-flex align-items-center justify-content-center"
                                    >
                                        <i
                                            class="bi bi-arrow-right text-muted"
                                            style="font-size: 2rem"
                                        ></i>
                                    </div>
                                    <div class="col-md-3">
                                        <div
                                            class="p-3 bg-body-tertiary rounded"
                                        >
                                            <i
                                                class="bi bi-database text-warning"
                                                style="font-size: 2rem"
                                            ></i>
                                            <h6 class="mt-2">Database</h6>
                                            <small class="text-muted"
                                                >MySQL</small
                                            >
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
