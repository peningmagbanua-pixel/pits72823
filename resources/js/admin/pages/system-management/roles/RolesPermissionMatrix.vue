<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { ROLE } from '../../../../constants';
const roles = ref([]);
const permissionTitles = ref([]);
const loading = ref(true);

const getPermissionMatrix = () => {
    loading.value = true;
    axios.get('/api/roles/permission-matrix')
        .then(response => {
            roles.value = response.data.roles;
            permissionTitles.value = response.data.permission_titles;
            loading.value = false;
        })
        .catch(() => {
            loading.value = false;
        });
};

const hasPermission = (role, permissionId) => {
    if (role.name === ROLE.SYSTEM_ADMINISTRATOR) {
        return true;
    }
    return role.permissions.includes(permissionId);
};

onMounted(() => {
    getPermissionMatrix();
});
</script>

<template>
    <div class="modal fade" id="permission-matrix" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content p-3 border-0">
                <div class="modal-body">
                    <h5 class="text-center" id="permissionMatrixLabel">
                        <i class="bi bi-grid-3x3-gap-fill me-2"></i>Permission Matrix
                    </h5>
                    <button type="button" class="btn-close" title="Close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div v-if="loading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 text-muted">Loading permission matrix...</p>
                    </div>

                    <div v-else>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1">
                                    <i class="bi bi-info-circle me-2"></i>Permission Matrix Overview
                                </h6>
                                <p class="small text-muted mb-0">
                                    View all roles and their permissions.
                                    <span class="badge bg-success-subtle text-success ms-2"><i class="bi bi-check-circle-fill me-1"></i>Has Permission</span>

                                    <span class="badge bg-secondary-subtle text-secondary ms-2"><i class="bi bi-x-circle-fill me-1"></i>No Permission</span>
                                </p>
                            </div>
                            <div class="text-end">
                                <div><strong>{{ roles.length }}</strong> Roles</div>
                                <div>
                                    <strong>{{ permissionTitles.reduce((sum, pt) => sum + pt.permissions.length, 0) }}</strong> Permissions
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead>
                                    <tr>
                                        <th class="text-center">Permission Title</th>
                                        <th class="text-center">Permission</th>
                                        <th v-for="role in roles" :key="role.id" class="text-center">
                                            <i class="nav-icon bi bi-person-fill-gear me-1"></i>{{ role.name }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="permissionTitle in permissionTitles" :key="permissionTitle.id">
                                        <tr v-for="(permission, index) in permissionTitle.permissions" :key="permission.id">
                                            <td v-if="index === 0" :rowspan="permissionTitle.permissions.length">
                                                <i class="bi bi-folder-fill me-2 text-primary"></i>{{ permissionTitle.name }}
                                                <div class="small text-muted">{{ permissionTitle.permissions.length }} permissions</div>
                                            </td>
                                            <td>{{ permission.name }}</td>
                                            <td v-for="role in roles" :key="role.id" class="text-center">
                                                <i v-if="hasPermission(role, permission.id)" class="bi bi-check-circle-fill text-success"></i>
                                                <i v-else class="bi bi-x-circle-fill text-secondary"></i>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="permissionTitles.length === 0" class="text-center py-5">
                            <i class="bi bi-grid-3x3 mb-3" style="font-size: 2rem;"></i>
                            <h5>No Data Available</h5>
                            <p class="text-muted">There are no permissions or roles to display.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>