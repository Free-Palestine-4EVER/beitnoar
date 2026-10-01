<template>
  <!-- Product cards use video when available and plate artwork otherwise. -->
  <div v-if="cardMedia.type === 'video' && !videoError" class="card-photo-wrap" :class="sizeClass">
    <div v-if="!videoReady && !videoError" class="card-video-loading" aria-hidden="true">
      <span></span>
    </div>
    <video
      ref="videoRef"
      :src="videoSrc || undefined"
      muted
      loop
      playsinline
      preload="none"
      class="card-photo-video"
      :class="{ 'is-ready': videoReady }"
      :aria-label="product.name_en || 'Product video'"
      aria-hidden="true"
      @loadeddata="handleVideoReady"
      @playing="handleVideoPlaying"
      @pause="handleCardVideoPause"
      @error="handleVideoError"
    ></video>
  </div>

  <!-- Products without video use the generated plate artwork. -->
  <div v-else class="pl" :class="sizeClass">
    <div class="shadow"></div>
    <div class="tilt">
      <div
        v-if="spin"
        class="spin"
        :style="{ '--dur': duration + 's', '--dir': direction }"
      >
        <div class="plate-svg-wrap" v-html="svgContent"></div>
      </div>
      <div v-else>
        <div class="plate-svg-wrap" v-html="svgContent"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { plateSVG, rngOf } from '../utils/plateArt';
import { getProductCardMedia } from '../utils/productMedia';
import { acquireSharedVideoSource } from '../utils/sharedVideoSource';

const props = defineProps({
  product: {
    type: Object,
    required: true,
  },
  categoryName: {
    type: String,
    default: '',
  },
  size: {
    type: String,
    default: 'm', // 'g', 'm', 'xl'
  },
  spin: {
    type: Boolean,
    default: true,
  },
  active: {
    type: Boolean,
    default: true,
  },
});

const sizeClass = computed(() => props.size);

const videoRef = ref(null);
const videoError = ref(false);
const videoReady = ref(false);
const prefersReducedMotion = ref(false);
const isIntersecting = ref(false);
const videoLoaded = ref(false);
const videoSrc = ref(null);
let observer = null;
let visibilityHandler = null;
let motionQuery = null;
let videoLease = null;
let videoRequestId = 0;
let videoRetryCount = 0;
let retryTimer = null;

const releaseVideoSource = () => {
  if (retryTimer) {
    clearTimeout(retryTimer);
    retryTimer = null;
  }
  videoLease?.release();
  videoLease = null;
  videoSrc.value = null;
  videoLoaded.value = false;
  videoReady.value = false;
};

const pauseVideoPlayback = () => {
  videoRef.value?.pause();
};

const loadVideoSource = async () => {
  if (!props.active || !isIntersecting.value || videoError.value) return;

  if (videoLoaded.value && videoSrc.value) {
    const player = videoRef.value;
    if (player && player.paused && !document.hidden && !prefersReducedMotion.value) {
      player.play().catch(() => {});
    }
    return;
  }

  videoLoaded.value = true;
  const requestId = ++videoRequestId;
  try {
    const lease = await acquireSharedVideoSource(cardMedia.value.src);
    if (requestId !== videoRequestId || !props.active || !isIntersecting.value) {
      lease.release();
      if (requestId === videoRequestId) videoLoaded.value = false;
      return;
    }

    videoLease = lease;
    videoSrc.value = lease.src;
    await nextTick();
    const player = videoRef.value;
    if (player && !document.hidden && !prefersReducedMotion.value) {
      player.play().catch(() => {});
    }
  } catch (error) {
    if (requestId !== videoRequestId) return;
    videoLoaded.value = false;
    console.warn('Card video failed to load:', error);
    if (videoRetryCount < 2) {
      videoRetryCount += 1;
      retryTimer = setTimeout(() => {
        retryTimer = null;
        if (requestId !== videoRequestId || !props.active || !isIntersecting.value) return;
        loadVideoSource();
      }, 400 * videoRetryCount);
      return;
    }
    videoError.value = true;
  }
};

watch(() => props.product?.id, () => {
  videoRequestId += 1;
  releaseVideoSource();
  videoError.value = false;
  videoRetryCount = 0;
  nextTick(loadVideoSource);
});

const cardMedia = computed(() => getProductCardMedia(props.product));

const handleVideoReady = (event) => {
  if (!videoSrc.value || event.currentTarget.currentSrc !== videoSrc.value) return;
  videoReady.value = true;
};

const handleVideoPlaying = (event) => {
  if (!videoSrc.value || event.currentTarget.currentSrc !== videoSrc.value) return;
  videoReady.value = true;
  videoRetryCount = 0;
};

const handleCardVideoPause = (event) => {
  const player = event.currentTarget;
  if (
    !props.active || !isIntersecting.value || document.hidden ||
    prefersReducedMotion.value || videoError.value || player.error
  ) return;

  // There are no card playback controls; a foreground-visible card should
  // keep animating if mobile Safari pauses it during idle or memory pressure.
  requestAnimationFrame(() => {
    if (
      props.active && isIntersecting.value && !document.hidden &&
      !prefersReducedMotion.value && !videoError.value && !player.error && player.paused
    ) {
      player.play().catch(() => {});
    }
  });
};

const svgContent = computed(() => {
  if (cardMedia.value.type === 'video' && !videoError.value) return '';
  return plateSVG(props.product, props.categoryName);
});

