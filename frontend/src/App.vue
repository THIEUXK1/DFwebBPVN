<template>
  <AppLayout v-if="$route.meta.requiresAuth">
    <router-view />
  </AppLayout>
  <router-view v-else />
</template>

<script setup lang="ts">
import AppLayout from './components/AppLayout.vue';
</script>

<style>
/* Global CSS is imported in main.ts */
</style>
