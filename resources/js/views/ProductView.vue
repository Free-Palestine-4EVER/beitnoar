<template>
  <div class="view product-view" :class="{ 'product-view-embedded': embedded }" v-if="productData">
    <div class="product-detail-layout">
      <!-- Left / Visual Column -->
      <div class="product-visual-col">
        <div class="hero">
          <div class="detail-media">
            <div v-if="isVideoActive" class="product-photo-card product-video-card">
              <div v-if="!videoReady" class="video-loading-indicator" role="status" aria-label="Loading dish video">
                <span></span>
              </div>
              <video
                ref="videoRef"
                :src="videoSrc || undefined"
                :autoplay="!prefersReducedMotion"
                muted
                loop
                playsinline
                webkit-playsinline
                preload="metadata"
                class="product-photo-img product-video-img"
                :class="{ 'is-ready': videoReady }"
                @loadeddata="videoReady = true"
                @error="handleVideoError"
                @play="handleVideoPlay"
                @pause="isPaused = true"
                @click="togglePlayPause"
              ></video>
              <button
                v-if="showPlayOverlay"
                class="video-play-btn"
                :class="{ 'is-initial-play': !videoStarted }"
                type="button"
                @click.stop="togglePlayPause"
                :aria-label="isPaused ? 'Play video' : 'Pause video'"
              >
                <svg v-if="isPaused" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M8 5v14l11-7z"/>
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="currentColor">
                  <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                </svg>
              </button>
            </div>

            <div v-else class="pl xl" ref="heroHost" id="hero">
              <div class="shadow"></div>
              <div class="tilt">
                <div class="spin" ref="heroSpin" id="heroSpin" style="animation: none">
                  <div class="hero-svg-wrap" v-html="svgContent"></div>
                </div>
              </div>
            </div>

            <button
              v-if="hasAr && !modelError"
              class="ar-table-btn"
              type="button"
              :disabled="arLoading || (arViewerRequested && !modelLoaded)"
              :aria-busy="arLoading || (arViewerRequested && !modelLoaded)"
              @click="launchAR"
            >
              <span class="ar-btn-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                  <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
              </span>
              <span>{{ ui.arBtn }}</span>
            </button>

            <transition name="fade">
              <div v-if="arNotice" class="ar-notice" role="status">
                {{ ui.arNotSupported }}
              </div>
            </transition>

            <div v-if="hasAr && arViewerRequested && modelViewerReady && !modelError" class="ar-model-preload" aria-hidden="true">
              <model-viewer
                ref="modelViewerRef"
                :src="glbSrc"
                :alt="t(product, 'name')"
                touch-action="pan-y"
                tabindex="-1"
                ar
                ar-modes="quick-look webxr scene-viewer"
                ar-scale="fixed"
                shadow-intensity="1.3"
                shadow-softness="0.8"
                exposure="1.05"
                camera-orbit="0deg 60deg 105%"
                min-camera-orbit="auto 15deg auto"
                max-camera-orbit="auto 90deg auto"
                loading="eager"
                reveal="auto"
                class="ar-model-preload-viewer"
                @error="handleModelError"
                @load="onModelLoaded"
              ></model-viewer>
            </div>
          </div>
        </div>
      </div>

      <!-- Right / Info Column -->
      <div class="product-info-col">
        <!-- Details -->
        <div class="dt">
          <div class="a">{{ t(product, 'name') }}</div>
          <div class="e">{{ ui.sig }}</div>
        </div>

        <!-- Tags -->
        <ProductTags :tags="product.tags" />

        <!-- Description -->
        <div class="desc" v-if="t(product, 'description')">
          {{ t(product, 'description') }}
        </div>

        <!-- Price -->
        <div class="price">
          {{ Number(product.price).toFixed(2) }} {{ ui.cur }}
        </div>

        <!-- Calories -->
        <div class="kcal" v-if="product.calories">
          {{ ui.kcal(product.calories) }}
        </div>
      </div>
    </div>

    <!-- You may also like -->
    <div class="also" v-if="siblingProducts.length > 0">
      <h3>{{ ui.also }}</h3>
      <div class="strip">
        <button
          v-for="item in siblingProducts"
          :key="item.id"
          class="card"
          @click="goToSibling(item)"
        >
          <PlateArt
            :product="item"
            :category-name="categoryName"
            :active="props.active"
            size="m"
          />
          <div class="a">{{ t(item, 'name') }}</div>
          <div class="p">{{ Number(item.price).toFixed(2) }} {{ ui.cur }}</div>
        </button>
      </div>
    </div>
  </div>

  <div v-else class="view">
    <EmptyMenu message="Product not found" @retry="$router.push('/')" />
  </div>
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted, onActivated, onDeactivated, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMenu } from '../stores/menu';
import { plateSVG } from '../utils/plateArt';
import { useSpinner } from '../composables/useSpinner';
import ProductTags from '../components/ProductTags.vue';
import PlateArt from '../components/PlateArt.vue';
import EmptyMenu from '../components/EmptyMenu.vue';
import { buildAndroidSceneViewerIntent, getNativeArLaunchMode, resolveArModelUrl } from '../utils/arLaunch';
import { hasProductVideo } from '../utils/productMedia';
import { acquireSharedVideoSource } from '../utils/sharedVideoSource';

