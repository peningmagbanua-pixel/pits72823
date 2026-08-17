<script setup>
import { ref } from "vue";

const teamMembers = ref([
    {
        name: "John Kenneth Pening",
        roles: ["Lead Developer", "Documentation", "Researcher", "Presenter"],
    },
    {
        name: "Leslien Olivete",
        roles: ["Documentation", "Researcher", "Presenter"],
    },
    {
        name: "Dennis Tabares",
        roles: ["Documentation", "Researcher", "Presenter"],
    },
    {
        name: "Jhon Sammel Sardenia",
        roles: ["Documentation", "Researcher", "Presenter"],
    },
]);
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Team</h3>
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
                            Team
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid py-4">
            <div class="row g-4">
                <div
                    v-for="member in teamMembers"
                    :key="member.name"
                    class="col-md-3"
                >
                    <div
                        class="card h-100 shadow-sm border-0 text-center"
                        style="transition: all 0.3s"
                    >
                        <div class="card-body">
                            <div
                                style="
                                    width: 100px;
                                    height: 100px;
                                    margin: 0 auto 1rem;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    background-color: #e7f1ff;
                                    border-radius: 50%;
                                "
                            >
                                <i
                                    class="bi bi-person-fill"
                                    style="font-size: 50px; color: #0d6efd"
                                ></i>
                            </div>
                            <h5 class="mt-2 mb-2 fw-bold">
                                {{ member.name }}
                            </h5>
                            <div
                                class="d-flex flex-wrap gap-1 justify-content-center"
                            >
                                <span
                                    v-for="(role, idx) in member.roles"
                                    :key="idx"
                                    class="badge bg-primary-subtle text-primary"
                                    style="font-size: 11px"
                                >
                                    {{ role }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
