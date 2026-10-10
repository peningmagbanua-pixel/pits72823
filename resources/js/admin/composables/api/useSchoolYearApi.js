import axios from "axios";

export default {
    getSchoolYears(params) {
        return axios.get('/api/school-years', { params });
    },

    createSchoolYear(form) {
        return axios.post('/api/school-years', form);
    },

    updateSchoolYear(id, form) {
        return axios.put(`/api/school-years/${id}`, form);
    },

    deleteSchoolYear(id) {
        return axios.delete(`/api/school-years/${id}`);
    },

    restoreSchoolYear(id) {
        return axios.post(`/api/school-years/${id}/restore`);
    },

    checkPermissions() {
        return axios.get('/api/check/school-years/permissions');
    }
}
