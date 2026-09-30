<template>
  <div class="view" v-if="category">
    <h1 class="t">{{ t(category, 'name') }}</h1>
    <div class="sub">{{ ui.sig }}</div>
    <div class="note" v-if="t(category, 'description')">{{ t(category, 'description') }}</div>

    <!-- Chips for subcategories -->
    <div class="chips" id="chips" v-if="hasMultipleSubcategories" ref="chipsContainer">
      <button
        v-for="sub in subcategories"
        :key="sub.id"
        class="chip"
        :ref="(el) => setChipRef(sub.id, el)"
        :class="{ on: activeSubId === sub.id }"
        @click="onCategoryClick(sub.id)"
      >
        {{ t(sub, 'name') }}
      </button>
    </div>

    <!-- If section has direct products without subcategories -->
    <div v-if="Array.isArray(category.products) && category.products.length > 0 && subcategories.length === 0" class="sec">
      <div class="grid">
        <ProductCard
          v-for="product in category.products"
          :key="product.id"
          :product="product"
          :category-name="category.name_en"
          :video-active="!quickViewProductId"
          @select="openQuickView(product)"
          @view-ar="viewInAr(product)"
        />
      </div>
    </div>

    <!-- Subcategories sections with their products -->
    <div
      v-for="sub in subcategories"
      :key="sub.id"
      class="sec"
      :id="`sec-${sub.id}`"
    >
      <h2>{{ t(sub, 'name') }}</h2>
      <div class="line"></div>
      <div class="grid">
        <ProductCard
          v-for="product in sub.products"
          :key="product.id"
          :product="product"
          :category-name="sub.name_en"
          :video-active="!quickViewProductId"
          @select="openQuickView(product)"
          @view-ar="viewInAr(product)"
        />
      </div>
    </div>
  </div>

  <div v-else class="view">
    <EmptyMenu message="Category not found" @retry="$router.push('/')" />
  </div>

  <ProductQuickView
    :open="Boolean(quickViewProductId)"
    :product-id="quickViewProductId"
    @close="closeQuickView"
  />
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMenu } from '../stores/menu';
import ProductCard from '../components/ProductCard.vue';
import ProductQuickView from '../components/ProductQuickView.vue';
import EmptyMenu from '../components/EmptyMenu.vue';
import { buildAndroidSceneViewerIntent, getNativeArLaunchMode, resolveArModelUrl } from '../utils/arLaunch';

const route = useRoute();
const router = useRouter();
const { findCategory, t, ui } = useMenu();
const quickViewProductId = ref(null);

const category = computed(() => {
  return findCategory(route.params.id);
});

const subcategories = computed(() => {
  if (!category.value || !Array.isArray(category.value.children)) return [];
  return category.value.children.filter(
    (c) => (Array.isArray(c.products) && c.products.length > 0) || (Array.isArray(c.children) && c.children.length > 0)
  );
});

const hasMultipleSubcategories = computed(() => {
  return subcategories.value.length > 1;
});

const activeSubId = ref(null);
const chipsContainer = ref(null);
const chipRefs = new Map();

const setChipRef = (id, el) => {
  if (el) {
    chipRefs.set(id, el);
  } else {
    chipRefs.delete(id);
  }
};

