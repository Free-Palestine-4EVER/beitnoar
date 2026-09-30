<template>
  <button class="group" type="button" @click="$emit('select', category)">
    <span class="ico" aria-hidden="true" v-html="iconForCategory(category)"></span>
    <div class="txt">
      <div class="a">{{ t(category, 'name') }}</div>
      <div class="n">{{ t(category, 'description') }}</div>
    </div>
    <div class="ch">›</div>
  </button>
</template>

<script setup>
import { useMenu } from '../stores/menu';

defineProps({
  category: {
    type: Object,
    required: true,
  },
});

defineEmits(['select']);

const { t } = useMenu();

// These are the original portal icons from the biet-aylah menu.html.
const categoryIcons = {
  chef: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2v20M21 2c0 4-3 7-3 7M3 2v7c0 2.2 1.8 4 4 4v9M7 2v7M11 2v7"/></svg>',
  bar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 22h8M12 11v11M19 3l-7 8-7-8z"/></svg>',
  shisha: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83"/></svg>',
  sweets: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
};

const iconForCategory = (category) => {
  const name = `${category.name_en || ''} ${category.name_ar || ''}`.toLowerCase();

  if (name.includes('bar') || name.includes('بار')) return categoryIcons.bar;
  if (name.includes('shisha') || name.includes('hookah') || name.includes('lounge') || name.includes('شيشة') || name.includes('أركيلة')) return categoryIcons.shisha;
  if (name.includes('sweet') || name.includes('dessert') || name.includes('حلويات') || name.includes('حلو')) return categoryIcons.sweets;

  return categoryIcons.chef;
};
</script>
