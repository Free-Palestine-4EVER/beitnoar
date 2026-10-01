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
      preload="auto"
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
import { acquireSharedVideoSource, prioritizeSharedVideoSource } from '../utils/sharedVideoSource';

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
const cardMedia = computed(() => getProductCardMedia(props.product));

const videoRef = ref(null);
const videoError = ref(false);
const videoReady = ref(false);
const prefersReducedMotion = ref(false);
const isVisible = ref(false);
const videoSrc = ref(null);
let observer = null;
let visibilityHandler = null;
let motionQuery = null;
let videoLease = null;
let videoRequestId = 0;
let requestedSource = null;

const pauseVideoPlayback = () => {
  videoRef.value?.pause();
};

const playVideoWhenReady = () => {
  const player = videoRef.value;
  if (!player || !videoSrc.value) return;
  player.muted = true;
  if (props.active && isVisible.value && !document.hidden && !prefersReducedMotion.value) {
    player.play().catch(() => {});
  } else {
    player.pause();
  }
};

const loadVideoSource = async (priority = false) => {
  const source = cardMedia.value.src;
  if (!source || videoError.value) return;

  if (requestedSource === source) {
    if (priority) prioritizeSharedVideoSource(source);
    playVideoWhenReady();
    return;
  }

  requestedSource = source;
  const requestId = ++videoRequestId;
  try {
    const lease = await acquireSharedVideoSource(source, { priority });
    if (requestId !== videoRequestId) {
      lease.release();
      return;
    }

    videoLease = lease;
    videoSrc.value = lease.src;
    await nextTick();
    playVideoWhenReady();
  } catch (error) {
    if (requestId !== videoRequestId) return;
    console.error(`Could not load product video for ${props.product?.id ?? 'unknown product'}:`, error);
    videoError.value = true;
  }
};

const resetVideo = () => {
  videoRequestId += 1;
  videoLease?.release();
  videoLease = null;
  requestedSource = null;
  videoSrc.value = null;
  videoReady.value = false;
  videoError.value = false;
  nextTick(() => loadVideoSource(isVisible.value));
};

watch(() => cardMedia.value.src, resetVideo);

const handleVideoReady = (event) => {
  if (!videoSrc.value) return;
  const currentSource = event.currentTarget.currentSrc;
  if (currentSource && currentSource !== videoSrc.value) return;
  videoReady.value = true;
  playVideoWhenReady();
};

const handleVideoPlaying = (event) => {
  if (!videoSrc.value) return;
  const currentSource = event.currentTarget.currentSrc;
  if (currentSource && currentSource !== videoSrc.value) return;
  videoReady.value = true;
};

const handleCardVideoPause = (event) => {
  const player = event.currentTarget;
  if (props.active && isVisible.value && !document.hidden && !prefersReducedMotion.value && !player.error) {
    requestAnimationFrame(playVideoWhenReady);
  }
};

const svgContent = computed(() => {
  if (cardMedia.value.type === 'video' && !videoError.value) return '';
  return plateSVG(props.product, props.categoryName);
});

const handleVideoError = (event) => {
  if (!videoSrc.value) return;
  const failedSource = event.currentTarget?.currentSrc;
  if (failedSource && failedSource !== videoSrc.value) return;

  console.error(`Product video could not be played for ${props.product?.id ?? 'unknown product'}:`, event);
  videoReady.value = false;
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
          isVisible.value = entry.isIntersecting && entry.intersectionRatio >= 0.2;
          if (isVisible.value && props.active) {
            loadVideoSource(true);
          } else {
            pauseVideoPlayback();
          }
        });
      },
      { threshold: 0.2 }
    );
    observer.observe(el);
  } else {
    isVisible.value = true;
  }

  // Queue every card's complete video immediately. Visible clips move to the
  // front of the shared queue; offscreen clips continue downloading as well.
  loadVideoSource(isVisible.value);
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
  videoLease?.release();
  videoLease = null;
};

watch(() => props.active, (active) => {
  if (!active) {
    pauseVideoPlayback();
    return;
  }

  if (isVisible.value) playVideoWhenReady();
}, { flush: 'post' });

watch(videoRef, (newEl, oldEl) => {
  if (oldEl) {
    observer?.disconnect();
    observer = null;
    oldEl.pause();
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
      } else if (props.active && isVisible.value && !prefersReducedMotion.value) {
        playVideoWhenReady();
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
  } else if (props.active && isVisible.value) {
    playVideoWhenReady();
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
