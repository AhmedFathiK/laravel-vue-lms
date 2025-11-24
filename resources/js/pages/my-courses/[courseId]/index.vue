<script setup>
import { ref, computed } from 'vue'

definePage({
  meta: {
    layout: 'learner',
  },
})

const courseData = ref({
  title: 'Interactive Learning Path',
  levels: [
    {
      id: 1,
      name: 'Level 1: The Basics',
      items: [
        { id: 1, type: 'lesson', title: 'Introduction to the Course', description: 'A brief overview of what you will learn.', icon: 'tabler-book', completed: true, locked: false },
        { id: 2, type: 'lesson', title: 'Setting Up Your Environment', description: 'Step-by-step guide to get your tools ready.', icon: 'tabler-tools', completed: true, locked: false },
        { id: 3, type: 'lesson', title: 'Hello World!', description: 'Your first simple program.', icon: 'tabler-code', completed: true, locked: false },
        { id: 4, type: 'lesson', title: 'Understanding Variables', description: 'Learn about data types and variables.', icon: 'tabler-variable', completed: true, locked: true },
        { id: 5, type: 'exam', title: 'Level 1 Exam', description: 'Test your knowledge on the basics.', icon: 'tabler-clipboard-check', completed: false, locked: true },
      ],
    },
    {
      id: 2,
      name: 'Level 2: Core Concepts',
      items: [
        { id: 6, type: 'lesson', title: 'Functions and Methods', description: 'Diving into reusable code blocks.', icon: 'tabler-function', completed: false, locked: true },
        { id: 7, type: 'lesson', title: 'Control Flow', description: 'Mastering loops and conditionals.', icon: 'tabler-arrows-random', completed: false, locked: true },
        { id: 8, type: 'lesson', title: 'Data Structures', description: 'Working with arrays and objects.', icon: 'tabler-brackets-contain', completed: false, locked: true },
        { id: 9, type: 'exam', title: 'Level 2 Exam', description: 'Test your knowledge on core concepts.', icon: 'tabler-clipboard-check', completed: false, locked: true },
      ],
    },
    {
      id: 3,
      name: 'Level 3: Advanced Topics',
      items: [
        { id: 10, type: 'lesson', title: 'Asynchronous Programming', description: 'Understanding promises and async/await.', icon: 'tabler-clock-hour-3', completed: false, locked: true },
        { id: 11, type: 'lesson', title: 'Working with APIs', description: 'Fetching data from external sources.', icon: 'tabler-api', completed: false, locked: true },
        { id: 12, type: 'exam', title: 'Level 3 Exam', description: 'Test your knowledge on advanced topics.', icon: 'tabler-clipboard-check', completed: false, locked: true },
      ],
    },
  ],

})

const selectedItem = ref(null)
const isModalVisible = ref(false)

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
  let level
  let itemIndex = -1

  for (const l of courseData.value.levels) {
    const index = l.items.findIndex(i => i.id === itemToComplete.id)
    if (index !== -1) {
      level = l
      itemIndex = index
      break
    }
  }

  if (level && itemIndex !== -1) {
    // Mark current item as complete
    level.items[itemIndex].completed = true

    // Unlock next item
    if (itemIndex + 1 < level.items.length) {
      level.items[itemIndex + 1].locked = false
    }
  }

  closeModal()
}

const getItemStatus = (item, level) => {
  if (item.completed) {
    return 'completed'
  }
  if (!item.locked) {
    const firstIncompleteIndex = level.items.findIndex(i => !i.completed)
    const currentItemIndex = level.items.findIndex(i => i.id === item.id)
    if (currentItemIndex === firstIncompleteIndex) {
      return 'current'
    }
  }
  
  return 'locked'
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
    <h1 class="text-h3 mb-10 text-center">
      {{ courseData.title }}
    </h1>

    <div
      v-for="(level) in courseData.levels"
      :key="level.id"
      class="level-section mb-12"
    >
      <h2 class="text-h4 mb-8">
        {{ level.name }}
      </h2>
      <div class="timeline-wrapper">
        <div
          v-for="(item, itemIndex) in level.items"
          :key="item.id"
          class="timeline-item"
          :class="getStatusClasses(item, level)"
        >
          <div
            v-if="itemIndex < level.items.length - 2"
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
                  small
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
            text
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
  left: 50px; /* Center of 56px avatar */
  top: 0;
  height: 100%;
  width: 2px;
  background-color: #9E9E9E; /* Default gray line */
  z-index: 1;
  margin-top: 8px;
}

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
