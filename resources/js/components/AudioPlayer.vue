<script setup>
import { computed, ref, watch } from 'vue'
import VuePlyr from '@skjnldsv/vue-plyr'
import '@skjnldsv/vue-plyr/dist/vue-plyr.css'

const props = defineProps({
  src: {
    type: String,
    required: true,
  },
  autoplay: {
    type: Boolean,
    default: false,
  },
})

const plyrRef = ref(null)

// Unified Plyr options
const plyrOptions = computed(() => ({
  autoplay: props.autoplay,
  controls: [
    'play',
    'progress',
    'current-time',
    'mute',
    'volume',
    'settings',
  ],
  settings: ['speed', 'loop'],
  speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
}))

// Unified Plyr source configuration
const plyrSource = computed(() => {
  if (!props.src) return null

  return {
    type: 'audio',
    sources: [{ src: props.src, type: 'audio/mp3' }],
  }
})

// Watch for source changes
watch(plyrSource, newSource => {
  const player = plyrRef.value?.player
  if (player && newSource) {
    player.source = newSource
  }
}, { deep: true })

const onReady = event => {
  const player = event?.detail?.plyr
  if (!player) return

  if (plyrSource.value) {
    player.source = plyrSource.value
  }

  if (props.autoplay) {
    player.play().catch(() => {
      /* ignore autoplay blocks */
    })
  }
}
</script>

<template>
  <div class="audio-player-wrapper">
    <VuePlyr
      :key="src"
      ref="plyrRef"
      :options="plyrOptions"
      @ready="onReady"
    >
      <audio
        playsinline
        crossorigin
      />
    </VuePlyr>
  </div>
</template>

<style scoped>
.audio-player-wrapper {
  width: 100%;
  border-radius: 8px;
  overflow: hidden;
  background-color: transparent;
}

:deep(.plyr--audio) {
  --plyr-color-main: rgb(var(--v-theme-primary));
  --plyr-audio-controls-background: transparent;
  --plyr-audio-control-color: rgb(var(--v-theme-on-surface));
  --plyr-audio-control-color-hover: #fff;
  --plyr-audio-control-background-hover: rgb(var(--v-theme-primary));
  --plyr-font-family: 'Public Sans', sans-serif;
  width: 100%;
}

:deep(.plyr--full-ui) {
  border-radius: 8px;
}
</style>
