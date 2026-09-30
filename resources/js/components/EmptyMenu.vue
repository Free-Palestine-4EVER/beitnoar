<template>
  <div class="error-state">
    <p>{{ message || ui.error }}</p>
    <button class="retry-btn" @click="$emit('retry')">
      {{ ui.retry }}
    </button>
  </div>
</template>

<script setup>
import { useMenu } from '../stores/menu';

defineProps({
  message: {
    type: String,
    default: '',
  },
});

defineEmits(['retry']);

const { ui } = useMenu();
</script>
