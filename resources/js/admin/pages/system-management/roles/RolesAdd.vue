<script setup>
import { Form } from 'vee-validate';
import Swal from 'sweetalert2';
import { sweetAlert } from '@/sweetalert.js';
import { processingAlert } from '../../../../sweetalert';
import { Modal } from 'bootstrap';
import useRoleForm from '../../../composables/forms/useRoleForm';
import useRoleApi from '../../../composables/api/useRoleApi';

const emit = defineEmits(['role-added']);

const hideModal = () => {
    const modalElement = document.getElementById('new-role');
    const modalInstance = Modal.getInstance(modalElement);
    modalInstance.hide();
};

const { form, resetForm } = useRoleForm();

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
            useRoleApi.createRole(form)
                .then((response) => {
                    Swal.close();
                    hideModal();
                    sweetAlert('success', response.data.message);
                    emit('role-added');
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
</script>

<template>
    <div class="modal fade" id="new-role" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">New Role</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                    <Form @submit="handleSubmit" v-slot:default="{ errors }">
                        <div class="form-group">
                            <label for="name" class="form-label required">Role Name</label>
                            <input type="text" v-model="form.name" :class="{ 'is-invalid': errors.name }"
                                class="form-control form-control-sm" placeholder="Enter role name">
                            <span class="invalid-feedback">{{ errors.name }}</span>
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
