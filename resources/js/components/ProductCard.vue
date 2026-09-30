<template>
  <article class="card product-card">
    <button class="card-main" type="button" @click="$emit('select', product)">
      <div class="card-media-wrap">
        <PlateArt
          :product="product"
          :category-name="categoryName"
          :active="videoActive"
          size="m"
        />
      </div>
      <div class="a">{{ t(product, 'name') }}</div>
      <div class="p">{{ Number(product.price).toFixed(2) }} {{ ui.cur }}</div>
    </button>

    <div v-if="product.ar_enabled && product.model_glb_url" class="product-card-actions">
      <button
        v-if="product.ar_enabled && product.model_glb_url"
        class="product-card-action product-card-ar"
        type="button"
        @click.stop="$emit('view-ar', product)"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m12 2 9 5v10l-9 5-9-5V7l9-5Z" />
          <path d="m3.3 7 8.7 5 8.7-5M12 22V12" />
        </svg>
        <span>{{ ui.viewInAr }}</span>
      </button>
    </div>
  </article>
</template>

<script setup>
import { useMenu } from '../stores/menu';
import PlateArt from './PlateArt.vue';

defineProps({
  product: {
    type: Object,
    required: true,
  },
  categoryName: {
    type: String,
    default: '',
  },
  videoActive: {
    type: Boolean,
    default: true,
  },
});

defineEmits(['select', 'view-ar']);

const { t, ui } = useMenu();
</script>

<style scoped>
.card-media-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.card-media-wrap :deep(.card-photo-wrap.m),
.card-media-wrap :deep(.pl.m) {
  width: min(138px, 100%);
  height: auto;
  aspect-ratio: 1;
}

.product-card {
  justify-content: flex-start;
  gap: 10px;
}

.card-main {
  display: flex;
  flex: 1;
  width: 100%;
  min-width: 0;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  gap: 8px;
  text-align: center;
}

.product-card-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
  margin-top: auto;
}

.product-card-action {
  display: inline-flex;
  width: 100%;
  min-width: 0;
  align-items: center;
  justify-content: center;
  gap: 5px;
  min-height: 38px;
  padding: 7px 9px;
  border: 1px solid rgba(166, 124, 51, .34);
  border-radius: 999px;
  color: var(--gold);
  font-family: var(--ui);
  font-size: 10px;
  letter-spacing: 1px;
  line-height: 1.15;
  text-transform: uppercase;
  transition: background .2s ease, color .2s ease, border-color .2s ease;
}

.product-card-action svg {
  width: 14px;
  height: 14px;
  flex: 0 0 auto;
}

.product-card-action:active {
  background: var(--char);
  border-color: var(--char);
  color: var(--gold-l);
}

body.ar .product-card-action {
  font-size: 11px;
  letter-spacing: 0;
  text-transform: none;
}

@media (min-width: 768px) {
  .product-card { gap: 12px; }
  .product-card-actions { gap: 8px; }
  .card-media-wrap :deep(.card-photo-wrap.m),
  .card-media-wrap :deep(.pl.m) { width: min(150px, 100%); }
  .product-card-action { min-height: 40px; font-size: 10px; }
}

@media (min-width: 1200px) {
  .card-media-wrap :deep(.card-photo-wrap.m),
  .card-media-wrap :deep(.pl.m) { width: min(164px, 100%); }
}

@media (max-width: 360px) {
  .card-media-wrap :deep(.card-photo-wrap.m),
  .card-media-wrap :deep(.pl.m) { width: min(124px, 100%); }
  .product-card-action { font-size: 9px; letter-spacing: .6px; }
}
</style>
