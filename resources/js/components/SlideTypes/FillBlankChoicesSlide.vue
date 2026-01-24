<script setup>
import VideoPlayer from '@/components/VideoPlayer.vue'
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
    type: [Object, Array],
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
const activeBlankIndex = ref(null)

// Sync from modelValue if provided (Exam Mode)
watch(() => props.modelValue, newVal => {
  if (props.isExam && newVal && JSON.stringify(newVal) !== JSON.stringify(userAnswers.value)) {
    // Break infinite loop: only update if different
    userAnswers.value = { ...newVal }
  }
}, { immediate: true, deep: true })

// Sync to modelValue (Exam Mode)
watch(userAnswers, newVal => {
  if (props.isExam) {
    // Always emit a clone so parent gets new reference and detects change
    emit('update:modelValue', { ...newVal })
  }
}, { deep: true })

// Parse question text to find blanks
const parts = computed(() => {
  const text = props.question.questionText
  const regex = /(\[[^\]]+\])/g
  
  return text ? text.split(regex) : []
})

const blanks = computed(() => {
  const text = props.question.questionText
  const matches = text ? (text.match(/\[([^\]]+)\]/g) || []) : []
    
  const blanksData = getBlanksData()

  return matches.map((m, i) => {
    const data = blanksData[i] || {}
    const options = data.options || []
    
    // In fill_blank_choices, correctAnswer is often an index in the options array
    let correctAnswer = data.correctAnswer
    const index = parseInt(correctAnswer)
    if (!isNaN(index) && index >= 0 && index < options.length) {
      correctAnswer = options[index]
    }
        
    return {
      original: m,
      answer: correctAnswer,
      options: options,
      index: i,
    }
  })
})

// Initialize user answers
const init = () => {
  blanks.value.forEach((b, i) => {
    userAnswers.value[i] = ''
  })
}

init()

const handleBlankClick = index => {
  if (isSubmitted.value && !props.isExam) return
  
  // If clicking an already filled blank, clear it and make it active
  if (userAnswers.value[index]) {
    userAnswers.value[index] = ''
  }
  
  activeBlankIndex.value = index
}

const selectOption = option => {
  if ((isSubmitted.value && !props.isExam) || activeBlankIndex.value === null) return
  
  userAnswers.value[activeBlankIndex.value] = option
  activeBlankIndex.value = null // Hide choices after selection
  
  // Check if all blanks are filled
  const allFilled = blanks.value.every((_, i) => userAnswers.value[i] !== '')
  if (allFilled && !props.isExam) {
    submitAnswer()
  }
}

