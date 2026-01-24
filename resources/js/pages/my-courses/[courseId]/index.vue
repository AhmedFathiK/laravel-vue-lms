<script setup>
import VideoPlayer from '@/components/VideoPlayer.vue'
import api from '@/utils/api'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

definePage({
  meta: {
    layout: 'learner',
  },
})

const router = useRouter()
const route = useRoute()
const courseData = ref(null)
const loading = ref(true)
const error = ref(null)
const selectedItem = ref(null)
const isModalVisible = ref(false)
const isVideoModalVisible = ref(false)

const fetchCourseContent = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await api.get(`/learner/my-courses/${route.params.courseId}`)

    courseData.value = response
  } catch (err) {
    console.error(err)
    error.value = err.response?.data?.error || "Failed to load course content. Please try again later."
  } finally {
    loading.value = false
  }
}

onMounted(fetchCourseContent)

const handleItemClick = item => {
  if (item.locked) return

  if (item.type === 'lesson') {
    router.push({ 
      name: 'my-courses-study-id', 
      params: { id: item.id }, 
    })
  } else if (item.type === 'exam' || item.type === 'placement' || item.item_type === 'exam') {
    router.push({
      name: 'my-courses-exam-id',
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
  // In a real scenario, this would trigger an API call or navigation to the actual lesson content.
  closeModal()
}

const getLevelProgress = level => {
  if (!level.items || level.items.length === 0) return 0
  const completedCount = level.items.filter(item => item.completed).length
  
  return Math.round((completedCount / level.items.length) * 100)
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
      <h1 class="text-h3 mb-6 text-center">
        {{ courseData.title }}
      </h1>

      <!-- Placement Exam Section -->
      <div
        v-if="courseData.placementExam"
        class="mb-8"
      >
        <h2 class="text-h5 font-weight-bold mb-4 px-2">
          Placement Test
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
                      :class="[{ 'avatar-completed': courseData.placementExam.completed, 'avatar-locked': courseData.placementExam.locked }]"
                      @click="handleItemClick(courseData.placementExam)"
                    >
                      <VIcon
                        size="28"
                        :color="courseData.placementExam.completed ? 'success' : (courseData.placementExam.locked ? 'disabled' : 'warning')"
                      >
                        tabler-list-check
                      </VIcon>
                    </VAvatar>
                    <div
                      v-if="courseData.placementExam.completed"
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
                  :class="{ 'cursor-pointer': !courseData.placementExam.locked }"
                  @click="handleItemClick(courseData.placementExam)"
                >
                  <div class="d-flex align-center justify-space-between">
                    <div class="flex-grow-1">
                      <h3
                        class="text-h6 font-weight-bold mb-1"
                        :class="{ 'text-disabled': courseData.placementExam.locked }"
                      >
                        {{ courseData.placementExam.title }}
                      </h3>
                      <p
                        class="text-body-2 mb-0"
                        :class="courseData.placementExam.locked ? 'text-disabled' : 'text-medium-emphasis'"
                      >
                        {{ courseData.placementExam.description }}
                      </p>
                    </div>
                  </div>
                  <VChip
                    color="warning"
                    size="x-small"
                    class="mt-2"
                    variant="tonal"
                  >
                    Placement Test
                  </VChip>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>

      <!-- Levels Section -->
      <div
        v-for="(level) in courseData.levels"
        :key="level.id"
        class="mb-8"
      >
        <VCard
          class="level-card"
          border
          flat
        >
          <VCardText class="pa-6">
            <!-- Level Title -->
            <h2 class="text-h5 font-weight-bold mb-2">
              {{ level.title }}
            </h2>

            <!-- Progress Bar -->
            <div class="d-flex align-center mb-8">
              <VProgressLinear
                :model-value="getLevelProgress(level)"
                color="success"
                height="10"
                rounded
                class="flex-grow-1"
                bg-color="#E0E0E0"
                bg-opacity="1"
              />
              <VChip 
                color="success" 
                size="small" 
                class="ms-4 font-weight-bold" 
                variant="flat"
              >
                {{ getLevelProgress(level) }}%
              </VChip>
            </div>

            <!-- Timeline Items -->
            <div class="timeline-container">
              <template v-if="level.items && level.items.length > 0">
                <div
                  v-for="(item, index) in level.items"
                  :key="item.id + '-' + item.type"
                  class="timeline-item-row"
                >
                  <!-- Visual Column (Avatar + Line) -->
                  <div class="timeline-visual d-flex flex-column align-center me-5">
                    <div class="avatar-wrapper">
                      <VAvatar
                        size="64"
                        class="item-avatar"
                        :class="[
                          { 
                            'avatar-completed': item.completed, 
                            'avatar-locked': item.locked,
                            'avatar-current': isCurrentItem(item, level)
                          }
                        ]"
                        @click="handleItemClick(item)"
                      >
                        <VImg
                          v-if="item.thumbnail"
                          :src="item.thumbnail"
                          cover
                        />
                        <VIcon
                          v-else
                          size="28"
                          :color="item.completed ? 'success' : (item.locked ? 'disabled' : 'primary')"
                          :class="{ 'icon-locked': item.locked }"
                        >
                          {{ item.type === 'exam' ? 'tabler-certificate' : 'tabler-book' }}
                        </VIcon>
                      </VAvatar>

                      <!-- Checkmark Badge (Now outside VAvatar) -->
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
                               
                    <!-- Connecting Line -->
                    <div 
                      v-if="!isLastItem(level, index)" 
                      class="timeline-line"
                      :class="{ 'line-completed': item.completed }"
                    />
                  </div>

                  <!-- Content Column -->
                  <div 
                    class="timeline-content py-4 px-5 mb-4 flex-grow-1 rounded-lg border"
                    :class="{ 
                      'cursor-pointer': !item.locked,
                      'bg-light-primary': isCurrentItem(item, level)
                    }"
                    @click="handleItemClick(item)"
                  >
                    <div class="d-flex align-center justify-space-between">
                      <div class="flex-grow-1">
                        <h3 
                          class="text-h6 font-weight-bold mb-1"
                          :class="{ 
                            'text-disabled': item.locked, 
                            'text-primary': isCurrentItem(item, level)
                          }"
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
                                 
                      <!-- Desktop Play Button -->
                      <VBtn
                        v-if="item.type === 'lesson' && item.videoType && !item.locked"
                        icon
                        variant="tonal"
                        color="primary"
                        size="small"
                        class="ms-4 play-btn d-none d-sm-inline-flex"
                        @click.stop="handlePlayClick(item)"
                      >
                        <VIcon
                          icon="tabler-player-play-filled"
                          size="20"
                        />
                      </VBtn>
                    </div>

                    <!-- Mobile Play Button -->
                    <VBtn
                      v-if="item.type === 'lesson' && item.videoType && !item.locked"
                      block
                      variant="tonal"
                      color="primary"
                      rounded="pill"
                      size="small"
                      class="mt-3 d-flex d-sm-none"
                      prepend-icon="tabler-player-play-filled"
                      @click.stop="handlePlayClick(item)"
                    >
                      Watch Video
                    </VBtn>

                    <VChip
                      v-if="item.type === 'exam'"
                      color="warning"
                      size="x-small"
                      class="mt-2"
                      variant="tonal"
                    >
                      Exam
                    </VChip>
                  </div>
                </div>
              </template>

              <div
                v-else
                class="d-flex flex-column align-center justify-center py-12 text-center"
              >
                <VIcon
                  icon="tabler-book-off"
                  size="48"
                  color="disabled"
                  class="mb-2"
                />
                <div class="text-h6 text-disabled">
                  No lessons yet
                </div>
                <div class="text-body-2 text-disabled">
                  New content will be added soon.
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
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
              <div
                class="timeline-item-row"
              >
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
