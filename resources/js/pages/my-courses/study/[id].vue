<script setup>
import LessonProgress from '@/components/SlideTypes/LessonProgress.vue'
import $api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTheme } from 'vuetify'

definePage({
  meta: {
    layout: 'blank',
  },
})

const route = useRoute()
const router = useRouter()
const theme = useTheme()

const lessonId = route.params.id
const isLoading = ref(true)
const error = ref(null)
const lesson = ref(null)
const slides = ref([])
const currentSlideIndex = ref(0)
const progress = ref(0)
const attempts = ref({})

// State for interaction
const drawerOpen = ref(false)
const isCorrect = ref(false)
const drawerFeedback = ref('')
const hasAnsweredCurrent = ref(false)

// Reshow Logic
const reshowQueue = ref([]) // Stores indices or slide objects to reshow

/*
// Audio
// Using online placeholders for now, replace with local assets
const correctSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2000/2000-preview.mp3')
const incorrectSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2003/2003-preview.mp3')
const isMuted = ref(false)

const toggleMute = () => {
  isMuted.value = !isMuted.value
}

const playSound = correct => {
  if (isMuted.value) return
  if (correct) {
    correctSound.currentTime = 0
    correctSound.play().catch(e => console.log('Audio play error', e))
  } else {
    incorrectSound.currentTime = 0
    incorrectSound.play().catch(e => console.log('Audio play error', e))
  }
}
*/

// Fetch Lesson
const fetchLesson = async () => {
  try {
    error.value = null

    const response = await $api.get(`/learner/lessons/${lessonId}/content`)

    lesson.value = response
    slides.value = response.slides || []
  } catch (err) {
    console.error('Error fetching lesson:', err)
    error.value = err.response?.data?.error || err.message || 'Failed to load lesson content.'
  } finally {
    isLoading.value = false
  }
}

const currentSlide = computed(() => {
  if (!slides.value.length) return null
  
  return slides.value[currentSlideIndex.value]
})

const isLastSlide = computed(() => {
  return currentSlideIndex.value >= slides.value.length - 1 && reshowQueue.value.length === 0
})

const currentProgress = computed(() => {
  // Simple progress based on index vs total initial slides
  // Does not regress when reshowing
  if (!slides.value.length) return 0

  // If we are in reshow mode (index > initial length), show 100% or near it?
  // Let's stick to simple index/total for now
  return currentSlideIndex.value
})

const lessonSlideRef = ref(null)

const handleQuestionAnswered = async ({ correct, userAnswer }) => {
  if (hasAnsweredCurrent.value) return
    
  hasAnsweredCurrent.value = true
  isCorrect.value = correct

  // Track attempt locally
  attempts.value[currentSlide.value.id] = (attempts.value[currentSlide.value.id] || 0) + 1
  
  if (correct) {
    drawerFeedback.value = currentSlide.value.question.correctFeedback || 'Correct!'
  } else {
    drawerFeedback.value = currentSlide.value.question.incorrectFeedback || 'Incorrect.'
  }

  // Open drawer immediately for better responsiveness
  drawerOpen.value = true

  if (correct) {
    // No API call here - tracked locally via attempts
  } else {
    // No API call here - tracked locally via attempts

    // Add to reshow queue if enabled
    if (lesson.value.reshowIncorrectSlides) {
      reshowQueue.value.push(currentSlide.value)
    }
  }
}

const triggerCheck = () => {
  if (lessonSlideRef.value && lessonSlideRef.value.submitAnswer) {
    lessonSlideRef.value.submitAnswer()
  }
}




const isReordering = computed(() => {
  return currentSlide.value?.question?.type === 'reordering'
})

const drawerMode = computed(() => {
  if (isReordering.value && !hasAnsweredCurrent.value) return 'continue'
  
  return currentSlide.value?.question ? 'feedback' : 'continue'
})

const handleNavigationClick = () => {
  // If reordering question and not answered yet, trigger check
  if (isReordering.value && !hasAnsweredCurrent.value) {
    drawerOpen.value = false // Hide drawer briefly
    
    // Wait for drawer to close (animation) before checking
    setTimeout(() => {
      triggerCheck()
    }, 400)
    
    return
  }

  // Otherwise proceed to next slide
  nextSlide()
}

// Watch for reordering slide to open drawer immediately

watch(currentSlide, newSlide => {
  if (newSlide?.question?.type === 'reordering' && !hasAnsweredCurrent.value) {
    drawerOpen.value = true
  }
}, { immediate: true })

