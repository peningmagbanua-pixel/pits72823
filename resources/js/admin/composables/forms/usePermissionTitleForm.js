import { reactive } from "vue";

export default function usePermissionTitleForm() {

    const form = reactive({
        id: null,
        name: '',
    });

    const resetForm = () => {
        form.id = null;
        form.name = '';
    };

    const fillForm = (permissionTitle) => {
        form.id = permissionTitle.id;
        form.name = permissionTitle.name;
    };

    return { form, resetForm, fillForm };
}
