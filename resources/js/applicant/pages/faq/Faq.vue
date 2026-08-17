<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";

const faqs = ref([]);
const loading = ref(true);
const activeIndex = ref(null);

const toggleFaq = (index) => {
    activeIndex.value = activeIndex.value === index ? null : index;
};

const fetchFaqs = async () => {
    try {
        const response = await axios.get("/api/applicant/faqs");
        faqs.value = response.data;
    } catch (error) {
        console.error("Error fetching FAQs:", error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchFaqs();
});
</script>

<template>
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">CNHS Frequently Asked Questions</h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item">
                            <a href="/applicant/dashboard">Home</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            FAQ
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <div v-else-if="faqs.length === 0" class="text-center py-5">
                <i class="bi bi-question-circle text-muted fs-1 mb-3 d-block"></i>
                <h5 class="fw-bold text-muted">No FAQs available</h5>
                <p class="text-muted">Check back later for updated frequently asked questions.</p>
            </div>

            <div v-else class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="card-title mb-0 fw-bold text-secondary">
                                <i class="bi bi-question-circle-fill me-2"></i>
                                Common Questions & Answers
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="accordion accordion-flush" id="faqAccordion">
                                <div
                                    v-for="(faq, index) in faqs"
                                    :key="faq.id"
                                    class="accordion-item border-bottom"
                                >
                                    <h2 class="accordion-header">
                                        <button
                                            class="accordion-button collapsed"
                                            :class="{ collapsed: activeIndex !== index }"
                                            type="button"
                                            @click="toggleFaq(index)"
                                        >
                                            <span class="fw-medium">{{ faq.question }}</span>
                                        </button>
                                    </h2>
                                    <div
                                        v-show="activeIndex === index"
                                        class="accordion-collapse"
                                    >
                                        <div class="accordion-body bg-light">
                                            {{ faq.answer }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
