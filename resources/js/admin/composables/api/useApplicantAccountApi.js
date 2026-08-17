import axios from "axios";

export default {
    getApplicantAccounts(params) {
        return axios.get('/api/applicant-accounts', { params });
    },

    updateApplicantAccount(id, form) {
        return axios.put(`/api/applicant-accounts/${id}`, form);
    },

    deleteApplicantAccount(id) {
        return axios.delete(`/api/applicant-accounts/${id}`);
    },
}
