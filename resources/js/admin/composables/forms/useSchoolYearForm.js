import { reactive } from "vue";

export default function useSchoolYearForm() {

    const form = reactive({
        id: null,
        school_year_start: '',
        school_year_end: '',
        status: null,
        application_start: '',
        application_deadline: '',
        exam_date: '',
        guidelines: '',
    });

    const resetForm = () => {
        form.id = null;
        form.school_year_start = '';
        form.school_year_end = '';
        form.status = null;
        form.application_start = '';
        form.application_deadline = '';
        form.exam_date = '';
        form.guidelines = '';
    };

    const fillForm = (school_year) => {
        form.id = school_year.id;
        form.school_year_start = school_year.school_year_start;
        form.school_year_end = school_year.school_year_end;
        form.status = school_year.is_active;
        form.application_start = school_year.application_start || '';
        form.application_deadline = school_year.application_deadline || '';
        form.exam_date = school_year.exam_date || '';
        form.guidelines = school_year.guidelines || '';
    };

    return { form, resetForm, fillForm };
}
