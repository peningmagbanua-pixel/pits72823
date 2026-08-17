import { reactive } from "vue";

export default function useUserForm() {

    const form = reactive({
        id: null,
        lastname: '',
        firstname: '',
        middlename: '',
        extname: '',
        email: '',
        roles: [],
        admin: '',
        status: null,
    });

    const resetForm = () => {
        form.id = null;
        form.lastname = '';
        form.firstname = '';
        form.middlename = '';
        form.extname = '';
        form.email = '';
        form.roles = [];
        form.admin = '';
        form.status = null;
    };

    const fillForm = (user) => {
        form.id = user.id;
        form.lastname = user.lastname;
        form.firstname = user.firstname;
        form.middlename = user.middlename;
        form.extname = user.extname;
        form.email = user.email;
        form.admin = user.is_system_administrator ? 'Yes' : 'No';
        form.status = user.is_active;
        form.roles = user.is_system_administrator ? [] : (user.role_ids || []);
    };

    return { form, resetForm, fillForm };
}
