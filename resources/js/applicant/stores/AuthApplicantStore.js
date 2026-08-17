import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useAuthApplicantStore = defineStore('AuthApplicantStore', () => {
    const applicant = ref({
        name: '',
        email: '',
        profile: '',
    });

    const getAuthApplicant = async () => {
        await axios.get('/api/applicant/profile')
            .then((response) => {
                applicant.value = response.data;
            })
            .catch(() => {
                // Silently handle - user may need to re-login
            });
    };

    return { applicant, getAuthApplicant };
});
