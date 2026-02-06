<script setup>
import $api from '@/utils/api'
import AudioPlayer from '@/components/AudioPlayer.vue'
import VideoPlayer from '@/components/VideoPlayer.vue'
import FillBlankChoicesSlide from '@/components/SlideTypes/FillBlankChoicesSlide.vue'
import FillBlankSlide from '@/components/SlideTypes/FillBlankSlide.vue'
import MatchingSlide from '@/components/SlideTypes/MatchingSlide.vue'
import MCQSlide from '@/components/SlideTypes/MCQSlide.vue'
import ReorderingSlide from '@/components/SlideTypes/ReorderingSlide.vue'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
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

const examId = route.params.id
const isLoading = ref(true)
const isStarting = ref(false)
const isSubmitting = ref(false)
const error = ref(null)

const exam = ref(null)
const attempt = ref(null)
const currentSectionIndex = ref(0)
const currentQuestionIndex = ref(0)
const userAnswers = ref({}) // Format: { questionId: answer }
const showResult = ref(false)
const placementOutcome = ref(null)

// Timer state
const remainingSeconds = ref(null)
const timerInterval = ref(null)

const formatTime = seconds => {
  if (seconds === null || seconds < 0) return '--:--'
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  const s = seconds % 60
  
  if (h > 0) {
    return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
  }
  
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

const startTimer = () => {
  if (timerInterval.value) clearInterval(timerInterval.value)
  
  timerInterval.value = setInterval(() => {
    if (remainingSeconds.value > 0) {
      remainingSeconds.value--
    } else if (remainingSeconds.value === 0) {
      clearInterval(timerInterval.value)
      autoSubmitExam()
    }
  }, 1000)
}

const autoSubmitExam = async () => {
  if (isSubmitting.value) return
  
  try {
    isSubmitting.value = true

    const response = await $api.post(`/learner/exam-attempts/${attempt.value.id}/complete`)
    
    if (response.attempt && response.attempt.placementOutcomeLevelId) {
      placementOutcome.value = response.attempt
      showResult.value = true
    } else {
      alert('Time is up! Your exam has been automatically submitted.')
      router.back()
    }
  } catch (err) {
    console.error('Error auto-submitting exam:', err)
    router.back()
  } finally {
    isSubmitting.value = false
  }
}

watch(attempt, newAttempt => {
  if (newAttempt && newAttempt.status === 'in_progress') {
    if (newAttempt.remainingTime !== undefined && newAttempt.remainingTime !== -1) {
      remainingSeconds.value = newAttempt.remainingTime
      startTimer()
    }
  } else {
    if (timerInterval.value) {
      clearInterval(timerInterval.value)
      timerInterval.value = null
    }
  }
}, { immediate: true })

onUnmounted(() => {
  if (timerInterval.value) clearInterval(timerInterval.value)
})

const fetchExam = async () => {
  try {
    isLoading.value = true

    const response = await $api.get(`/learner/exams/${examId}`)

    exam.value = response
    
    // Check if there's an in-progress attempt
    const attemptsResponse = await $api.get(`/learner/exams/${examId}/attempts`)
    const inProgress = attemptsResponse.find(a => a.status === 'in_progress')
    if (inProgress) {
      attempt.value = inProgress


      // Optionally fetch attempt details to resume
      const attemptDetails = await $api.get(`/learner/exam-attempts/${inProgress.id}`)

      attempt.value = attemptDetails
      
      // Map existing responses to userAnswers
      if (attemptDetails.responses) {
        attemptDetails.responses.forEach(r => {
          userAnswers.value[r.questionId] = r.userAnswer
        })
      }
    }
  } catch (err) {
    console.error('Error fetching exam:', err)
    error.value = err.response?.data?.message || 'Failed to load exam.'
  } finally {
    isLoading.value = false
  }
}

const startExam = async () => {
  try {
    isStarting.value = true

    const response = await $api.post(`/learner/exams/${examId}/start`)

    attempt.value = response.attempt
  } catch (err) {
    console.error('Error starting exam:', err)
    error.value = err.response?.data?.message || 'Failed to start exam.'
  } finally {
    isStarting.value = false
  }
}

const handleFinishPlacement = () => {
  const levelId = placementOutcome.value?.placementOutcomeLevelId
  const courseId = exam.value?.courseId
  
  if (levelId && courseId) {
    router.push({
      path: `/my-courses/${courseId}`,
      query: { targetLevel: levelId },
    })
  } else {
    router.back()
  }
}

const currentSection = computed(() => {
  if (!exam.value || !exam.value.sections) return null
  
  return exam.value.sections[currentSectionIndex.value]
})

const currentQuestion = computed(() => {
  if (!currentSection.value || !currentSection.value.questions) return null
  
  return currentSection.value.questions[currentQuestionIndex.value]
})

const totalQuestions = computed(() => {
  if (!exam.value || !exam.value.sections) return 0
  
  return exam.value.sections.reduce((acc, section) => acc + (section.questions?.length || 0), 0)
})

const completedQuestionsCount = computed(() => {
  return Object.keys(userAnswers.value).length
})

const progress = computed(() => {
  if (totalQuestions.value === 0) return 0
  
  return (completedQuestionsCount.value / totalQuestions.value) * 100
})

const specializedTypes = ['fill_blank_choices', 'fill_blank', 'matching', 'reordering', 'mcq']

const shouldRenderGenericHeader = computed(() => {
  return currentQuestion.value && !specializedTypes.includes(currentQuestion.value.type)
})

const handleAnswer = async answer => {
  if (!attempt.value || !currentQuestion.value) return
  
  const questionId = currentQuestion.value.id

  userAnswers.value[questionId] = answer
  
  try {
    await $api.post(`/learner/exam-attempts/${attempt.value.id}/questions/${questionId}/response`, {
      userAnswer: answer,
      sectionId: currentSection.value.id,
    })
  } catch (err) {
    console.error('Error saving answer:', err)
    if (err.response?.status === 403 && err.response?.data?.isExpired) {
      alert('Time limit exceeded. Your exam has been automatically submitted.')
      router.back()
    }
  }
}

const nextQuestion = () => {
  if (currentQuestionIndex.value < currentSection.value.questions.length - 1) {
    currentQuestionIndex.value++
  } else if (currentSectionIndex.value < exam.value.sections.length - 1) {
    currentSectionIndex.value++
    currentQuestionIndex.value = 0
  }
}

const prevQuestion = () => {
  if (currentQuestionIndex.value > 0) {
    currentQuestionIndex.value--
  } else if (currentSectionIndex.value > 0) {
    currentSectionIndex.value--
    currentQuestionIndex.value = exam.value.sections[currentSectionIndex.value].questions.length - 1
  }
}

const finishExam = async () => {
  if (!confirm('Are you sure you want to finish the exam?')) return
  
  try {
    isSubmitting.value = true

    const response = await $api.post(`/learner/exam-attempts/${attempt.value.id}/complete`)
    
    if (response.attempt && response.attempt.placementOutcomeLevelId) {
      placementOutcome.value = response.attempt
      showResult.value = true
    } else {
      router.back()
    }
  } catch (err) {
    console.error('Error finishing exam:', err)
    error.value = err.response?.data?.message || 'Failed to finish exam.'
  } finally {
    isSubmitting.value = false
  }
}

onMounted(fetchExam)
</script>

<template>
  <VLayout class="rounded-0">
    <VMain class="exam-page bg-var-theme-background">
      <div
        v-if="isLoading"
        class="d-flex align-center justify-center h-screen"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="64"
        />
      </div>

      <div
        v-else-if="error"
        class="d-flex align-center justify-center h-screen p-4"
      >
        <VCard
          max-width="500"
          class="text-center p-6"
        >
          <VIcon
            color="error"
            size="64"
            icon="tabler-alert-circle"
            class="mb-4"
          />
          <h2 class="text-h4 mb-2">
            Error
          </h2>
          <p class="text-body-1 mb-6">
            {{ error }}
          </p>
          <VBtn
            color="primary"
            @click="router.back()"
          >
            Go Back
          </VBtn>
        </VCard>
      </div>

      <template v-else-if="exam">
        <!-- Placement Result Screen -->
        <div
          v-if="showResult"
          class="d-flex align-center justify-center h-screen p-4"
        >
          <VCard
            max-width="600"
            class="w-100 p-8 text-center"
          >
            <VIcon
              icon="tabler-trophy"
              size="80"
              color="warning"
              class="mb-6"
            />
            
            <h1 class="text-h3 font-weight-bold mb-2">
              Placement Complete!
            </h1>
            <p class="text-body-1 text-medium-emphasis mb-8">
              Based on your results, we have assigned you a starting level.
            </p>

            <div class="d-flex justify-center gap-8 mb-8">
              <div class="text-center">
                <div class="text-h2 font-weight-bold text-primary mb-1">
                  {{ placementOutcome?.percentage }}%
                </div>
                <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis">
                  Score
                </div>
              </div>
            </div>

            <VDivider class="mb-8" />

            <div class="mb-8">
              <div class="text-body-1 mb-2">
                You have been placed in:
              </div>
              <h2 class="text-h4 font-weight-bold text-primary">
                {{ placementOutcome?.placementOutcomeLevel?.title || 'Level Assigned' }}
              </h2>
            </div>

            <VBtn
              block
              size="x-large"
              color="primary"
              @click="handleFinishPlacement"
            >
              Start Learning
            </VBtn>
          </VCard>
        </div>

        <!-- Cover Page -->
        <div
          v-else-if="!attempt"
          class="d-flex align-center justify-center h-screen p-4"
        >
          <VCard
            max-width="600"
            class="w-100 p-6 overflow-hidden"
          >
            <div class="text-center mb-6">
              <VIcon
                color="primary"
                size="80"
                icon="tabler-certificate"
                class="mb-4"
              />
              <h1 class="text-h3 mb-2">
                {{ exam.title }}
              </h1>
            </div>

            <VDivider class="mb-6" />

            <div class="mb-8">
              <h3 class="text-h5 mb-4">
                Instructions
              </h3>
              <div
                class="text-body-1"
                v-html="exam.description || 'No specific instructions provided.'"
              />
            </div>

            <div class="d-flex flex-column gap-3 mb-8">
              <div class="d-flex align-center gap-2">
                <VIcon
                  size="20"
                  icon="tabler-clock"
                  color="secondary"
                />
                <span class="text-body-1">Duration: {{ exam.duration ? exam.duration + ' minutes' : 'No time limit' }}</span>
              </div>
              <div class="d-flex align-center gap-2">
                <VIcon
                  size="20"
                  icon="tabler-list-numbers"
                  color="secondary"
                />
                <span class="text-body-1">Questions: {{ totalQuestions }}</span>
              </div>
              <div class="d-flex align-center gap-2">
                <VIcon
                  size="20"
                  icon="tabler-award"
                  color="secondary"
                />
                <span class="text-body-1">Passing Score: {{ exam.passingPercentage }}%</span>
              </div>
            </div>

            <VBtn
              block
              size="large"
              color="primary"
              :loading="isStarting"
              @click="startExam"
            >
              Start Exam
            </VBtn>
            <VBtn
              variant="text"
              block
              class="mt-2"
              @click="router.back()"
            >
              Cancel
            </VBtn>
          </VCard>
        </div>

        <!-- Exam Interface -->
        <div
          v-else
          class="h-screen d-flex flex-column"
        >
          <!-- Header -->
          <header class="d-flex align-center justify-space-between px-6 py-4 border-b bg-surface">
            <div class="d-flex align-center gap-4">
              <VBtn
                icon
                variant="text"
                @click="router.back()"
              >
                <VIcon icon="tabler-x" />
              </VBtn>
              <div>
                <h2 class="text-h5 font-weight-bold line-clamp-1">
                  {{ exam.title }}
                </h2>
                <div class="text-caption text-secondary">
                  Section {{ currentSectionIndex + 1 }} of {{ exam.sections.length }}
                </div>
              </div>
            </div>

            <div class="d-flex align-center gap-2 gap-sm-4">
              <div
                v-if="remainingSeconds !== null"
                class="text-right me-2 me-sm-4"
              >
                <div class="text-caption text-secondary d-none d-sm-block">
                  Time Remaining
                </div>
                <div
                  class="text-body-2 text-sm-h6 font-weight-bold"
                  :class="remainingSeconds < 60 ? 'text-error' : ''"
                >
                  <VIcon
                    v-if="remainingSeconds < 60"
                    icon="tabler-clock-exclamation"
                    size="18"
                    class="me-1 d-sm-none"
                  />
                  {{ formatTime(remainingSeconds) }}
                </div>
              </div>
              <div class="text-right d-none d-md-block">
                <div class="text-caption text-secondary">
                  Progress
                </div>
                <div class="text-body-2 font-weight-medium">
                  {{ completedQuestionsCount }} / {{ totalQuestions }}
                </div>
              </div>
              <VProgressCircular
                :model-value="progress"
                color="primary"
                size="40"
                width="4"
              >
                <span class="text-caption">{{ Math.round(progress) }}%</span>
              </VProgressCircular>
            </div>
          </header>

          <!-- Question Content -->
          <div class="flex-grow-1 overflow-y-auto p-4 p-sm-8 d-flex justify-center">
            <div
              v-if="currentQuestion"
              class="w-100"
              style="max-width: 800px;"
            >
              <div class="mb-4">
                <VChip
                  color="secondary"
                  variant="tonal"
                  size="small"
                  class="mb-2"
                >
                  Question {{ currentQuestionIndex + 1 }}
                </VChip>

                <!-- 1. Question Context -->
                <VCard
                  v-if="currentQuestion.context"
                  variant="flat"
                  color="grey-lighten-4"
                  class="mb-6 p-4 rounded-lg border"
                >
                  <div
                    v-if="currentQuestion.context.title"
                    class="text-h6 font-weight-bold mb-3"
                  >
                    {{ currentQuestion.context.title }}
                  </div>

                  <!-- Text Passage -->
                  <div
                    v-if="currentQuestion.context.contextType === 'text_passage'"
                    class="text-body-1"
                    v-html="currentQuestion.context.content"
                  />

                  <!-- Image -->
                  <div
                    v-else-if="currentQuestion.context.contextType === 'image'"
                    class="d-flex justify-center"
                  >
                    <img
                      :src="currentQuestion.context.mediaUrl"
                      class="rounded border"
                      style="max-width: 100%; max-height: 400px; width: auto; height: auto; display: block;"
                    >
                  </div>

                  <!-- Audio -->
                  <div
                    v-else-if="currentQuestion.context.contextType === 'audio'"
                    class="d-flex flex-column align-center p-4"
                  >
                    <AudioPlayer :src="currentQuestion.context.mediaUrl" />
                  </div>

                  <!-- Video -->
                  <div
                    v-else-if="currentQuestion.context.contextType === 'video'"
                    class="d-flex justify-center"
                  >
                    <div 
                      class="rounded-lg overflow-hidden border"
                      style="max-width: 650px; width: 100%;"
                    >
                      <VideoPlayer
                        :src="currentQuestion.context.mediaUrl"
                        :type="currentQuestion.context.videoSource === 'direct' ? 'hosted' : currentQuestion.context.videoSource"
                        class="w-100"
                      />
                    </div>
                  </div>

                  <!-- Image with Audio -->
                  <div
                    v-else-if="currentQuestion.context.contextType === 'image_with_audio'"
                    class="d-flex flex-column align-center gap-4"
                  >
                    <img
                      :src="currentQuestion.context.mediaUrl"
                      class="rounded border"
                      style="max-width: 100%; max-height: 300px; width: auto; height: auto; display: block;"
                    >
                    <AudioPlayer :src="currentQuestion.context.audioUrl" />
                  </div>
                </VCard>

                <!-- 2. Question Title -->
                <h3
                  v-if="currentQuestion.title && shouldRenderGenericHeader"
                  class="text-h5 text-secondary mb-2"
                >
                  {{ currentQuestion.title }}
                </h3>

                <!-- 3. Question Media -->
                <div
                  v-if="shouldRenderGenericHeader && currentQuestion.mediaType && currentQuestion.mediaType !== 'none'"
                  class="mb-6 d-flex justify-center"
                >
                  <div 
                    class="rounded-lg overflow-hidden border"
                    style="width: 100%; max-width: 650px;"
                  >
                    <!-- Image -->
                    <img 
                      v-if="currentQuestion.mediaType === 'image'" 
                      :src="currentQuestion.mediaUrl" 
                      class="d-block"
                      style="max-width: 100%; max-height: 400px; width: auto; height: auto; margin: 0 auto;"
                    >

                    <!-- Audio -->
                    <div
                      v-else-if="currentQuestion.mediaType === 'audio'"
                      class="p-4"
                    >
                      <AudioPlayer :src="currentQuestion.mediaUrl" />
                    </div>

                    <!-- Video -->
                    <VideoPlayer
                      v-else-if="currentQuestion.mediaType === 'video'"
                      :src="currentQuestion.mediaUrl"
                      :type="currentQuestion.videoSource === 'direct' ? 'hosted' : currentQuestion.videoSource"
                      class="w-100"
                    />

                    <!-- Image with Audio -->
                    <div
                      v-else-if="currentQuestion.mediaType === 'image_with_audio'"
                      class="d-flex flex-column align-center gap-4 p-4"
                    >
                      <img
                        :src="currentQuestion.mediaUrl"
                        class="rounded border"
                        style="max-width: 100%; max-height: 300px; width: auto; height: auto; display: block;"
                      >
                      <AudioPlayer :src="currentQuestion.audioUrl" />
                    </div>
                  </div>
                </div>

                <!-- 4. Question Text -->
                <h3
                  v-if="shouldRenderGenericHeader"
                  class="text-h4 font-weight-medium mb-6"
                  v-html="currentQuestion.questionText"
                />
              </div>

              <!-- Answer Options -->
              <div class="d-flex flex-column gap-3">
                <template v-if="currentQuestion.type === 'mcq'">
                  <MCQSlide
                    :key="currentQuestion.id"
                    :question="currentQuestion"
                    :model-value="userAnswers[currentQuestion.id]"
                    is-exam
                    @update:model-value="handleAnswer"
                  />
                </template>

                <template v-else-if="currentQuestion.type === 'true_false'">
                  <VCard
                    variant="outlined"
                    :color="userAnswers[currentQuestion.id] === 'true' ? 'primary' : undefined"
                    class="p-4 cursor-pointer d-flex align-center gap-4"
                    @click="handleAnswer('true')"
                  >
                    <VIcon
                      icon="tabler-check"
                      :color="userAnswers[currentQuestion.id] === 'true' ? 'primary' : 'secondary'"
                    />
                    <span class="text-body-1">True</span>
                  </VCard>
                  <VCard
                    variant="outlined"
                    :color="userAnswers[currentQuestion.id] === 'false' ? 'primary' : undefined"
                    class="p-4 cursor-pointer d-flex align-center gap-4"
                    @click="handleAnswer('false')"
                  >
                    <VIcon
                      icon="tabler-x"
                      :color="userAnswers[currentQuestion.id] === 'false' ? 'primary' : 'secondary'"
                    />
                    <span class="text-body-1">False</span>
                  </VCard>
                </template>

                <template v-else-if="currentQuestion.type === 'short_answer' || currentQuestion.type === 'writing'">
                  <VTextarea
                    v-model="userAnswers[currentQuestion.id]"
                    label="Your Answer"
                    variant="outlined"
                    rows="5"
                    @update:model-value="handleAnswer"
                  />
                </template>

                <template v-else-if="currentQuestion.type === 'fill_blank_choices'">
                  <FillBlankChoicesSlide
                    :key="currentQuestion.id"
                    :question="currentQuestion"
                    :model-value="userAnswers[currentQuestion.id]"
                    is-exam
                    @update:model-value="handleAnswer"
                  />
                </template>

                <template v-else-if="currentQuestion.type === 'fill_blank'">
                  <FillBlankSlide
                    :key="currentQuestion.id"
                    :question="currentQuestion"
                    :model-value="userAnswers[currentQuestion.id]"
                    is-exam
                    @update:model-value="handleAnswer"
                  />
                </template>

                <template v-else-if="currentQuestion.type === 'matching'">
                  <MatchingSlide
                    :key="currentQuestion.id"
                    :question="currentQuestion"
                    :model-value="userAnswers[currentQuestion.id]"
                    is-exam
                    @update:model-value="handleAnswer"
                  />
                </template>

                <template v-else-if="currentQuestion.type === 'reordering'">
                  <ReorderingSlide
                    :key="currentQuestion.id"
                    :question="currentQuestion"
                    :model-value="userAnswers[currentQuestion.id]"
                    is-exam
                    @update:model-value="handleAnswer"
                  />
                </template>
                
                <div
                  v-else
                  class="p-8 text-center border rounded-lg bg-light"
                >
                  <VIcon
                    icon="tabler-question-mark"
                    size="48"
                    color="secondary"
                    class="mb-4"
                  />
                  <p class="text-body-1">
                    This question type ({{ currentQuestion.type }}) is not yet supported in the mobile view.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer Navigation -->
          <footer class="p-4 border-t bg-surface d-flex align-center justify-space-between">
            <VBtn
              variant="text"
              prepend-icon="tabler-arrow-left"
              :disabled="currentSectionIndex === 0 && currentQuestionIndex === 0"
              @click="prevQuestion"
            >
              Previous
            </VBtn>

            <div class="d-flex gap-2">
              <VBtn
                v-if="currentSectionIndex < exam.sections.length - 1 || currentQuestionIndex < currentSection.questions.length - 1"
                color="primary"
                append-icon="tabler-arrow-right"
                @click="nextQuestion"
              >
                Next
              </VBtn>
              <VBtn
                v-else
                color="success"
                prepend-icon="tabler-check"
                :loading="isSubmitting"
                @click="finishExam"
              >
                Finish Exam
              </VBtn>
            </div>
          </footer>
        </div>
      </template>
    </VMain>
  </VLayout>
</template>

<style scoped>
.exam-page {
  min-height: 100vh;
}

.hover-bg-light:hover {
  background-color: rgba(var(--v-theme-primary), 0.05);
}

.transition-all {
  transition: all 0.2s ease-in-out;
}

.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.p-4 { padding: 1rem; }
.p-6 { padding: 1.5rem; }
.p-8 { padding: 2rem; }
.px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
.py-4 { padding-top: 1rem; padding-bottom: 1rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mb-8 { margin-bottom: 2rem; }
.mt-2 { margin-top: 0.5rem; }
.gap-2 { gap: 0.5rem; }
.gap-3 { gap: 0.75rem; }
.gap-4 { gap: 1rem; }
</style>
