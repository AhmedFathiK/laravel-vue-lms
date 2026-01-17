<script setup>
import { ref } from 'vue'
import VuePlyr from '@skjnldsv/vue-plyr'
import '@skjnldsv/vue-plyr/dist/vue-plyr.css'

const props = defineProps({
  src: {
    type: String,
    required: true,
  },
})

const plyrRef = ref(null)
const isPlaying = ref(false)

const onReady = () => {
  const player = plyrRef.value.player
  
  player.on('play', () => isPlaying.value = true)
  player.on('pause', () => isPlaying.value = false)
  player.on('ended', () => isPlaying.value = false)
}

const togglePlay = () => {
  if (!plyrRef.value?.player) return

  const player = plyrRef.value.player
  if (isPlaying.value) {
    player.pause()
  } else {
    player.play()
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
      <VuePlyr 
        ref="plyrRef" 
        :options="{ controls: [] }"
        @ready="onReady"
      >
        <audio>
          <source
            :src="src"
            type="audio/mpeg"
          >
        </audio>
      </VuePlyr>
    </div>
  </div>
</template>
