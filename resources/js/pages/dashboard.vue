<script setup>
import LevelCard from '@/components/learner/LevelCard.vue'
import VideoPlayer from '@/components/VideoPlayer.vue'
import api from '@/utils/api'
import { formatDate } from '@core/utils/formatters'
import { onMounted, ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useActiveCourse } from '@/stores/activeCourse'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: {
    layout: 'learner',
  },
})

const router = useRouter()
const activeCourseStore = useActiveCourse()
const authStore = useAuthStore()

const courseData = ref(null)
const loading = ref(true)
const error = ref(null)
const noAccessData = ref(null)
const selectedItem = ref(null)
const forcedCurrentItem = ref(null)
const isModalVisible = ref(false)
const isVideoModalVisible = ref(false)
const isPricingPlanDialogVisible = ref(false)

const fetchCourseContent = async () => {
  loading.value = true
  error.value = null
  noAccessData.value = null

  if (!activeCourseStore.activeCourseId) {
    await activeCourseStore.fetchActiveCourse()
    if (!activeCourseStore.activeCourseId) {
      router.push('/courses/select')
      loading.value = false
      
      return
    }
  }

  try {
    const response = await api.get('/learner/course-content')

    if (!response) {
      // If content is null but we have an active course ID, it might be invalid
      // Let's clear it and redirect
      activeCourseStore.clearActiveCourse()
      router.push('/courses/select')
      loading.value = false
      
      return
    }

    courseData.value = response

    // Auto-scroll to next lesson logic
    findNextLesson()
  } catch (err) {
    if (err.response?.status === 404) {
      // If course not found, clear and redirect
      activeCourseStore.clearActiveCourse()
      router.push('/courses/select')
      loading.value = false
      
      return
    }

    if (err.response?.status === 403 && err.response?.data?.reason) {
      noAccessData.value = err.response.data
      loading.value = false
      
      return
    }

    error.value = err.response?.data?.error || "Failed to load course content. Please try again later."
  } finally {
    loading.value = false
  }
}

const findNextLesson = () => {
  if (!courseData.value?.levels) return
    
  // Find first unlocked level with incomplete items
  for (const level of courseData.value.levels) {
    const status = level.currentUserProgress?.status

    // Skip levels that are completed or skipped
    if (status === 'completed' || status === 'skipped') continue

    if (level.items) {
      const firstActive = level.items.find(i => !i.completed && !i.locked)
      if (firstActive) {
        forcedCurrentItem.value = { levelId: level.id, itemId: firstActive.id }
        
        // Optional: Scroll to it after a delay
        setTimeout(() => {
          const levelElement = document.getElementById(`level-${level.id}`)
          if (levelElement) {
            levelElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
          }
        }, 500)
        
        return
      }
    }
  }
}

onMounted(fetchCourseContent)

watch(() => activeCourseStore.activeCourseId, newId => {
  if (newId) fetchCourseContent()
  else router.push('/courses/select')
})

const handleItemClick = item => {
  if (item.locked) return

  if (item.type === 'lesson') {
    router.push({ 
      name: 'study-id', 
      params: { id: item.id }, 
    })
  } else if (item.type === 'exam' || item.type === 'placement' || item.item_type === 'exam') {
    router.push({
      name: 'exam-id',
      params: { id: item.id },
    })
  } else {
    // Fallback to modal for exams or other types
    selectedItem.value = item
    isModalVisible.value = true
  }
}

const handlePlayClick = item => {
  if (item.locked) return
  
  if (item.type === 'lesson' && item.videoType) {
    selectedItem.value = item
    isVideoModalVisible.value = true
  }
}

const openModal = item => {
  if (!item.locked) {
    if (item.type === 'lesson' && item.videoType) {
      selectedItem.value = item
      isVideoModalVisible.value = true
    } else {
      selectedItem.value = item
      isModalVisible.value = true
    }
  }
}

const closeModal = () => {
  isModalVisible.value = false
  isVideoModalVisible.value = false
  selectedItem.value = null
}

