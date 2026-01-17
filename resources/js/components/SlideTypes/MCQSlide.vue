<script setup>
import VideoPlayer from '@/components/VideoPlayer.vue'
import { ref } from 'vue'

const props = defineProps({
  question: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['answered'])

const selectedOptionIndices = ref([])
const isSubmitted = ref(false)

const options = computed(() => {
  const content = props.question.content || {}
  
  if (content.options) return content.options
  if (props.question.options) return props.question.options
  
  // Legacy support for when content IS the options array
  if (Array.isArray(content) && typeof content[0] === 'string') return content
  
  return []
})

const normalizedCorrectAnswers = computed(() => {
  const content = props.question.content || {}
  
  let raw = content.correctAnswer || props.question.correctAnswer || []
  
  // Handle stringified JSON array if necessary
  if (typeof raw === 'string') {
    try {
      const parsed = JSON.parse(raw)
      if (Array.isArray(parsed)) raw = parsed
    } catch (e) {
      // Not a JSON array, treat as single string answer
    }
  }
  
  const arr = Array.isArray(raw) ? raw : [raw]
  
  // Robustness: Split comma-separated strings if present (e.g. "1,0")
  const flatArr = []

  arr.forEach(item => {
    if (typeof item === 'string' && item.includes(',')) {
      flatArr.push(...item.split(','))
    } else {
      flatArr.push(item)
    }
  })
  
  return flatArr.map(val => String(val).trim())
})

const isMultiSelect = computed(() => {
  return normalizedCorrectAnswers.value.length > 1
})

const handleOptionClick = index => {
  if (isSubmitted.value) return

  const i = selectedOptionIndices.value.indexOf(index)
  
  if (isMultiSelect.value) {
    // Toggle selection for multi-select
    if (i === -1) {
      selectedOptionIndices.value.push(index)
    } else {
      selectedOptionIndices.value.splice(i, 1)
    }
    
    // Auto-submit if we have selected the same number of options as correct answers
    if (selectedOptionIndices.value.length === normalizedCorrectAnswers.value.length) {
      submitAnswer()
    }
  } else {
    // Single select - replace selection and auto-submit
    selectedOptionIndices.value = [index]
    submitAnswer()
  }
}

const submitAnswer = () => {
  if (selectedOptionIndices.value.length === 0) return
  
  isSubmitted.value = true
  
  const correctAnswers = normalizedCorrectAnswers.value

  // Normalize user answers to strings
  const userAnswers = selectedOptionIndices.value.map(String)

  // Check correctness:
  // 1. Same number of answers
  // 2. All user answers must be in correct answers
  const isCorrect = userAnswers.length === correctAnswers.length && 
    userAnswers.every(ans => correctAnswers.includes(ans))
  
  emit('answered', {
    correct: isCorrect,
    userAnswer: userAnswers,
  })
}

const getCardClass = index => {
  const baseClass = 'mb-4 cursor-pointer transition-all'
  const isSelected = selectedOptionIndices.value.includes(index)
  
  if (!isSubmitted.value) {
    return isSelected
      ? `${baseClass} border-primary elevation-4 bg-primary-subtle` 
      : `${baseClass} hover-elevation`
  }
  
  const correctAnswers = normalizedCorrectAnswers.value
    
  const isCorrectOption = correctAnswers.includes(String(index))
  
  if (isCorrectOption) return `${baseClass} bg-success-subtle border-success`
  if (isSelected && !isCorrectOption) return `${baseClass} bg-error-subtle border-error`
  
  return `${baseClass} opacity-50`
}
</script>

<template>
  <div
    class="mcq-slide mx-auto"
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
          :key="question.mediaUrl"
          :src="question.mediaUrl"
          :type="question.mediaUrl.includes('youtube') ? 'youtube' : (question.mediaUrl.includes('vimeo') ? 'vimeo' : 'hosted')"
          class="rounded-lg overflow-hidden elevation-2"
        />
      </div>
    </div>

    <div class="options-container">
      <VCard
        v-for="(option, index) in options"
        :key="index"
        :class="getCardClass(index)"
        variant="outlined"
        :disabled="isSubmitted"
        @click="handleOptionClick(index)"
      >
        <VCardText class="d-flex align-center py-4 px-6">
          <div class="flex-grow-1 text-body-1 font-weight-medium">
            {{ option }}
          </div>
          
          <VIcon
            v-if="isSubmitted"
            :icon="getCardClass(index).includes('success') ? 'tabler-check' : (getCardClass(index).includes('error') ? 'tabler-x' : '')"
            :color="getCardClass(index).includes('success') ? 'success' : 'error'"
          />
        </VCardText>
      </VCard>
    </div>

    <div
      v-if="isMultiSelect && !isSubmitted && selectedOptionIndices.length < normalizedCorrectAnswers.length"
      class="d-flex justify-center mt-6"
    >
      <div class="text-caption text-medium-emphasis">
        Select {{ normalizedCorrectAnswers.length }} options to submit
      </div>
    </div>
  </div>
</template>

<style scoped>
.hover-elevation:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.transition-all {
  transition: all 0.3s ease;
}
.bg-primary-subtle {
  background-color: rgba(var(--v-theme-primary), 0.1) !important;
}
.bg-success-subtle {
  background-color: rgba(var(--v-theme-success), 0.1) !important;
}
.bg-error-subtle {
  background-color: rgba(var(--v-theme-error), 0.1) !important;
}
</style>
