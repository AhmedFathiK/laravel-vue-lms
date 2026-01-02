<script setup>
import { ref, watch } from "vue"

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  mode: {
    type: String,
    default: 'feedback', // 'feedback' | 'continue'
  },
  isCorrect: {
    type: Boolean,
    default: false,
  },
  isLastSlide: {
    type: Boolean,
    default: false,
  },
  correctFeedback: {
    type: String,
    default: '',
  },
  incorrectFeedback: {
    type: String,
    default: '',
  },
  feedbackSentence: {
    type: String,
    default: '',
  },
  feedbackTranslation: {
    type: String,
    default: '',
  },
  language: {
    type: String,
    default: 'en-US',
  },
})

const emit = defineEmits(['update:modelValue', 'next', 'finish'])

const rightSound = ref()
const wrongSound = ref()
const isPlayingSentence = ref(false)

const speakSentence = () => {
  if (!props.feedbackSentence) return

  const utterance = new SpeechSynthesisUtterance(props.feedbackSentence)
  
  // Handle cases where language might be just 'en' instead of 'en-US'
  let lang = props.language
  if (lang === 'en') lang = 'en-US'
  if (lang === 'ar') lang = 'ar-SA'
  
  utterance.lang = lang
  
  utterance.onstart = () => { isPlayingSentence.value = true }
  utterance.onend = () => { isPlayingSentence.value = false }
  utterance.onerror = () => { isPlayingSentence.value = false }
  
  window.speechSynthesis.cancel() // Stop any current speech
  window.speechSynthesis.speak(utterance)
}

watch(() => props.modelValue, newVal => {
  // Only play sound if we are in feedback mode (question answered)
  if (newVal && props.mode === 'feedback') {
    // Play sound immediately or with slight delay
    setTimeout(() => {
      const player = props.isCorrect ? rightSound.value : wrongSound.value
      if (player) {
        player.currentTime = 0
        player.play().catch(e => console.log('Audio play error', e))
      }
    }, 100)
  }
}, { immediate: true })

const handleNext = () => {
  emit('next')
}

const handleFinish = () => {
  emit('finish')
}
</script>

<template>
  <Transition name="slide-y-reverse-transition">
    <div
      v-if="modelValue"
      class="lesson-navigation elevation-24 bg-surface"
      :class="{ 'navigation-continue-only': mode === 'continue' }"
    >
      <div class="d-none">
        <audio
          ref="rightSound"
          controlslist="nodownload"
          preload="auto"
        >
          <source
            src="/audio/system-sounds/right.mp3"
            type="audio/mp3"
          >
        </audio>
        <audio
          ref="wrongSound"
          controlslist="nodownload"
          preload="auto"
        >
          <source
            src="/audio/system-sounds/wrong.mp3"
            type="audio/mp3"
          >
        </audio>
      </div>

      <!-- Main Content Container -->
      <div class="px-3 px-md-15 py-4">
        <VRow align="center">
          <!-- LEFT: STATUS (Only for Feedback Mode) -->
          <VCol
            v-if="mode === 'feedback'"
            cols="12"
            md="3"
            class="answer-comment-title d-flex align-center justify-start gap-2 mb-4 mb-md-0"
          >
            <VIcon
              v-if="isCorrect"
              icon="tabler-circle-check"
              size="3rem"
              color="success"
            />
            <VIcon
              v-if="!isCorrect"
              icon="tabler-playstation-x"
              size="3rem"
              color="error"
            />
            <span class="text-h5 font-weight-bold ms-2">
              {{ isCorrect ? 'Correct answer' : ' Wrong answer' }}
            </span>
          </VCol>

          <!-- MIDDLE: FEEDBACK CONTENT (Only for Feedback Mode) -->
          <VCol
            v-if="mode === 'feedback'"
            cols="12"
            md="6"
            class="mb-4 mb-md-0"
          >
            <div
              v-if="feedbackSentence"
              class="feedback-sentence-wrapper rounded pa-3 mb-2"
            >
              <div class="d-flex align-center gap-3">
                <VBtn
                  icon="tabler-player-play"
                  variant="text"
                  color="primary"
                  size="small"
                  :class="{ 'text-primary': isPlayingSentence, 'text-medium-emphasis': !isPlayingSentence }"
                  @click="speakSentence"
                />
                <div>
                  <div class="text-subtitle-1 font-weight-medium mb-1">
                    {{ feedbackSentence }}
                  </div>
                  <div
                    v-if="feedbackTranslation"
                    class="text-body-2 text-medium-emphasis"
                  >
                    {{ feedbackTranslation }}
                  </div>
                </div>
              </div>
            </div>
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div 
              class="text-body-1"
              v-html="isCorrect ? correctFeedback : incorrectFeedback" 
            />
          </VCol>

          <!-- RIGHT: ACTION BUTTON (Always Visible) -->
          <VCol
            cols="12"
            :md="mode === 'feedback' ? 3 : 12"
            class="d-flex justify-end"
          >
            <VBtn
              v-if="!isLastSlide"
              class="rounded px-8"
              size="x-large"
              color="primary"
              @click="handleNext"
            >
              Continue
            </VBtn>
            <VBtn
              v-else
              class="rounded px-8"
              size="x-large"
              color="primary"
              @click="handleFinish"
            >
              Finish
            </VBtn>
          </VCol>
        </VRow>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.lesson-navigation {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 2000;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  max-height: 80vh;
  overflow-y: auto;
}

.navigation-continue-only {
  /* Minimal styling for continue mode if needed */
  background-color: rgb(var(--v-theme-surface)) !important;
}

.feedback-sentence-wrapper {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

.answer-comment-title{
  font-size: 2rem;
  display: flex;
  align-items: center;
  white-space: nowrap;
}

.slide-y-reverse-transition-enter-active,
.slide-y-reverse-transition-leave-active {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-y-reverse-transition-enter-from,
.slide-y-reverse-transition-leave-to {
  transform: translateY(100%);
}
</style>
