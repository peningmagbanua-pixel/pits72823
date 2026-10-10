<script setup>
import { computed, onMounted, ref } from 'vue';
import AppHeader from './components/AppHeader.vue';
import AppSidebar from './components/AppSidebar.vue';
import AppFooter from './components/AppFooter.vue';
import Animated from './components/Animated.vue';
import ChangePasswordModal from './components/ChangePasswordModal.vue';

import { useAuthApplicantStore } from './stores/AuthApplicantStore';
const authApplicantStore = useAuthApplicantStore();

const ready = ref(false);
const locked = computed(() => ready.value && authApplicantStore.applicant.must_change_password === true);

onMounted(async () => {
    await authApplicantStore.getAuthApplicant();
    ready.value = true;
});
</script>
<template>
    <Animated />
    <ChangePasswordModal v-if="locked" />
    <div class="app-wrapper" v-else-if="ready">
        <AppHeader />
        <AppSidebar />
        <main class="app-main">
            <router-view></router-view>
        </main>
        <AppFooter />
    </div>
    <div v-else class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="spinner-border text-primary" role="status"></div>
    </div>
</template>
