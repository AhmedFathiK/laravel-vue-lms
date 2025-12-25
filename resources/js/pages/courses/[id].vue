<script setup>
import api from '@/utils/api'
import { VideoPlayer } from '@videojs-player/vue'
import 'video.js/dist/video-js.css'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const courseDetails = ref(null)
const panelStatus = ref(0)
const isLoading = ref(false)
const processingPlanId = ref(null)
const error = ref(null)

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

onMounted(() => {
  fetchCourseDetails()
})

const subscriptionPlans = computed(() => courseDetails.value?.subscriptionPlans || [])

const resolvePlanTypeColor = type => {
  if (type === 'monthly') return 'primary'
  if (type === 'annual') return 'success'
  if (type === 'one-time') return 'warning'
  
  return 'secondary'
}

const handlePayment = async plan => {
  processingPlanId.value = plan.id
  try {
    const response = await api.post('/payments/checkout', {
      amount: plan.price,
      currency: plan.currency || import.meta.env.VITE_DEFAULT_CURRENCY || 'EGP',
      planId: plan.id,
      courseId: courseDetails.value.id,
    })

    if (response.success && response.paymentUrl) {
      window.location.href = response.paymentUrl
    } else {
      console.error('Payment initiation failed', response)

      // toast.error('Failed to initiate payment')
    }
  } catch (err) {
    console.error('Payment error', err)

    // toast.error(err.message || 'Payment failed')
  } finally {
    processingPlanId.value = null
  }
}

// Check for payment status in query params
if (route.query.payment === 'success') {
  console.log('Payment successful')
} else if (route.query.payment === 'failed') {
  console.log('Payment failed')
}
</script>

<template>
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
                :src="courseDetails.videoUrl || 'https://cdn.plyr.io/static/demo/View_From_A_Blue_Moon_Trailer-576p.mp4'"
                :poster="courseDetails.thumbnail"
                controls
                plays-inline
                :height="$vuetify.display.mdAndUp ? 440 : 250"
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
                Subscription Plans
              </h5>
              
              <div
                v-if="subscriptionPlans.length > 0"
                class="d-flex flex-wrap gap-4"
              >
                <VCard 
                  v-for="plan in subscriptionPlans" 
                  :key="plan.id"
                  variant="outlined"
                  class="flex-grow-1"
                  :style="{ minWidth: '250px' }"
                >
                  <VCardItem>
                    <template #prepend>
                      <VIcon 
                        :icon="plan.planType === 'one-time' ? 'tabler-infinity' : 'tabler-calendar'" 
                        :color="resolvePlanTypeColor(plan.planType)"
                        size="32"
                        class="me-2"
                      />
                    </template>
                    <VCardTitle>{{ plan.name }}</VCardTitle>
                    <VCardSubtitle class="text-capitalize">
                      {{ plan.planType?.replace('-', ' ') }}
                    </VCardSubtitle>
                  </VCardItem>
                  
                  <VCardText>
                    <div class="d-flex align-baseline mb-2">
                      <span class="text-h4 font-weight-bold text-primary">{{ plan.price }}</span>
                      <span class="text-body-2 ms-1">{{ plan.currency }}</span>
                      <span
                        v-if="plan.planType !== 'one-time'"
                        class="text-body-2 text-medium-emphasis"
                      >/ {{ plan.billingCycle }}</span>
                    </div>
                    <p class="text-body-2 mb-4">
                      {{ plan.description }}
                    </p>
                    
                    <VBtn 
                      block 
                      :color="resolvePlanTypeColor(plan.planType)" 
                      variant="tonal"
                      :loading="processingPlanId === plan.id"
                      @click="handlePayment(plan)"
                    >
                      Subscribe Now
                    </VBtn>
                  </VCardText>
                </VCard>
              </div>
              <div v-else>
                <VAlert
                  type="info"
                  variant="tonal"
                  text="No subscription plans available for this course."
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
.course-content {
  /* Style for course content if needed */
}

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
