<script setup>
import { Form } from 'vee-validate';
import Swal from 'sweetalert2';
import { sweetAlert } from '@/sweetalert.js';
import { processingAlert } from '../../../../sweetalert';
import { Modal } from 'bootstrap';
import usePermissionTitleForm from '../../../composables/forms/usePermissionTitleForm';
import usePermissionTitleApi from '../../../composables/api/usePermissionTitleApi';

const emit = defineEmits(['permission-title-added']);

const hideModal = () => {
    const modalElement = document.getElementById('new-permission-title');
    const modalInstance = Modal.getInstance(modalElement);
    modalInstance.hide();
};

const { form, resetForm } = usePermissionTitleForm();

const handleSubmit = (values, actions) => {
    Swal.fire({
        title: "Are you sure?",
        text: "This will create permissions for browse, read, create, update, and delete actions!",
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
            usePermissionTitleApi.createPermissionTitle(form)
                .then((response) => {
                    Swal.close();
                    hideModal();
                    sweetAlert('success', response.data.message);
                    emit('permission-title-added');
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
    <div class="modal fade" id="new-permission-title" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">New Permission Title</h6>
                    <button class="btn-close" title="Close" data-bs-dismiss="modal"></button>
                    <Form @submit="handleSubmit" v-slot:default="{ errors }">
                        <div class="form-group">
                            <label for="name" class="form-label required">Title Name</label>
                            <input type="text" v-model="form.name" :class="{ 'is-invalid': errors.name }"
                                class="form-control form-control-sm" placeholder="Enter title name (e.g., Users, Dashboard)">
                            <span class="invalid-feedback">{{ errors.name }}</span>
                        </div>
                        <hr>
                        <button type="submit" class="btn btn-primary btn-sm" title="Add permission title">
                            <i class="bi bi-floppy me-1"></i>Submit
                        </button>
                    </Form>
                </div>
            </div>
        </div>
    </div>
</template>
