import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useAuthUserStore = defineStore('AuthUserStore', () => {
    const user = ref({
        name: '',
        email: '',
        role: '',
        avatar: '',
        view: '',
        must_change_password: false
    });

    const loaded = ref(false);
    let inFlight = null;

    const getAuthUser = async () => {
        if (loaded.value) return;
        if (inFlight) return inFlight;

        inFlight = axios.get('/api/profile')
            .then((response) => {
                user.value = response.data;
                loaded.value = true;
            })
            .catch((error) => {
                console.error('Unable to load staff permissions.', error);
            })
            .finally(() => {
                inFlight = null;
            });

        return inFlight;
    };

    return { user, loaded, getAuthUser };
});