const handleNonQuestionCompleted = async () => {
  // Track attempt (1 view)
  attempts.value[currentSlide.value.id] = (attempts.value[currentSlide.value.id] || 0) + 1

  hasAnsweredCurrent.value = true
  isCorrect.value = true
  drawerFeedback.value = "Great! Let's continue."
  drawerOpen.value = true
}

const nextSlide = () => {
  drawerOpen.value = false
  hasAnsweredCurrent.value = false
    
  if (currentSlideIndex.value < slides.value.length - 1) {
    currentSlideIndex.value++
  } else if (reshowQueue.value.length > 0) {
    const nextReshow = reshowQueue.value.shift()

    slides.value.push(nextReshow)
    currentSlideIndex.value++
  } else {
    // Should be finished
  }
}

const finishLesson = async () => {
  try {
    // Prepare results for batch submission
    const results = Object.entries(attempts.value).map(([slideId, count]) => ({
      slideId: parseInt(slideId),
      attempts: count,
    }))

    // Mark lesson as complete with results
    await $api.post(`/learner/lessons/${lessonId}/complete`, {
      results,
    })
        
    // Redirect to course page
    router.push({ name: 'my-courses' }) 
  } catch (error) {
    console.error("Error finishing:", error)

    // Even if error, try to redirect
    router.push({ name: 'my-courses' }) 
  }
}

onMounted(() => {
  fetchLesson()
})
</script>

<template>
  <div class="lesson-player-page h-screen d-flex flex-column bg-surface">
    <!-- Header / Progress -->
    <div class="pa-4 d-flex align-center gap-4 elevation-1 bg-surface z-index-10">
      <VBtn
        icon="tabler-x"
        variant="text"
        @click="router.back()"
      />
      <LessonProgress
        :current="currentProgress"
        :total="slides.length"
      />
      <!--
        <div class="d-flex align-center">
        <VBtn
        icon
        variant="text"
        @click="toggleMute"
        >
        <VIcon :icon="isMuted ? 'tabler-volume-off' : 'tabler-volume'" />
        </VBtn>
        </div>
      -->
    </div>

    <!-- Main Content Area -->
    <div 
      class="flex-grow-1 d-flex justify-center pa-4 overflow-y-auto position-relative"
      style="padding-bottom: 200px !important"
    >
      <div
        v-if="isLoading"
        class="text-center"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="64"
        />
        <div class="mt-4">
          Loading Lesson...
        </div>
      </div>

      <div
        v-else-if="error"
        class="text-center"
      >
        <VIcon
          icon="tabler-alert-circle"
          color="error"
          size="64"
          class="mb-4"
        />
        <div class="text-h4 mb-2 text-error">
          Error
        </div>
        <div class="text-body-1 mb-6">
          {{ error }}
        </div>
        <VBtn
          color="primary"
          @click="errorRedirectTo ? router.push(errorRedirectTo) : router.back()"
        >
          {{ errorButtonText }}
        </VBtn>
      </div>

      <div
        v-else-if="currentSlide"
        class="slide-container w-100 my-auto"
        style="max-width: 800px;"
      >
        <LessonSlide 
          ref="lessonSlideRef"
          :slide="currentSlide"
          @answered="handleQuestionAnswered"
          @completed="handleNonQuestionCompleted"
        />
      </div>
      
      <div
        v-else
        class="text-center"
      >
        <div class="text-h4 mb-4">
          Lesson Completed!
        </div>
        <VBtn
          color="primary"
          size="large"
          @click="finishLesson"
        >
          Return to Course
        </VBtn>
      </div>
    </div>



    <!-- Navigation / Feedback Drawer -->
    <LessonNavigation
      v-model="drawerOpen"
      :mode="drawerMode"
      :is-correct="isCorrect"
      :is-last-slide="isLastSlide"
      :correct-feedback="drawerFeedback"
      :incorrect-feedback="drawerFeedback"
      :feedback-sentence="currentSlide?.feedbackSentence"
      :feedback-translation="currentSlide?.feedbackTranslation"
      :language="lesson?.courseMainLocale"
      @next="handleNavigationClick"
      @finish="finishLesson"
    />
  </div>
</template>

<style scoped>
.gap-4 {
    gap: 16px;
}
.z-index-10 {
    z-index: 10;
}
.slide-container {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
