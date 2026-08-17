<script setup>
import { onMounted } from 'vue';
import AppHeader from './components/AppHeader.vue';
import AppSidebar from './components/AppSidebar.vue';
import AppFooter from './components/AppFooter.vue';
import Animated from './components/Animated.vue';

import { useAuthUserStore } from './stores/AuthUserStore';
const authUserStore = useAuthUserStore();

onMounted(() => {
    authUserStore.getAuthUser();
});
</script>
<template>
    <Animated />
    <div class="app-wrapper">
        <AppHeader />
        <AppSidebar />
        <main class="app-main">
            <router-view></router-view>
        </main>
        <AppFooter />
    </div>
</template>