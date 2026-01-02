<script setup>
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

const content = computed(() => {
  let c = props.slide.content
  if (!c) return ''
  
  // Handle localized object (e.g. { en: "..." })
  if (typeof c === 'object') {
    // Try to get current locale if we had access to i18n, otherwise default to 'en' or first key
    c = c.en || c[Object.keys(c)[0]]
  }
  
  // Handle double encoded JSON if applicable (e.g. "{\"en\":\"...\"}")
  try {
    if (typeof c === 'string' && (c.trim().startsWith('{') || c.trim().startsWith('['))) {
      const parsed = JSON.parse(c)

      // If parsed is an object with 'en', return that
      if (typeof parsed === 'object' && !Array.isArray(parsed) && parsed.en) {
        return parsed.en
      }

      // If parsed is just a string or other object, return it if it makes sense?
      // But for now, let's assume if it parsed to object, we might want a specific key.
      // If it parsed to string (JSON stringified string), return that.
      return parsed
    }
  } catch (e) {
    // Not JSON, return original string
  }
  
  return c
})

onMounted(() => {
  emit('completed')
})
</script>

<template>
  <div
    class="explanation-slide mx-auto"
    style="max-width: 800px;"
  >
    <div class="text-h3 text-center mb-6 font-weight-bold text-primary">
      {{ slide.title }}
    </div>
    
    <!-- Media Handling -->
    <div
      v-if="slide.mediaUrl"
      class="mb-8 d-flex justify-center"
    >
      <VImg
        v-if="slide.mediaType === 'image'"
        :src="slide.mediaUrl"
        max-height="400"
        class="rounded-lg elevation-2"
        contain
      />
      
      <div
        v-else-if="['video', 'audio'].includes(slide.mediaType)"
        class="w-100"
        style="max-width: 600px;"
      >
        <VideoPlayer
          :src="slide.mediaUrl"
          controls
          class="rounded-lg overflow-hidden elevation-2"
          fluid
        />
      </div>
    </div>
    
    <VCard class="pa-8 elevation-2 rounded-lg bg-surface">
      <!-- eslint-disable vue/no-v-html -->
      <div
        class="text-body-1 text-high-emphasis lh-loose"
        v-html="content"
      />
    <!-- eslint-enable vue/no-v-html -->
    </VCard>
  </div>
</template>

<style scoped>
.lh-loose {
    line-height: 1.8;
}
</style>
