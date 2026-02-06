<script setup>
import { computed } from 'vue'

const props = defineProps({
  level: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['itemClick'])

// Status logic derived strictly from user_status
const status = computed(() => {
  return props.level.currentUserProgress?.status || 'locked'
})

const isLocked = computed(() => status.value === 'locked')
const isSkipped = computed(() => status.value === 'skipped')
const isCompleted = computed(() => status.value === 'completed')

// Helper for progress calculation
const progressPercentage = computed(() => {
  if (!props.level.items || props.level.items.length === 0) return 0
  
  const lessons = props.level.items.filter(item => item.type === 'lesson')
  if (lessons.length === 0) return 0
  
  const completedCount = lessons.filter(item => item.completed).length
  
  return Math.round((completedCount / lessons.length) * 100)
})

const handleItemClick = item => {
  if (isLocked.value) return
  emit('itemClick', item)
}

const isLastItem = index => {
  return index === props.level.items.length - 1
}

const isCurrentItem = item => {
  if (item.completed || item.locked) return false

  // It is current if it's the first one that is neither completed nor locked
  // But if the level is locked, nothing is current.
  if (isLocked.value) return false
  
  const firstActive = props.level.items.find(i => !i.completed && !i.locked)
  
  return firstActive && firstActive.id === item.id
}
</script>

<template>
  <VCard
    class="level-card"
    border
    flat
    :class="{ 'level-locked': isLocked, 'level-skipped': isSkipped }"
  >
    <!-- Locked Overlay -->
    <div
      v-if="isLocked"
      class="locked-overlay d-flex align-center justify-center"
    >
      <VIcon
        icon="tabler-lock"
        size="48"
        color="medium-emphasis"
      />
    </div>

    <VCardText class="pa-6 position-relative">
      <div class="d-flex align-center justify-space-between mb-2">
        <!-- Level Title -->
        <h2
          class="text-h5 font-weight-bold"
          :class="{ 'text-disabled': isLocked }"
        >
          {{ level.title }}
        </h2>

        <!-- Status Badges -->
        <div
          v-if="isSkipped && progressPercentage < 100"
          class="d-flex align-center"
        >
          <VChip
            color="warning"
            variant="flat"
            size="small"
            class="font-weight-bold"
          >
            SKIPPED
          </VChip>
        </div>
        <div
          v-else-if="isCompleted || (isSkipped && progressPercentage === 100)"
          class="d-flex align-center"
        >
          <VIcon
            icon="tabler-circle-check-filled"
            color="success"
            size="24"
            class="me-2"
          />
          <span class="text-success font-weight-bold">Completed</span>
        </div>
      </div>

      <!-- Progress Bar -->
      <div
        v-if="!isLocked"
        class="mb-8"
      >
        <div class="text-body-2 mb-1 text-medium-emphasis">
          Completed Lessons
        </div>
        <div class="d-flex align-center">
          <VProgressLinear
            :model-value="progressPercentage"
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
            {{ progressPercentage }}%
          </VChip>
        </div>
      </div>
      
      <!-- Placeholder for Skipped Level description -->
      <div
        v-if="isSkipped && progressPercentage < 100"
        class="mb-6 text-body-2 text-warning"
      >
        You tested out of this level. You can still review the content below.
      </div>

      <!-- Timeline Items -->
      <div
        class="timeline-container"
        :class="{ 'opacity-50': isLocked }"
      >
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
                      'avatar-completed': item.completed, // Show checkmarks even if skipped/completed
                      'avatar-locked': item.locked || isLocked,
                      'avatar-current': isCurrentItem(item)
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
                    :color="item.completed ? 'success' : ((item.locked || isLocked) ? 'disabled' : 'primary')"
                  >
                    {{ item.type === 'exam' ? 'tabler-certificate' : 'tabler-book' }}
                  </VIcon>
                </VAvatar>

                <!-- Checkmark Badge -->
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
                v-if="!isLastItem(index)" 
                class="timeline-line"
                :class="{ 'line-completed': item.completed }"
              />
            </div>

            <!-- Content Column -->
            <div 
              class="timeline-content py-4 px-5 mb-4 flex-grow-1 rounded-lg border"
              :class="{ 
                'cursor-pointer': !item.locked && !isLocked,
                'bg-light-primary': isCurrentItem(item)
              }"
              @click="handleItemClick(item)"
            >
              <div class="d-flex align-center justify-space-between">
                <div class="flex-grow-1">
                  <h3 
                    class="text-h6 font-weight-bold mb-1"
                    :class="{ 
                      'text-disabled': item.locked || isLocked, 
                      'text-primary': isCurrentItem(item)
                    }"
                  >
                    {{ item.title }}
                  </h3>
                  <p 
                    class="text-body-2 mb-0"
                    :class="(item.locked || isLocked) ? 'text-disabled' : 'text-medium-emphasis'"
                  >
                    {{ item.description }}
                  </p>
                </div>
                           
                <!-- Play Button -->
                <VBtn
                  v-if="item.type === 'lesson' && item.videoType && !item.locked && !isLocked"
                  icon
                  variant="tonal"
                  color="primary"
                  size="small"
                  class="ms-4"
                  @click.stop="handleItemClick(item)"
                >
                  <VIcon
                    icon="tabler-player-play-filled"
                    size="20"
                  />
                </VBtn>
              </div>

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
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.level-card {
  border-radius: 16px;
  background-color: rgb(var(--v-theme-surface));
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
}

