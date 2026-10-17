<script setup>
import { reactive, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import { sweetAlert } from '@/sweetalert.js';
import { processingAlert } from '../../../../sweetalert';
import { Modal } from 'bootstrap';
import { ROLE } from '../../../../constants';

const emit = defineEmits(['permissions-updated']);

const hideModal = () => {
    const modalElement = document.getElementById('manage-permissions');
    const modalInstance = Modal.getInstance(modalElement);
    modalInstance.hide();
};

const form = reactive({
    id: '',
    name: ''
});

const permissions = ref([]);
const selected_permissions = ref([]);
const permissionsLoading = ref(false);

// Access to the Applications page implies the ability to act on them, so the
// backend auto-grants these; keep the modal in sync so the coordinator sees it.
const APPLICATION_IMPLIED_PERMISSIONS = ['update applications', 'read applicants'];

watch(selected_permissions, () => {
    const all = permissions.value.flatMap(group => group.permissions);
    const browse = all.find(p => p.name === 'browse applications');
    if (!browse || !selected_permissions.value.includes(browse.id)) return;

    all.forEach(permission => {
        if (APPLICATION_IMPLIED_PERMISSIONS.includes(permission.name)
            && !selected_permissions.value.includes(permission.id)) {
            selected_permissions.value.push(permission.id);
        }
    });
}, { deep: true });

const getRolePermissions = (role) => {
    form.id = role.id;
    form.name = role.name;

    permissionsLoading.value = true;
    axios.get(`/api/roles/${role.id}/permissions`)
        .then((response) => {
            permissions.value = response.data.permissions;

            selected_permissions.value = [];
            response.data.permissions.forEach(group => {
                group.permissions.forEach(permission => {
                    if (permission.checked) {
                        selected_permissions.value.push(permission.id);
                    }
                });
            });
            if (role.name === ROLE.SYSTEM_ADMINISTRATOR) {
                selected_permissions.value = permissions.value.flatMap(group =>
                    group.permissions.map(p => p.id)
                );
            }
        })
        .finally(() => {
            permissionsLoading.value = false;
        });
};

defineExpose({ getRolePermissions });

const handleSubmit = () => {
    Swal.fire({
        title: "Are you sure?",
        text: "You are about to update permissions for this role!",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, update it!",
        allowOutsideClick: false,
        allowEscapeKey: false,
    })
        .then((result) => {
            if (result.isConfirmed) {
                processingAlert('.btn');
                axios.put(`/api/roles/${form.id}/permissions`, {
                    permissions: selected_permissions.value
                })
                    .then((response) => {
                        Swal.close();
                        hideModal();
                        sweetAlert('success', response.data.message);
                        emit('permissions-updated');
                    }).catch((error) => {
                        Swal.close();
                        sweetAlert('error', error.response.data.message);
                    });
            }
        });
};

const toggleAll = (groupIndex) => {
    const group = permissions.value[groupIndex];
    const allChecked = group.permissions.every(p => selected_permissions.value.includes(p.id));
    
    if (allChecked) {
        group.permissions.forEach(permission => {
            const index = selected_permissions.value.indexOf(permission.id);
            if (index > -1) {
                selected_permissions.value.splice(index, 1);
            }
        });
    } else {
        group.permissions.forEach(permission => {
            if (!selected_permissions.value.includes(permission.id)) {
                selected_permissions.value.push(permission.id);
            }
        });
    }
};

const isGroupChecked = (groupIndex) => {
    const group = permissions.value[groupIndex];
    return group.permissions.every(p => selected_permissions.value.includes(p.id));
};

const isGroupIndeterminate = (groupIndex) => {
    const group = permissions.value[groupIndex];
    const checkedCount = group.permissions.filter(p => selected_permissions.value.includes(p.id)).length;
    return checkedCount > 0 && checkedCount < group.permissions.length;
};

const selectAll = () => {
    const allSelected = permissions.value.every(group => 
        group.permissions.every(p => selected_permissions.value.includes(p.id))
    );
    
    if (allSelected) {
        selected_permissions.value = [];
    } else {
        selected_permissions.value = [];
        permissions.value.forEach(group => {
            group.permissions.forEach(permission => {
                selected_permissions.value.push(permission.id);
            });
        });
    }
};

const isAllSelected = () => {
    return permissions.value.every(group => 
        group.permissions.every(p => selected_permissions.value.includes(p.id))
    );
};
</script>
<template>
    <div class="modal fade" id="manage-permissions" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center mb-4">
                        <i class="bi bi-shield-lock me-2"></i>Manage Permissions
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <div>
                            <strong>Role:</strong> {{ form.name }}
                            <span class="ms-3 text-muted">|</span>
                            <span class="ms-3">
                                <strong>Selected:</strong> {{ selected_permissions.length }} permission(s)
                            </span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Permissions</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="selectAll" :disabled="form.name === ROLE.SYSTEM_ADMINISTRATOR">
                            <i class="bi" :class="isAllSelected() ? 'bi-check-square' : 'bi-square'"></i>
                            {{ isAllSelected() ? 'Deselect All' : 'Select All' }}
                        </button>
                    </div>

                    <div v-if="permissionsLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-3 mb-0 text-muted">Loading ...</p>
                    </div>

                    <template v-else>
                        <div class="row g-3">
                            <div v-for="(group, index) in permissions" :key="index" class="col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-body">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" :checked="isGroupChecked(index)" :indeterminate="isGroupIndeterminate(index)" @change="toggleAll(index)" :id="'group-' + index" :disabled="form.name === ROLE.SYSTEM_ADMINISTRATOR">
                                        <label :for="'group-' + index" class="form-check-label fw-bold">
                                            <i class="bi bi-folder-fill me-1 text-primary"></i>
                                            {{ group.title }}
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div v-for="permission in group.permissions" :key="permission.id" class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" :value="permission.id" v-model="selected_permissions" :id="'permission-' + permission.id" :disabled="form.name === ROLE.SYSTEM_ADMINISTRATOR">
                                        <label class="form-check-label" :for="'permission-' + permission.id">
                                           <span class="badge fw-semibold transition" :class="selected_permissions.includes(permission.id)
                                                ? 'bg-success-subtle text-success'
                                                : 'bg-secondary-subtle text-secondary'">
                                                {{ permission.name }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="permissions.length === 0" class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <p class="text-muted mt-3">No permissions available</p>
                    </div>
                    </template>

                    <button type="submit" @click="handleSubmit" class="btn btn-primary btn-sm mt-3" :disabled="form.name === ROLE.SYSTEM_ADMINISTRATOR">
                        <i class="bi bi-floppy me-1"></i>Update
                    </button>
                </div>

                 
            </div>
        </div>
    </div>
</template>