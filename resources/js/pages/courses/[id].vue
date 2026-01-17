<script setup>
import api from '@/utils/api'
import VideoPlayer from '@/components/VideoPlayer.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

definePage({
  meta: {
    layout: "learner",
  },
})

const route = useRoute()
const router = useRouter()
const courseDetails = ref(null)
const panelStatus = ref(0)
const isLoading = ref(false)
const processingPlanId = ref(null)
const error = ref(null)
const isPaymentErrorDialogVisible = ref(false)
const isPaymentMethodDialogVisible = ref(false)
const paymentMethods = ref([])
const selectedPaymentMethod = ref(null)
const selectedPlan = ref(null)
const fetchedBillingPlans = ref([])
const isFreePlanDialogVisible = ref(false)

const fetchBillingPlans = async () => {
  try {
    const response = await api.get(`/learner/courses/${route.params.id}/billing-plans`)
    
    fetchedBillingPlans.value = response.plans || []
  } catch (err) {
    console.error('Error fetching billing plans:', err)
  }
}

const fetchCourseDetails = async () => {
  isLoading.value = true
  error.value = null
  try {
    const response = await api.get(`/learner/courses/${route.params.id}`)

    courseDetails.value = response
  } catch (err) {
    console.error(err)
    error.value = err.message || 'Failed to fetch course details'
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await fetchCourseDetails()
  await fetchBillingPlans()
  
  // Check for payment success and redirect if enrolled
  if (route.query.payment === 'success' && courseDetails.value?.hasActiveAccess) {
    // Optional: Show a toast here
    router.push(`/my-courses/${courseDetails.value.id}`)
  } else if (route.query.payment === 'failed') {
    if (route.query.payment_id) {
      error.value = 'Entitlement payment failed. Please try again or contact support.'
      isPaymentErrorDialogVisible.value = true
    }

    // Clean up URL without reload
    const query = { ...route.query }

    delete query.payment
    delete query.id
    delete query.payment_id
    router.replace({ query })
  }
})

const billingPlans = computed(() => fetchedBillingPlans.value)

const resolvePlanTypeColor = type => {
  if (type === 'recurring') return 'primary'
  if (type === 'one-time') return 'warning'
  if (type === 'free') return 'success'
  
  return 'secondary'
}

const handlePayment = async plan => {
  processingPlanId.value = plan.id
  selectedPlan.value = plan
  try {
    // If the plan is free, show confirmation dialog
    if (parseFloat(plan.price) === 0) {
      isFreePlanDialogVisible.value = true
    } else {
      // For paid plans, fetch payment methods first
      const response = await api.get('/payments/methods', {
        params: {
          amount: plan.price,
          currency: plan.currency || import.meta.env.VITE_DEFAULT_CURRENCY || 'EGP',
        },
      })

      if (response.success) {
        paymentMethods.value = response.data
        isPaymentMethodDialogVisible.value = true
      } else {
        throw new Error('Failed to fetch payment methods')
      }
    }
  } catch (err) {
    console.error('Entitlement acquisition error', err)
    error.value = err.message || 'Entitlement acquisition failed'
    isPaymentErrorDialogVisible.value = true
  } finally {
    processingPlanId.value = null
  }
}

const confirmFreeEnrollment = async () => {
  if (!selectedPlan.value) return

  processingPlanId.value = selectedPlan.value.id
  isFreePlanDialogVisible.value = false

  try {
    await api.post('/learner/acquire-entitlement', {
      planId: selectedPlan.value.id,
    })
      
    // Refresh course details to update UI state
    await fetchCourseDetails()

    if (courseDetails.value?.hasActiveAccess) {
      router.push(`/my-courses/${courseDetails.value.id}`)
    }
  } catch (err) {
    console.error('Free enrollment error', err)
    error.value = err.message || 'Free enrollment failed'
    isPaymentErrorDialogVisible.value = true
  } finally {
    processingPlanId.value = null
    selectedPlan.value = null
  }
}

