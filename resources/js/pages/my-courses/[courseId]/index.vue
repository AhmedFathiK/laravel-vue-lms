<script setup>
import api from '@/utils/api'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

definePage({
  meta: {
    layout: 'learner',
  },
})

const route = useRoute()
const courseData = ref(null)
const loading = ref(true)
const error = ref(null)
const selectedItem = ref(null)
const isModalVisible = ref(false)

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

const openModal = item => {
  if (!item.locked) {
    selectedItem.value = item
    isModalVisible.value = true
  }
}

const closeModal = () => {
  isModalVisible.value = false
  selectedItem.value = null
}

const completeItem = itemToComplete => {
  // This is a placeholder for completion logic.
  // In a real scenario, this would trigger an API call or navigation to the actual lesson content.
  closeModal()
}

const getItemStatus = (item, level) => {
  if (item.completed) {
    return 'completed'
  }
  if (item.locked) {
    return 'locked'
  }
  
  return 'current'
}

const getStatusClasses = (item, level) => {
  const status = getItemStatus(item, level)
  const classes = [`status-${status}`]
  if (item.type === 'exam') {
    classes.push('item-exam')
  }
  
  return classes
}
</script>

<template>
  <VContainer class="course-timeline">
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
      <h1 class="text-h3 mb-10 text-center">
        {{ courseData.title }}
      </h1>

      <div
        v-for="(level) in courseData.levels"
        :key="level.id"
        class="level-section mb-12"
      >
        <h2 class="text-h4 mb-8">
          {{ level.title }}
        </h2>
        <div class="timeline-wrapper">
          <div class="timeline-main-line" />
          <div
            v-for="(item) in level.items"
            :key="item.id + '-' + item.type"
            class="timeline-item"
            :class="getStatusClasses(item, level)"
          >
            <div class="timeline-avatar-wrapper">
              <VAvatar
                size="100"
                class="timeline-avatar"
                :style="{ cursor: item.locked ? 'not-allowed' : 'pointer' }"
                variant="flat"
                color="surface"
                @click="openModal(item)"
              >
                <VIcon
                  size="x-large"
                  :class="{ 'opacity-60': item.locked }"
                >
                  {{ item.icon }}
                </VIcon>
              </VAvatar>
            </div>
            <VCard
              class="timeline-card ms-4"
              :disabled="item.locked"
              @click="openModal(item)"
            >
              <VCardText>
                <div class="d-flex justify-space-between align-center">
                  <h3 class="text-h6">
                    {{ item.title }}
                  </h3>
                  <VChip
                    v-if="item.type === 'exam'"
                    color="amber"
                    text-color="white"
                    size="small"
                  >
                    Exam
                  </VChip>
                </div>
                <p class="text-body-1 mt-2">
                  {{ item.description }}
                </p>
              </VCardText>
            </VCard>
          </div>
        </div>
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
  </VContainer>
</template>

<style scoped>
.course-timeline {
  padding: 2rem;
  max-width: 800px;
  margin: auto;
}

.timeline-wrapper {
  position: relative;
  padding-left: 0;
}

.timeline-main-line {
  position: absolute;
  left: 50px; /* Center of 100px avatar */
  top: 50px; /* Start from center of first avatar */
  bottom: 50px; /* End at center of last avatar */
  width: 2px;
  background-color: rgba(var(--v-border-color), 0.2);
  z-index: 0;
  transition: all 0.3s ease;
}

.timeline-item {
  display: flex;
  align-items: center;
  position: relative;
  padding-bottom: 64px;
  z-index: 1;
}

.timeline-item:last-child {
  padding-bottom: 0;
}

.timeline-avatar-wrapper {
  position: relative;
  z-index: 2;
  flex-shrink: 0;
}

.timeline-avatar {
  border: 2px solid rgba(var(--v-border-color), 0.3);
  background-color: rgb(var(--v-theme-surface)) !important;
  transition: all 0.3s ease;
  z-index: 2;
  position: relative;
  opacity: 1 !important;
}

.timeline-card {
  width: 100%;
  transition: all 0.3s ease;
}

.status-completed .timeline-avatar {
  border-color: rgb(var(--v-theme-success));
  color: rgb(var(--v-theme-success));
}

.status-completed .timeline-card {
  border-inline-start: 4px solid rgb(var(--v-theme-success));
}

.status-current .timeline-avatar {
  border-color: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 12px 4px rgba(var(--v-theme-primary), 0.25);
}

.status-current .timeline-card {
  border-inline-start: 4px solid rgb(var(--v-theme-primary));
}

.status-locked .timeline-card {
  opacity: 0.6;
}

.opacity-60 {
  opacity: 0.6;
}

/* Exam Specific Styles */
.item-exam.status-completed .timeline-avatar {
  border-color: rgb(var(--v-theme-warning));
  color: rgb(var(--v-theme-warning));
}

.item-exam.status-completed .timeline-card {
  border-inline-start: 4px solid rgb(var(--v-theme-warning));
}

@media (max-width: 600px) {
  .course-timeline {
    padding: 1rem;
  }
  .timeline-card .text-h6 {
    font-size: 1rem !important;
  }
  .timeline-card .text-body-1 {
    font-size: 0.875rem !important;
  }
}
</style>
