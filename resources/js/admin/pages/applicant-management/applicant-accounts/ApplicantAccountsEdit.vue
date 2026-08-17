<script setup>
import { Form } from 'vee-validate';
import Swal from 'sweetalert2';
import { sweetAlert } from '@/sweetalert.js';
import { processingAlert } from '../../../../sweetalert';
import { Modal } from 'bootstrap';
import { STATUS } from '../../../../constants';
import useApplicantAccountForm from '../../../composables/forms/useApplicantAccountForm';
import useApplicantAccountApi from '../../../composables/api/useApplicantAccountApi';

const emit = defineEmits(['applicant-updated']);

const hideModal = () => {
    const modalElement = document.getElementById('update-applicant-account');
    const modalInstance = Modal.getInstance(modalElement);
    modalInstance.hide();
};

const { form, fillForm } = useApplicantAccountForm();

defineExpose({ getApplicant: fillForm });

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
            useApplicantAccountApi.updateApplicantAccount(form.id, { status: form.status })
                .then((response) => {
                    Swal.close();
                    hideModal();
                    sweetAlert('success', response.data.message);
                    emit('applicant-updated');
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
    <div class="modal fade" id="update-applicant-account" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">Update Applicant Account</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                    <Form @submit="handleSubmit" v-slot:default="{ errors }">
                        <div class="form-group mt-3">
                            <label class="form-label">Name</label>
                            <input type="text" :value="form.name" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">Email</label>
                            <input type="text" :value="form.email" class="form-control form-control-sm" disabled>
                        </div>
                        <div class="form-group mt-3">
                            <label for="status" class="form-label required">Status</label>
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
