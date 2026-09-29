<script setup lang="ts">
import Toasts from '@/components/common/Toasts.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
</script>

<template>
  <router-view />
  <Toasts />
  <ConfirmDialog />
</template>