defineOptions({ name: 'ProductView' });

const props = defineProps({
  productId: { type: [String, Number], default: null },
  embedded: { type: Boolean, default: false },
  active: { type: Boolean, default: true },
});

const route = useRoute();
const router = useRouter();
const { findProduct, getSiblingProducts, t, ui } = useMenu();
const embeddedProductId = ref(props.productId);

watch(() => props.productId, (id) => {
  if (id != null) embeddedProductId.value = id;
});

const lastRouteProductId = ref(route.params.id ?? null);
watch(() => route.name === 'product' ? route.params.id : null, (id) => {
  if (id != null) lastRouteProductId.value = id;
});

// A kept-alive product route remains mounted while another route is active.
// Keep its last ID so leaving for the menu doesn't tear down the video element.
const activeProductId = computed(() => props.embedded
  ? embeddedProductId.value
  : route.name === 'product' ? route.params.id : lastRouteProductId.value);

const productData = computed(() => {
  return findProduct(activeProductId.value);
});

const product = computed(() => productData.value?.product || null);
const category = computed(() => productData.value?.category || null);
const categoryName = computed(() => category.value?.name_en || '');

const siblingProducts = computed(() => {
  if (!product.value || !category.value) return [];
  return getSiblingProducts(product.value.id, category.value.id, 3);
});

// Play a single product video on its detail page; menu cards use their still image.
const videoRef = ref(null);
const videoError = ref(false);
const isPaused = ref(true);
const isPausedByUser = ref(false);
const videoStarted = ref(false);
const videoReady = ref(false);
const videoSrc = ref(null);
const prefersReducedMotion = ref(false);
const modelViewerRef = ref(null);
const modelViewerReady = ref(false);
const arViewerRequested = ref(false);
const modelError = ref(false);
const modelLoaded = ref(false);
const arLoading = ref(false);
const arNotice = ref(false);
const heroHost = ref(null);
const heroSpin = ref(null);
const hintEl = ref(null);
let visibilityHandler = null;
let arNoticeTimeout = null;
let modelViewerScriptPromise = null;
let detailVideoLease = null;
let detailVideoRetryCount = 0;
let detailVideoRetryTimer = null;

const hasAr = computed(() => Boolean(product.value?.ar_enabled && product.value?.model_glb_url));

// iOS Quick Look is generated from this corrected GLB rather than the stored USDZ.
const glbSrc = computed(() => resolveArModelUrl(product.value?.model_glb_url, window.location.origin));

const svgContent = computed(() => {
  if (!product.value) return '';
  return plateSVG(product.value, categoryName.value);
});

