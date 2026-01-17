<script setup>
import VideoPlayer from '@/components/VideoPlayer.vue'
import { ref } from 'vue'
import { SlickItem, SlickList } from 'vue-slicksort'

const props = defineProps({
  question: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['answered'])

const getItems = () => {
  if (Array.isArray(props.question.content)) {
    return props.question.content
  }
  
  return props.question.content?.items || props.question.items || props.question.options || []
}

const items = ref([])
const isSubmitted = ref(false)

// Initialize items
const init = () => {
  // Assuming question.items is array of strings or objects
  // If it's a reordering question, we should probably present them shuffled
  // and let the user order them.
  // Or if question.options is used.
    
  // Let's assume question.items contains the items to order.
  // We shuffle them initially.
  const sourceItems = getItems()
    
  // Create objects to track original index (which implies correct order)
  const initial = sourceItems.map((text, i) => ({ 
    text, 
    originalIndex: i,
    id: i, 
  }))
    
  // Shuffle
  for (let i = initial.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));

    [initial[i], initial[j]] = [initial[j], initial[i]]
  }
    
  items.value = initial
}

init()

const submitAnswer = () => {
  isSubmitted.value = true
  
  // Check if items are in correct order (originalIndex 0, 1, 2...)
  const isCorrect = items.value.every((item, index) => item.originalIndex === index)
  
  emit('answered', {
    correct: isCorrect,
    userAnswer: items.value.map(i => i.text),
  })
}

defineExpose({ submitAnswer })

const getItemClass = index => {
  const base = 'pa-4 mb-2 bg-surface border rounded cursor-move d-flex align-center gap-2 elevation-1'
    
  if (isSubmitted.value) {
    // Highlight correct positions?
    const item = items.value[index]
    const isPositionCorrect = item.originalIndex === index
    
    return isPositionCorrect 
      ? `${base} border-success bg-success-subtle`
      : `${base} border-error bg-error-subtle`
  }
    
  return base
}
</script>

<template>
  <div
    class="reordering-slide mx-auto"
    style="max-width: 800px;"
  >
    <div class="text-h4 text-center mb-6 font-weight-bold">
      {{ question.questionText }}
    </div>

    <!-- Media Handling -->
    <div
      v-if="question.mediaUrl"
      class="mb-6 d-flex justify-center"
    >
      <VImg
        v-if="question.mediaType === 'image'"
        :src="question.mediaUrl"
        max-height="400"
        class="rounded-lg"
        contain
      />
      <div
        v-else-if="['video', 'audio'].includes(question.mediaType)"
        class="w-100"
        style="max-width: 600px;"
      >
        <VideoPlayer
          :key="question.mediaUrl || question.media_url"
          :src="question.mediaUrl || question.media_url"
          :type="(question.mediaUrl || question.media_url)?.includes('youtube') ? 'youtube' : ((question.mediaUrl || question.media_url)?.includes('vimeo') ? 'vimeo' : 'hosted')"
          class="rounded-lg overflow-hidden elevation-2"
        />
      </div>
    </div>

    <div class="reordering-container">
      <SlickList 
        v-model:list="items" 
        axis="y" 
        lock-axis="y"
        :disabled="isSubmitted"
        class="list-group"
      >
        <SlickItem 
          v-for="(item, index) in items" 
          :key="item.id" 
          :index="index"
          class="list-group-item"
        >
          <div :class="getItemClass(index)">
            <VIcon
              icon="tabler-drag-drop"
              color="medium-emphasis"
            />
            <span class="text-body-1">{{ item.text }}</span>
          </div>
        </SlickItem>
      </SlickList>
    </div>
  </div>
</template>

<style scoped>
.reordering-slide {
  user-select: none;
}
.bg-success-subtle {
  background-color: rgba(var(--v-theme-success), 0.1) !important;
}
.bg-error-subtle {
  background-color: rgba(var(--v-theme-error), 0.1) !important;
}
</style>
