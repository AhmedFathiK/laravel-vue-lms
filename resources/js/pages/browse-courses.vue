<script setup>
import { useAuthStore } from '@/stores/auth'
import api from '@/utils/api'
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

definePage({
  meta: {
    layout: 'learner',
  },
})

const router = useRouter()
const authStore = useAuthStore()

const courses = ref([])
const categories = ref([])
const currentPage = ref(1)
const totalCourses = ref(0)
const perPage = ref(9)

// Filters and search
const searchQuery = ref('')
const selectedCategory = ref(null)
const sortBy = ref('popularity,desc')

const sortOptions = [
  { title: 'Most Popular', value: 'popularity,desc' },
  { title: 'Newest', value: 'createdAt,desc' },
  { title: 'Alphabetical', value: 'title,asc' },
]

// Entitlement and Payment dialogs
const isAcquireDialogVisible = ref(false)
const selectedCourseForEntitlement = ref(null)
const billingPlans = ref([])
const selectedPlan = ref(null)

const isLoading = ref(false)
const processingPlanId = ref(null)
const error = ref(null)
const isPaymentErrorDialogVisible = ref(false)
const isPaymentMethodDialogVisible = ref(false)
const paymentMethods = ref([])
const selectedPaymentMethod = ref(null)

const fetchCategories = async () => {
  try {
    const response = await api.get('/admin/course-categories')

    categories.value = response.categories.map(cat => ({
      title: cat.name,
      value: cat.id,
    }))
  } catch (error) {
    console.error('Error fetching categories:', error)
  }
}

const fetchCourses = async () => {
  try {
    const [sortField, sortOrder] = sortBy.value.split(',')

    const params = {
      page: currentPage.value,
      perPage: perPage.value,
      search: searchQuery.value,
      categoryId: selectedCategory.value,
      sort: sortField,
      order: sortOrder,
    }

    const response = await api.get('/learner/courses', { params })

    courses.value = response.data
    totalCourses.value = response.total
  } catch (error) {
    console.error('Error fetching courses:', error)
  }
}

const viewCourseDetails = courseId => {
  router.push(`/courses/${courseId}`)
}

const handleAcquireClick = async course => {
  if (!authStore.isAuthenticated) {
    router.push({ name: 'login', query: { to: router.currentRoute.value.fullPath } })
    
    return
  }

  if (course.hasActiveAccess) {
    router.push(`/my-courses/${course.id}`)

    return
  }

  viewCourseDetails(course.id)
}

