<script setup>
import axios from 'axios'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTheme } from 'vuetify'

definePage({
  meta: {
    layout: 'learner',
  },
})

const { global } = useTheme()
const router = useRouter()

const dashboardData = ref(null)
const trophyStatistics = ref(null)
const loading = ref(true)

const fetchDashboardData = async () => {
  loading.value = true
  try {
    const [statsResponse, trophyStatsResponse] = await Promise.all([
      axios.get('/api/learner/statistics'),
      axios.get('/api/gamification/trophy-statistics'),
    ])

    dashboardData.value = statsResponse.data
    trophyStatistics.value = trophyStatsResponse.data
  } catch (error) {
    console.error('Error fetching dashboard data:', error)
  } finally {
    loading.value = false
  }
}

const handleAction = nba => {
  if (nba.route) {
    router.push(nba.route)
  }
}

const getActionIcon = type => {
  switch (type) {
  case 'review': return 'tabler-refresh'
  case 'continue': return 'tabler-player-play'
  case 'start': return 'tabler-rocket'
  default: return 'tabler-search'
  }
}

const getActionColor = type => {
  switch (type) {
  case 'review': return 'error'
  case 'continue': return 'primary'
  case 'start': return 'success'
  default: return 'info'
  }
}

onMounted(() => {
  fetchDashboardData()
})
</script>

