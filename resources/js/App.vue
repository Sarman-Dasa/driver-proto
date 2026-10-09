<template>
    <component :is="layoutComponent">
        <router-view />
    </component>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AuthLayout from '@/layouts/AuthLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PublicPaymentLayout from '@/layouts/PublicPaymentLayout.vue';

const route = useRoute();

const layoutComponent = computed(() => {
    if (route.meta.layout === 'auth') {
        return AuthLayout;
    }
    if (route.meta.layout === 'public') {
        return PublicPaymentLayout;
    }
    return AppLayout;
});
</script>
