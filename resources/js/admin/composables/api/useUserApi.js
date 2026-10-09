import axios from "axios";

export default {
    getUsers(params) {
        return axios.get('/api/users', { params });
    },

    createUser(form) {
        return axios.post('/api/users', form);
    },

    updateUser(id, form) {
        return axios.put(`/api/users/${id}`, form);
    },

    deleteUser(id) {
        return axios.delete(`/api/users/${id}`);
    },

    restoreUser(id) {
        return axios.post(`/api/users/${id}/restore`);
    },

    getRoles() {
        return axios.get('/api/list/roles');
    },

    getAllRoles() {
        return axios.get('/api/all/roles');
    },

    checkPermissions() {
        return axios.get('/api/check/users/permissions');
    },
}
