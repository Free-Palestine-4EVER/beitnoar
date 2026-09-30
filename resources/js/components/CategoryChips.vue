<template>
  <div class="chips" id="chips" ref="chipsBar">
    <button
      v-for="sub in subcategories"
      :key="sub.id"
      class="chip"
      :ref="(el) => setChipRef(sub.id, el)"
      :class="{ on: activeId === sub.id }"
      @click="$emit('select', sub)"
    >
      {{ t(sub, 'name') }}
    </button>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onUnmounted } from 'vue';
import { useMenu } from '../stores/menu';

const props = defineProps({
  subcategories: {
    type: Array,
    required: true,
  },
  activeId: {
    type: [Number, String],
    default: null,
  },
});

defineEmits(['select']);

const { t } = useMenu();

const chipsBar = ref(null);
const chipRefs = new Map();

const setChipRef = (id, el) => {
  if (el) {
    chipRefs.set(id, el);
  } else {
    chipRefs.delete(id);
  }
};

const scrollActiveChip = async (id) => {
  await nextTick();
  const chip = chipRefs.get(id);
  const container = chipsBar.value;
  if (!chip || !container) return;

  const chipRect = chip.getBoundingClientRect();
  const containerRect = container.getBoundingClientRect();
  const isRtl = document.documentElement.dir === 'rtl' || document.body.classList.contains('ar');

  const chipCenter = chipRect.left + chipRect.width / 2;
  const containerCenter = containerRect.left + containerRect.width / 2;
  const delta = chipCenter - containerCenter;

  if (Math.abs(delta) < 2) return;

  const targetLeft = isRtl
    ? container.scrollLeft - delta
    : container.scrollLeft + delta;

  container.scrollTo({
    left: targetLeft,
    behavior: 'smooth',
  });
};

watch(() => props.activeId, (newId, oldId) => {
  if (newId && newId !== oldId) {
    scrollActiveChip(newId);
  }
}, { immediate: true });

onUnmounted(() => {
  chipRefs.clear();
});
</script>