const handleVideoError = (event) => {
  // Removing/changing a source can dispatch a delayed error for the old URL.
  // Ignore it so it cannot mark the next card source as permanently broken.
  if (!videoSrc.value) return;
  const failedSource = event.currentTarget?.currentSrc;
  if (failedSource && failedSource !== videoSrc.value) return;

  console.warn('Card video failed to load:', event);
  videoReady.value = false;

  // Mobile Safari can evict a paused decoder after backgrounding or memory
  // pressure. Retry the same source before hiding the video card.
  if (videoRetryCount < 2 && videoRef.value) {
    const requestId = videoRequestId;
    videoRetryCount += 1;
    retryTimer = setTimeout(() => {
      retryTimer = null;
      if (requestId !== videoRequestId || !videoSrc.value || !videoRef.value) return;
      videoRef.value.load();
      if (!document.hidden && !prefersReducedMotion.value) {
        videoRef.value.play().catch(() => {});
      }
    }, 400 * videoRetryCount);
    return;
  }

  releaseVideoSource();
  videoError.value = true;
};

const setupObserver = (el) => {
  if (!el) return;

  // Explicitly set DOM muted property for mobile browser compatibility
  el.muted = true;

  if (typeof IntersectionObserver !== 'undefined') {
    observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          isIntersecting.value = entry.isIntersecting;
          if (entry.isIntersecting && props.active) {
            loadVideoSource();
          } else {
            // Keep the URL and buffered bytes attached so scrolling back does
            // not restart the network request from byte zero.
            pauseVideoPlayback();
          }
        });
      },
      {
        rootMargin: '60px 0px',
        threshold: 0.2,
      }
    );

    observer.observe(el);
  } else {
    isIntersecting.value = true;
    loadVideoSource();
  }
};

const cleanupObserver = () => {
  if (observer) {
    observer.disconnect();
    observer = null;
  }
  if (videoRef.value) {
    videoRef.value.pause();
    videoRef.value.removeAttribute('src');
    videoRef.value.load();
  }
  videoRequestId += 1;
  releaseVideoSource();
};

watch(() => props.active, (active) => {
  const player = videoRef.value;
  if (!active) {
    pauseVideoPlayback();
    return;
  }

  if (isIntersecting.value) loadVideoSource();
}, { flush: 'post' });

watch(videoRef, (newEl, oldEl) => {
  if (oldEl && observer) {
    observer.disconnect();
    observer = null;
  }
  if (newEl) {
    setupObserver(newEl);
  }
}, { flush: 'post' });

onMounted(() => {
  if (window.matchMedia) {
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    prefersReducedMotion.value = motionQuery.matches;
    motionQuery.addEventListener?.('change', onMotionPreferenceChange);
  }

  visibilityHandler = () => {
    if (videoRef.value) {
      if (document.hidden) {
        videoRef.value.pause();
      } else if (props.active && isIntersecting.value && !prefersReducedMotion.value) {
        videoRef.value.play().catch(() => {});
      }
    }
  };
  document.addEventListener('visibilitychange', visibilityHandler);
});

onUnmounted(() => {
  motionQuery?.removeEventListener?.('change', onMotionPreferenceChange);
  if (visibilityHandler) {
    document.removeEventListener('visibilitychange', visibilityHandler);
  }
  cleanupObserver();
});

function onMotionPreferenceChange(event) {
  prefersReducedMotion.value = event.matches;
  if (event.matches) {
    videoRef.value?.pause();
  } else if (props.active && isIntersecting.value) {
    loadVideoSource();
  }
}

const rng = rngOf((props.product.id || 'p') + 'z');
const duration = (13 + rng() * 8).toFixed(1);
const direction = rng() > 0.5 ? 'normal' : 'reverse';
</script>

<style scoped>
.plate-svg-wrap {
  width: 100%;
  height: 100%;
  display: block;
}
.plate-svg-wrap :deep(svg) {
  width: 100%;
  height: 100%;
  display: block;
}
.card-photo-wrap {
  position: relative;
  flex: 0 0 auto;
  aspect-ratio: 1 / 1;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--line);
  background: var(--card);
  box-shadow: 0 4px 14px rgba(46, 45, 43, 0.10);
  display: flex;
  align-items: center;
  justify-content: center;
}
.card-photo-wrap.m {
  width: 120px;
  height: 120px;
}
.card-photo-wrap.g {
  width: 82px;
  height: 82px;
  border-radius: 12px;
}
.card-photo-wrap.xl {
  width: min(74vw, 268px);
  height: min(74vw, 268px);
  border-radius: 20px;
}
.card-photo-video {
  width: 100%;
  height: 100%;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  display: block;
  pointer-events: none;
}

.card-photo-fallback {
  position: absolute;
  inset: 0;
  z-index: 1;
}

.card-photo-video {
  position: absolute;
  inset: 0;
  z-index: 2;
  opacity: 1;
  transition: opacity 160ms ease;
}

.card-photo-video:not(.is-ready) {
  opacity: 0;
}

.card-video-loading {
  position: absolute;
  inset: 0;
  z-index: 3;
  display: grid;
  place-items: center;
  pointer-events: none;
}

.card-video-loading span {
  width: 24px;
  height: 24px;
  border: 2px solid rgba(166, 124, 51, .2);
  border-top-color: var(--gold);
  border-radius: 50%;
  animation: card-video-loading-spin .8s linear infinite;
}

@keyframes card-video-loading-spin {
  to { transform: rotate(360deg); }
}
</style>