const isVideoActive = computed(() => hasProductVideo(product.value) && !videoError.value);
const showPlayOverlay = computed(() => isVideoActive.value && videoReady.value && isPaused.value);
watch(() => product.value?.video_url, (source, _previous, onCleanup) => {
  clearTimeout(detailVideoRetryTimer);
  detailVideoRetryTimer = null;
  detailVideoRetryCount = 0;
  detailVideoLease?.release();
  detailVideoLease = null;
  videoSrc.value = null;
  videoStarted.value = false;
  videoReady.value = false;
  if (!source) return;

  let cancelled = false;
  onCleanup(() => {
    cancelled = true;
    clearTimeout(detailVideoRetryTimer);
    detailVideoRetryTimer = null;
    detailVideoLease?.release();
    detailVideoLease = null;
  });

  acquireSharedVideoSource(source).then((lease) => {
    if (cancelled) {
      lease.release();
      return;
    }
    detailVideoLease = lease;
    videoSrc.value = lease.src;
    nextTick(startVideoPlayback);
  }).catch((error) => {
    if (cancelled) return;
    // Keep the dish playable if its streaming URL cannot be resolved.
    console.warn('Could not prepare the product video source; using the original URL.', error);
    videoSrc.value = source;
    nextTick(startVideoPlayback);
  });
}, { immediate: true });

const startVideoPlayback = () => {
  const video = videoRef.value;
  if (!video || !props.active) return;
  video.muted = true;
  if (!prefersReducedMotion.value && !isPausedByUser.value && video.paused) {
    video.play().catch(() => {
      isPaused.value = true;
    });
  }
};

const loadModelViewerScript = () => {
  if (modelViewerReady.value) return Promise.resolve();
  if (!modelViewerScriptPromise) {
    modelViewerScriptPromise = import('@google/model-viewer')
      .then(() => {
        modelViewerReady.value = true;
      })
      .catch((error) => {
        console.warn('Could not load @google/model-viewer package', error);
        modelError.value = true;
        arLoading.value = false;
        modelViewerScriptPromise = null;
        showArNotice();
      });
  }

  return modelViewerScriptPromise;
};

watch(activeProductId, () => {
  videoError.value = false;
  videoReady.value = false;
  isPaused.value = true;
  isPausedByUser.value = false;
  videoStarted.value = false;
  arViewerRequested.value = false;
  modelError.value = false;
  modelLoaded.value = false;
  arLoading.value = false;
  arNotice.value = false;

  nextTick(() => {
    startVideoPlayback();
  });
});

watch(() => props.active, (active) => {
  const video = videoRef.value;
  if (!active) {
    video?.pause();
    isPaused.value = true;
    return;
  }

  nextTick(startVideoPlayback);
}, { flush: 'post' });

const handleVideoError = (event) => {
  // Ignore delayed errors from a video URL that has already been replaced.
  if (!videoSrc.value) return;
  const failedSource = event.currentTarget?.currentSrc;
  if (failedSource && failedSource !== videoSrc.value) return;

  console.warn('Product video playback failed; retrying its source:', event);
  videoReady.value = false;
  isPaused.value = true;

  // iOS may evict a paused decoder after backgrounding. Retry the source before
  // switching away from the video presentation.
  if (detailVideoRetryCount < 2 && videoRef.value) {
    const source = videoSrc.value;
    detailVideoRetryCount += 1;
    detailVideoRetryTimer = setTimeout(() => {
      detailVideoRetryTimer = null;
      const video = videoRef.value;
      if (!video || videoSrc.value !== source) return;
      video.load();
      if (props.active && !document.hidden && !prefersReducedMotion.value && !isPausedByUser.value) {
        startVideoPlayback();
      }
    }, 400 * detailVideoRetryCount);
    return;
  }

  detailVideoLease?.release();
  detailVideoLease = null;
  videoSrc.value = null;
  videoError.value = true;
};

const handleVideoPlay = () => {
  videoReady.value = true;
  videoStarted.value = true;
  isPaused.value = false;
  detailVideoRetryCount = 0;
};

const togglePlayPause = () => {
  const video = videoRef.value;
  if (!video) return;
  if (video.paused) {
    isPausedByUser.value = false;
    video.play().catch(() => {
      isPaused.value = true;
    });
  } else {
    isPausedByUser.value = true;
    video.pause();
  }
};