const completeItem = itemToComplete => {
  // This is a placeholder for completion logic.
  closeModal()
}

const getLevelProgress = level => {
  if (!level.items || level.items.length === 0) return 0
  
  const lessons = level.items.filter(item => item.type === 'lesson')
  if (lessons.length === 0) return 0
  
  const completedCount = lessons.filter(item => item.completed).length
  
  return Math.round((completedCount / lessons.length) * 100)
}

const isLastItem = (level, index) => {
  return index === level.items.length - 1
}

const isCurrentItem = (item, level) => {
  if (item.completed || item.locked) return false

  // It is current if it's the first one that is neither completed nor locked
  const firstActive = level.items.find(i => !i.completed && !i.locked)
  
  return firstActive && firstActive.id === item.id
}

const hasPlacementExam = computed(() => {
  return courseData.value?.placementExam !== null
})

const userHasMeaningfulProgress = computed(() => {
  const levels = courseData.value?.levels ?? []

  const hasStartedLevel = levels.some(level =>
    ['in_progress', 'completed', 'skipped'].includes(level.currentUserProgress?.status),
  )

  const hasCompletedItem = levels.some(level =>
    level.items?.some(item => item.completed),
  )

  return hasStartedLevel || hasCompletedItem
})

const shouldOfferPlacement = computed(() => {
  return hasPlacementExam.value && !userHasMeaningfulProgress.value
})

const placementResult = computed(() => {
  if (!hasPlacementExam.value || !courseData.value.placementExam.outcome) return null
  
  const outcome = courseData.value.placementExam.outcome

  // Fix: Access using camelCase as per middleware conversion
  const levelId = outcome.levelId || outcome.level_id
  const level = courseData.value.levels.find(l => l.id === levelId)
  
  return {
    score: Math.round(outcome.percentage),
    levelTitle: level ? (typeof level.title === 'object' ? (level.title.en || Object.values(level.title)[0]) : level.title) : 'Unknown Level',
    levelId: levelId,
  }
})

const startPlacementExam = () => {
  if (!courseData.value.placementExam) return

  router.push({ 
    name: 'exam-id', 
    params: { id: courseData.value.placementExam.id }, 
  })
}

const scrollToLevels = () => {
  const levelsElement = document.getElementById('course-levels-list')
  if (levelsElement) {
    levelsElement.scrollIntoView({ behavior: 'smooth' })
  }
}

const scrollToLevel = levelId => {
  const levelElement = document.getElementById(`level-${levelId}`)
  if (levelElement) {
    levelElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }
}
</script>

