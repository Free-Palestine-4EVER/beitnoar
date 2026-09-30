<template>
  <Teleport to="body">
    <Transition name="quick-view">
      <div
        v-show="open && productId"
        class="quick-view-backdrop"
        @click.self="emit('close')"
      >
        <section
          class="quick-view-dialog"
          role="dialog"
          aria-modal="true"
          :aria-label="productTitle || ui.quickView"
          @keydown.esc.stop.prevent="emit('close')"
        >
          <header class="quick-view-header">
            <div class="quick-view-heading">
              <span class="quick-view-kicker">{{ ui.quickView }}</span>
              <span class="quick-view-title">{{ productTitle }}</span>
            </div>
            <button
              ref="closeButton"
              class="quick-view-close"
              type="button"
              :aria-label="ui.close"
              @click="emit('close')"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="m6 6 12 12M18 6 6 18" />
              </svg>
            </button>
          </header>
          <div class="quick-view-scroll">
            <ProductView
              v-if="hasBeenOpened"
              :product-id="productId"
              :embedded="true"
              :active="open"
            />
          </div>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useMenu } from '../stores/menu';
import ProductView from '../views/ProductView.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  productId: { type: [String, Number], default: null },
});

const emit = defineEmits(['close']);
const { findProduct, t, ui } = useMenu();
const closeButton = ref(null);
const hasBeenOpened = ref(false);
const productTitle = computed(() => t(findProduct(props.productId)?.product, 'name'));

const handleKeydown = (event) => {
  if (props.open && event.key === 'Escape') emit('close');
};

watch(() => props.open, async (open) => {
  if (!open) return;
  // Keep the detail video mounted after its first open. iOS Safari may fetch
  // the same MP4 again when a video element is destroyed and recreated.
  hasBeenOpened.value = true;
  await nextTick();
  closeButton.value?.focus({ preventScroll: true });
});

onMounted(() => document.addEventListener('keydown', handleKeydown));
onUnmounted(() => document.removeEventListener('keydown', handleKeydown));
</script>

<style scoped>
.quick-view-backdrop {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: grid;
  place-items: center;
  padding: max(12px, env(safe-area-inset-top)) 12px max(12px, env(safe-area-inset-bottom));
  background: rgba(30, 29, 27, .66);
  -webkit-backdrop-filter: blur(8px);
  backdrop-filter: blur(8px);
}

.quick-view-dialog {
  display: flex;
  flex-direction: column;
  width: min(100%, 1000px);
  height: min(92dvh, 920px);
  max-height: 92dvh;
  min-height: 0;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, .4);
  border-radius: 22px;
  background: linear-gradient(180deg, #F2EFEA, #E5E1DA);
  box-shadow: 0 24px 80px rgba(0, 0, 0, .3);
}

.quick-view-header {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--line);
  background: rgba(248, 246, 242, .92);
}

.quick-view-heading {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 2px;
}

.quick-view-kicker {
  color: var(--gold);
  font-size: 9px;
  letter-spacing: 2px;
  text-transform: uppercase;
}

.quick-view-title {
  overflow: hidden;
  font-family: var(--en);
  font-size: 22px;
  line-height: 1.2;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.quick-view-close {
  display: grid;
  width: 38px;
  height: 38px;
  flex: 0 0 auto;
  place-items: center;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: var(--card);
  color: var(--char);
}

.quick-view-close svg { width: 17px; height: 17px; }

.quick-view-scroll {
  flex: 1 1 auto;
  min-height: 0;
  overflow: auto;
  overscroll-behavior: contain;
  padding: 14px 18px 24px;
  scrollbar-width: thin;
  scrollbar-color: rgba(166, 124, 51, .35) transparent;
}

.quick-view-enter-active,
.quick-view-leave-active { transition: opacity .2s ease, transform .2s ease; }
.quick-view-enter-from,
.quick-view-leave-to { opacity: 0; }
.quick-view-enter-from .quick-view-dialog,
.quick-view-leave-to .quick-view-dialog { transform: translateY(12px) scale(.99); }

@media (max-width: 600px) {
  .quick-view-backdrop {
    place-items: end center;
    padding: 0;
  }

  .quick-view-dialog {
    width: 100%;
    height: 100dvh;
    max-height: 100dvh;
    border-radius: 0;
    padding-bottom: env(safe-area-inset-bottom);
  }

  .quick-view-header { padding: max(12px, env(safe-area-inset-top)) 16px 12px; }
  .quick-view-scroll { padding: 8px 16px 22px; }
}

body.ar .quick-view-kicker { letter-spacing: 0; text-transform: none; font-size: 12px; }
</style>
