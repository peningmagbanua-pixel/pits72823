<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { sweetAlert, logoutAlert } from '../../sweetalert';
import { useAuthUserStore } from '../stores/AuthUserStore';

const router = useRouter();
const authUserStore = useAuthUserStore();

const submitting = ref(false);

const form = reactive({
    current_password: '',
    new_password: '',
    new_password_confirmation: ''
});

const errors = reactive({
    current_password: '',
    new_password: '',
    new_password_confirmation: ''
});

const clearErrors = () => {
    errors.current_password = '';
    errors.new_password = '';
    errors.new_password_confirmation = '';
};

const submit = () => {
    if (submitting.value) return;

    clearErrors();
    submitting.value = true;

    axios.put('/api/profile/change-password', {
        current_password: form.current_password,
        new_password: form.new_password,
        new_password_confirmation: form.new_password_confirmation
    })
        .then((response) => {
            authUserStore.user.must_change_password = false;
            sweetAlert('success', response.data.message);
        })
        .catch((error) => {
            const data = error.response ? error.response.data : null;
            if (data && data.errors) {
                Object.keys(data.errors).forEach((key) => {
                    if (key in errors) {
                        errors[key] = Array.isArray(data.errors[key])
                            ? data.errors[key][0]
                            : data.errors[key];
                    }
                });
            } else if (data && data.message) {
                errors.current_password = data.message;
            } else {
                errors.current_password = 'Something went wrong. Please try again.';
            }
        })
        .finally(() => {
            submitting.value = false;
        });
};

const logout = () => {
    logoutAlert();
    axios.post('/admin/logout').finally(() => {
        router.push('/admin/login');
        location.reload();
    });
};
</script>

<template>
    <div class="modal fade show" style="display: block;" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock-fill fs-1 text-primary"></i>
                        <h5 class="mt-2 mb-1">Change Your Password</h5>
                        <p class="text-muted mb-0 small">
                            You are required to change your password before you can continue.
                        </p>
                    </div>

                    <form @submit.prevent="submit">
                        <div class="form-group mb-3">
                            <label class="form-label required">Current Password</label>
                            <input type="password" v-model="form.current_password"
                                :class="{ 'is-invalid': errors.current_password }"
                                class="form-control form-control-sm"
                                autocomplete="current-password" autofocus>
                            <span class="invalid-feedback">{{ errors.current_password }}</span>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label required">New Password</label>
                            <input type="password" v-model="form.new_password"
                                :class="{ 'is-invalid': errors.new_password }"
                                class="form-control form-control-sm"
                                autocomplete="new-password">
                            <span class="invalid-feedback">{{ errors.new_password }}</span>
                        </div>

                        <div class="form-group mb-3">
                            <label class="form-label required">Confirm New Password</label>
                            <input type="password" v-model="form.new_password_confirmation"
                                :class="{ 'is-invalid': errors.new_password_confirmation }"
                                class="form-control form-control-sm"
                                autocomplete="new-password">
                            <span class="invalid-feedback">{{ errors.new_password_confirmation }}</span>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-sm" :disabled="submitting">
                                <span v-if="submitting" class="spinner-border spinner-border-sm me-1"
                                    role="status"></span>
                                <i v-else class="bi bi-check-lg me-1"></i>
                                Change Password
                            </button>
                            <button type="button" class="btn btn-link btn-sm text-muted" @click="logout">
                                Log out instead
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
</template>
