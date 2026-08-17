import axios from "axios";

export default {
    getRoles(params) {
        return axios.get('/api/roles', { params });
    },

    getPermissionMatrix() {
        return axios.get('/api/roles/permission-matrix');
    },

    createRole(form) {
        return axios.post('/api/roles', form);
    },

    updateRole(id, form) {
        return axios.put(`/api/roles/${id}`, form);
    },

    deleteRole(id) {
        return axios.delete(`/api/roles/${id}`);
    },

    getRolePermissions(id) {
        return axios.get(`/api/roles/${id}/permissions`);
    },

    updateRolePermissions(id, form) {
        return axios.put(`/api/roles/${id}/permissions`, form);
    },

    getRoleUsers(id) {
        return axios.get(`/api/roles/${id}/users`);
    },
}