const proceedToCheckout = async () => {
  if (!selectedPlan.value || !selectedPaymentMethod.value) return

  processingPlanId.value = selectedPlan.value.id
  isPaymentMethodDialogVisible.value = false

  try {
    const response = await api.post('/payments/checkout', {
      amount: selectedPlan.value.price,
      currency: selectedPlan.value.currency || import.meta.env.VITE_DEFAULT_CURRENCY || 'EGP',
      planId: selectedPlan.value.id,
      courseId: courseDetails.value.id,
      paymentMethodId: String(selectedPaymentMethod.value),
    })

    if (response.success && response.paymentUrl) {
      window.location.href = response.paymentUrl
    } else {
      throw new Error('Payment initiation failed')
    }
  } catch (err) {
    console.error('Checkout error', err)
    error.value = err.message || 'Checkout failed'
    isPaymentErrorDialogVisible.value = true
  } finally {
    processingPlanId.value = null
    selectedPlan.value = null
    selectedPaymentMethod.value = null
  }
}

// Check for payment status in query params
if (route.query.payment === 'success') {
  console.log('Payment successful')
}
</script>

<template>
  <VDialog
    v-model="isFreePlanDialogVisible"
    max-width="500"
  >
    <VCard>
      <VCardTitle class="text-h5 font-weight-bold pa-4">
        Confirm Enrollment
      </VCardTitle>
      <VCardText class="pa-4">
        <p class="text-body-1 mb-0">
          You are about to enroll in <strong>{{ courseDetails?.title }}</strong> for free.
        </p>
      </VCardText>
      <VCardActions class="justify-end pa-4">
        <VBtn
          variant="outlined"
          color="secondary"
          @click="isFreePlanDialogVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          @click="confirmFreeEnrollment"
        >
          Enroll for Free
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="isPaymentErrorDialogVisible"
    max-width="500"
  >
    <VCard class="text-center pa-4">
      <VCardText>
        <VIcon
          icon="tabler-alert-circle"
          size="64"
          color="error"
          class="mb-4"
        />
        <h3 class="text-h5 font-weight-bold text-error mb-2">
          Payment Failed
        </h3>
        <p class="text-body-1 mb-0">
          {{ error }}
        </p>
      </VCardText>
      <VCardActions class="justify-center">
        <VBtn
          color="primary"
          variant="elevated"
          @click="isPaymentErrorDialogVisible = false"
        >
          Close
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VDialog
    v-model="isPaymentMethodDialogVisible"
    max-width="600"
  >
    <VCard>
      <VCardTitle class="d-flex justify-space-between align-center pa-4">
        <span class="text-h5">Select Payment Method</span>
        <VBtn
          icon
          variant="text"
          color="default"
          @click="isPaymentMethodDialogVisible = false"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>
      
      <VDivider />

      <VCardText class="pa-4">
        <VRow>
          <VCol
            v-for="method in paymentMethods"
            :key="method.paymentMethodId"
            cols="12"
            sm="6"
            md="4"
          >
            <VCard
              border
              :color="selectedPaymentMethod === method.paymentMethodId ? 'primary' : ''"
              :variant="selectedPaymentMethod === method.paymentMethodId ? 'tonal' : 'outlined'"
              class="d-flex flex-column align-center justify-center pa-4 cursor-pointer h-100 transition-all"
              @click="selectedPaymentMethod = method.paymentMethodId"
            >
              <VImg
                :src="method.imageUrl"
                height="40"
                width="60"
                class="mb-2"
                contain
              />
              <span class="text-subtitle-2 text-center">{{ method.paymentMethodEn }}</span>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="isPaymentMethodDialogVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          :disabled="!selectedPaymentMethod"
          :loading="!!processingPlanId"
          @click="proceedToCheckout"
        >
          Proceed to Pay
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <VRow v-if="courseDetails">
    <VCol
      cols="12"
      md="8"
    >
      <VCard>
        <VCardItem
          :title="courseDetails.title"
          class="pb-6"
        >
          <template #append>
            <div class="d-flex gap-4 align-center">
              <VChip
                variant="tonal"
                color="error"
                size="small"
              >
                {{ courseDetails.category?.name || 'Course' }}
              </VChip>
              <VIcon
                size="24"
                class="cursor-pointer"
                icon="tabler-share"
              />
              <VIcon
                size="24"
                class="cursor-pointer"
                icon="tabler-bookmarks"
              />
            </div>
          </template>
        </VCardItem>
        <VCardText>
          <VCard
            flat
            border
          >
            <div class="px-2 pt-2">
              <VideoPlayer
                v-if="courseDetails.videoUrl || courseDetails.thumbnail"
                :key="courseDetails.videoUrl"
                :src="courseDetails.videoUrl || 'https://cdn.plyr.io/static/demo/View_From_A_Blue_Moon_Trailer-576p.mp4'"
                :type="courseDetails.videoUrl?.includes('youtube') ? 'youtube' : (courseDetails.videoUrl?.includes('vimeo') ? 'vimeo' : 'hosted')"
                class="w-100 rounded"
              />
              <div
                v-else
                class="d-flex justify-center align-center bg-grey-200 rounded"
                style="height: 250px;"
              >
                <VIcon
                  icon="tabler-video-off"
                  size="48"
                />
              </div>
            </div>
            <VCardText>
              <h5 class="text-h5 mb-4">
                About this course
              </h5>
              <p class="text-body-1">
                {{ courseDetails.description }}
              </p>
              <VDivider class="my-6" />

              <h5 class="text-h5 mb-4">
                By the numbers
              </h5>
              <div class="d-flex gap-x-12 gap-y-5 flex-wrap">
                <div>
                  <VList class="card-list text-medium-emphasis">
                    <VListItem>
                      <template #prepend>
                        <VIcon
                          icon="tabler-check"
                          size="20"
                        />
                      </template>
                      <VListItemTitle>Skill Level: {{ courseDetails.skillLevel || 'All Levels' }}</VListItemTitle>
                    </VListItem>
                    <VListItem>
                      <template #prepend>
                        <VIcon
                          icon="tabler-users"
                          size="20"
                        />
                      </template>
                      <VListItemTitle>Students: {{ courseDetails.totalStudents || 0 }}</VListItemTitle>
                    </VListItem>
                    <VListItem>
                      <template #prepend>
                        <VIcon
                          icon="tabler-world"
                          size="20"
                        />
                      </template>
                      <VListItemTitle>Languages: {{ courseDetails.language || 'English' }}</VListItemTitle>
                    </VListItem>
                  </VList>
                </div>

                <div>
                  <VList class="card-list text-medium-emphasis">
                    <VListItem>
                      <template #prepend>
                        <VIcon
                          icon="tabler-video"
                          size="20"
                        />
                      </template>
                      <VListItemTitle>Lectures: {{ courseDetails.totalLectures || 0 }}</VListItemTitle>
                    </VListItem>
                    <VListItem>
                      <template #prepend>
                        <VIcon
                          icon="tabler-clock"
                          size="20"
                        />
                      </template>
                      <VListItemTitle>Video: {{ courseDetails.duration || '0h' }}</VListItemTitle>
                    </VListItem>
                  </VList>
                </div>
              </div>
              
              <VDivider class="my-6" />

              <h5 class="text-h5 mb-4">
                Billing Plans
              </h5>
              
              <div v-if="courseDetails.hasActiveAccess">
                <VAlert
                  type="success"
                  variant="tonal"
                  class="mb-4"
                >
                  You have active access to this course.
                </VAlert>
                <VBtn
                  block
                  color="success"
                  :to="`/my-courses/${courseDetails.id}`"
                >
                  Continue Learning
                </VBtn>
              </div>

              <div
                v-else-if="billingPlans.length > 0"
                class="d-flex flex-wrap gap-4"
              >
                <VCard 
                  v-for="plan in billingPlans" 
                  :key="plan.id"
                  variant="outlined"
                  class="flex-grow-1"
                  :style="{ minWidth: '250px' }"
                >
                  <VCardItem>
                    <template #prepend>
                      <VIcon 
                        :icon="plan.billingType === 'one-time' ? 'tabler-infinity' : 'tabler-calendar'" 
                        :color="resolvePlanTypeColor(plan.billingType)"
                        size="32"
                        class="me-2"
                      />
                    </template>
                    <VCardTitle>{{ plan.name }}</VCardTitle>
                    <VCardSubtitle class="text-capitalize">
                      {{ plan.billingType?.replace('-', ' ') }}
                    </VCardSubtitle>
                  </VCardItem>
                  
                  <VCardText>
                    <div class="d-flex align-baseline mb-2">
                      <span class="text-h4 font-weight-bold text-primary">{{ plan.price }}</span>
                      <span class="text-body-2 ms-1">{{ plan.currency }}</span>
                      <span
                        v-if="plan.billingType === 'recurring'"
                        class="text-body-2 text-medium-emphasis"
                      >/ {{ plan.billingInterval }}</span>
                    </div>
                    <p class="text-body-2 mb-4">
                      {{ plan.description }}
                    </p>
                    
                    <VBtn 
                      block 
                      :color="resolvePlanTypeColor(plan.billingType)" 
                      variant="tonal"
                      :loading="processingPlanId === plan.id"
                      @click="handlePayment(plan)"
                    >
                      {{ parseFloat(plan.price) === 0 ? 'Enroll for Free' : 'Acquire Now' }}
                    </VBtn>
                  </VCardText>
                </VCard>
              </div>
              <div v-else>
                <VAlert
                  type="info"
                  variant="tonal"
                  text="No billing plans available for this course."
                />
              </div>
            </VCardText>
          </VCard>
        </VCardText>
      </VCard>
    </VCol>

    <VCol
      cols="12"
      md="4"
    >
      <div class="course-content">
        <h5 class="text-h5 mb-4">
          Course Content
        </h5>
        <VExpansionPanels
          v-model="panelStatus"
          variant="accordion"
          class="expansion-panels-width-border"
        >
          <template
            v-for="(level, index) in courseDetails.levels"
            :key="index"
          >
            <VExpansionPanel
              elevation="0"
              :value="index"
              expand-icon="tabler-chevron-right"
              collapse-icon="tabler-chevron-down"
            >
              <template #title>
                <div>
                  <h5 class="text-h6 mb-1">
                    {{ level.title }}
                  </h5>
                  <div class="text-medium-emphasis font-weight-normal text-body-2">
                    {{ level.lessons?.length || 0 }} Lessons
                  </div>
                </div>
              </template>
              <template #text>
                <VList class="card-list">
                  <VListItem
                    v-for="(lesson, id) in level.lessons"
                    :key="id"
                    class="py-2 px-0"
                  >
                    <template #prepend>
                      <VIcon
                        icon="tabler-player-play"
                        size="16"
                        class="me-2"
                      />
                    </template>
                    <VListItemTitle class="text-body-2 font-weight-medium">
                      {{ lesson.title }}
                    </VListItemTitle>
                    <template #append>
                      <div class="text-caption text-disabled">
                        {{ lesson.duration || '10m' }}
                      </div>
                    </template>
                  </VListItem>
                </VList>
              </template>
            </VExpansionPanel>
          </template>
        </VExpansionPanels>
      </div>
    </VCol>
  </VRow>
  <div
    v-else-if="isLoading"
    class="d-flex justify-center align-center"
    style="min-height: 400px;"
  >
    <VProgressCircular
      indeterminate
      color="primary"
    />
  </div>
  <div
    v-else
    class="d-flex justify-center align-center px-6"
    style="min-height: 400px;"
  >
    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      max-width="500"
    >
      {{ error }}
      <template #append>
        <VBtn
          variant="plain"
          size="small"
          @click="fetchCourseDetails"
        >
          Retry
        </VBtn>
      </template>
    </VAlert>
    <div
      v-else-if="!courseDetails && !isLoading"
      class="text-center"
    >
      <VIcon
        icon="tabler-search-off"
        size="64"
        color="disabled"
        class="mb-4"
      />
      <p class="text-h6 text-medium-emphasis">
        Course not found
      </p>
      <VBtn
        to="/browse-courses"
        variant="text"
        color="primary"
      >
        Back to Courses
      </VBtn>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.course-pricing {
  position: sticky;
  inset-block: 4rem 0;
}

