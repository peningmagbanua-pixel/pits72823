import axios from "axios";

export default {
    getPrograms(params) {
        return axios.get('/api/programs', { params });
    },

    createProgram(form) {
        return axios.post('/api/programs', form)
    },

    updateProgram(id, form) {
        return axios.put(`/api/programs/${id}`, form);
    },

    deleteProgram(id) {
        return axios.delete(`/api/programs/${id}`);
    },

    restoreProgram(id) {
        return axios.post(`/api/programs/${id}/restore`);
    },

    checkPermissions() {
        return axios.get('/api/check/programs/permissions');
    }
}