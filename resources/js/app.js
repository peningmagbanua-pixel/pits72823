import './bootstrap.js';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'bootstrap';

import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router';
import { createPinia } from 'pinia';
import { APP } from './constants.js';

import AdminApp from './admin/App.vue';
import AdminRoutes from './admin/routes.js';

import ApplicantApp from './applicant/App.vue';
import ApplicantRoutes from './applicant/routes.js';

// Admin App
if (document.getElementById(APP.ADMIN)) {
    const adminApp = createApp(AdminApp);
    const adminRouter = createRouter({
        history: createWebHistory(),
        routes: AdminRoutes,
    });
    adminApp.use(createPinia());
    adminApp.use(adminRouter);
    adminApp.mount('#' + APP.ADMIN);
}

// Applicant App
if (document.getElementById(APP.APPLICANT)) {
    const applicantApp = createApp(ApplicantApp);
    const applicantRouter = createRouter({
        history: createWebHistory(),
        routes: ApplicantRoutes,
    });
    applicantApp.use(createPinia());
    applicantApp.use(applicantRouter);
    applicantApp.mount('#' + APP.APPLICANT);
}