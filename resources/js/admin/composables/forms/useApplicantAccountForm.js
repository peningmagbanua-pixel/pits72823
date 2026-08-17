import { reactive } from "vue";

export default function useApplicantAccountForm() {

    const form = reactive({
        id: null,
        name: '',
        email: '',
        status: null,
    });

    const resetForm = () => {
        form.id = null;
        form.name = '';
        form.email = '';
        form.status = null;
    };

    const fillForm = (applicant) => {
        form.id = applicant.id;
        form.name = applicant.name;
        form.email = applicant.email;
        form.status = applicant.is_active;
    };

    return { form, resetForm, fillForm };
}
