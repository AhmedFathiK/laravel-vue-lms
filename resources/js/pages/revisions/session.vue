<script setup>
import LessonNavigation from '@/components/SlideTypes/LessonNavigation.vue'
import LessonSlide from '@/components/SlideTypes/LessonSlide.vue'
import $api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

definePage({
  meta: {
    layout: 'blank',
  },
})

const route = useRoute()
const router = useRouter()

const slides = ref([])
const currentSlideIndex = ref(0)
const loading = ref(true)
const answers = ref({}) // Map of revision_item_id => [bool, bool, bool]
const submitting = ref(false)

// Interaction state
const drawerOpen = ref(false)
const isCorrect = ref(false)
const drawerFeedback = ref('')
const hasAnsweredCurrent = ref(false)
const lessonSlideRef = ref(null)

const currentSlide = computed(() => slides.value[currentSlideIndex.value])

const progress = computed(() => {
  if (slides.value.length === 0) return 0
  
  return ((currentSlideIndex.value) / slides.value.length) * 100
})

const isLastSlide = computed(() => currentSlideIndex.value >= slides.value.length - 1)

const fetchSession = async () => {
  loading.value = true
  try {
    const type = route.query.type || 'both'
    const earlyReview = route.query.earlyReview === '1'

    const res = await $api.get('/revision/practice', { 
      params: { 
        type, 
        limit: 20,
        earlyReview: earlyReview ? 1 : 0,
      }, 
    })

    slides.value = res.slides.map(slide => ({
      ...slide,
      ...slide.data,
    })) // Interceptor returns response.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchSession()
})

const handleQuestionAnswered = ({ correct }) => {
  if (hasAnsweredCurrent.value) return
  hasAnsweredCurrent.value = true
  isCorrect.value = correct
  
  // Store answer
  const slide = currentSlide.value
  const itemId = slide.revisionItemId
  if (!answers.value[itemId]) answers.value[itemId] = []
  answers.value[itemId].push(correct)
  
  // Feedback
  drawerFeedback.value = correct ? 'Correct!' : 'Incorrect.'
  drawerOpen.value = true
}

const handleNonQuestionCompleted = () => {
  // Should not happen in revision (all are questions), but safe fallback
  hasAnsweredCurrent.value = true
  isCorrect.value = true
  drawerOpen.value = true
}

const triggerCheck = () => {
  if (lessonSlideRef.value && lessonSlideRef.value.submitAnswer) {
    lessonSlideRef.value.submitAnswer()
  }
}

const nextSlide = () => {
  drawerOpen.value = false
  hasAnsweredCurrent.value = false
  
  if (currentSlideIndex.value < slides.value.length - 1) {
    currentSlideIndex.value++
  } else {
    finishSession()
  }
}

const handleNavigationClick = () => {
  if (currentSlide.value?.question && !hasAnsweredCurrent.value) {
    drawerOpen.value = false
    setTimeout(() => triggerCheck(), 400)
    
    return
  }
  nextSlide()
}

const handleFinish = () => {
  if (currentSlide.value?.question && !hasAnsweredCurrent.value) {
    triggerCheck()
    
    return
  }
  finishSession()
}

const finishSession = async () => {
  if (submitting.value) return
  submitting.value = true
  
  try {
    const promises = Object.keys(answers.value).map(itemId => {
      return $api.post('/revision/response', {
        revisionItemId: itemId,
        results: answers.value[itemId],
      })
    })
    
    const responses = await Promise.all(promises)
    const summaryData = responses.map(r => r) // Interceptor returns data directly

    router.push({ 
      name: 'revisions-summary',
      state: { summary: JSON.stringify(summaryData) },
    }) 
  } catch (e) {
    console.error(e)
  } finally {
    submitting.value = false
  }
}

const drawerMode = computed(() => {
  if (currentSlide.value?.question?.type === 'reordering' && !hasAnsweredCurrent.value) return 'continue'
  
  return hasAnsweredCurrent.value ? 'continue' : 'feedback' 
})

// Watchers similar to Lesson.vue if needed
watch(currentSlide, newSlide => {
  if (newSlide?.question?.type === 'reordering' && !hasAnsweredCurrent.value) {
    drawerOpen.value = true
  }
}, { immediate: true })
</script>

<template>
  <div class="revision-session h-screen d-flex flex-column bg-surface">
    <!-- Header -->
    <div class="pa-4 d-flex align-center gap-4 elevation-1 bg-surface z-index-10">
      <VBtn
        icon="tabler-x"
        variant="text"
        :to="{ name: 'revisions' }"
      />
      <VProgressLinear
        :model-value="progress"
        height="8"
        color="primary"
        rounded
      />
    </div>

    <!-- Content -->
    <div
      class="flex-grow-1 d-flex justify-center pa-4 overflow-y-auto position-relative"
      style="padding-bottom: 200px !important"
    >
      <div
        v-if="loading"
        class="text-center mt-10"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="64"
        />
        <div class="mt-4">
          Loading Review...
        </div>
      </div>
      
      <div
        v-else-if="slides.length === 0"
        class="text-center mt-10"
      >
        <div class="text-h4 mb-4">
          No reviews due!
        </div>
        <VBtn to="/revisions">
          Back to Dashboard
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
    </div>

    <!-- Navigation Drawer -->
    <LessonNavigation
      v-model="drawerOpen"
      :mode="hasAnsweredCurrent ? 'continue' : 'feedback'" 
      :is-correct="isCorrect"
      :is-last-slide="isLastSlide"
      :correct-feedback="drawerFeedback"
      :incorrect-feedback="drawerFeedback"
      @next="handleNavigationClick"
      @finish="handleFinish"
    />
  </div>
</template>

<style scoped>
.gap-4 { gap: 16px; }
.z-index-10 { z-index: 10; }
.slide-container { animation: fadeIn 0.5s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>
