<script setup>
import { onMounted, reactive, ref } from 'vue';
import { Form } from 'vee-validate';
import Swal from 'sweetalert2';
import { sweetAlert } from '@/sweetalert.js';
import { processingAlert } from '../../../sweetalert';

const user = reactive({
    id: '',
    lastname: '',
    firstname: '',
    middlename: '',
    extname: '',
    email: '',
    avatar: '',
    roles: [],
    created_at: ''
});

const passwordForm = reactive({
    current_password: '',
    new_password: '',
    new_password_confirmation: ''
});

const getProfile = () => {
    axios.get('/api/profile/details')
        .then((response) => {
            user.id = response.data.id;
            user.lastname = response.data.lastname;
            user.firstname = response.data.firstname;
            user.middlename = response.data.middlename;
            user.extname = response.data.extname;
            user.email = response.data.email;
            user.avatar = response.data.avatar;
            user.roles = response.data.roles;
            user.created_at = response.data.created_at;
        });
};

const handleUpdateProfile = (values, actions) => {
    Swal.fire({
        title: "Are you sure?",
        text: "You are about to update your profile!",
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
                axios.put('/api/profile/update', user)
                    .then((response) => {
                        Swal.close();
                        sweetAlert('success', response.data.message);
                        getProfile();
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

const handleChangePassword = (values, actions) => {
    Swal.fire({
        title: "Are you sure?",
        text: "You are about to change your password!",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, change it!",
        allowOutsideClick: false,
        allowEscapeKey: false,
    })
        .then((result) => {
            if (result.isConfirmed) {
                processingAlert('.btn');
                axios.put('/api/profile/change-password', passwordForm)
                    .then((response) => {
                        Swal.close();
                        sweetAlert('success', response.data.message);
                        passwordForm.current_password = '';
                        passwordForm.new_password = '';
                        passwordForm.new_password_confirmation = '';
                        actions.resetForm();
                        getProfile();
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

onMounted(() => {
    getProfile();
});
</script>
<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Profile</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item fw-medium">
                            <a href="/admin/dashboard">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active fw-medium" aria-current="page">Profile</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-body text-center">
                            <img :src="user.avatar || '/images/default.jpeg'" 
                                 alt="Avatar" 
                                 class="rounded-circle mb-3" 
                                 style="width: 150px; height: 150px; object-fit: cover;">
                            <h5 class="mb-1">{{ user.firstname }} {{ user.lastname }} {{ user.extname }}</h5>
                            <p class="text-muted small mb-2">{{ user.email }}</p>
                            <div class="mb-3">
                                <span v-for="(role, index) in user.roles" :key="index" 
                                      class="badge bg-primary-subtle text-primary me-1">
                                    {{ role }}
                                </span>
                            </div>
                            <small class="text-muted">Member since {{ user.created_at }}</small>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-header bg-body">
                            <h6 class="mb-0"><i class="bi bi-person-fill me-2"></i>Update Profile</h6>
                        </div>
                        <div class="card-body">
                            <Form @submit="handleUpdateProfile" v-slot:default="{ errors }">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="lastname" class="form-label required">Last Name</label>
                                            <input type="text" v-model="user.lastname" 
                                                   :class="{ 'is-invalid': errors.lastname }"
                                                   class="form-control form-control-sm" 
                                                   placeholder="Enter lastname">
                                            <span class="invalid-feedback">{{ errors.lastname }}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="firstname" class="form-label required">First Name</label>
                                            <input type="text" v-model="user.firstname" 
                                                   :class="{ 'is-invalid': errors.firstname }"
                                                   class="form-control form-control-sm" 
                                                   placeholder="Enter firstname">
                                            <span class="invalid-feedback">{{ errors.firstname }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="middlename" class="form-label">Middle Name</label>
                                            <input type="text" v-model="user.middlename" 
                                                   :class="{ 'is-invalid': errors.middlename }"
                                                   class="form-control form-control-sm" 
                                                   placeholder="Enter middlename (optional)">
                                            <span class="invalid-feedback">{{ errors.middlename }}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-3">
                                            <label for="extname" class="form-label">Extension Name</label>
                                            <input type="text" v-model="user.extname" 
                                                   :class="{ 'is-invalid': errors.extname }"
                                                   class="form-control form-control-sm" 
                                                   placeholder="Enter extension name (optional)">
                                            <span class="invalid-feedback">{{ errors.extname }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mb-3">
                                    <label for="email" class="form-label required">Email</label>
                                    <input type="email" v-model="user.email" 
                                           :class="{ 'is-invalid': errors.email }"
                                           class="form-control form-control-sm" 
                                           placeholder="Enter email">
                                    <span class="invalid-feedback">{{ errors.email }}</span>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-floppy me-1"></i>Update
                                </button>
                            </Form>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-body">
                            <h6 class="mb-0"><i class="bi bi-lock-fill me-2"></i>Change Password</h6>
                        </div>
                        <div class="card-body">
                            <Form @submit="handleChangePassword" v-slot:default="{ errors }">
                                <div class="form-group mb-3">
                                    <label for="current_password" class="form-label required">Current Password</label>
                                    <input type="password" v-model="passwordForm.current_password" 
                                           :class="{ 'is-invalid': errors.current_password }"
                                           class="form-control form-control-sm" 
                                           placeholder="Enter current password">
                                    <span class="invalid-feedback">{{ errors.current_password }}</span>
                                </div>
                                <div class="form-group mb-3">
                                    <label for="new_password" class="form-label required">New Password</label>
                                    <input type="password" v-model="passwordForm.new_password" 
                                           :class="{ 'is-invalid': errors.new_password }"
                                           class="form-control form-control-sm" 
                                           placeholder="Enter new password (min. 8 characters)">
                                    <span class="invalid-feedback">{{ errors.new_password }}</span>
                                </div>
                                <div class="form-group mb-3">
                                    <label for="new_password_confirmation" class="form-label required">Confirm New Password</label>
                                    <input type="password" v-model="passwordForm.new_password_confirmation" 
                                           :class="{ 'is-invalid': errors.new_password_confirmation }"
                                           class="form-control form-control-sm" 
                                           placeholder="Confirm new password">
                                    <span class="invalid-feedback">{{ errors.new_password_confirmation }}</span>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-key-fill me-1"></i>Change
                                </button>
                            </Form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