.card-list {
  --v-card-list-gap: 16px;
}
</style>

<style lang="scss">
@use "@layouts/styles/mixins" as layoutsMixins;

body .v-layout .v-application__wrap {
  .course-content {
    .v-expansion-panels {
      border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
      border-radius: 6px;

      .v-expansion-panel {
        &--active {
          .v-expansion-panel-title--active {
            border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));

            .v-expansion-panel-title__overlay {
              opacity: var(--v-hover-opacity) !important;
            }
          }
        }

        .v-expansion-panel-title {
          .v-expansion-panel-title__overlay {
            background-color: rgba(var(--v-theme-on-surface));
            opacity: var(--v-hover-opacity) !important;
          }

          &:hover {
            .v-expansion-panel-title__overlay {
              opacity: var(--v-hover-opacity) !important;
            }
          }

          &__icon {
            .v-icon {
              block-size: 1.5rem !important;
              color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
              font-size: 1.5rem !important;
              inline-size: 1.5rem !important;

              @include layoutsMixins.rtl {
                transform: scaleX(-1);
              }
            }
          }
        }

        .v-expansion-panel-text {
          &__wrapper {
            padding-block: 1rem;
            padding-inline: 0.75rem;
          }
        }
      }
    }
  }

  .card-list {
    .v-list-item__prepend {
      .v-list-item__spacer {
        inline-size: 8px !important;
      }
    }
  }
}
</style>
