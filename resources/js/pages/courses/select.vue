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
          v-if="enrollments.length === 0 && !loading"
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

        <VRow v-if="!loading">
          <VCol
            v-for="enrollment in enrollments"
            :key="enrollment.id"
            cols="12"
            sm="6"
            md="4"
          >
            <VCard
              class="d-flex flex-column h-100 cursor-pointer hover-card"
              rounded="lg"
              :class="{'border-primary': activeCourseStore.activeCourseId === enrollment.course.id}"
              :variant="activeCourseStore.activeCourseId === enrollment.course.id ? 'outlined' : 'elevated'"
              :loading="loadingId === enrollment.course.id"
              @click="isEntitlementActive(enrollment) ? selectCourse(enrollment.course.id) : null"
            >
              <VImg
                :src="enrollment.course.thumbnail || 'https://placehold.co/600x400/EEE/31343C'"
                height="200px"
                cover
                class="rounded-t-lg align-end"
              >
                <VChip
                  v-if="activeCourseStore.activeCourseId === enrollment.course.id"
                  color="primary"
                  class="ma-2"
                  label
                >
                  Active
                </VChip>
              </VImg>

              <VCardText class="flex-grow-1">
                <div
                  class="d-flex align-center mb-2"
                  style="max-width: 100%;"
                >
                  <div
                    class="d-flex gap-2 flex-wrap"
                    style="max-width: 100%;"
                  >
                    <VChip
                      v-if="enrollment.userEntitlement && enrollment.userEntitlement.billingPlan"
                      color="info"
                      size="small"
                      class="d-inline-flex align-center"
                      style="max-width: 100%;"
                    >
                      <span
                        style="display: inline-block; max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: bottom;"
                        :title="enrollment.userEntitlement.billingPlan.name"
                      >
                        {{ enrollment.userEntitlement.billingPlan.name }}
                      </span>
                    </VChip>
                    <VChip
                      v-else
                      color="success"
                      size="small"
                    >
                      Enrolled
                    </VChip>

                    <VChip
                      v-if="!isEntitlementActive(enrollment)"
                      color="error"
                      size="small"
                    >
                      Expired
                    </VChip>
                  </div>
                </div>
                
                <h5 class="text-h5 font-weight-bold mb-2">
                  {{ enrollment.course.title }}
                </h5>
                
                <p
                  v-if="enrollment.userEntitlement && enrollment.userEntitlement.endsAt"
                  class="text-caption"
                >
                  Entitlement ends on:
                  {{ new Date(enrollment.userEntitlement.endsAt).toLocaleDateString() }}
                </p>
                
                <VProgressLinear
                  :model-value="enrollment.completionPercentage"
                  color="primary"
                  height="8"
                  rounded
                  class="my-2"
                />
                <span class="text-caption">{{ enrollment.completionPercentage }}% Complete</span>
              </VCardText>
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
  name: 'courses-select',
  meta: {
    layout: 'learner',
  },
})

const router = useRouter()
const activeCourseStore = useActiveCourse()
const enrollments = ref([])
const loading = ref(false)
const loadingId = ref(null)
const error = ref(null)

const fetchEnrollments = async () => {
  loading.value = true
  try {
    const response = await api.get('/learner/courses/enrolled')

    enrollments.value = response
  } catch (err) {
    error.value = err.response?.data?.error || 'Failed to load your courses.'
    console.error(err)
  } finally {
    loading.value = false
  }
}

const isEntitlementActive = enrollment => {
  if (!enrollment.userEntitlement) return false
  
  const isStatusActive = enrollment.userEntitlement.status === 'active'
  const isNotExpired = !enrollment.userEntitlement.endsAt || new Date(enrollment.userEntitlement.endsAt) > new Date()
  
  return isStatusActive && isNotExpired
}

onMounted(async () => {
  await fetchEnrollments()
})

const selectCourse = async courseId => {
  // Always force API call to validate entitlement, even if ID matches store
  // if (activeCourseStore.activeCourseId === courseId) {
  //   router.push('/dashboard')
  //   
  //   return
  // }

  loadingId.value = courseId
  try {
    const success = await activeCourseStore.setActiveCourse(courseId)
    if (success) {
      router.push('/dashboard')
    } else {
      const err = activeCourseStore.error

      error.value = err?.response?.data?.message || err?.response?.data?.error || 'Failed to activate course.'
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