<template>
  <VContainer class="course-timeline pa-4">
    <div
      v-if="loading"
      class="d-flex justify-center align-center"
      style="min-height: 300px"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
    </div>

    <div
      v-else-if="noAccessData"
      class="d-flex justify-center align-center flex-column"
      style="min-height: 60vh"
    >
      <VCard
        width="500"
        class="text-center overflow-hidden"
      >
        <VImg
          v-if="noAccessData.course?.thumbnail"
          :src="noAccessData.course.thumbnail"
          height="200"
          cover
        />
        
        <VCardTitle class="text-h5 pt-6 font-weight-bold text-wrap">
          {{ noAccessData.course?.title }}
        </VCardTitle>

        <VCardText class="pb-2">
          <VDivider class="my-4" />

          <div class="d-flex align-center justify-center gap-2 mb-4">
            <VIcon 
              color="error" 
              size="32" 
              icon="tabler-lock"
            />
            <h3 class="text-h6 text-error">
              Access Restricted
            </h3>
          </div>

          <p class="text-body-1 mb-6">
            <template v-if="noAccessData.reason === 'expired'">
              Your subscription for this course expired on <strong>{{ noAccessData.entitlement?.endsAt ? formatDate(noAccessData.entitlement.endsAt) : (noAccessData.entitlement?.updatedAt ? formatDate(noAccessData.entitlement.updatedAt) : 'Unknown Date') }}</strong>.
            </template>
            <template v-else-if="noAccessData.reason === 'canceled'">
              Your subscription was canceled on <strong>{{ noAccessData.entitlement?.updatedAt ? formatDate(noAccessData.entitlement.updatedAt) : (noAccessData.entitlement?.endsAt ? formatDate(noAccessData.entitlement.endsAt) : 'Unknown Date') }}</strong>.
            </template>
            <template v-else-if="noAccessData.reason === 'past_due'">
              Your payment is past due. Please update your payment method to regain access.
            </template>
            <template v-else-if="noAccessData.reason === 'not_enrolled'">
              You are currently not enrolled in this course.
            </template>
            <template v-else>
              You do not have active access to this course. Please contact support if you believe this is an error.
            </template>
          </p>
        </VCardText>

        <VCardActions class="justify-center flex-column pb-6 px-6 gap-2">
          <VBtn
            v-if="['expired', 'canceled', 'past_due', 'not_enrolled'].includes(noAccessData.reason)"
            block
            color="primary"
            variant="elevated"
            size="large"
            :to="{ name: 'courses-id', params: { id: noAccessData.course?.id } }"
          >
            {{ noAccessData.reason === 'not_enrolled' ? 'View Enrollment Options' : 'Renew Access' }}
          </VBtn>
          <VBtn
            v-else
            block
            color="primary"
            variant="tonal"
            href="mailto:support@example.com"
          >
            Contact Support
          </VBtn>
           
          <VBtn
            block
            color="secondary"
            variant="text"
            to="/courses/select"
          >
            Select Another Course
          </VBtn>
        </VCardActions>
      </VCard>
    </div>

    <VAlert
      v-else-if="error"
      color="error"
      variant="tonal"
      density="compact"
      class="mb-4"
      icon="tabler-alert-circle"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <span>{{ error }}</span>
        <VBtn
          color="error"
          variant="text"
          size="small"
          prepend-icon="tabler-refresh"
          @click="fetchCourseContent"
        >
          Retry
        </VBtn>
      </div>
    </VAlert>

    <template v-else-if="courseData">
      <VAlert
        v-if="courseData.entitlement?.isGracePeriod"
        variant="tonal"
        color="warning"
        class="mb-6"
      >
        <template #prepend>
          <VIcon icon="tabler-alert-triangle" />
        </template>
        Your subscription for this course has expired, but you are currently in a grace period. Please renew your plan to ensure uninterrupted access.
        <template #append>
          <VBtn
            color="warning"
            variant="flat"
            size="small"
            @click="isPricingPlanDialogVisible = true"
          >
            Renew Now
          </VBtn>
        </template>
      </VAlert>

      <h1 class="text-h3 mb-6 text-center">
        {{ courseData.course?.title || courseData.title }}
      </h1>

      <!-- Placement CTA Section -->
      <VCard
        v-if="shouldOfferPlacement"
        variant="tonal"
        color="primary"
        class="mb-8"
      >
        <VCardText class="d-flex flex-column flex-md-row align-center pa-6 gap-6">
          <div class="flex-grow-1 text-center text-md-left">
            <h2 class="text-h4 font-weight-bold mb-2">
              Not sure where to start?
            </h2>
            <p class="text-body-1 mb-0 opacity-90">
              Take a short placement test to find the right level for you.
            </p>
          </div>
           
          <div
            class="d-flex flex-column align-center gap-3"
            style="min-width: 250px;"
          >
            <VBtn
              block
              color="primary"
              variant="flat"
              class="font-weight-bold"
              size="large"
              prepend-icon="tabler-wand"
              @click="startPlacementExam"
            >
              Take Placement Test
            </VBtn>
              
            <VBtn
              block
              variant="text"
              color="primary"
              size="small"
              class="opacity-80"
              @click="scrollToLevels"
            >
              Skip and start from Level 1
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <!-- Placement Result Section -->
      <VCard
        v-else-if="placementResult"
        variant="tonal"
        color="success"
        class="mb-8"
      >
        <VCardText class="d-flex flex-column flex-md-row align-center pa-6 gap-6">
          <div class="flex-grow-1 text-center text-md-left">
            <h2 class="text-h4 font-weight-bold mb-2">
              Placement Test Completed
            </h2>
            <p class="text-body-1 mb-0 opacity-90">
              You scored {{ placementResult.score }}% and placed into <strong>{{ placementResult.levelTitle }}</strong>.
            </p>
          </div>
           
          <div
            class="d-flex flex-column align-center gap-3"
            style="min-width: 250px;"
          >
            <VBtn
              block
              color="success"
              variant="flat"
              class="font-weight-bold"
              size="large"
              prepend-icon="tabler-arrow-right"
              @click="scrollToLevel(placementResult.levelId)"
            >
              Go to Level
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <!-- Levels Section -->
      <div id="course-levels-list">
        <div
          v-for="(level) in courseData.levels"
          :id="`level-${level.id}`"
          :key="level.id"
          class="mb-8"
        >
          <LevelCard 
            :level="level" 
            :forced-current-item-id="forcedCurrentItem?.levelId === level.id ? forcedCurrentItem.itemId : null"
            @item-click="handleItemClick"
          />
        </div>
      </div>

      <!-- Final Exam Section -->
      <div
        v-if="courseData.finalExam"
        class="mb-8"
      >
        <h2 class="text-h5 font-weight-bold mb-4 px-2">
          Final Certification
        </h2>
        <VCard
          border
          flat
          class="level-card mb-4"
        >
          <VCardText class="pa-6">
            <div class="timeline-container">
              <div class="timeline-item-row">
                <!-- Visual Column -->
                <div class="timeline-visual d-flex flex-column align-center me-5">
                  <div class="avatar-wrapper">
                    <VAvatar
                      size="64"
                      class="item-avatar"
                      :class="[{ 'avatar-completed': courseData.finalExam.completed, 'avatar-locked': courseData.finalExam.locked }]"
                      @click="handleItemClick(courseData.finalExam)"
                    >
                      <VIcon
                        size="28"
                        :color="courseData.finalExam.completed ? 'success' : (courseData.finalExam.locked ? 'disabled' : 'primary')"
                      >
                        tabler-certificate
                      </VIcon>
                    </VAvatar>
                    <div
                      v-if="courseData.finalExam.completed"
                      class="completion-badge"
                    >
                      <VIcon
                        size="14"
                        color="white"
                        icon="tabler-check"
                      />
                    </div>
                  </div>
                </div>

                <!-- Content Column -->
                <div 
                  class="timeline-content py-4 px-5 mb-4 flex-grow-1 rounded-lg border"
                  :class="{ 'cursor-pointer': !courseData.finalExam.locked }"
                  @click="handleItemClick(courseData.finalExam)"
                >
                  <div class="d-flex align-center justify-space-between">
                    <div class="flex-grow-1">
                      <h3
                        class="text-h6 font-weight-bold mb-1"
                        :class="{ 'text-disabled': courseData.finalExam.locked }"
                      >
                        {{ courseData.finalExam.title }}
                      </h3>
                      <p
                        class="text-body-2 mb-0"
                        :class="courseData.finalExam.locked ? 'text-disabled' : 'text-medium-emphasis'"
                      >
                        {{ courseData.finalExam.description }}
                      </p>
                    </div>
                  </div>
                  <VChip
                    color="primary"
                    size="x-small"
                    class="mt-2"
                    variant="tonal"
                  >
                    Course Certification
                  </VChip>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>

      <!-- Other Course Exams Section -->
      <div
        v-if="courseData.other_exams && courseData.other_exams.length > 0"
        class="mb-8"
      >
        <h2 class="text-h5 font-weight-bold mb-4 px-2">
          Additional Exams
        </h2>
        <VCard
          border
          flat
          class="level-card mb-4"
        >
          <VCardText class="pa-6">
            <div class="timeline-container">
              <div
                v-for="(item, index) in courseData.other_exams"
                :key="item.id + '-other'"
                class="timeline-item-row"
              >
                <!-- Visual Column -->
                <div class="timeline-visual d-flex flex-column align-center me-5">
                  <div class="avatar-wrapper">
                    <VAvatar
                      size="64"
                      class="item-avatar"
                      :class="[{ 'avatar-completed': item.completed, 'avatar-locked': item.locked }]"
                      @click="handleItemClick(item)"
                    >
                      <VIcon
                        size="28"
                        :color="item.completed ? 'success' : (item.locked ? 'disabled' : 'info')"
                      >
                        tabler-clipboard-check
                      </VIcon>
                    </VAvatar>
                    <div
                      v-if="item.completed"
                      class="completion-badge"
                    >
                      <VIcon
                        size="14"
                        color="white"
                        icon="tabler-check"
                      />
                    </div>
                  </div>
                  <div
                    v-if="index < courseData.other_exams.length - 1"
                    class="timeline-line"
                    :class="{ 'line-completed': item.completed }"
                  />
                </div>

                <!-- Content Column -->
                <div 
                  class="timeline-content py-4 px-5 mb-4 flex-grow-1 rounded-lg border"
                  :class="{ 'cursor-pointer': !item.locked }"
                  @click="handleItemClick(item)"
                >
                  <div class="d-flex align-center justify-space-between">
                    <div class="flex-grow-1">
                      <h3
                        class="text-h6 font-weight-bold mb-1"
                        :class="{ 'text-disabled': item.locked }"
                      >
                        {{ item.title }}
                      </h3>
                      <p
                        class="text-body-2 mb-0"
                        :class="item.locked ? 'text-disabled' : 'text-medium-emphasis'"
                      >
                        {{ item.description }}
                      </p>
                    </div>
                  </div>
                  <VChip
                    color="info"
                    size="x-small"
                    class="mt-2"
                    variant="tonal"
                  >
                    Extra Exam
                  </VChip>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>
    </template>

    <VDialog
      v-model="isModalVisible"
      max-width="600px"
    >
      <VCard>
        <VCardTitle class="text-h5">
          {{ selectedItem?.title }}
        </VCardTitle>
        <VCardText>
          <p>{{ selectedItem?.description }}</p>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="grey"
            variant="text"
            @click="closeModal"
          >
            Close
          </VBtn>
          <VBtn
            v-if="selectedItem && !selectedItem.completed"
            color="primary"
            @click="completeItem(selectedItem)"
          >
            Complete
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Video Player Modal -->
    <VDialog
      v-model="isVideoModalVisible"
      fullscreen
      transition="dialog-bottom-transition"
      class="video-player-dialog"
    >
      <VCard
        color="black"
        class="d-flex flex-column h-screen position-relative"
      >
        <!-- Transparent Overlay Header -->
        <div class="video-header-overlay d-flex align-center px-6 py-4">
          <div class="text-h6 text-white font-weight-medium text-truncate">
            {{ selectedItem?.title }}
          </div>
          
          <VSpacer />
          
          <VBtn
            icon
            variant="tonal"
            color="white"
            class="close-btn"
            @click="closeModal"
          >
            <VIcon size="24">
              tabler-x
            </VIcon>
          </VBtn>
        </div>
        
        <VCardText class="flex-grow-1 d-flex align-center justify-center pa-0 bg-black overflow-hidden">
          <div class="w-100 h-100 d-flex align-center justify-center">
            <VideoPlayer 
              v-if="selectedItem && selectedItem.videoType"
              :key="selectedItem.id"
              :src="selectedItem.videoUrl" 
              :type="selectedItem.videoType" 
              autoplay 
              class="video-player-content"
            />
          </div>
        </VCardText>
      </VCard>
    </VDialog>

    <PricingPlanDialog
      v-model:is-dialog-visible="isPricingPlanDialogVisible"
      :course-id="courseData?.course?.id || courseData?.id"
      :active-entitlement="courseData?.entitlement"
    />
  </VContainer>
