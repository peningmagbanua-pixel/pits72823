<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { processingAlert } from '../../../sweetalert';

const profile = ref({
    lastname: '',
    firstname: '',
    middlename: '',
    extname: '',
    email: '',
});

const passwordForm = ref({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
});

const loading = ref(true);

const fetchProfile = async () => {
    try {
        const response = await axios.get('/api/applicant/profile/details');
        profile.value = response.data;
    } catch (error) {
        console.error('Error fetching profile:', error);
    } finally {
        loading.value = false;
    }
};

const updateProfile = async () => {
    processingAlert('.btn');
    try {
        await axios.put('/api/applicant/profile/update', profile.value);
        Swal.fire('Success', 'Profile updated successfully!', 'success');
    } catch (error) {
        Swal.fire('Error', error.response?.data?.message || 'Failed to update profile', 'error');
    }
};

const changePassword = async () => {
    processingAlert('.btn');
    try {
        await axios.put('/api/applicant/profile/change-password', passwordForm.value);
        Swal.fire('Success', 'Password changed successfully!', 'success');
        passwordForm.value = {
            current_password: '',
            new_password: '',
            new_password_confirmation: '',
        };
    } catch (error) {
        Swal.fire('Error', error.response?.data?.message || 'Failed to change password', 'error');
    }
};

onMounted(() => {
    fetchProfile();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Profile Settings</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="/applicant/dashboard">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Profile</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <div v-else class="row">
                <!-- Personal Information -->
                <div class="col-lg-7 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold text-secondary">Personal Information</h6>
                        </div>
                        <div class="card-body">
                            <form @submit.prevent="updateProfile">
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">First Name</label>
                                        <input v-model="profile.firstname" type="text" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Last Name</label>
                                        <input v-model="profile.lastname" type="text" class="form-control" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Middle Name</label>
                                        <input v-model="profile.middlename" type="text" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label small fw-bold">Extension Name</label>
                                        <input v-model="profile.extname" type="text" class="form-control" placeholder="e.g. Jr, III">
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold">Email Address</label>
                                    <input v-model="profile.email" type="email" class="form-control" required>
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary px-4">
                                        Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="col-lg-5 mb-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold text-secondary">Security & Password</h6>
                        </div>
                        <div class="card-body">
                            <form @submit.prevent="changePassword">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Current Password</label>
                                    <input v-model="passwordForm.current_password" type="password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">New Password</label>
                                    <input v-model="passwordForm.new_password" type="password" class="form-control" required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold">Confirm New Password</label>
                                    <input v-model="passwordForm.new_password_confirmation" type="password" class="form-control" required>
                                </div>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-outline-danger px-4">
                                        Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
