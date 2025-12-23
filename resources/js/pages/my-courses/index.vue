<script setup>
import api from "@/utils/api"
import { onMounted, ref } from "vue"

definePage({
  meta: {
    layout: "learner",
  },
})

const enrollments = ref([])
const loading = ref(true)
const error = ref(null)

const fetchEnrollments = async () => {
  try {
    const response = await api.get("/learner/my-courses")

    enrollments.value = response
  } catch (err) {
    error.value = err.response?.data?.error || "Failed to fetch enrollments."
    console.error(err)
  } finally {
    loading.value = false
  }
}

const isSubscriptionActive = enrollment => {
  if (!enrollment.userSubscription) return true
  
  const isStatusActive = enrollment.userSubscription.status === 'active'
  const isNotExpired = !enrollment.userSubscription.endsAt || new Date(enrollment.userSubscription.endsAt) > new Date()
  
  return isStatusActive && isNotExpired
}

onMounted(fetchEnrollments)
</script>

<template>
  <VContainer>
    <h1 class="text-h4 mb-4">
      My Courses
    </h1>

    <div
      v-if="loading"
      class="text-center"
    >
      <VProgressCircular
        indeterminate
        color="primary"
      />
      <p>Loading your courses...</p>
    </div>

    <VAlert
      v-if="error"
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
          @click="fetchEnrollments"
        >
          Retry
        </VBtn>
      </div>
    </VAlert>

    <VRow v-if="!loading && !error">
      <VCol
        v-for="enrollment in enrollments"
        :key="enrollment.id"
        cols="12"
        sm="6"
        md="4"
      >
        <VCard
          class="d-flex flex-column h-100"
          rounded="lg"
        >
          <VImg
            :src="
              enrollment.course.thumbnail ||
                'https://placehold.co/600x400/EEE/31343C'
            "
            height="200px"
            cover
            class="rounded-t-lg"
          />

          <VCardText class="flex-grow-1">
            <div class="d-flex justify-space-between align-center mb-2">
              <div class="d-flex gap-2">
                <VChip
                  v-if="
                    enrollment.userSubscription &&
                      enrollment.userSubscription
                        .subscriptionPlan
                  "
                  color="info"
                  size="small"
                >
                  {{
                    enrollment.userSubscription
                      .subscriptionPlan.name
                  }}
                </VChip>
                <VChip
                  v-else
                  color="success"
                  size="small"
                >
                  Enrolled
                </VChip>

                <VChip
                  v-if="!isSubscriptionActive(enrollment)"
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
              v-if="
                enrollment.userSubscription &&
                  enrollment.userSubscription.endsAt
              "
              class="text-caption"
            >
              Subscription ends on:
              {{
                new Date(
                  enrollment.userSubscription.endsAt
                ).toLocaleDateString()
              }}
            </p>
            <VProgressLinear
              :model-value="enrollment.completionPercentage"
              color="primary"
              height="8"
              rounded
              class="my-2"
            />
            <span class="text-caption">{{ enrollment.completionPercentage }}%
              Complete</span>
          </VCardText>
          <VBtn
            rounded="lg"
            class="ma-4"
            :to="isSubscriptionActive(enrollment) ? `/my-courses/${enrollment.course.id}` : null"
            :disabled="!isSubscriptionActive(enrollment)"
            :color="isSubscriptionActive(enrollment) ? 'primary' : 'secondary'"
          >
            {{ isSubscriptionActive(enrollment) ? 'Continue Learning' : 'Subscription Expired' }}
          </VBtn>
        </VCard>
      </VCol>
      <VCol
        v-if="!enrollments.length"
        cols="12"
      >
        <VAlert
          type="info"
          variant="tonal"
        >
          You are not enrolled in any courses yet.
        </VAlert>
      </VCol>
    </VRow>
  </VContainer>
</template>
