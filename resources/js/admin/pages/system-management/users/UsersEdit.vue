<script setup>
import { ref } from 'vue';
import { Form } from 'vee-validate';
import Swal from 'sweetalert2';
import { sweetAlert } from '@/sweetalert.js';
import { processingAlert } from '../../../../sweetalert';
import { Modal } from 'bootstrap';
import { ADMIN, STATUS, PROGRAM_ACCESS } from '../../../../constants';
import { useAuthUserStore } from '../../../stores/AuthUserStore';
import useUserForm from '../../../composables/forms/useUserForm';
import useUserApi from '../../../composables/api/useUserApi';

const authUserStore = useAuthUserStore();
const emit = defineEmits(['user-updated']);
const roles = ref([]);
const show = ref(false);

const hideModal = () => {
    const modalElement = document.getElementById('update-user');
    const modalInstance = Modal.getInstance(modalElement);
    modalInstance.hide();
};

const { form, fillForm } = useUserForm();

const getUser = (user) => {
    if (!roles.value.length) getRoles();
    fillForm(user);
    isSystemAdministrator();
};

defineExpose({ getUser });

const handleSubmit = (values, actions) => {
    Swal.fire({
        title: "Are you sure?",
        text: "You are about to update this record!",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, update it!",
        allowOutsideClick: false,
        allowEscapeKey: false,
    }).then((result) => {
        if (result.isConfirmed) {
            processingAlert('.btn');
            useUserApi.updateUser(form.id, form)
                .then((response) => {
                    Swal.close();
                    hideModal();
                    sweetAlert('success', response.data.message);
                    emit('user-updated');
                }).catch((error) => {
                    Swal.close();
                    if (error.response.data.errors) {
                        actions.setErrors(error.response.data.errors);
                    }
                    sweetAlert('error', error.response.data.message);
                });
        }
    });
};

const getRoles = () => {
    useUserApi.getRoles().then((response) => {
        roles.value = response.data;
    });
};

const isSystemAdministrator = () => {
    show.value = form.admin === ADMIN.YES ? false : true;
    if (form.admin === ADMIN.YES) {
        form.roles = [];
        form.program_access = [];
    }
};
</script>

<template>
    <div class="modal fade" id="update-user" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">Update User</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                    <Form @submit="handleSubmit" v-slot:default="{ errors }">
                        <div class="row">
                            <div class="col-md-6 form-group mt-3">
                                <label class="form-label required">Last Name</label>
                                <input type="text" v-model="form.lastname" :class="{ 'is-invalid': errors.lastname }"
                                    class="form-control form-control-sm" placeholder="Enter lastname">
                                <span class="invalid-feedback">{{ errors.lastname }}</span>
                            </div>
                            <div class="col-md-6 form-group mt-3">
                                <label class="form-label required">First Name</label>
                                <input type="text" v-model="form.firstname" :class="{ 'is-invalid': errors.firstname }"
                                    class="form-control form-control-sm" placeholder="Enter firstname">
                                <span class="invalid-feedback">{{ errors.firstname }}</span>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group mt-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" v-model="form.middlename"
                                    :class="{ 'is-invalid': errors.middlename }" class="form-control form-control-sm"
                                    placeholder="Enter middlename (optional)">
                                <span class="invalid-feedback">{{ errors.middlename }}</span>
                            </div>
                            <div class="col-md-6 form-group mt-3">
                                <label class="form-label">Extension Name</label>
                                <input type="text" v-model="form.extname" :class="{ 'is-invalid': errors.extname }"
                                    class="form-control form-control-sm" placeholder="Enter extension name (optional)">
                                <span class="invalid-feedback">{{ errors.extname }}</span>
                            </div>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label required">Email</label>
                            <input type="text" v-model="form.email" :class="{ 'is-invalid': errors.email }"
                                class="form-control form-control-sm" placeholder="Enter email">
                            <span class="invalid-feedback">{{ errors.email }}</span>
                        </div>
                        <div v-if="authUserStore.user.view.system_administrator" class="form-group mt-3">
                            <label class="form-label required">System Administrator?</label>
                            <select class="form-select form-select-sm" @change="isSystemAdministrator()"
                                v-model="form.admin">
                                <option :value="ADMIN.YES">Yes</option>
                                <option :value="ADMIN.NO">No</option>
                            </select>
                        </div>
                        <div v-if="show || !authUserStore.user.view.system_administrator" class="form-group mt-3">
                            <label class="form-label required">Role</label>
                            <div v-for="role in roles" :key="role.id" class="form-check">
                                <input class="form-check-input" type="checkbox" :value="role.id" v-model="form.roles">
                                <label class="form-check-label">{{ role.name }}</label>
                            </div>
                            <span class="invalid-feedback d-block">{{ errors.role }}</span>
                        </div>
                        <div v-if="show || !authUserStore.user.view.system_administrator" class="form-group mt-3">
                            <label class="form-label">Program Assignment</label>
                            <div v-for="program in PROGRAM_ACCESS" :key="program.value" class="form-check">
                                <input class="form-check-input" type="checkbox" :value="program.value" v-model="form.program_access">
                                <label class="form-check-label">{{ program.label }}</label>
                            </div>
                            <small class="text-muted d-block">Assigned staff can only manage data for these
                                programs.</small>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label required">Status</label>
                            <div class="d-flex align-items-center gap-2"
                                :class="form.status == STATUS.ACTIVE ? 'text-success' : 'text-danger'">
                                <input class="form-check-input mt-0" type="checkbox" id="statusSwitch"
                                    :true-value="STATUS.ACTIVE" :false-value="STATUS.INACTIVE" v-model="form.status">
                                <label class="form-check-label fw-semibold mb-0" for="statusSwitch">
                                    {{ form.status == STATUS.ACTIVE ? 'Active' : 'Inactive' }}
                                </label>
                            </div>
                        </div>
                        <hr>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-floppy me-1"></i>Update
                        </button>
                    </Form>
                </div>
            </div>
        </div>
    </div>
</template>