<template>
  <div
    v-if="loading"
    class="d-flex justify-center align-center h-100"
  >
    <VProgressCircular
      indeterminate
      size="64"
      color="primary"
    />
  </div>

  <VRow v-else-if="dashboardData">
    <!-- Welcome Header -->
    <VCol cols="12">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <h1 class="text-h4 mb-1">
            Welcome back! 👋
          </h1>
          <p class="text-body-1 text-medium-emphasis">
            Here's what's happening with your learning journey today.
          </p>
        </div>
      </div>
    </VCol>

    <!-- Global Stats -->
    <VCol cols="12">
      <VRow>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            variant="tonal"
            color="primary"
          >
            <VCardText class="d-flex align-center">
              <VAvatar
                color="primary"
                variant="tonal"
                rounded
                size="48"
                class="me-4"
              >
                <VIcon
                  icon="tabler-book"
                  size="24"
                />
              </VAvatar>
              <div>
                <div class="text-h5 font-weight-bold">
                  {{ dashboardData.global.enrolledCoursesCount }}
                </div>
                <div class="text-caption">
                  Courses Enrolled
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            variant="tonal"
            color="success"
          >
            <VCardText class="d-flex align-center">
              <VAvatar
                color="success"
                variant="tonal"
                rounded
                size="48"
                class="me-4"
              >
                <VIcon
                  icon="tabler-circle-check"
                  size="24"
                />
              </VAvatar>
              <div>
                <div class="text-h5 font-weight-bold">
                  {{ dashboardData.global.totalCompletedLessons }}
                </div>
                <div class="text-caption">
                  Lessons Completed
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            variant="tonal"
            color="error"
          >
            <VCardText class="d-flex align-center">
              <VAvatar
                color="error"
                variant="tonal"
                rounded
                size="48"
                class="me-4"
              >
                <VIcon
                  icon="tabler-clock-play"
                  size="24"
                />
              </VAvatar>
              <div>
                <div class="text-h5 font-weight-bold">
                  {{ dashboardData.global.totalDueReviews }}
                </div>
                <div class="text-caption">
                  Reviews Due
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            variant="tonal"
            color="warning"
          >
            <VCardText class="d-flex align-center">
              <VAvatar
                color="warning"
                variant="tonal"
                rounded
                size="48"
                class="me-4"
              >
                <VIcon
                  icon="tabler-award"
                  size="24"
                />
              </VAvatar>
              <div>
                <div class="text-h5 font-weight-bold">
                  {{ dashboardData.global.points }}
                </div>
                <div class="text-caption">
                  Total Points
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCol>

    <!-- Next Best Action -->
    <VCol
      cols="12"
      md="8"
    >
      <VCard
        class="nba-card overflow-hidden"
        :color="getActionColor(dashboardData.nextBestAction.type)"
        variant="tonal"
      >
        <VRow no-gutters>
          <VCol
            cols="12"
            sm="8"
          >
            <VCardText class="pa-6">
              <div class="text-h5 font-weight-bold mb-2">
                {{ dashboardData.nextBestAction.title }}
              </div>
              <p class="text-body-1 mb-6">
                {{ dashboardData.nextBestAction.description }}
              </p>
              <VBtn
                :color="getActionColor(dashboardData.nextBestAction.type)"
                size="large"
                prepend-icon="tabler-arrow-right"
                @click="handleAction(dashboardData.nextBestAction)"
              >
                {{ dashboardData.nextBestAction.actionLabel }}
              </VBtn>
            </VCardText>
          </VCol>
          <VCol
            sm="4"
            class="d-none d-sm-flex align-center justify-center pa-6"
          >
            <VIcon
              :icon="getActionIcon(dashboardData.nextBestAction.type)"
              size="120"
              class="opacity-25"
            />
          </VCol>
        </VRow>
      </VCard>
    </VCol>

    <!-- Trophy Summary -->
    <VCol
      cols="12"
      md="4"
    >
      <VCard
        title="Trophy Room"
        subtitle="Keep learning to earn more!"
      >
        <template #append>
          <VIcon
            icon="tabler-trophy"
            color="warning"
          />
        </template>
        <VCardText v-if="trophyStatistics">
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-body-2">Earned Trophies</span>
            <span class="text-body-2 font-weight-bold">{{ trophyStatistics.earnedTrophies }} / {{ trophyStatistics.totalTrophies }}</span>
          </div>
          <VProgressLinear
            :model-value="trophyStatistics.completionPercentage"
            color="warning"
            height="8"
            rounded
          />
          <div class="mt-4">
            <VBtn
              variant="text"
              block
              size="small"
              to="/trophies"
            >
              View All Trophies
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- My Courses Section -->
    <VCol cols="12">
      <div class="d-flex align-center justify-space-between mt-6 mb-4">
        <h2 class="text-h5">
          My Courses
        </h2>
        <VBtn
          variant="text"
          to="/my-courses"
        >
          View All
        </VBtn>
      </div>

      <VRow v-if="dashboardData.courses.length">
        <VCol
          v-for="course in dashboardData.courses"
          :key="course.id"
          cols="12"
          md="6"
          lg="4"
        >
          <VCard class="course-card h-100 d-flex flex-column">
            <VImg
              :src="course.image || '/images/course-placeholder.jpg'"
              height="160"
              cover
            >
              <div
                class="d-flex flex-column justify-end h-100 pa-4 text-white"
                style="background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 100%)"
              >
                <div class="text-h6 font-weight-bold">
                  {{ course.title }}
                </div>
              </div>
            </VImg>

            <VCardText class="pt-4 flex-grow-1">
              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-caption text-medium-emphasis">Progress</span>
                <span class="text-caption font-weight-bold">{{ course.progress }}%</span>
              </div>
              <VProgressLinear
                :model-value="course.progress"
                color="primary"
                height="6"
                rounded
                class="mb-4"
              />

              <div class="d-flex flex-column gap-2">
                <div class="d-flex align-center">
                  <VIcon
                    icon="tabler-book"
                    size="16"
                    class="me-2 text-medium-emphasis"
                  />
                  <span class="text-body-2">{{ course.completedLessons }} / {{ course.totalLessons }} Lessons</span>
                </div>
                <div
                  class="d-flex align-center"
                  :class="course.dueReviews > 0 ? 'text-error font-weight-bold' : 'text-medium-emphasis'"
                >
                  <VIcon
                    icon="tabler-refresh"
                    size="16"
                    class="me-2"
                  />
                  <span class="text-body-2">{{ course.dueReviews }} reviews due</span>
                </div>
              </div>
            </VCardText>

            <VDivider />

            <VCardActions class="pa-4">
              <VBtn
                variant="flat"
                color="primary"
                block
                :to="`/my-courses/${course.id}`"
              >
                Continue Learning
              </VBtn>
            </VCardActions>
          </VCard>
        </VCol>
      </VRow>

      <VCard
        v-else
        variant="tonal"
        color="info"
        class="text-center pa-10"
      >
        <VCardText>
          <VIcon
            icon="tabler-book-off"
            size="64"
            class="mb-4"
          />
          <h3 class="text-h5 mb-2">
            No courses yet
          </h3>
          <p class="mb-6">
            Start your learning journey by enrolling in a course.
          </p>
          <VBtn
            color="primary"
            to="/browse-courses"
          >
            Browse Courses
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <VRow
    v-else
    class="text-center pa-10"
  >
    <VCol cols="12">
      <VIcon
        icon="tabler-alert-triangle"
        size="64"
        color="error"
        class="mb-4"
      />
      <h3 class="text-h5">
        Failed to load dashboard
      </h3>
      <p>We couldn't retrieve your learning data. Please try again later.</p>
      <VBtn
        color="primary"
        class="mt-4"
        @click="fetchDashboardData"
      >
        Retry
      </VBtn>
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>
.nba-card {
  transition: transform 0.2s;
  &:hover {
    transform: translateY(-2px);
  }
}

.course-card {
  transition: all 0.2s;
  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }
}

.gap-2 {
  gap: 0.5rem;
}
</style>
