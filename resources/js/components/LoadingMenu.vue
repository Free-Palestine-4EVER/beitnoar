<template>
  <div class="loading-state">
    <div class="loading-spinner"></div>
    <div class="loading-text">{{ ui.loading }}</div>
  </div>
</template>

<script setup>
import { useMenu } from '../stores/menu';

const { ui } = useMenu();
</script>
