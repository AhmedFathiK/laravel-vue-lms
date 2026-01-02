<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  src: {
    type: String,
    required: true,
  },
})

const audio = ref(null)
const isPlaying = ref(false)
const duration = ref(0)
const currentTime = ref(0)
const playbackRate = ref(1)
const isDragging = ref(false)

const playbackRates = [0.5, 0.75, 1, 1.25, 1.5, 2]

const togglePlay = () => {
  if (audio.value.paused) {
    audio.value.play()
  } else {
    audio.value.pause()
  }
}

const onTimeUpdate = () => {
  if (!isDragging.value) {
    currentTime.value = audio.value.currentTime
  }
}

const onLoadedMetadata = () => {
  duration.value = audio.value.duration
}

const onEnded = () => {
  isPlaying.value = false
  currentTime.value = 0
  audio.value.currentTime = 0
}

const onPlay = () => isPlaying.value = true
const onPause = () => isPlaying.value = false

const onSeekStart = () => {
  isDragging.value = true
}

const onSeekEnd = val => {
  isDragging.value = false
  audio.value.currentTime = val
}

const seek = value => {
  // While dragging, just update the slider visual
  currentTime.value = value
}

const setSpeed = rate => {
  playbackRate.value = rate
  audio.value.playbackRate = rate
}

const formatTime = seconds => {
  if (!seconds) return '00:00'
  const m = Math.floor(seconds / 60)
  const s = Math.floor(seconds % 60)
  
  return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
}

watch(() => props.src, newSrc => {
  if(audio.value) {
    audio.value.load()
    currentTime.value = 0
    isPlaying.value = false
  }
})
</script>

<template>
  <div class="d-flex align-center flex-grow-1 gap-2 w-100">
    <!-- Play/Pause -->
    <VBtn
      icon
      variant="text"
      size="small"
      color="primary"
      @click="togglePlay"
    >
      <VIcon :icon="isPlaying ? 'tabler-player-pause' : 'tabler-player-play'" />
    </VBtn>

    <!-- Progress -->
    <div
      class="flex-grow-1 d-flex align-center mx-2"
      style="min-width: 100px;"
    >
      <VSlider
        :model-value="currentTime"
        :max="duration"
        :min="0"
        hide-details
        color="primary"
        track-color="grey-lighten-2"
        track-fill-color="primary"
        thumb-size="12"
        @update:model-value="seek"
        @start="onSeekStart"
        @end="onSeekEnd"
      />
    </div>

    <!-- Time -->
    <div
      class="text-caption text-medium-emphasis"
      style="min-width: 35px;"
    >
      {{ formatTime(currentTime) }}
    </div>

    <!-- Speed -->
    <VMenu location="top">
      <template #activator="{ props }">
        <VBtn
          v-bind="props"
          variant="text"
          size="small"
          class="px-0 text-caption font-weight-bold"
          style="min-width: 35px;"
        >
          {{ playbackRate }}x
        </VBtn>
      </template>
      <VList density="compact">
        <VListItem
          v-for="rate in playbackRates"
          :key="rate"
          :value="rate"
          :active="playbackRate === rate"
          density="compact"
          min-height="32"
          @click="setSpeed(rate)"
        >
          <VListItemTitle class="text-caption">
            {{ rate }}x
          </VListItemTitle>
        </VListItem>
      </VList>
    </VMenu>

    <audio
      ref="audio"
      :src="src"
      class="d-none"
      @timeupdate="onTimeUpdate"
      @loadedmetadata="onLoadedMetadata"
      @ended="onEnded"
      @play="onPlay"
      @pause="onPause"
    />
  </div>
</template>

<style scoped>
:deep(.v-slider-track__background) {
    opacity: 0.5;
}
</style>
