<script setup>
import AppIconAudioPlayer from '@/components/app-form-elements/AppIconAudioPlayer.vue'
import AppOverlayAudioPlayer from '@/components/app-form-elements/AppOverlayAudioPlayer.vue'
import { VideoPlayer } from '@videojs-player/vue'
import 'video.js/dist/video-js.css'
import { computed, onMounted } from 'vue'

const props = defineProps({
  slide: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['completed'])

onMounted(() => {
  emit('completed')
})

// Helper to access term data whether it's nested or flat
const term = computed(() => props.slide.term || props.slide)
</script>

<template>
  <div
    class="term-slide mx-auto"
    style="max-width: 600px;"
  >
    <!-- Term Card -->
    <VCard class="term-card overflow-hidden rounded-lg elevation-4 mx-auto">
      <!-- Media Section -->
      <div class="media-section position-relative">
        <!-- Image & Image with Audio -->
        <div
          v-if="['image', 'image_with_audio'].includes(term.mediaType) || term.image"
          class="term-img-wrapper position-relative"
        >
          <VImg
            :src="term.mediaUrl || term.image"
            cover
            aspect-ratio="1.7"
            class="term-image"
          >
            <!-- Overlay Player for Image with Audio -->
            <div
              v-if="term.mediaType === 'image_with_audio'"
              class="audio-overlay d-flex align-end justify-center w-100 h-100 pb-6"
            >
              <div class="player-pill bg-surface rounded-pill px-4 py-2 d-flex align-center elevation-3">
                <AppOverlayAudioPlayer 
                  v-if="term.audioUrl"
                  :src="term.audioUrl" 
                />
              </div>
            </div>
          </VImg>
        </div>

        <!-- Video -->
        <div 
          v-else-if="term.mediaType === 'video' || term.video" 
          class="term-video-wrapper w-100"
        >
          <VideoPlayer
            :src="term.mediaUrl || term.video"
            controls
            class="rounded-lg overflow-hidden elevation-2"
            fluid
          />
        </div>
      </div>

      <!-- Content Section -->
      <VCardText class="text-center pa-6">
        <h2 class="text-h4 font-weight-bold mb-2">
          {{ term.term || term.title }}
        </h2>
        <p class="text-h6 text-medium-emphasis mb-0">
          {{ term.meaning || term.description }}
        </p>
      </VCardText>
    </VCard>

    <!-- Example Section -->
    <div 
      v-if="term.example" 
      class="example-section mt-8 d-flex align-start gap-4 px-2 mx-auto"
    >
      <div class="pt-1">
        <AppIconAudioPlayer 
          v-if="term.exampleAudioUrl" 
          :src="term.exampleAudioUrl" 
        />
        <VIcon 
          v-else 
          icon="tabler-quote" 
          color="primary" 
          size="large"
        />
      </div>
      <div>
        <p class="text-h6 font-weight-medium mb-1">
          {{ term.example }}
        </p>
        <p 
          v-if="term.exampleTranslation" 
          class="text-body-1 text-medium-emphasis"
        >
          {{ term.exampleTranslation }}
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.term-img-wrapper {
  min-height: 200px;
  background-color: rgb(var(--v-theme-surface));
}

.audio-overlay {
  background: linear-gradient(to top, rgba(0,0,0,0.4) 0%, transparent 100%);
}

.player-pill {
  min-width: 200px;
}
</style>
