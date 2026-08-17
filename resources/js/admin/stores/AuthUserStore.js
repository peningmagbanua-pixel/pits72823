import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useAuthUserStore = defineStore('AuthUserStore', () => {
    const user = ref({
        name: '',
        email: '',
        role: '',
        avatar: '',
        view: ''
    });

    const getAuthUser = async () => {
        await axios.get('/api/profile')
            .then((response) => {
                user.value = response.data;
            });
    };

    return { user, getAuthUser };
});