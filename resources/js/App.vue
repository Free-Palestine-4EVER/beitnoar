<template>
  <div id="shell">
    <div class="tex"></div>
    <div class="vig"></div>

    <div id="app-inner">
      <AppHeader />

      <!-- Offline / cached data banner -->
      <Transition name="offline-banner">
        <div
          v-if="showOfflineBanner"
          class="offline-banner"
          role="status"
          aria-live="polite"
        >
          {{ ui.offline }}
        </div>
      </Transition>

      <main id="main">
        <LoadingMenu v-if="state.loading" />
        <EmptyMenu v-else-if="state.error" :message="state.error" @retry="loadMenu(true)" />
        <router-view v-else v-slot="{ Component }">
          <KeepAlive :max="3">
            <component :is="Component" />
          </KeepAlive>
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { useMenu } from './stores/menu';
import AppHeader from './components/AppHeader.vue';
import LoadingMenu from './components/LoadingMenu.vue';
import EmptyMenu from './components/EmptyMenu.vue';

const {
  state,
  loadMenu,
  applyLang,
  ui,
  setupConnectivityListeners,
  startVersionPolling,
  stopVersionPolling,
} = useMenu();

/**
 * Show the offline banner when:
 * - Device is offline, OR
 * - We're displaying stale cached data
 * AND we have menu data to show (if we have no data, EmptyMenu handles the UX)
 */
const showOfflineBanner = computed(() => {
  return (state.isOffline || state.isShowingCached) && state.menu.length > 0;
});

onMounted(() => {
  applyLang(state.lang);
  loadMenu();

  // Set up online/offline event listeners
  setupConnectivityListeners();

  // Start periodic version checking (every 60s)
  startVersionPolling();
});

onUnmounted(() => {
  stopVersionPolling();
});
</script>

<style scoped>
.offline-banner {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 9999;
  padding: 10px 16px;
  text-align: center;
  font-family: var(--ui);
  font-size: 12px;
  letter-spacing: 1px;
  color: #F4F1EB;
  background: rgba(46, 45, 43, 0.92);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}

/* Transition for smooth show/hide */
.offline-banner-enter-active,
.offline-banner-leave-active {
  transition: transform 0.3s ease, opacity 0.3s ease;
}
.offline-banner-enter-from,
.offline-banner-leave-to {
  transform: translateY(100%);
  opacity: 0;
}
</style>
