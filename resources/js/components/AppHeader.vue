<template>
  <div class="top">
    <button
      class="back"
      id="back"
      :style="{ visibility: showBack ? 'visible' : 'hidden' }"
      @click="onBack"
      :aria-label="ui.back"
    >
      ‹
    </button>
    <div class="brandwrap">
      <router-link to="/" class="brandlink">
        <div class="logo sm"></div>
      </router-link>
    </div>
    <div class="header-actions">
      <button class="lang" id="lang" @click="toggleLang">
        {{ state.lang === 'ar' ? 'EN' : 'ع' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMenu } from '../stores/menu';

const route = useRoute();
const router = useRouter();
const { state, toggleLang, ui, findProduct } = useMenu();

const showBack = computed(() => route.name !== 'home');

const onBack = () => {
  if (route.name === 'product') {
    const categoryId = typeof route.query.fromCategory === 'string'
      ? route.query.fromCategory
      : findProduct(route.params.id)?.category?.id;

    if (categoryId) {
      const categoryLocation = { name: 'category', params: { id: categoryId } };
      const categoryPath = router.resolve(categoryLocation).path;

      if (window.history.state?.back === categoryPath) {
        router.back();
      } else {
        router.replace(categoryLocation);
      }

      return;
    }
  }

  router.replace({ name: 'home' });
};
</script>

<style scoped>
.brandlink {
  display: block;
}

.header-actions {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 8px;
}
</style>
