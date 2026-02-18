<template>
  <VContainer class="fill-height">
    <VRow
      justify="center"
      align="center"
    >
      <VCol
        cols="12"
        md="10"
        lg="8"
      >
        <div class="text-center mb-10">
          <h1 class="text-h3 font-weight-bold mb-4">
            Select Your Course
          </h1>
          <p class="text-h6 text-medium-emphasis">
            Choose a course to focus on. You can switch at any time.
          </p>
        </div>
        
        <VAlert
          v-if="error"
          type="error"
          class="mb-6"
          closable
          @click:close="error = null"
        >
          {{ error }}
        </VAlert>
        <VAlert
          v-if="courses.length === 0 && !loading"
          type="info"
          class="mb-6"
        >
          You are not enrolled in any courses yet. <VBtn
            to="/learner/courses"
            variant="text"
            class="px-0 text-decoration-underline"
          >
            Browse Courses
          </VBtn>
        </VAlert>

        <VRow>
          <VCol
            v-for="course in courses"
            :key="course.id"
            cols="12"
            sm="6"
            md="4"
          >
            <VCard 
              :loading="loadingId === course.id" 
              class="cursor-pointer hover-card h-100 d-flex flex-column" 
              :class="{'border-primary': activeCourseStore.activeCourseId === course.id}"
              :variant="activeCourseStore.activeCourseId === course.id ? 'outlined' : 'elevated'"
              @click="selectCourse(course.id)"
            >
              <VImg
                :src="course.image || '/placeholder.jpg'"
                height="180"
                cover
                class="align-end"
              >
                <VChip
                  v-if="activeCourseStore.activeCourseId === course.id"
                  color="primary"
                  class="ma-2"
                  label
                >
                  Active
                </VChip>
              </VImg>
              
              <VCardTitle class="pt-4">
                {{ course.title }}
              </VCardTitle>
              
              <VCardText class="flex-grow-1">
                <div class="d-flex align-center mb-2">
                  <VProgressLinear
                    :model-value="course.progress"
                    color="primary"
                    height="6"
                    rounded
                    class="flex-grow-1 me-3"
                  />
                  <span class="text-caption font-weight-bold">{{ course.progress }}%</span>
                </div>
                <div class="d-flex justify-space-between text-caption text-medium-emphasis">
                  <span>{{ course.completed_lessons }} / {{ course.total_lessons }} Lessons</span>
                  <span
                    v-if="course.due_reviews > 0"
                    class="text-warning"
                  >{{ course.due_reviews }} Reviews</span>
                </div>
              </VCardText>
              
              <VCardActions class="pt-0 pb-4 px-4">
                <VBtn 
                  block 
                  :color="activeCourseStore.activeCourseId === course.id ? 'primary' : 'secondary'" 
                  :variant="activeCourseStore.activeCourseId === course.id ? 'flat' : 'tonal'"
                  :loading="loadingId === course.id"
                >
                  {{ activeCourseStore.activeCourseId === course.id ? 'Continue' : 'Switch Course' }}
                </VBtn>
              </VCardActions>
            </VCard>
          </VCol>
        </VRow>
      </VCol>
    </VRow>
  </VContainer>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useActiveCourse } from '@/stores/activeCourse'
import api from '@/utils/api'

definePage({
  meta: {
    layout: 'learner',
  },
})

const router = useRouter()
const activeCourseStore = useActiveCourse()
const courses = ref([])
const loading = ref(false)
const loadingId = ref(null)
const error = ref(null)

onMounted(async () => {
  loading.value = true
  try {
    // Fetch stats which includes enrolled courses list with progress
    const response = await api.get('/learner/statistics')

    courses.value = response.courses || []
    
    // If user has no active course but has enrolled courses, maybe we don't auto-select to force choice?
    // Or we let them choose.
  } catch (err) {
    error.value = 'Failed to load your courses.'
    console.error(err)
  } finally {
    loading.value = false
  }
})

const selectCourse = async courseId => {
  if (activeCourseStore.activeCourseId === courseId) {
    router.push('/dashboard')
    
    return
  }

  loadingId.value = courseId
  try {
    const success = await activeCourseStore.setActiveCourse(courseId)
    if (success) {
      router.push('/dashboard')
    } else {
      error.value = 'Failed to activate course.'
    }
  } catch (err) {
    error.value = 'An error occurred.'
  } finally {
    loadingId.value = null
  }
}
</script>

<style scoped>
.hover-card {
  transition: all 0.3s ease;
}
.hover-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0,0,0,0.1) !important;
}
.border-primary {
  border: 2px solid rgb(var(--v-theme-primary));
}
</style>
