<script setup>
import VideoPlayer from '@/components/VideoPlayer.vue'
import { useDebounceFn } from '@vueuse/core'
import { ref } from 'vue'

const props = defineProps({
  question: {
    type: Object,
    required: true,
  },
  isExam: {
    type: Boolean,
    default: false,
  },
  modelValue: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['answered', 'update:modelValue'])

const getBlanksData = () => {
  if (Array.isArray(props.question.content)) {
    return props.question.content
  }
  
  return props.question.content?.blanks || []
}

const userAnswers = ref({}) // { blankIndex: value }
const isSubmitted = ref(false)

// Parse question text to find blanks
const parts = computed(() => {
  // Regex to split by square brackets
  // Example: "The capital is [Paris]." -> ["The capital is ", "[Paris]", "."]
  const text = props.question.questionText
  const regex = /(\[[^\]]+\])/g
  
  return text ? text.split(regex) : []
})

const blanks = computed(() => {
  const text = props.question.questionText
  const matches = text ? (text.match(/\[([^\]]+)\]/g) || []) : []
    
  // If we have content.correctAnswer (array of answers corresponding to blanks)
  // we should use that instead of parsing the blank text itself for the answer
  let correctAnswers = props.question.content?.correctAnswer || props.question.correctAnswer || []
  
  const blanksData = getBlanksData()
  if (blanksData.length > 0) {
    correctAnswers = blanksData.map(b => {
      // If correctAnswer is provided (could be index or value)
      const rawCorrect = b.correctAnswer !== undefined ? b.correctAnswer : b.answer
      
      // If it's an index and options exist, resolve to value
      if (b.options && Array.isArray(b.options)) {
        // Check if rawCorrect is a numeric index
        const index = parseInt(rawCorrect)
        if (!isNaN(index) && index >= 0 && index < b.options.length) {
          return b.options[index]
        }
      }
      
      return rawCorrect
    })
  }

  return matches.map((m, i) => {
    let answer = m.replace('[', '').replace(']', '')
        
    // If we have explicit correct answers array, use it
    // The array might contain strings or arrays of strings (alternatives)
    if (correctAnswers[i]) {
      answer = correctAnswers[i]
    }
        
    return {
      original: m,
      answer: answer, // This can be string or array
      index: i,
    }
  })
})

// Initialize user answers
const init = () => {
  // Sync from modelValue (Exam resume)
  if (props.isExam && props.modelValue) {
    userAnswers.value = { ...props.modelValue }
    
    return
  }

  blanks.value.forEach((b, i) => {
    userAnswers.value[i] = ''
  })
}

init()

// Debounced emit to prevent excessive API calls
const debouncedEmit = useDebounceFn(val => {
  emit('update:modelValue', { ...val })
}, 1000)

// Sync to modelValue
watch(userAnswers, newVal => {
  if (props.isExam) {
    debouncedEmit(newVal)
  }
}, { deep: true })

const submitAnswer = () => {
  isSubmitted.value = true
  
  // Calculate correctness
  const results = blanks.value.map((blank, index) => {
    const user = userAnswers.value[index] ? userAnswers.value[index].toLowerCase().trim() : ''
    let isCorrect = false
    
    if (Array.isArray(blank.answer)) {
      isCorrect = blank.answer.some(ans => user === String(ans).toLowerCase().trim())
    } else {
      isCorrect = user === String(blank.answer).toLowerCase().trim()
    }
    
    return {
      index,
      correct: isCorrect,
      userAnswer: userAnswers.value[index],
      correctAnswer: blank.answer,
    }
  })
  
  const allCorrect = results.every(r => r.correct)
  
  emit('answered', {
    correct: allCorrect,
    details: results,
  })
}

defineExpose({ submitAnswer })

const getFeedbackClass = index => {
  if (isSubmitted.value && !props.isExam) {
    const blank = blanks.value[index]
    const user = userAnswers.value[index] ? userAnswers.value[index].toLowerCase().trim() : ''
        
    let isCorrect = false
    if (Array.isArray(blank.answer)) {
      isCorrect = blank.answer.some(ans => user === String(ans).toLowerCase().trim())
    } else {
      isCorrect = user === String(blank.answer).toLowerCase().trim()
    }
        
    return isCorrect ? 'text-success font-weight-bold' : 'text-error text-decoration-line-through'
  }
  
  return ''
}

const mediaUrl = computed(() => props.question.mediaUrl)
const mediaType = computed(() => props.question.mediaType)
const audioUrl = computed(() => props.question.audioUrl)
const termText = computed(() => props.question.termText)
</script>

<template>
  <div
    class="fill-blank-slide mx-auto"
    style="max-width: 800px;"
  >
    <!-- Media Handling -->
    <div
      v-if="mediaUrl"
      class="mb-6 d-flex justify-center"
    >
      <div
        v-if="['image', 'image_with_audio'].includes(mediaType)"
        class="position-relative"
      >
        <VImg
          :src="mediaUrl"
          max-height="400"
          max-width="100%"
          class="rounded-lg"
          contain
        >
          <div
            v-if="mediaType === 'image_with_audio' && audioUrl"
            class="d-flex align-end justify-center w-100 h-100 pb-4"
            style="background: rgba(0,0,0,0.1)"
          >
            <div class="bg-surface rounded-pill px-3 py-1 elevation-2">
              <AppOverlayAudioPlayer :src="audioUrl" />
            </div>
          </div>
        </VImg>
      </div>

      <div
        v-else-if="['video', 'audio'].includes(mediaType)"
        class="w-100"
        style="max-width: 600px;"
      >
        <VideoPlayer
          :key="mediaUrl"
          :src="mediaUrl"
          :type="mediaUrl.includes('youtube') ? 'youtube' : (mediaUrl.includes('vimeo') ? 'vimeo' : 'hosted')"
          class="rounded-lg overflow-hidden elevation-2"
        />
      </div>
    </div>

    <!-- Term Display -->
    <div
      v-if="termText"
      class="text-h3 text-center mb-6 font-weight-bold text-primary"
    >
      {{ termText }}
    </div>

    <div class="text-h5 text-center mb-8 lh-loose">
      <template
        v-for="(part, i) in parts"
        :key="i"
      >
        <!-- If part is a blank (starts with [) -->
        <span v-if="part.startsWith('[') && part.endsWith(']')">
          <!-- Find blank index -->
          <template v-for="(blank, bIndex) in blanks">
            <span
              v-if="blank.original === part"
              :key="bIndex"
              class="d-inline-block"
            >
                        
              <!-- Input for Type Fill Blank -->
              <VTextField
                v-model="userAnswers[bIndex]"
                variant="underlined"
                density="compact"
                class="d-inline-block mx-2"
                style="width: 150px; text-align: center;"
                :disabled="isSubmitted && !isExam"
                :class="getFeedbackClass(bIndex)"
                hide-details
              />
              
              <!-- Correct answer display if wrong -->
              <div
                v-if="isSubmitted && !getFeedbackClass(bIndex).includes('text-success') && !isExam"
                class="text-caption text-success mt-1"
              >
                {{ Array.isArray(blank.answer) ? blank.answer[0] : blank.answer }}
              </div>
            </span>
          </template>
        </span>
        
        <!-- Normal text -->
        <span
          v-else
          class="text-high-emphasis"
        >{{ part }}</span>
      </template>
    </div>
  </div>
</template>

<style scoped>
.lh-loose {
  line-height: 2 !important;
}
</style>
