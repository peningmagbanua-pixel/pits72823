import { reactive } from "vue";

export default function useRoleForm() {

    const form = reactive({
        id: null,
        name: '',
    });

    const resetForm = () => {
        form.id = null;
        form.name = '';
    };

    const fillForm = (role) => {
        form.id = role.id;
        form.name = role.name;
    };

    return { form, resetForm, fillForm };
}