const scrollActiveChipIntoView = async (id) => {
  await nextTick();
  const chip = chipRefs.get(id);
  const container = chipsContainer.value;
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

watch(activeSubId, (newId, oldId) => {
  if (newId && newId !== oldId) {
    scrollActiveChipIntoView(newId);
  }
});

watch(subcategories, (newSubs) => {
  if (newSubs.length > 0 && !activeSubId.value) {
    activeSubId.value = newSubs[0].id;
    nextTick(() => {
      scrollActiveChipIntoView(newSubs[0].id);
    });
  }
});

watch(() => route.params.id, () => {
  if (subcategories.value.length > 0) {
    activeSubId.value = subcategories.value[0].id;
    nextTick(() => {
      scrollActiveChipIntoView(subcategories.value[0].id);
    });
  }
});

onMounted(async () => {
  if (subcategories.value.length > 0) {
    activeSubId.value = subcategories.value[0].id;
    nextTick(() => {
      scrollActiveChipIntoView(subcategories.value[0].id);
    });
  }
  setupScrollSpy();

  const savedScrollTop = sessionStorage.getItem(`category-scroll-position:${route.params.id}`);
  if (savedScrollTop !== null) {
    sessionStorage.removeItem(`category-scroll-position:${route.params.id}`);
    await nextTick();
    requestAnimationFrame(() => {
      const main = document.querySelector('main');
      if (main) {
        main.scrollTop = Number(savedScrollTop);
        scrollHandler?.();
      }
    });
  }
});

let isProgrammaticScroll = false;
let programmaticScrollTimeout = null;
let scrollHandler = null;
let scrollEndHandler = null;
let userInteractionHandler = null;

const scrollToSection = (subId) => {
  const el = document.getElementById(`sec-${subId}`);
  const main = document.querySelector('main');
  if (!el || !main) return;

  isProgrammaticScroll = true;
  activeSubId.value = subId;

  const mainRect = main.getBoundingClientRect();
  const elRect = el.getBoundingClientRect();
  const stickyHeight = chipsContainer.value ? chipsContainer.value.offsetHeight : 54;
  const targetTop = main.scrollTop + (elRect.top - mainRect.top) - stickyHeight - 8;

  main.scrollTo({
    top: Math.max(0, targetTop),
    behavior: 'smooth',
  });

  if (programmaticScrollTimeout) {
    clearTimeout(programmaticScrollTimeout);
  }
  programmaticScrollTimeout = setTimeout(() => {
    isProgrammaticScroll = false;
  }, 600);
};

const onCategoryClick = (subId) => {
  activeSubId.value = subId;
  scrollToSection(subId);
  scrollActiveChipIntoView(subId);
};

const setupScrollSpy = () => {
  const main = document.querySelector('main');
  if (!main) return;

  scrollHandler = () => {
    if (isProgrammaticScroll) return;
    if (subcategories.value.length === 0) return;

    // Check if user has scrolled to the bottom of the container
    const canScroll = main.scrollHeight > main.clientHeight + 20;
    const isBottom = canScroll && (main.scrollTop + main.clientHeight >= main.scrollHeight - 15);

    if (isBottom) {
      const lastSub = subcategories.value[subcategories.value.length - 1];
      if (lastSub && activeSubId.value !== lastSub.id) {
        activeSubId.value = lastSub.id;
      }
      return;
    }

    const mainRect = main.getBoundingClientRect();
    const stickyHeight = chipsContainer.value ? chipsContainer.value.offsetHeight : 54;
    let currentSubId = subcategories.value[0]?.id;

    for (const sub of subcategories.value) {
      const el = document.getElementById(`sec-${sub.id}`);
      if (el) {
        const elRect = el.getBoundingClientRect();
        if (elRect.top - mainRect.top <= stickyHeight + 25) {
          currentSubId = sub.id;
        }
      }
    }

    if (currentSubId && activeSubId.value !== currentSubId) {
      activeSubId.value = currentSubId;
    }
  };

  userInteractionHandler = (e) => {
    if (chipsContainer.value && e?.target && chipsContainer.value.contains(e.target)) {
      return;
    }
    if (isProgrammaticScroll) {
      isProgrammaticScroll = false;
      if (programmaticScrollTimeout) {
        clearTimeout(programmaticScrollTimeout);
        programmaticScrollTimeout = null;
      }
    }
  };

  scrollEndHandler = () => {
    if (isProgrammaticScroll) {
      isProgrammaticScroll = false;
      if (programmaticScrollTimeout) {
        clearTimeout(programmaticScrollTimeout);
        programmaticScrollTimeout = null;
      }
    }
  };

  main.addEventListener('scroll', scrollHandler, { passive: true });
  main.addEventListener('scrollend', scrollEndHandler, { passive: true });
  main.addEventListener('wheel', userInteractionHandler, { passive: true });
  main.addEventListener('touchstart', userInteractionHandler, { passive: true });
  main.addEventListener('pointerdown', userInteractionHandler, { passive: true });
};

onUnmounted(() => {
  const main = document.querySelector('main');
  if (main) {
    if (scrollHandler) main.removeEventListener('scroll', scrollHandler);
    if (scrollEndHandler) main.removeEventListener('scrollend', scrollEndHandler);
    if (userInteractionHandler) {
      main.removeEventListener('wheel', userInteractionHandler);
      main.removeEventListener('touchstart', userInteractionHandler);
      main.removeEventListener('pointerdown', userInteractionHandler);
    }
  }
  if (programmaticScrollTimeout) {
    clearTimeout(programmaticScrollTimeout);
  }
  chipRefs.clear();
});

const goToProduct = (product, { prepareAr = false, showUnsupportedNotice = false } = {}) => {
  const main = document.querySelector('main');
  if (main) {
    sessionStorage.setItem(`category-scroll-position:${route.params.id}`, String(main.scrollTop));
  }

  const query = { fromCategory: String(route.params.id) };
  if (prepareAr) {
    query.ar = 'prepare';
  } else if (showUnsupportedNotice) {
    query.ar = 'unsupported';
  }

  router.push({
    name: 'product',
    params: { id: product.id },
    query,
  });
};

const viewInAr = (product) => {
  const mode = getNativeArLaunchMode({
    userAgent: navigator.userAgent,
    platform: navigator.platform,
    maxTouchPoints: navigator.maxTouchPoints,
  });

  if (mode === 'scene-viewer') {
    const modelUrl = resolveArModelUrl(product.model_glb_url, window.location.origin);
    const intentUrl = buildAndroidSceneViewerIntent(modelUrl, product.name_en || 'Beit Elia Dish');
    if (intentUrl) {
      window.location.href = intentUrl;
      return;
    }
  }

  goToProduct(product, {
    prepareAr: mode === 'quick-look',
    showUnsupportedNotice: mode === 'unsupported',
  });
};

const openQuickView = (product) => {
  quickViewProductId.value = product.id;
};

const closeQuickView = () => {
  quickViewProductId.value = null;
};
</script>
