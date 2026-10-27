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
import { useAuthUserStore } from './admin/stores/AuthUserStore';

import ApplicantApp from './applicant/App.vue';
import ApplicantRoutes from './applicant/routes.js';

import { startSessionTimeout, sessionActivity } from './sessionTimeout.js';

// ── Loading indicator helpers ──────────────────────────────────────────────
const pageLoader = () => document.getElementById('cnhs-page-loader');

function startLoader() {
    const el = pageLoader();
    if (!el) return;
    el.className = '';
    // force reflow so transition re-triggers
    void el.offsetWidth;
    el.classList.add('loading');
}

function finishLoader() {
    const el = pageLoader();
    if (!el) return;
    el.classList.remove('loading');
    el.classList.add('done');
    setTimeout(() => { el.className = ''; }, 650);
}

function hideBootOverlay() {
    const overlay = document.getElementById('cnhs-boot-overlay');
    if (!overlay) return;
    overlay.replaceChildren();
    overlay.classList.add('hidden');
    setTimeout(() => overlay.remove(), 400);
}

// ── Admin App ──────────────────────────────────────────────────────────────
if (document.getElementById(APP.ADMIN)) {
    const adminApp = createApp(AdminApp);
    const adminRouter = createRouter({
        history: createWebHistory(),
        routes: AdminRoutes,
    });

    adminRouter.beforeEach(async (to) => {
        startLoader();

        if (to.meta && to.meta.view) {
            const authUserStore = useAuthUserStore();

            if (!authUserStore.loaded) {
                await authUserStore.getAuthUser();
            }

            if (!authUserStore.loaded || !authUserStore.user.view?.[to.meta.view]) {
                return { name: 'admin.profile' };
            }
        }

        return true;
    });
    adminRouter.afterEach(() => {
        finishLoader();
        sessionActivity();
    });

    adminApp.use(createPinia());
    adminApp.use(adminRouter);
    adminApp.mount('#' + APP.ADMIN);
    hideBootOverlay();

    startSessionTimeout({
        logoutUrl: '/admin/logout',
        loginPath: '/admin/login',
        statusUrl: '/api/session/status',
        activityUrl: '/api/session/activity',
    });
}

// ── Applicant App ──────────────────────────────────────────────────────────
if (document.getElementById(APP.APPLICANT)) {
    const applicantApp = createApp(ApplicantApp);
    const applicantRouter = createRouter({
        history: createWebHistory(),
        routes: ApplicantRoutes,
    });

    applicantRouter.beforeEach(() => startLoader());
    applicantRouter.afterEach(() => {
        finishLoader();
        sessionActivity();
    });

    applicantApp.use(createPinia());
    applicantApp.use(applicantRouter);
    applicantApp.mount('#' + APP.APPLICANT);
    hideBootOverlay();

    startSessionTimeout({
        logoutUrl: '/applicant/logout',
        loginPath: '/applicant/login',
        statusUrl: '/api/applicant/session/status',
        activityUrl: '/api/applicant/session/activity',
    });
}