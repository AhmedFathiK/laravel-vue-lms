<script setup>
import { useAuthStore } from '@/stores/auth'
import axios from 'axios'
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
const selectedPricing = ref('All') // 'All', 'Free', 'Paid'
const sortBy = ref('popularity,desc')

const pricingOptions = ['All', 'Free', 'Paid']

const sortOptions = [
  { title: 'Most Popular', value: 'popularity,desc' },
  { title: 'Newest', value: 'createdAt,desc' },
  { title: 'Alphabetical', value: 'title,asc' },
]

// Subscription dialog
const isSubscribeDialogVisible = ref(false)
const selectedCourseForSubscription = ref(null)
const subscriptionPlans = ref([])
const selectedPlan = ref(null)

const fetchCategories = async () => {
  try {
    const response = await axios.get('/api/admin/course-categories')

    categories.value = response.data.categories.map(cat => ({
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
      isFree: selectedPricing.value === 'Free' ? true : (selectedPricing.value === 'Paid' ? false : null),
      sort: sortField,
      order: sortOrder,
    }

    const response = await axios.get('/api/learner/courses', { params })

    courses.value = response.data.data
    totalCourses.value = response.data.total
  } catch (error) {
    console.error('Error fetching courses:', error)
  }
}

const viewCourseDetails = courseId => {
  router.push({ name: 'course-details', params: { id: courseId } })
}

const handleSubscribeClick = async course => {
  if (!authStore.isAuthenticated) {
    router.push({ name: 'login', query: { to: router.currentRoute.value.fullPath } })
    
    return
  }

  selectedCourseForSubscription.value = course

  if (course.isFree) {
    try {
      await axios.post(`/api/learner/courses/${course.id}/enroll`)
      alert(`Successfully enrolled in ${course.title}`)
    } catch (error) {
      console.error('Error enrolling in free course:', error)
      alert('Failed to enroll in course.')
    }
  } else {
    try {
      const response = await axios.get(`/api/learner/courses/${course.id}/subscription-plans`)

      subscriptionPlans.value = response.data.plans
      isSubscribeDialogVisible.value = true
    } catch (error) {
      console.error('Error fetching subscription plans:', error)
      alert('Failed to load subscription plans.')
    }
  }
}

const confirmSubscription = async () => {
  if (!selectedPlan.value) {
    alert('Please select a plan.')
    
    return
  }

  try {
    await axios.post('/api/learner/subscribe', { planId: selectedPlan.value.id })
    alert(`Successfully subscribed to ${selectedCourseForSubscription.value.title} - ${selectedPlan.value.name}`)
    isSubscribeDialogVisible.value = false
  } catch (error) {
    console.error('Error subscribing:', error)
    alert('Failed to subscribe.')
  }
}

watch([searchQuery, selectedCategory, selectedPricing, sortBy, currentPage], fetchCourses, { deep: true })

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
        <VCard class="pa-4 mb-6">
          <VRow>
            <VCol
              cols="12"
              md="5"
            >
              <VTextField
                v-model="searchQuery"
                label="Search courses..."
                prepend-inner-icon="tabler-search"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="3"
            >
              <VSelect
                v-model="selectedCategory"
                label="Category"
                :items="categories"
                item-title="title"
                item-value="value"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="2"
            >
              <VSelect
                v-model="selectedPricing"
                label="Pricing"
                :items="pricingOptions"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="2"
            >
              <VSelect
                v-model="sortBy"
                label="Sort By"
                :items="sortOptions"
                item-title="title"
                item-value="value"
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
              class="d-flex flex-column h-100"
              rounded="lg"
              :elevation="course.isFeatured ? 8 : 2"
            >
              <div class="position-relative">
                <VImg
                  :src="course.thumbnail || 'https://placehold.co/600x400/EEE/31343C'"
                  height="200px"
                  max-height="200px"
                  cover
                  class="rounded-t-lg"
                  :aspect-ratio="16/9"
                />
                <VChip
                  v-if="course.isFeatured"
                  color="warning"
                  size="small"
                  class="position-absolute top-0 start-0 ma-2 font-weight-bold"
                  style="z-index: 1;"
                >
                  Featured
                </VChip>
              </div>

              <VCardText class="flex-grow-1">
                <div class="d-flex justify-space-between align-center mb-2">
                  <VChip
                    :color="course.isFree ? 'success' : 'info'"
                    size="small"
                  >
                    {{ course.isFree ? 'Free' : 'Paid' }}
                  </VChip>
                  <VChip
                    v-if="course.category"
                    color="secondary"
                    size="small"
                  >
                    {{ course.category.name }}
                  </VChip>
                </div>
                <h5 class="text-h5 font-weight-bold mb-2">
                  {{ course.title }}
                </h5>
                <p class="text-body-1 truncated-text">
                  {{ course.description }}
                </p>
              </VCardText>

              <VCardActions class="pa-4">
                <VRow>
                  <VCol cols="6">
                    <VBtn
                      block
                      rounded="lg"
                      color="secondary"
                      variant="tonal"
                      @click="viewCourseDetails(course.id)"
                    >
                      View Details
                    </VBtn>
                  </VCol>
                  <VCol cols="6">
                    <VBtn
                      block
                      rounded="lg"
                      color="primary"
                      variant="tonal"
                      @click="handleSubscribeClick(course)"
                    >
                      Subscribe
                    </VBtn>
                  </VCol>
                </VRow>
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

    <!-- Subscribe Dialog -->
    <VDialog
      v-model="isSubscribeDialogVisible"
      max-width="500"
    >
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center">
          <span>Subscribe to {{ selectedCourseForSubscription?.title }}</span>
          <VBtn
            icon="tabler-x"
            variant="plain"
            size="small"
            @click="isSubscribeDialogVisible = false"
          />
        </VCardTitle>
        <VCardText>
          <p class="mb-4">
            Select a subscription plan:
          </p>
          <VRadioGroup v-model="selectedPlan">
            <VRadio
              v-for="plan in subscriptionPlans"
              :key="plan.id"
              :value="plan"
              class="mb-2"
            >
              <template #label>
                <div>
                  <span class="font-weight-semibold">{{ plan.name }}</span>
                  <span class="text-sm ms-2">({{ plan.price === '0.00' ? 'Free' : `${plan.price} ${plan.currency} / ${plan.billingCycle}` }})</span>
                  <p class="text-caption text-medium-emphasis">
                    {{ plan.description }}
                  </p>
                </div>
              </template>
            </VRadio>
          </VRadioGroup>
          <VAlert
            v-if="!subscriptionPlans.length"
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
            @click="isSubscribeDialogVisible = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            :disabled="!selectedPlan"
            @click="confirmSubscription"
          >
            Confirm Subscription
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>

<style scoped>
.truncated-text {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3; /* Limit to 3 lines */
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
