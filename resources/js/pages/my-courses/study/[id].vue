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
const initialSlideCount = ref(0)
const currentSlideIndex = ref(0)
const progress = ref(0)
const attempts = ref({})
const resolvedSlideIds = ref(new Set())

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
    initialSlideCount.value = slides.value.length
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

const isTrulyFinished = computed(() =>

  // Only show finish if we are on the last slide AND we have resolved it (answered)
  // Or if it's not a question (explanation) and we reached the end
  isLastSlide.value &&
  (
    !currentSlide.value?.question ||
    hasAnsweredCurrent.value
  ),
)


const isLastSlide = computed(() => {
  return currentSlideIndex.value >= slides.value.length - 1 && reshowQueue.value.length === 0
})

const currentProgress = computed(() => {
  return resolvedSlideIds.value.size
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
    resolvedSlideIds.value.add(currentSlide.value.id)
  } else {
    drawerFeedback.value = currentSlide.value.question.incorrectFeedback || 'Incorrect.'
  }

  // Open drawer immediately for better responsiveness
  drawerOpen.value = true

  if (correct) {
    // No API call here - tracked locally via attempts
  } else {
    // No API call here - tracked locally via attempts

    // Add to reshow queue if enabled and limits allow
    const reshowIncorrect = lesson.value.reshowIncorrectSlides
    const reshowEnabled = !!reshowIncorrect
    
    let allowedReshows = Infinity
    if (lesson.value.reshowCount !== undefined) allowedReshows = parseInt(lesson.value.reshowCount)

    const currentAttempts = attempts.value[currentSlide.value.id] || 0
    
    // reshowEnabled must be true
    // AND current attempts (which includes this one) must be <= maxReshows (which implies additional shows?)
    // Actually, if reshowCount is "number of reshows", then total allowed attempts is 1 + reshowCount.
    // If currentAttempts <= maxReshows, it means we haven't used up our "reshow" allowance?
    // Let's assume reshowCount = 1.
    // Attempt 1: currentAttempts = 1. 1 <= 1? Yes. Push.
    // Attempt 2: currentAttempts = 2. 2 <= 1? No. Don't push.
    // Result: 2 attempts total. Correct.
    
    if (reshowEnabled && currentAttempts <= allowedReshows) {
      // Deep clone to reset state (user answers) for the new attempt
      const slideClone = JSON.parse(JSON.stringify(currentSlide.value))

      reshowQueue.value.push(slideClone)
    } else {
      resolvedSlideIds.value.add(currentSlide.value.id)
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

const showExitDialog = ref(false)

const handleExit = () => {
  showExitDialog.value = true
}

const confirmExit = () => {
  showExitDialog.value = false
  if (lesson.value && lesson.value.courseId) {
    router.push(`/my-courses/${lesson.value.courseId}`)
  } else {
    router.back()
  }
}

const handleNavigationClick = () => {

  // If question and not answered yet, trigger check
  // This applies to reordering, fill_blank, or any type requiring manual submission
  if (currentSlide.value?.question && !hasAnsweredCurrent.value) {
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
  resolvedSlideIds.value.add(currentSlide.value.id)

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

const isFinishing = ref(false)

const finishLesson = async () => {
  if (isFinishing.value) return
  isFinishing.value = true

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
    if (lesson.value?.courseId) {
      const cId = lesson.value?.courseId

      router.push(`/my-courses/${cId}`)
    } else {
      router.push({ name: 'my-courses' })
    }
  } catch (error) {
    console.error("Error finishing:", error)

    // Even if error, try to redirect
    if (lesson.value?.data?.courseId || lesson.value?.courseId) {
      const cId = lesson.value?.data?.courseId || lesson.value?.courseId

      router.push(`/my-courses/${cId}`)
    } else {
      router.push({ name: 'my-courses' })
    }
  } finally {
    isFinishing.value = false
  }
}

const handleFinish = () => {
  // If it's a question and not answered, we must check it first
  if (currentSlide.value?.question && !hasAnsweredCurrent.value) {
    triggerCheck()
    
    return
  }
  
  finishLesson()
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
        @click="handleExit"
      />
      <LessonProgress
        :current="currentProgress"
        :total="initialSlideCount"
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
          :key="currentSlideIndex"
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
      :is-last-slide="isTrulyFinished"
      :correct-feedback="drawerFeedback"
      :incorrect-feedback="drawerFeedback"
      :feedback-sentence="currentSlide?.feedbackSentence"
      :feedback-translation="currentSlide?.feedbackTranslation"
      :language="lesson?.courseMainLocale"
      :loading="isFinishing"
      @next="handleNavigationClick"
      @finish="handleFinish"
    />

    <!-- Exit Dialog -->
    <VDialog
      v-model="showExitDialog"
      max-width="400"
    >
      <VCard title="Exit Lesson?">
        <VCardText>
          Are you sure you want to leave? Your progress in this session may be lost.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            @click="showExitDialog = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="error"
            variant="text"
            @click="confirmExit"
          >
            Exit
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
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
