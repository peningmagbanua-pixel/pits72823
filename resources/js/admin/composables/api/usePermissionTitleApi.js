import axios from "axios";

export default {
    getPermissionTitles(params) {
        return axios.get('/api/permission-titles', { params });
    },

    createPermissionTitle(form) {
        return axios.post('/api/permission-titles', form);
    },

    updatePermissionTitle(id, form) {
        return axios.put(`/api/permission-titles/${id}`, form);
    },

    deletePermissionTitle(id) {
        return axios.delete(`/api/permission-titles/${id}`);
    },

    restorePermissionTitle(id) {
        return axios.post(`/api/permission-titles/${id}/restore`);
    },

    checkPermissions() {
        return axios.get('/api/check/permission-titles/permissions');
    },
}
