<template>
  <VMenu
    location="bottom end"
    offset="10"
  >
    <template #activator="{ props }">
      <VBtn
        v-bind="props"
        variant="text"
        class="me-2 text-capitalize"
        :loading="activeCourseStore.isLoading"
      >
        <template v-if="activeCourseStore.activeCourse">
          <VAvatar
            size="24"
            class="me-2"
          >
            <VImg
              :src="activeCourseStore.activeCourse.thumbnail || '/placeholder.jpg'"
              cover
            />
          </VAvatar>
          <span
            class="d-none d-md-inline text-truncate"
            style="max-width: 150px;"
          >{{ activeCourseStore.activeCourse.title }}</span>
        </template>
        <template v-else>
          Select Course
        </template>
        <VIcon
          icon="tabler-chevron-down"
          class="ms-1"
          size="16"
        />
      </VBtn>
    </template>
    <VList
      width="320"
      class="py-0"
    >
      <VListItem
        title="My Courses"
        class="bg-light-primary text-primary font-weight-bold py-2"
      >
        <template #append>
          <VBtn
            size="x-small"
            variant="text"
            to="/courses/select"
          >
            See All
          </VBtn>
        </template>
      </VListItem>
      <VDivider />
      
      <VListItem
        v-for="course in courses"
        :key="course.id"
        :value="course.id"
        :active="activeCourseStore.activeCourseId === course.id"
        class="py-3"
        @click="switchCourse(course.id)"
      >
        <template #prepend>
          <VAvatar
            size="40"
            rounded
            class="me-3"
          >
            <VImg
              :src="course.image || '/placeholder.jpg'"
              cover
            />
          </VAvatar>
        </template>
        <VListItemTitle class="font-weight-medium mb-1">
          {{ course.title }}
        </VListItemTitle>
        <VListItemSubtitle>
          <div class="d-flex align-center">
            <VProgressLinear
              :model-value="course.progress"
              color="primary"
              height="4"
              rounded
              class="flex-grow-1 me-2"
              style="max-width: 60px;"
            />
            <span class="text-xs">{{ course.progress }}%</span>
          </div>
        </VListItemSubtitle>
        <template
          v-if="activeCourseStore.activeCourseId === course.id"
          #append
        >
          <VIcon
            color="primary"
            icon="tabler-check"
            size="20"
          />
        </template>
      </VListItem>
      
      <VDivider />
      
      <VListItem
        to="/courses/select"
        prepend-icon="tabler-plus"
        title="Browse More Courses"
      />
    </VList>
  </VMenu>
</template>

<script setup>
import api from '@/utils/api'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'

const authStore = useAuthStore()
const router = useRouter()
const toast = useToast()
const courses = ref([])

const fetchCourses = async () => {
  try {
    const response = await api.get('/learner/statistics')

    courses.value = (response.courses || []).slice(0, 5) // Limit to 5 in dropdown
  } catch (e) {
    console.error(e)
  }
}

const switchCourse = async courseId => {
  if (authStore.user?.activeCourseId === courseId) return
    
  try {
    await api.patch('/user/active-course', { course_id: courseId })
    
    // Refresh user to update global state
    await authStore.fetchUser()
    
    router.push('/dashboard')
  } catch (err) {
    const message = err?.response?.data?.message || err?.response?.data?.error || 'Failed to activate course.'

    toast.error(message)
  }
}

onMounted(() => {
  fetchCourses()
})
</script>

<style scoped>
.bg-light-primary {
    background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>
