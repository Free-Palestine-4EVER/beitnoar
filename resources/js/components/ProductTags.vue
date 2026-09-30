<template>
  <div class="tags" v-if="tags && tags.length > 0">
    <span v-for="tag in tags" :key="tag.slug" class="tag">
      {{ t(tag, 'name') }}
    </span>
  </div>
</template>

<script setup>
import { useMenu } from '../stores/menu';

defineProps({
  tags: {
    type: Array,
    default: () => [],
  },
});

const { t } = useMenu();
</script>