const submitAnswer = () => {
  if (isSubmitted.value) return
  isSubmitted.value = true
  
  const results = blanks.value.map((blank, index) => {
    const user = userAnswers.value[index] ? userAnswers.value[index].toLowerCase().trim() : ''
    const correct = String(blank.answer).toLowerCase().trim()
    const isCorrect = user === correct
    
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

const getBlankClass = index => {
  const base = 'blank-box d-inline-flex align-center justify-center mx-1 transition-all'
  
  if (isSubmitted.value && !props.isExam) {
    const blank = blanks.value[index]
    const user = userAnswers.value[index] ? userAnswers.value[index].toLowerCase().trim() : ''
    const correct = String(blank.answer).toLowerCase().trim()
    const isCorrect = user === correct
    
    return `${base} ${isCorrect ? 'is-correct' : 'is-error'}`
  }
  
  if (activeBlankIndex.value === index) {
    return `${base} is-active`
  }
  
  if (userAnswers.value[index]) {
    return `${base} is-filled`
  }
  
  return base
}

const currentChoices = computed(() => {
  if (activeBlankIndex.value === null) return []
  
  const blank = blanks.value[activeBlankIndex.value]
  
  return blank ? blank.options : []
})

const showChoicesBox = computed(() => {
  return activeBlankIndex.value !== null && !userAnswers.value[activeBlankIndex.value] && (!isSubmitted.value || props.isExam)
})

const mediaUrl = computed(() => props.question.mediaUrl)
const mediaType = computed(() => props.question.mediaType)
const audioUrl = computed(() => props.question.audioUrl)
const termText = computed(() => props.question.termText)
</script>

<template>
  <div
    class="fill-blank-choices-slide mx-auto px-4"
    style="max-width: 900px;"
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
          max-height="300"
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

    <!-- Sentence with Blanks -->
    <div class="sentence-container text-h4 text-center mb-12 lh-loose">
      <template
        v-for="(part, i) in parts"
        :key="i"
      >
        <template v-if="part.startsWith('[') && part.endsWith(']')">
          <template v-for="(blank, bIndex) in blanks">
            <div
              v-if="blank.original === part"
              :key="bIndex"
              :class="getBlankClass(bIndex)"
              @click="handleBlankClick(bIndex)"
            >
              <span v-if="userAnswers[bIndex]">{{ userAnswers[bIndex] }}</span>
              <span
                v-else
                class="blank-placeholder"
              >&nbsp;</span>
              
              <!-- Correct answer hint on error -->
              <div
                v-if="isSubmitted && !getBlankClass(bIndex).includes('is-correct') && !isExam"
                class="correct-hint"
              >
                {{ blank.answer }}
              </div>
            </div>
          </template>
        </template>
        
        <span
          v-else
          class="text-high-emphasis"
        >{{ part }}</span>
      </template>
    </div>

    <!-- Choices Container Box -->
    <div
      class="choices-box-wrapper rounded-xl pa-8 transition-all d-flex align-center justify-center"
      :class="{ 'has-choices': showChoicesBox }"
    >
      <div
        v-if="showChoicesBox"
        class="d-flex flex-wrap gap-4 justify-center animate__animated animate__fadeInUp"
      >
        <VBtn
          v-for="option in currentChoices"
          :key="option"
          variant="outlined"
          class="choice-btn rounded-lg text-none px-6"
          size="large"
          @click="selectOption(option)"
        >
          {{ option }}
        </VBtn>
      </div>
      <div
        v-else-if="!isSubmitted || isExam"
        class="text-body-1 text-disabled italic animate__animated animate__fadeIn"
      >
        Click a blank to see choices
      </div>
    </div>
  </div>
</template>

<style scoped>
.lh-loose {
  line-height: 2.8 !important;
}

.blank-box {
  min-width: 100px;
  height: 54px;
  border-bottom: 2px dashed rgba(var(--v-theme-on-surface), 0.3);
  background-color: rgba(var(--v-theme-on-surface), 0.04);
  border-radius: 8px 8px 0 0;
  cursor: pointer;
  position: relative;
  padding: 0 16px;
  vertical-align: middle;
}

.blank-box.is-active {
  background-color: rgba(var(--v-theme-primary), 0.1);
  border-bottom-color: rgb(var(--v-theme-primary));
  border-bottom-style: solid;
}

.blank-box.is-filled {
  background-color: rgba(var(--v-theme-info), 0.1);
  border-bottom-color: rgb(var(--v-theme-info));
  border-bottom-style: solid;
  font-weight: 600;
}

.blank-box.is-correct {
  background-color: rgba(var(--v-theme-success), 0.1);
  border-bottom-color: rgb(var(--v-theme-success));
  border-bottom-style: solid;
  color: rgb(var(--v-theme-success));
}

.blank-box.is-error {
  background-color: rgba(var(--v-theme-error), 0.1);
  border-bottom-color: rgb(var(--v-theme-error));
  border-bottom-style: solid;
  color: rgb(var(--v-theme-error));
  text-decoration: line-through;
}

.correct-hint {
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  color: rgb(var(--v-theme-success));
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  margin-top: 4px;
  text-decoration: none !important;
}

.choices-box-wrapper {
  min-height: 160px;
  border: 2px solid rgba(var(--v-theme-on-surface), 0.08);
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.choices-box-wrapper.has-choices {
  border-color: rgba(var(--v-theme-primary), 0.2);
  background-color: rgba(var(--v-theme-primary), 0.02);
}

.choice-btn {
  background-color: rgb(var(--v-theme-surface)) !important;
  font-weight: 600;
  border-width: 2px;
}

.choice-btn:hover {
  background-color: rgb(var(--v-theme-primary)) !important;
  color: white !important;
  border-color: rgb(var(--v-theme-primary)) !important;
}

.animate__animated {
  animation-duration: 0.3s;
}
</style>
