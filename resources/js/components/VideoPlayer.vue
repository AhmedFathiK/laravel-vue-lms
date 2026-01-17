<script setup>
import { computed, ref, watch } from 'vue'
import VuePlyr from '@skjnldsv/vue-plyr'
import '@skjnldsv/vue-plyr/dist/vue-plyr.css'

const props = defineProps({
  src: {
    type: String,
    required: true,
  },
  type: {
    type: String,
    required: true, // 'youtube', 'vimeo', 'hosted'
  },
  autoplay: {
    type: Boolean,
    default: false,
  },
})

const plyrRef = ref(null)

// Extraction logic for Vimeo ID and Hash
const vimeoConfig = computed(() => {
  const fallback = { id: '', hash: '' }
  if (props.type?.toLowerCase() !== 'vimeo' || !props.src) return fallback

  // Handle vimeo.com/{id}/{hash}
  const hashMatch = props.src.match(/vimeo\.com\/(\d+)\/([a-z0-9]+)/i)
  if (hashMatch) {
    return { id: hashMatch[1], hash: hashMatch[2] }
  }

  // Handle player.vimeo.com/video/{id}?h={hash}
  const playerMatch = props.src.match(/player\.vimeo\.com\/video\/(\d+)(?:\?h=([a-z0-9]+))?/i)
  if (playerMatch) {
    return { id: playerMatch[1], hash: playerMatch[2] || '' }
  }

  // Handle standard vimeo.com/{id}
  const standardMatch = props.src.match(/vimeo\.com\/(\d+)/i)
  if (standardMatch) {
    return { id: standardMatch[1], hash: '' }
  }

  // If it's just an ID
  if (/^\d+$/.test(props.src)) {
    return { id: props.src, hash: '' }
  }

  return fallback
})

// Extraction logic for YouTube ID
const youtubeId = computed(() => {
  if (props.type?.toLowerCase() !== 'youtube' || !props.src) return ''

  if (/^[\w-]{11}$/.test(props.src)) return props.src

  const regExp = /^.*(?:youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/
  const match = props.src.match(regExp)

  return (match && match[1].length === 11) ? match[1] : ''
})

// Unified Plyr options
const plyrOptions = computed(() => {
  const options = {
    autoplay: props.autoplay,
    muted: props.autoplay,
    controls: [
      'play-large',
      'play',
      'progress',
      'current-time',
      'mute',
      'volume',
      'captions',
      'settings',
      'pip',
      'airplay',
      'fullscreen',
    ],
    settings: ['captions', 'quality', 'speed', 'loop'],
    speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
  }

  if (props.type?.toLowerCase() === 'vimeo') {
    options.vimeo = {
      byline: false,
      portrait: false,
      title: false,
      transparent: false,
      speed: true,
      gesture: 'media',
    }
    if (vimeoConfig.value.hash) {
      options.vimeo.h = vimeoConfig.value.hash
    }
  }

  if (props.type?.toLowerCase() === 'youtube') {
    options.youtube = {
      noCookie: true,
      rel: 0,
      showinfo: 0,
      iv_load_policy: 3, // eslint-disable-line camelcase
      modestbranding: 1,
    }
  }

  return options
})

// Unified Plyr source configuration
const plyrSource = computed(() => {
  const type = props.type?.toLowerCase()
  
  if (type === 'youtube' && youtubeId.value) {
    return {
      type: 'video',
      sources: [{ src: youtubeId.value, provider: 'youtube' }],
    }
  }

  if (type === 'vimeo' && vimeoConfig.value.id) {
    return {
      type: 'video',
      sources: [{ src: vimeoConfig.value.id, provider: 'vimeo' }],
    }
  }

  if (type === 'hosted' && props.src) {
    return {
      type: 'video',
      sources: [{ src: props.src, provider: 'html5' }],
    }
  }

  return null
})

// Watch for source changes to update player manually if component isn't re-mounted
watch(plyrSource, newSource => {
  const player = plyrRef.value?.player
  if (player && newSource) {
    player.source = newSource
  }
}, { deep: true })

const onReady = event => {
  const player = event?.detail?.plyr
  if (!player) return

  // Manually set the source as the prop binding might be falling through
  if (plyrSource.value) {
    player.source = plyrSource.value
  }

  if (props.autoplay) {
    player.muted = true
    player.play().catch(() => {
      /* ignore autoplay blocks */
    })
  }
}
</script>

<template>
  <div class="video-player-wrapper">
    <!-- Using :key ensures a fresh player instance when the source changes, avoiding black screens -->
    <VuePlyr
      :key="src"
      ref="plyrRef"
      :options="plyrOptions"
      @ready="onReady"
    >
      <video
        playsinline
        crossorigin
      />
    </VuePlyr>
  </div>
</template>

<style scoped>
.video-player-wrapper {
  width: 100%;
  border-radius: 12px;
  overflow: hidden;
  background-color: #000;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  position: relative;
  aspect-ratio: 16/9;
}

:deep(.plyr) {
  --plyr-color-main: rgb(var(--v-theme-primary));
  --plyr-video-background: #000;
  --plyr-menu-background: rgba(20, 20, 20, 0.95);
  --plyr-menu-color: #fff;
  --plyr-font-family: 'Public Sans', sans-serif;
  height: 100%;
  width: 100%;
  border-radius: 12px;
}

:deep(.plyr--full-ui) {
  border-radius: 12px;
}

:deep(.plyr__video-wrapper) {
  aspect-ratio: 16/9;
  border-radius: 12px;
}

:deep(.plyr__control--overlaid) {
  background: rgba(var(--v-theme-primary), 0.85);
}

:deep(.plyr--video .plyr__control.plyr__tab-focus),
:deep(.plyr--video .plyr__control:hover),
:deep(.plyr--video .plyr__control[aria-expanded=true]) {
  background: rgb(var(--v-theme-primary));
}

:deep(.plyr__menu__container .plyr__control[role=menuitemradio][aria-checked=true]::before) {
  background: rgb(var(--v-theme-primary));
}

:deep(.plyr--video) {
  height: 100% !important;
}
</style>