const showArNotice = () => {
  arNotice.value = true;
  clearTimeout(arNoticeTimeout);
  arNoticeTimeout = setTimeout(() => {
    arNotice.value = false;
  }, 4500);
};

const launchAndroidSceneViewer = () => {
  const p = product.value;
  if (!p?.model_glb_url) return false;

  const glbUrl = resolveArModelUrl(p.model_glb_url, window.location.origin);
  if (!glbUrl) return false;
  const intentUrl = buildAndroidSceneViewerIntent(glbUrl, p.name_en || 'Beit Elia Dish');
  window.location.href = intentUrl;
  return true;
};

const launchAR = () => {
  const mode = getNativeArLaunchMode({
    userAgent: navigator.userAgent,
    platform: navigator.platform,
    maxTouchPoints: navigator.maxTouchPoints,
  });

  if (mode === 'scene-viewer' && launchAndroidSceneViewer()) {
    return;
  }

  if (mode === 'quick-look' && modelLoaded.value) {
    const viewer = modelViewerRef.value;
    if (!viewer?.canActivateAR) {
      showArNotice();
      return;
    }

    Promise.resolve(viewer.activateAR()).catch((error) => {
      console.warn('Could not open the AR viewer:', error);
      showArNotice();
    });
    return;
  }

  if (mode === 'quick-look') {
    arLoading.value = true;
    arViewerRequested.value = true;
    loadModelViewerScript();
    return;
  }

  showArNotice();
};

const handleModelError = (event) => {
  console.warn('3D model failed to load for AR:', event);
  arLoading.value = false;
  modelLoaded.value = false;
  modelError.value = true;
  showArNotice();
};

const onModelLoaded = () => {
  modelLoaded.value = true;
  arLoading.value = false;
};

watch(() => [route.query.ar, hasAr.value], ([arRequest, arAvailable]) => {
  if (!arRequest || !arAvailable || props.embedded) return;

  const query = { ...route.query };
  delete query.ar;
  router.replace({ query });

  if (arRequest === 'unsupported') {
    showArNotice();
    return;
  }

  const mode = getNativeArLaunchMode({
    userAgent: navigator.userAgent,
    platform: navigator.platform,
    maxTouchPoints: navigator.maxTouchPoints,
  });
  if (arRequest !== 'prepare' || mode !== 'quick-look') return;

  arLoading.value = true;
  arViewerRequested.value = true;
  loadModelViewerScript();
}, { immediate: true });

useSpinner(heroHost, heroSpin, hintEl);