</template>

<style scoped>
.course-timeline {
  max-width: 900px;
  margin: auto;
}

.level-card {
    border-radius: 16px;
    background-color: rgb(var(--v-theme-surface));
}

.timeline-item-row {
    display: flex;
    align-items: flex-start;
    min-height: 100px; /* Ensure enough space */
}

.timeline-visual {
    min-width: 64px;
    align-self: stretch;
}

.avatar-wrapper {
    position: relative;
    z-index: 2;
    padding: 4px; /* Space for border/shadow */
    margin: -4px; /* Compensate for padding to keep alignment */
    display: flex;
    justify-content: center;
    align-items: center;
    width: 72px; /* 64 + 4 + 4 */
    height: 72px;
}

.item-avatar {
    border: 2px solid transparent;
    background-color: rgb(var(--v-theme-surface));
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.avatar-completed {
    border-color: rgb(var(--v-theme-success));
}

.avatar-current {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), 0.2);
}

.avatar-locked {
    background-color: rgba(var(--v-theme-on-surface), 0.08) !important;
    border-color: rgba(var(--v-theme-on-surface), 0.12);
    cursor: not-allowed;
    box-shadow: none;
    opacity: 1; /* Override Vuetify's potential opacity on disabled */
}

.icon-locked {
    color: rgba(var(--v-theme-on-surface), 0.38) !important;
}

