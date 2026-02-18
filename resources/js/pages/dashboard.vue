<template>
  <VContainer
    v-if="loading"
    class="fill-height"
  >
    <VRow justify="center">
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
    </VRow>
  </VContainer>

  <VContainer
    v-else-if="!activeCourseStore.activeCourseId"
    class="fill-height"
  >
    <VRow
      justify="center"
      class="text-center"
    >
      <VCol cols="12">
        <h2 class="text-h4 mb-4">
          No Active Course Selected
        </h2>
        <VBtn
          color="primary"
          to="/courses/select"
        >
          Select a Course
        </VBtn>
      </VCol>
    </VRow>
  </VContainer>

  <VContainer
    v-else
    class="course-timeline pa-4"
  >
    <!-- Active Course Header Stats -->
    <VRow class="mb-6">
      <VCol cols="12">
        <VCard
          elevation="2"
          class="rounded-lg"
        >
          <VCardText class="d-flex justify-space-between align-center py-4">
            <div class="d-flex align-center gap-4">
              <VAvatar
                size="56"
                rounded="lg"
              >
                <VImg
                  :src="courseData?.thumbnail || '/placeholder.jpg'"
                  cover
                />
              </VAvatar>
              <div>
                <h2 class="text-h5 font-weight-bold">
                  {{ courseData?.title }}
                </h2>
                <div class="text-body-2 text-medium-emphasis">
                  {{ stats?.progress }}% Complete
                </div>
              </div>
            </div>
            
            <div class="d-flex gap-6 text-center">
              <div>
                <div class="text-h6 font-weight-bold text-warning">
                  <VIcon
                    icon="tabler-flame"
                    size="20"
                    class="mb-1"
                  />
                  {{ stats?.streak }}
                </div>
                <div class="text-caption">
                  Day Streak
                </div>
              </div>
              <div>
                <div class="text-h6 font-weight-bold text-primary">
                  <VIcon
                    icon="tabler-award"
                    size="20"
                    class="mb-1"
                  />
                  {{ stats?.xp }}
                </div>
                <div class="text-caption">
                  Total XP
                </div>
              </div>
              <div>
                <div
                  class="text-h6 font-weight-bold"
                  :class="stats?.due_reviews > 0 ? 'text-error' : 'text-success'"
                >
                  <VIcon
                    icon="tabler-refresh"
                    size="20"
                    class="mb-1"
                  />
                  {{ stats?.due_reviews }}
                </div>
                <div class="text-caption">
                  Reviews
                </div>
              </div>
            </div>
            
            <div class="d-none d-md-flex">
              <VBtn
                variant="text"
                icon="tabler-settings"
                to="/courses/select"
                title="Switch Course"
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Error Alert -->
    <VAlert
      v-if="error"
      color="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="error = null"
    >
      {{ error }}
      <template #append>
        <VBtn
          variant="text"
          size="small"
          @click="fetchData"
        >
          Retry
        </VBtn>
      </template>
    </VAlert>

    <!-- Content Tree -->
    <template v-if="courseData">
      <!-- Placement CTA -->
      <VCard
        v-if="shouldOfferPlacement"
        color="primary"
        theme="dark"
        class="mb-8"
        elevation="4"
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
          <div class="d-flex flex-column align-center gap-3">
            <VBtn
              color="white"
              variant="flat"
              class="text-primary font-weight-bold"
              size="large"
              prepend-icon="tabler-wand"
              @click="startPlacementExam"
            >
              Take Placement Test
            </VBtn>
            <VBtn
              variant="text"
              color="white"
              size="small"
              class="opacity-80"
              @click="scrollToLevels"
            >
              Skip and start from Level 1
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <!-- Placement Result -->
      <VCard
        v-else-if="placementResult"
        color="success"
        theme="dark"
        class="mb-8"
        elevation="4"
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
          <div class="d-flex flex-column align-center gap-3">
            <VBtn
              color="white"
              variant="flat"
              class="text-success font-weight-bold"
              size="large"
              prepend-icon="tabler-arrow-right"
              @click="scrollToLevel(placementResult.levelId)"
            >
              Go to Level
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <!-- Levels List -->
      <div id="course-levels-list">
        <div
          v-for="level in courseData.levels"
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
      
      <!-- Final Exam -->
      <div
        v-if="courseData.finalExam"
        class="mb-8"
      >
        <h2 class="text-h5 font-weight-bold mb-4 px-2">
          Final Certification
        </h2>
        <!--
          Reuse existing logic for final exam card rendering from my-courses/[id]/index.vue if needed, 
          or simplified version here since LevelCard handles most items. 
          Actually LevelCard handles ITEMS inside a level. Final Exam is separate.
          I should verify LevelCard usage. 
        -->
        <VCard
          border
          flat
          class="level-card mb-4"
        >
          <VCardText class="pa-6">
            <div class="d-flex align-center">
              <VAvatar
                size="64"
                :color="courseData.finalExam.completed ? 'success' : (courseData.finalExam.locked ? 'grey' : 'primary')"
                variant="tonal"
                class="me-4"
                @click="handleItemClick(courseData.finalExam)"
              >
                <VIcon
                  icon="tabler-certificate"
                  size="32"
                />
              </VAvatar>
              <div>
                <div class="text-h6">
                  {{ courseData.finalExam.title }}
                </div>
                <div class="text-body-2">
                  {{ courseData.finalExam.description }}
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>
    </template>
  </VContainer>

  <!-- Modals -->
  <VDialog
    v-model="isModalVisible"
    max-width="600px"
  >
    <VCard>
      <VCardTitle>{{ selectedItem?.title }}</VCardTitle>
      <VCardText>{{ selectedItem?.description }}</VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="primary"
          @click="isModalVisible = false"
        >
          Close
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  
  <VDialog
    v-model="isVideoModalVisible"
    fullscreen
    transition="dialog-bottom-transition"
  >
    <VCard
      color="black"
      class="d-flex flex-column h-screen"
    >
      <div class="d-flex align-center px-6 py-4 bg-black">
        <span class="text-white">{{ selectedItem?.title }}</span>
        <VSpacer />
        <VBtn
          icon
          variant="text"
          color="white"
          @click="isVideoModalVisible = false"
        >
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </div>
      <VCardText class="flex-grow-1 d-flex align-center justify-center pa-0 bg-black">
        <VideoPlayer
          v-if="selectedItem?.videoType"
          :src="selectedItem.videoUrl"
          :type="selectedItem.videoType"
          autoplay
          class="w-100"
        />
      </VCardText>
    </VCard>
  </VDialog>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useActiveCourse } from '@/stores/activeCourse'
