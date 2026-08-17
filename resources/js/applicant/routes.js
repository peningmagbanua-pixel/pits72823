import Dashboard from "./components/Dashboard.vue";

// Application
import Application from "./pages/application/Application.vue";
import ProgramSelection from "./pages/application/ProgramSelection.vue";
import ApplicationForm from "./pages/application/ApplicationForm.vue";
import ApplicationStatus from "./pages/application/ApplicationStatus.vue";

// FAQ
import Faq from "./pages/faq/Faq.vue";

// Profile
import Profile from "./pages/profile/Profile.vue";

export default [
    {
        path: '/applicant/dashboard',
        name: 'applicant.dashboard',
        component: Dashboard,
    },

    // Application
    {
        path: '/applicant/application',
        name: 'applicant.application',
        component: Application,
    },
    {
        path: '/applicant/application/select-program',
        name: 'applicant.application.select-program',
        component: ProgramSelection,
    },
    {
        path: '/applicant/application/form',
        name: 'applicant.application.form',
        component: ApplicationForm,
    },
    {
        path: '/applicant/application/status',
        name: 'applicant.application.status',
        component: ApplicationStatus,
    },

    // Profile
    {
        path: '/applicant/profile',
        name: 'applicant.profile',
        component: Profile,
    },

    // FAQ
    {
        path: '/applicant/faq',
        name: 'applicant.faq',
        component: Faq,
    }
]
