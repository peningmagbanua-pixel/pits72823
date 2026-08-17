import { reactive } from "vue";

export default function useProgramForm() {

    const form = reactive({
        id: null,
        initials: "",
        name: "",
        description: null,
        status: null,
    });

    const resetForm = () => {
        form.initials = "";
        form.name = "";
        form.description = "";
    };

    const fillForm = (program) => {
        form.id = program.id;
        form.initials = program.initials;
        form.name = program.name;
        form.description = program.description;
        form.status = program.is_active;
    };

    return { form, resetForm, fillForm };
}