onMounted(() => {
  if (window.matchMedia) {
    prefersReducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  nextTick(startVideoPlayback);

  visibilityHandler = () => {
    const video = videoRef.value;
    if (!video) return;
    if (document.hidden || !props.active) {
      video.pause();
    } else if (!isPausedByUser.value && !prefersReducedMotion.value) {
      startVideoPlayback();
    }
  };
  document.addEventListener('visibilitychange', visibilityHandler);
});

onActivated(() => {
  nextTick(startVideoPlayback);
});

onDeactivated(() => {
  // Keep the video element and its buffered media alive when navigating home.
  // Pausing prevents background playback while allowing a quick revisit.
  videoRef.value?.pause();
});

onUnmounted(() => {
  if (visibilityHandler) document.removeEventListener('visibilitychange', visibilityHandler);
  clearTimeout(arNoticeTimeout);
  if (videoRef.value) {
    videoRef.value.pause();
    videoRef.value.removeAttribute('src');
    videoRef.value.load();
  }
});

const goToSibling = (item) => {
  if (props.embedded) {
    embeddedProductId.value = item.id;
    nextTick(() => {
      const modalScroll = document.querySelector('.quick-view-scroll');
      if (modalScroll) modalScroll.scrollTop = 0;
    });
    return;
  }

  // Recommendations should replace the current detail instead of growing a
  // long browser-history stack for every suggested item.
  router.replace({ name: 'product', params: { id: item.id }, query: route.query });
  const main = document.querySelector('main');
  if (main) main.scrollTop = 0;
};
</script>

<style scoped>
.hero-svg-wrap {
  width: 100%;
  height: 100%;
  display: block;
}
.hero-svg-wrap :deep(svg) {
  width: 100%;
  height: 100%;
  display: block;
}
.detail-media {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
}

.product-photo-card {
  width: min(74vw, 268px);
  aspect-ratio: 1 / 1;
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 22px;
  box-shadow: 0 10px 24px rgba(46, 45, 43, 0.12), 0 2px 6px rgba(46, 45, 43, 0.05);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.product-photo-img {
  width: 100%;
  height: 100%;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  display: block;
}

.ar-model-preload {
  position: fixed;
  top: 0;
  left: 0;
  width: min(78vw, 320px);
  height: min(78vw, 320px);
  overflow: hidden;
  opacity: 0;
  pointer-events: none;
  contain: strict;
  z-index: -1;
}

.ar-model-preload-viewer {
  display: block;
  width: 100%;
  height: 100%;
  touch-action: pan-y;
}

.ar-table-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-top: 14px;
  padding: 12px 26px;
  border-radius: 999px;
  background: var(--char);
  border: 1px solid rgba(192, 143, 60, 0.5);
  box-shadow: 0 10px 24px rgba(46, 45, 43, 0.22);
  color: var(--gold-l);
  font-family: var(--ui);
  font-size: 12px;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  cursor: pointer;
  transition: all 0.25s cubic-bezier(0.2, 0.7, 0.3, 1);
}

.ar-table-btn:active {
  transform: scale(0.96);
  background: #3A3835;
  border-color: var(--gold);
}

.ar-table-btn:disabled {
  cursor: wait;
  opacity: 0.72;
}

.ar-btn-ico {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
}

.ar-btn-ico svg {
  width: 100%;
  height: 100%;
}

.ar-notice {
  margin-top: 12px;
  padding: 8px 16px;
  border-radius: 12px;
  background: rgba(166, 124, 51, 0.12);
  border: 1px solid rgba(166, 124, 51, 0.3);
  color: var(--char);
  font-size: 11.5px;
  line-height: 1.5;
  text-align: center;
  max-width: 320px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

body.ar .ar-table-btn {
  font-size: 13.5px;
  letter-spacing: 0;
  font-weight: 500;
}

.product-video-card {
  position: relative;
  display: grid;
  place-items: center;
  overflow: hidden;
  background: var(--card);
}

.product-video-img {
  position: absolute;
  inset: 0;
  z-index: 2;
  width: 100%;
  height: 100%;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  display: block;
  opacity: 0;
  transition: opacity 180ms ease;
}

.product-video-img.is-ready {
  opacity: 1;
}

.video-loading-indicator {
  position: absolute;
  inset: 0;
  z-index: 3;
  display: grid;
  place-items: center;
  pointer-events: none;
}

.video-loading-indicator span {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(166, 124, 51, .2);
  border-top-color: var(--gold);
  border-radius: 50%;
  animation: product-video-spin .8s linear infinite;
}

@keyframes product-video-spin {
  to { transform: rotate(360deg); }
}

.video-play-btn {
  position: absolute;
  bottom: 12px;
  inset-inline-end: 12px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(46, 45, 43, 0.7);
  backdrop-filter: blur(4px);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: #F4F1EB;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  opacity: 0.85;
  transition: opacity 0.2s, transform 0.2s;
  z-index: 3;
}

.video-play-btn:hover {
  opacity: 1;
  transform: scale(1.06);
}

.video-play-btn.is-initial-play {
  top: 50%;
  right: auto;
  bottom: auto;
  left: 50%;
  width: 54px;
  height: 54px;
  transform: translate(-50%, -50%);
}

.video-play-btn.is-initial-play:hover {
  transform: translate(-50%, -50%) scale(1.06);
}

.video-play-btn svg {
  width: 18px;
  height: 18px;
}

@media (min-width: 768px) {
  .product-photo-card {
    width: min(100%, 320px);
    height: min(100%, 320px);
    border-radius: 24px;
  }
  .pl.xl {
    width: min(100%, 300px);
    height: min(100%, 300px);
  }
}
</style>
