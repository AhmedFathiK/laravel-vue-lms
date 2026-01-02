<script setup>
import { VideoPlayer } from '@videojs-player/vue'
import 'video.js/dist/video-js.css'
import { ref } from 'vue'

const props = defineProps({
  src: {
    type: String,
    required: true,
  },
})

const player = ref(null)
const isPlaying = ref(false)

const handleMounted = (payload) => {
  player.value = payload.player
  player.value.on('play', () => isPlaying.value = true)
  player.value.on('pause', () => isPlaying.value = false)
  player.value.on('ended', () => isPlaying.value = false)
}

const togglePlay = () => {
  if (!player.value) return
  
  if (isPlaying.value) {
    player.value.pause()
  } else {
    player.value.play()
  }
}
</script>

<template>
  <div class="d-inline-flex align-center gap-2">
    <VBtn
      icon
      variant="text"
      size="small"
      color="primary"
      @click="togglePlay"
    >
      <VIcon :icon="isPlaying ? 'tabler-player-pause' : 'tabler-volume'" />
    </VBtn>
    
    <div class="d-none">
      <VideoPlayer
        :src="src"
        controls
        :height="40"
        :width="200"
        @mounted="handleMounted"
      />
    </div>
  </div>
</template>
