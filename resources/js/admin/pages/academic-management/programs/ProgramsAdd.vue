<script setup>
import { Form } from "vee-validate";
import Swal from "sweetalert2";
import { sweetAlert } from "@/sweetalert.js";
import { processingAlert } from "../../../../sweetalert";
import { Modal } from "bootstrap";
import useProgramForm from "../../../composables/forms/useProgramForm";
import useProgramApi from "../../../composables/api/useProgramApi";

const emit = defineEmits(["program-added"]);

const hideModal = () => {
    const modalElement = document.getElementById("new-program");
    const modalInstance = Modal.getInstance(modalElement);
    modalInstance.hide();
};

const { form, resetForm } = useProgramForm();

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
            useProgramApi.createProgram(form)
                .then((response) => {
                    Swal.close();
                    hideModal();
                    sweetAlert("success", response.data.message);
                    emit("program-added");
                    resetForm();
                }).catch((error) => {
                    Swal.close();
                    if (error.response.data.errors) {
                        actions.setErrors(error.response.data.errors);
                    }
                    sweetAlert("error", error.response.data.message);
                });
        }
    });
};
</script>
<template>
    <div class="modal fade" id="new-program" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content border-0 p-3">
                <div class="modal-body">
                    <h6 class="text-center">New Program</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                    <Form @submit="handleSubmit" v-slot:default="{ errors }">
                        <div class="form-group">
                            <label for="initials" class="form-label required">Initials</label>
                            <input type="text" v-model="form.initials" id="initials"
                                :class="{ 'is-invalid': errors.initials }" class="form-control form-control-sm"
                                placeholder="e.g., SPICT, STE" >
                            <span class="invalid-feedback">{{ errors.initials }}</span>
                        </div>

                        <div class="form-group mt-3">
                            <label for="name" class="form-label required">Name</label>
                            <input type="text" v-model="form.name" id="name" :class="{ 'is-invalid': errors.name }"
                                class="form-control form-control-sm" placeholder="e.g., Special Program in ICT">
                            <span class="invalid-feedback">{{ errors.name }}</span>
                        </div>

                        <div class="form-group mt-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea v-model="form.description" id="description" class="form-control form-control-sm"
                                placeholder="Optional: Brief description of the program"></textarea>
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
