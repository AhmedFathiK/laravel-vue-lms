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
          <div
            v-for="(item, itemIndex) in level.items"
            :key="item.id + '-' + item.type"
            class="timeline-item"
            :class="getStatusClasses(item, level)"
          >
            <div
              v-if="itemIndex < level.items.length - 1"
              class="timeline-connector"
            />
            <div class="timeline-avatar-wrapper">
              <VAvatar
                size="100"
                class="timeline-avatar"
                :style="{ cursor: item.locked ? 'not-allowed' : 'pointer' }"
                @click="openModal(item)"
              >
                <VIcon size="x-large">
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
}

.timeline-item {
  display: flex;
  align-items: flex-start;
  position: relative;
  padding-bottom: 16px;
}

.timeline-item:last-child {
  padding-bottom: 0;
}

.timeline-avatar-wrapper {
  position: relative;
  z-index: 2;
  margin-top: 8px;
}

.timeline-avatar {
  border: 2px solid #9E9E9E; /* Default gray border */
  background-color: white;
  transition: all 0.3s ease;
}

.timeline-card {
  width: 100%;
  transition: all 0.3s ease;
  margin-top: 8px;
}

.timeline-connector {
  position: absolute;
  left: 50px; /* Center of 100px avatar */
  top: 0;
  height: 100%;
  width: 2px;
  background-color: #9E9E9E; /* Default gray line */
  z-index: 1;
  margin-top: 58px; /* Offset to start below avatar center? No, usually top: 0 and z-index below avatar */
}
/* Adjust connector logic */
/* In original: left: 50px. Avatar size 100. Center is 50px. */
/* margin-top: 8px on avatar wrapper. */
/* The connector should probably start from the center of the avatar and go down. */
/* But here it seems to run full height. */

/* States */
.status-completed .timeline-avatar {
  border-color: #4CAF50;
  color: #4CAF50;
}
.status-completed .timeline-connector {
  background-color: #4CAF50;
}
.status-completed .timeline-card {
  border-left: 4px solid #4CAF50;
}

.status-current .timeline-avatar {
  border-color: #2196F3;
  color: #2196F3;
  box-shadow: 0 0 12px 4px rgba(33, 150, 243, 0.5);
}
.status-current .timeline-card {
  border-left: 4px solid #2196F3;
}

.status-locked {
  opacity: 0.6;
}

/* Exam Specific Styles */
.item-exam.status-completed .timeline-avatar {
  border-color: #FFC107;
  color: #FFC107;
}
.item-exam.status-completed .timeline-connector {
  background-color: #FFC107;
}
.item-exam.status-completed .timeline-card {
  border-left: 4px solid #FFC107;
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