.level-locked {
  background-color: rgb(var(--v-theme-surface)); /* Or slightly grayed out */
  pointer-events: none; /* Disable interaction */
}

.level-skipped {
  border-color: rgb(var(--v-theme-warning)) !important;
  border-style: dashed !important;
}

.locked-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(255, 255, 255, 0.5);
  z-index: 10;
  /* Ensure icon is visible */
}

.opacity-50 {
  opacity: 0.5;
}

/* Timeline Styles (Copied/Adapted) */
.timeline-item-row { display: flex; align-items: flex-start; min-height: 100px; }
.timeline-visual { min-width: 64px; align-self: stretch; }
.avatar-wrapper { position: relative; z-index: 2; padding: 4px; margin: -4px; display: flex; justify-content: center; align-items: center; width: 72px; height: 72px; }
.item-avatar { border: 2px solid transparent; background-color: rgb(var(--v-theme-surface)); transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.avatar-completed { border-color: rgb(var(--v-theme-success)); }
.avatar-current { border-color: rgb(var(--v-theme-primary)); box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), 0.2); }
.avatar-locked { background-color: rgba(var(--v-theme-on-surface), 0.08) !important; border-color: rgba(var(--v-theme-on-surface), 0.12); box-shadow: none; }
.completion-badge { position: absolute; bottom: 2px; right: 2px; width: 24px; height: 24px; background-color: rgb(var(--v-theme-success)); border-radius: 50%; border: 2px solid rgb(var(--v-theme-surface)); display: flex; align-items: center; justify-content: center; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
.timeline-line { width: 4px; flex-grow: 1; background-color: rgba(var(--v-theme-on-surface), 0.08); margin-top: -2px; margin-bottom: -2px; position: relative; z-index: 1; border-radius: 2px; min-height: 40px; }
.line-completed { background-color: rgb(var(--v-theme-success)); }
.timeline-content { background-color: rgb(var(--v-theme-surface)); transition: all 0.2s ease; border-color: rgba(var(--v-theme-on-surface), 0.08) !important; }
.timeline-content:hover:not(.text-disabled) { background-color: rgba(var(--v-theme-primary), 0.04); border-color: rgba(var(--v-theme-primary), 0.4) !important; }
.bg-light-primary { background-color: rgba(var(--v-theme-primary), 0.05) !important; border-color: rgba(var(--v-theme-primary), 0.5) !important; }
.cursor-pointer { cursor: pointer; }
.text-disabled { color: rgba(var(--v-theme-on-surface), 0.38) !important; }

/* Responsive */
@media (max-width: 600px) {
  .item-avatar { width: 48px !important; height: 48px !important; }
  .avatar-wrapper { width: 56px; height: 56px; }
  .timeline-visual { min-width: 56px; }
}
</style>
