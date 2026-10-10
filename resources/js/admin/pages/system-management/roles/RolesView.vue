<script setup>
import { reactive, ref } from 'vue';
import { Modal } from 'bootstrap';

const role = reactive({
    id: '',
    name: '',
    created_at: ''
});

const users = ref([]);
const usersLoading = ref(false);

const getRole = (roleData) => {
    role.id = roleData.id;
    role.name = roleData.name;
    role.created_at = roleData.created_at;
    
    usersLoading.value = true;
    axios.get(`/api/roles/${roleData.id}/users`)
        .then((response) => {
            users.value = response.data.users;
        })
        .finally(() => {
            usersLoading.value = false;
        });
};

defineExpose({ getRole });
</script>
<template>
    <div class="modal fade" id="view-role" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">Role Details</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">Role Name:</label>
                                <p class="mb-0">{{ role.name }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <label class="form-label fw-bold">Date Created:</label>
                                <p class="mb-0">{{ role.created_at }}</p>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Users with this role:</label>
                        <div v-if="usersLoading" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-3 mb-0 text-muted">Loading ...</p>
                        </div>
                        <div v-else-if="users.length > 0" class="table-responsive">
                            <table class="table table-bordered table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th class="text-center align-middle">#</th>
                                        <th class="text-center align-middle">Name</th>
                                        <th class="text-center align-middle">Email</th>
                                        <th class="text-center align-middle">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(user, index) in users" :key="user.id">
                                        <td class="text-center align-middle">{{ index + 1 }}</td>
                                        <td class="align-middle">{{ user.name }}</td>
                                        <td class="align-middle">{{ user.email }}</td>
                                        <td class="text-center align-middle">
                                            <span class="badge" :class="user.status_color">{{ user.status_text }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="alert alert-info text-center">
                            <i class="bi bi-info-circle me-2"></i>No users assigned to this role
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