.text-disabled {
    color: rgba(var(--v-theme-on-surface), 0.38) !important;
}

.timeline-content {
    background-color: rgb(var(--v-theme-surface));
    transition: all 0.2s ease;
    border-color: rgba(var(--v-theme-on-surface), 0.08) !important;
}

.timeline-content:hover:not(.text-disabled) {
    background-color: rgba(var(--v-theme-primary), 0.04);
    border-color: rgba(var(--v-theme-primary), 0.4) !important;
}

.bg-light-primary {
    background-color: rgba(var(--v-theme-primary), 0.05) !important;
    border-color: rgba(var(--v-theme-primary), 0.5) !important;
}

.play-btn {
    transition: all 0.2s ease;
}

.play-btn:hover {
    transform: scale(1.1);
    background-color: rgb(var(--v-theme-primary)) !important;
    color: white !important;
}

/* Badge styling */
.completion-badge {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 24px;
    height: 24px;
    background-color: rgb(var(--v-theme-success));
    border-radius: 50%;
    border: 2px solid rgb(var(--v-theme-surface));
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.timeline-line {
    width: 4px;
    flex-grow: 1;
    background-color: rgba(var(--v-theme-on-surface), 0.08); /* Faint line for better contrast in dark mode */
    margin-top: -2px; 
    margin-bottom: -2px;
    position: relative;
    z-index: 1;
    border-radius: 2px;
    min-height: 40px;
}

.line-completed {
    background-color: rgb(var(--v-theme-success));
}

.cursor-pointer {
    cursor: pointer;
}

/* Video Player Dialog Enhancements */
.video-header-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  background: linear-gradient(to bottom, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 100%);
  pointer-events: none;
  transition: opacity 0.3s ease;
}

.video-header-overlay > * {
  pointer-events: auto;
}

.video-player-dialog :deep(.v-card) {
  background-color: #000 !important;
}

.close-btn {
  background-color: rgba(255, 255, 255, 0.1) !important;
  backdrop-filter: blur(8px);
  transition: all 0.2s ease;
}

.close-btn:hover {
  background-color: rgba(255, 255, 255, 0.2) !important;
  transform: scale(1.1);
}

.video-player-content {
  width: 100%;
  max-width: 1200px;
  height: auto;
  aspect-ratio: 16/9;
}

/* Hide header when user is inactive (if implemented in player) */
.video-player-dialog:hover .video-header-overlay {
  opacity: 1;
}

/* Responsive adjustments */
@media (max-width: 600px) {
  .course-timeline {
    padding: 1rem !important;
  }
  
  .item-avatar {
      width: 48px !important;
      height: 48px !important;
  }
  
  .avatar-wrapper {
      width: 56px;
      height: 56px;
  }
  
  .timeline-visual {
      min-width: 56px;
  }
  
  .text-h6 {
      font-size: 1rem !important;
  }
}
</style>