import Dashboard from "./components/Dashboard.vue";

// Admissions
import ApplicantAccountsList from "./pages/applicant-management/applicant-accounts/ApplicantAccountsList.vue";
import ApplicationsList from "./pages/applicant-management/applications/ApplicationsList.vue";
import ExamSchedulesList from "./pages/applicant-management/exam-scheduling/ExamSchedulesList.vue";

// Academic Management
import SchoolYearsList from "./pages/academic-management/school-years/SchoolYearsList.vue";
import ProgramsList from "./pages/academic-management/programs/ProgramsList.vue";

// Report & Monitorings
import ReportsList from "./pages/reports/ReportsList.vue";
import AuditLogsList from "./pages/audit-logs/AuditLogsList.vue";
import ActivityLogsList from "./pages/applicant-management/activity-logs/ActivityLogsList.vue";

// System Management
import UsersList from "./pages/system-management/users/UsersList.vue";
import RolesList from "./pages/system-management/roles/RolesList.vue";
import PermissionTitlesList from "./pages/system-management/permission-titles/PermissionTitlesList.vue";
import SettingsList from "./pages/system-management/settings/SettingsList.vue";

// About
import Overview from "./pages/about/overview/Overview.vue";
import Team from "./pages/about/team/Team.vue";
import TechStack from "./pages/about/tech-stack/TechStack.vue";

// Others
import Profile from "./pages/profile/Profile.vue";

export default [
    {
        path: "/admin/dashboard",
        name: "admin.dashboard",
        component: Dashboard,
    },

    // Admissions
    {
        path: "/admin/applicant-accounts",
        name: "admin.applicant-accounts",
        component: ApplicantAccountsList,
    },
    {
        path: "/admin/applications",
        name: "admin.applications",
        component: ApplicationsList,
    },
    {
        path: "/admin/exam-scheduling",
        name: "admin.exam-scheduling",
        component: ExamSchedulesList,
    },

    // Academic Management
    {
        path: "/admin/academic-setup/school-years",
        name: "admin.acadmic-setup.school-years",
        component: SchoolYearsList,
    },
    {
        path: "/admin/academic-setup/programs",
        name: "admin.acadmic-setup.programs",
        component: ProgramsList,
    },

    // Report & Monitorings
    {
        path: "/admin/reports",
        name: "admin.reports",
        component: ReportsList,
    },
    {
        path: "/admin/audit-logs",
        name: "admin.audit-logs",
        component: AuditLogsList,
    },
    {
        path: "/admin/activity-logs",
        name: "admin.activity-logs",
        component: ActivityLogsList,
    },

    // System Management
    {
        path: "/admin/system-management/users",
        name: "admin.system-management.users",
        component: UsersList,
    },
    {
        path: "/admin/system-management/roles",
        name: "admin.system-management.roles",
        component: RolesList,
    },
    {
        path: "/admin/system-management/permission-titles",
        name: "admin.system-management.permission-titles",
        component: PermissionTitlesList,
    },
    {
        path: "/admin/system-management/settings",
        name: "admin.system-management.settings",
        component: SettingsList,
    },

    // About
    {
        path: "/admin/about/overview",
        name: "admin.about.overview",
        component: Overview,
    },
    {
        path: "/admin/about/team",
        name: "admin.about.team",
        component: Team,
    },
    {
        path: "/admin/about/tech-stack",
        name: "admin.about.tech-stack",
        component: TechStack,
    },

    // Others
    {
        path: "/admin/profile",
        name: "admin.profile",
        component: Profile,
    },
];
