<script setup>
import { Form } from 'vee-validate';
import Swal from 'sweetalert2';
import { sweetAlert } from '@/sweetalert.js';
import { processingAlert } from '../../../../sweetalert';
import { Modal } from 'bootstrap';
import useSchoolYearForm from '../../../composables/forms/useSchoolYearForm';
import useSchoolYearApi from '../../../composables/api/useSchoolYearApi';

const emit = defineEmits(['school-year-added']);

const hideModal = () => {
    const modalElement = document.getElementById('new-school-year');
    const modalInstance = Modal.getInstance(modalElement);
    modalInstance.hide();
};

const { form, resetForm } = useSchoolYearForm();

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
            useSchoolYearApi.createSchoolYear(form)
                .then((response) => {
                    Swal.close();
                    hideModal();
                    sweetAlert('success', response.data.message);
                    emit('school-year-added');
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
    <div class="modal fade" id="new-school-year" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">New School Year</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                    <Form @submit="handleSubmit" v-slot:default="{ errors }">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="school_year_start" class="form-label required">School Year Start</label>
                                    <input type="number" v-model="form.school_year_start" id="school_year_start"
                                        :class="{ 'is-invalid': errors.school_year_start }"
                                        class="form-control form-control-sm" placeholder="Start Year (YYYY)">
                                    <span class="invalid-feedback">{{ errors.school_year_start }}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="school_year_end" class="form-label required">School Year End</label>
                                    <input type="number" v-model="form.school_year_end" id="school_year_end"
                                        :class="{ 'is-invalid': errors.school_year_end }"
                                        class="form-control form-control-sm" placeholder="End Year (YYYY)">
                                    <span class="invalid-feedback">{{ errors.school_year_end }}</span>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">
                        <h6 class="text-secondary fw-bold mb-3">Dashboard Dates</h6>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="application_start" class="form-label">Application Start</label>
                                    <input type="date" v-model="form.application_start" id="application_start"
                                        class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="application_deadline" class="form-label">Application Deadline</label>
                                    <input type="date" v-model="form.application_deadline" id="application_deadline"
                                        class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="exam_date" class="form-label">Exam Date</label>
                                    <input type="date" v-model="form.exam_date" id="exam_date"
                                        class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <label for="guidelines" class="form-label">Qualification Guidelines</label>
                            <textarea v-model="form.guidelines" id="guidelines"
                                class="form-control form-control-sm" rows="4"
                                placeholder="Enter qualification guidelines..."></textarea>
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