const handlePayment = async plan => {
  processingPlanId.value = plan.id
  selectedPlan.value = plan
  try {
    // If the plan is free, acquire entitlement directly without payment gateway
    if (parseFloat(plan.price) === 0) {
      await api.post('/learner/acquire-entitlement', {
        planId: plan.id,
      })
        
      isAcquireDialogVisible.value = false
      
      // Refresh courses to update UI state
      await fetchCourses()

      router.push(`/my-courses/${selectedCourseForEntitlement.value.id}`)
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
        isAcquireDialogVisible.value = false
      } else {
        throw new Error('Failed to fetch payment methods')
      }
    }
  } catch (err) {
    console.error('Entitlement acquisition error', err)
    error.value = err.message || 'Acquisition failed'
    isPaymentErrorDialogVisible.value = true
  } finally {
    processingPlanId.value = null
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
      courseId: selectedCourseForEntitlement.value.id,
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

const confirmAcquisition = async () => {
  if (!selectedPlan.value) {
    error.value = 'Please select a plan.'
    isPaymentErrorDialogVisible.value = true
    
    return
  }

  await handlePayment(selectedPlan.value)
}

watch([searchQuery, selectedCategory, sortBy, currentPage], fetchCourses, { deep: true })

onMounted(() => {
  fetchCourses()
  fetchCategories()
})
</script>

<template>
  <VContainer>
    <VRow>
      <VCol cols="12">
        <h1 class="text-h4 mb-4">
          Browse Courses
        </h1>
      </VCol>

      <!-- Search and Filter Bar -->
      <VCol cols="12">
        <VCard class="pa-6 mb-8 filter-card">
          <VRow align="center">
            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model="searchQuery"
                label="Search courses..."
                prepend-inner-icon="tabler-search"
                variant="outlined"
                hide-details
                clearable
                rounded="lg"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <VSelect
                v-model="selectedCategory"
                label="Category"
                :items="categories"
                item-title="title"
                item-value="value"
                variant="outlined"
                hide-details
                clearable
                rounded="lg"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="4"
            >
              <VSelect
                v-model="sortBy"
                label="Sort By"
                :items="sortOptions"
                item-title="title"
                item-value="value"
                variant="outlined"
                hide-details
                rounded="lg"
              />
            </VCol>
          </VRow>
        </VCard>
      </VCol>

      <!-- Course Grid -->
      <VCol cols="12">
        <VRow>
          <VCol
            v-for="course in courses"
            :key="course.id"
            cols="12"
            sm="6"
            md="4"
          >
            <VCard
              class="course-card d-flex flex-column h-100"
              :elevation="course.isFeatured ? 4 : 1"
            >
              <div
                class="position-relative overflow-hidden cursor-pointer"
                @click="viewCourseDetails(course.id)"
              >
                <VImg
                  :src="course.thumbnail || 'https://placehold.co/600x400/EEE/31343C'"
                  height="220px"
                  cover
                  class="course-thumbnail transition-all"
                  :aspect-ratio="16/9"
                />
                
                <!-- Overlay Badges -->
                <div
                  class="position-absolute top-0 start-0 w-100 pa-3 d-flex justify-space-between align-start"
                  style="z-index: 2;"
                >
                  <div class="d-flex flex-column gap-2">
                    <VChip
                      v-if="course.isFeatured"
                      color="warning"
                      size="x-small"
                      variant="elevated"
                      class="font-weight-bold px-2"
                    >
                      FEATURED
                    </VChip>
                  </div>
                  
                  <VChip
                    v-if="course.hasActiveAccess"
                    color="success"
                    size="small"
                    variant="elevated"
                    class="font-weight-bold"
                  >
                    <VIcon
                      start
                      icon="tabler-circle-check"
                      size="14"
                    />
                    ENROLLED
                  </VChip>
                </div>
              </div>

              <VCardText class="pa-5 flex-grow-1">
                <div class="d-flex align-center mb-2">
                  <span
                    v-if="course.category"
                    class="text-overline text-secondary font-weight-bold"
                  >
                    {{ course.category.name }}
                  </span>
                  <VSpacer />
                </div>
                
                <h3
                  class="text-h6 font-weight-bold mb-2 line-clamp-2"
                  style="min-height: 3rem; line-height: 1.5rem;"
                >
                  {{ course.title }}
                </h3>
                
                <p class="text-body-2 text-medium-emphasis truncated-text mb-0">
                  {{ course.description }}
                </p>
              </VCardText>

              <VDivider class="mx-5" />

              <VCardActions class="pa-5">
                <VBtn
                  block
                  :color="course.isEnrolled ? 'success' : 'primary'"
                  variant="elevated"
                  rounded="lg"
                  size="large"
                  class="font-weight-bold"
                  @click="handleAcquireClick(course)"
                >
                  <VIcon
                    v-if="course.isEnrolled"
                    start
                    icon="tabler-player-play"
                  />
                  {{ course.isEnrolled ? 'Continue Learning' : 'View Details' }}
                </VBtn>
              </VCardActions>
            </VCard>
          </VCol>
          <VCol
            v-if="!courses.length"
            cols="12"
          >
            <VAlert
              type="info"
              variant="tonal"
            >
              No courses found matching your criteria.
            </VAlert>
          </VCol>
        </VRow>
      </VCol>

      <!-- Pagination -->
      <VCol
        cols="12"
        class="d-flex justify-center mt-4"
      >
        <VPagination
          v-model="currentPage"
          :length="Math.ceil(totalCourses / perPage)"
          :total-visible="7"
        />
      </VCol>
    </VRow>

    <!-- Acquire Entitlement Dialog -->
    <VDialog
      v-model="isAcquireDialogVisible"
      max-width="500"
    >
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center">
          <span>Acquire {{ selectedCourseForEntitlement?.title }}</span>
          <VBtn
            icon="tabler-x"
            variant="plain"
            size="small"
            @click="isAcquireDialogVisible = false"
          />
        </VCardTitle>
        <VCardText>
          <p class="mb-4">
            Select a billing plan:
          </p>
          <VRadioGroup v-model="selectedPlan">
            <VRadio
              v-for="plan in billingPlans"
              :key="plan.id"
              :value="plan"
              class="mb-2"
            >
              <template #label>
                <div>
                  <span class="font-weight-semibold">{{ plan.name }}</span>
                  <span class="text-sm ms-2">({{ plan.price === '0.00' ? 'Free' : `${plan.price} ${plan.currency} / ${plan.billingInterval}` }})</span>
                  <p class="text-caption text-medium-emphasis">
                    {{ plan.description }}
                  </p>
                </div>
              </template>
            </VRadio>
          </VRadioGroup>
          <VAlert
            v-if="!billingPlans.length"
            type="info"
            variant="tonal"
            class="mt-4"
          >
            No plans available for this course.
          </VAlert>
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn
            variant="tonal"
            @click="isAcquireDialogVisible = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            :disabled="!selectedPlan"
            @click="confirmAcquisition"
          >
            Confirm
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Payment Method Dialog -->
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

    <!-- Payment Error Dialog -->
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
  </VContainer>
</template>

<style scoped>
.course-card {
  transition: all 0.3s ease-in-out;
  border: 1px solid rgba(var(--v-border-color), 0.05);
}

.course-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px -4px rgba(var(--v-theme-on-surface), 0.1) !important;
}

.course-card:hover .course-thumbnail {
  transform: scale(1.05);
}

.course-thumbnail {
  transition: transform 0.5s ease;
}

.truncated-text {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.6;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  overflow: hidden;
}

.filter-card {
  border: 1px solid rgba(var(--v-border-color), 0.1);
  background: rgba(var(--v-theme-surface), 0.8);
  backdrop-filter: blur(8px);
}
</style>