import api from '@/utils/api'
import LevelCard from '@/components/learner/LevelCard.vue'
import VideoPlayer from '@/components/VideoPlayer.vue'

const router = useRouter()
const activeCourseStore = useActiveCourse()
const loading = ref(true)
const courseData = ref(null)
const stats = ref(null)
const error = ref(null)
const forcedCurrentItem = ref(null)
const selectedItem = ref(null)
const isModalVisible = ref(false)
const isVideoModalVisible = ref(false)

const fetchData = async () => {
  loading.value = true
  error.value = null
  
  if (!activeCourseStore.activeCourseId) {
    // Try to fetch from API if store is empty but maybe token exists
    await activeCourseStore.fetchActiveCourse()
    if (!activeCourseStore.activeCourseId) {
      router.push('/courses/select')
      loading.value = false
      
      return
    }
  }

  try {
    const [contentRes, statsRes] = await Promise.all([
      api.get('/learner/course-content'),
      api.get('/learner/dashboard/active-stats'),
    ])

    courseData.value = contentRes.data || contentRes
    stats.value = statsRes.data || statsRes
    
    // Logic for auto-scrolling to next lesson
    findNextLesson()
    
  } catch (err) {
    console.error(err)
    error.value = "Failed to load course data."
  } finally {
    loading.value = false
  }
}

const findNextLesson = () => {
  if (!courseData.value?.levels) return
    
  // Find first unlocked level with incomplete items
  for (const level of courseData.value.levels) {
    if (level.items) {
      const firstActive = level.items.find(i => !i.completed && !i.locked)
      if (firstActive) {
        forcedCurrentItem.value = { levelId: level.id, itemId: firstActive.id }

        // Optional: Scroll to it
        return
      }
    }
  }
}

const handleItemClick = item => {
  if (item.locked) return

  if (item.type === 'lesson') {
    router.push({ name: 'my-courses-study-id', params: { id: item.id } })
  } else if (item.type === 'exam' || item.item_type === 'exam') {
    router.push({ name: 'my-courses-exam-id', params: { id: item.id } })
  } else {
    selectedItem.value = item
    isModalVisible.value = true
  }
}

const scrollToLevels = () => {
  document.getElementById('course-levels-list')?.scrollIntoView({ behavior: 'smooth' })
}

const scrollToLevel = levelId => {
  document.getElementById(`level-${levelId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

const startPlacementExam = () => {
  if (courseData.value?.placementExam) {
    router.push({ name: 'my-courses-exam-id', params: { id: courseData.value.placementExam.id } })
  }
}

const hasPlacementExam = computed(() => !!courseData.value?.placementExam)
const shouldOfferPlacement = computed(() => hasPlacementExam.value && !courseData.value?.placementExam?.completed && !courseData.value?.levels.some(l => l.currentUserProgress))

const placementResult = computed(() => {
  if (!courseData.value?.placementExam?.outcome) return null
  const outcome = courseData.value.placementExam.outcome
  const levelId = outcome.levelId || outcome.level_id
  const level = courseData.value.levels.find(l => l.id === levelId)
  
  return {
    score: Math.round(outcome.percentage),
    levelTitle: level?.title || 'Unknown Level',
    levelId,
  }
})

onMounted(() => {
  fetchData()
})

watch(() => activeCourseStore.activeCourseId, newId => {
  if (newId) fetchData()
  else router.push('/courses/select')
})
</script>

<style scoped>
.course-timeline {
  max-width: 900px;
  margin: auto;
}
.gap-4 { gap: 1rem; }
.gap-6 { gap: 1.5rem; }
.level-card { border-radius: 16px; }
</style>
