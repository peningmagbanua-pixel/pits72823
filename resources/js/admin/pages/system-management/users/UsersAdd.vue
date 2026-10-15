<script setup>
import { ref, computed, onMounted } from 'vue';
import { Form } from 'vee-validate';
import Swal from 'sweetalert2';
import { sweetAlert } from '@/sweetalert.js';
import { processingAlert } from '../../../../sweetalert';
import { Modal } from 'bootstrap';
import { ADMIN, PROGRAM_ACCESS } from '../../../../constants';
import { useAuthUserStore } from '../../../stores/AuthUserStore';
import useUserForm from '../../../composables/forms/useUserForm';
import useUserApi from '../../../composables/api/useUserApi';

const authUserStore = useAuthUserStore();
const emit = defineEmits(['user-added']);
const roles = ref([]);
const show = ref(false);

// Coordinators may only assign programs they themselves manage.
// System Administrators are assigned every program in the profile payload.
const assignablePrograms = computed(() =>
    PROGRAM_ACCESS.filter((program) => (authUserStore.user.programs || []).includes(program.value))
);

const hideModal = () => {
    const modalElement = document.getElementById('new-user');
    const modalInstance = Modal.getInstance(modalElement);
    modalInstance.hide();
};

const { form, resetForm } = useUserForm();

const handleSubmit = (values, actions) => {
    Swal.fire({
        title: "Are you sure?",
        text: "You are about to submit this record!",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, submit it!",
        allowOutsideClick: false,
        allowEscapeKey: false,
    }).then((result) => {
        if (result.isConfirmed) {
            processingAlert('.btn');
            useUserApi.createUser(form)
                .then((response) => {
                    Swal.close();
                    hideModal();
                    sweetAlert('success', response.data.message);
                    emit('user-added');
                    resetForm();
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

const load = () => {
    if (!roles.value.length) getRoles();
};

const open = () => {
    const modal = document.getElementById('new-user');
    modal.addEventListener('shown.bs.modal', () => load());
};

onMounted(() => {
    open();
});
</script>

<template>
    <div class="modal fade" id="new-user" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">New User</h6>
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
                                <input type="text" v-model="form.middlename" :class="{ 'is-invalid': errors.middlename }"
                                    class="form-control form-control-sm" placeholder="Enter middlename (optional)">
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
                            <label class="form-label required">System Administrator ?</label>
                            <select class="form-select form-select-sm" @change="isSystemAdministrator()" v-model="form.admin">
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
                            <div v-for="program in assignablePrograms" :key="program.value" class="form-check">
                                <input class="form-check-input" type="checkbox" :value="program.value" v-model="form.program_access">
                                <label class="form-check-label">{{ program.label }}</label>
                            </div>
                            <small class="text-muted d-block">Assigned staff can only manage data for these
                                programs.</small>
                        </div>
                        <hr>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-floppy me-1"></i>Submit
                        </button>
                    </Form>
                </div>
            </div>
        </div>
    </div>
</template>
