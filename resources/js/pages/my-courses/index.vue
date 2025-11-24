<script setup>
import { ref, onMounted } from "vue"
import api from "@/utils/api"

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
    error.value = "Failed to fetch enrollments."
    console.error(err)
  } finally {
    loading.value = false
  }
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
      type="error"
      class="mb-4"
    >
      {{ error }}
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
              <VChip
                v-if="
                  enrollment.user_subscription &&
                    enrollment.user_subscription
                      .subscription_plan
                "
                color="info"
                size="small"
              >
                {{
                  enrollment.user_subscription
                    .subscription_plan.name
                }}
              </VChip>
              <VChip
                v-else
                color="success"
                size="small"
              >
                Enrolled
              </VChip>
            </div>
            <h5 class="text-h5 font-weight-bold mb-2">
              {{ enrollment.course.title }}
            </h5>
            <p
              v-if="
                enrollment.user_subscription &&
                  enrollment.user_subscription.ends_at
              "
              class="text-caption"
            >
              Subscription ends on:
              {{
                new Date(
                  enrollment.user_subscription.ends_at
                ).toLocaleDateString()
              }}
            </p>
            <VProgressLinear
              :model-value="enrollment.completion_percentage"
              color="primary"
              height="8"
              rounded
              class="my-2"
            />
            <span class="text-caption">{{ enrollment.completion_percentage }}%
              Complete</span>
          </VCardText>
          <VBtn
            rounded="lg"
            class="ma-4"
            :to="`/my-courses/${enrollment.course.id}`"
          >
            Continue Learning
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
