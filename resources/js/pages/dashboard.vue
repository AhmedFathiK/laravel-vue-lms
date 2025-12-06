<script setup>
import { hexToRgb } from '@layouts/utils'
import axios from 'axios'
import { onMounted, ref } from 'vue'
import { useTheme } from 'vuetify'

definePage({
  meta: {
    layout: 'learner',
  },
})

const { global } = useTheme()

const userStatistics = ref(null)
const userPoints = ref(null)
const trophyStatistics = ref(null)

const fetchDashboardData = async () => {
  try {
    const [statsResponse, pointsResponse, trophyStatsResponse] = await Promise.all([
      axios.get('/api/learner/statistics'),
      axios.get('/api/gamification/points'),
      axios.get('/api/gamification/trophy-statistics'),
    ])

    userStatistics.value = statsResponse.data
    userPoints.value = pointsResponse.data.totalPoints
    trophyStatistics.value = trophyStatsResponse.data
  } catch (error) {
    console.error('Error fetching dashboard data:', error)
  }
}

onMounted(() => {
  fetchDashboardData()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <h1 :style="{ color: `rgba(${hexToRgb(global.current.value.colors.primary)}, 0.8)` }">
        Learner Dashboard
      </h1>
      <p>Welcome to your personalized learning dashboard!</p>
    </VCol>

    <VCol
      cols="12"
      md="6"
      lg="4"
    >
      <VCard title="Learning Progress">
        <VCardText v-if="userStatistics">
          <p>Total Enrollments: {{ userStatistics.enrollments.total }}</p>
          <p>Completed Courses: {{ userStatistics.enrollments.completed }}</p>
          <p>In Progress Courses: {{ userStatistics.enrollments.inProgress }}</p>
          <p>Completed Slides: {{ userStatistics.slides.completed }} / {{ userStatistics.slides.attempted }}</p>
          <p>Accuracy: {{ userStatistics.answers.accuracyPercentage }}%</p>
        </VCardText>
        <VCardText v-else>
          Loading progress data...
        </VCardText>
      </VCard>
    </VCol>

    <VCol
      cols="12"
      md="6"
      lg="4"
    >
      <VCard title="Gamification Stats">
        <VCardText v-if="userPoints !== null && trophyStatistics">
          <p>Total Points: {{ userPoints }}</p>
          <p>Earned Trophies: {{ trophyStatistics.earnedTrophies }} / {{ trophyStatistics.totalTrophies }}</p>
          <p>Trophy Completion: {{ trophyStatistics.completionPercentage }}%</p>
        </VCardText>
        <VCardText v-else>
          Loading gamification data...
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
