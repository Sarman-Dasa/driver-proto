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

const route = useRoute();

const layoutComponent = computed(() => {
    return route.meta.layout === 'auth' ? AuthLayout : AppLayout;
});
</script>
