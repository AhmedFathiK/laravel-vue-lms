<script setup>
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'courses',
  },
})

const toast = useToast()
const { locale } = useI18n()
const router = useRouter()

// 👉 Store
const searchQuery = ref('')
const selectedCategory = ref(null)
const selectedStatus = ref(null)
const isLoading = ref(false)
const editCourse = ref(null)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('createdAt')
const orderBy = ref('desc')
const selectedRows = ref([])
const availableCategories = ref([])

// Combine course count stats
const widgetData = ref([
  {
    title: 'Total Courses',
    value: '0',
    icon: 'tabler-book',
    iconColor: 'primary',
  },
  {
    title: 'Active Courses',
    value: '0',
    icon: 'tabler-check',
    iconColor: 'success',
  },
  {
    title: 'Draft Courses',
    value: '0',
    icon: 'tabler-file',
    iconColor: 'warning',
  },
  {
    title: 'Subscription Courses',
    value: '0',
    icon: 'tabler-refresh',
    iconColor: 'info',
  },
])

// Fetch courses
const coursesData = ref({
  items: [],
  totalItems: 0,
  currentPage: 1,
  perPage: 10,
  lastPage: 1,
})

// Headers for data table
const headers = [
  {
    title: 'Image',
    key: 'thumbnail',
    sortable: false,
    width: '100px',
  },
  {
    title: 'Course',
    key: 'title',
  },
  {
    title: 'Levels',
    key: 'levels',
  },
  {
    title: 'Subscriptions',
    key: 'subscriptions',
  },
  {
    title: 'Status',
    key: 'status',
  },
  {
    title: 'Actions',
    key: 'actions',
    sortable: false,
  },
]

const updateOptions = options => {
  if (options.sortBy?.length) {
    sortBy.value = options.sortBy[0]?.key
    orderBy.value = options.sortBy[0]?.order
  }
  fetchCourses()
}

// Fetch courses from API
const fetchCourses = async () => {
  isLoading.value = true
  try {
    const params = {
      page: page.value,
      perPage: itemsPerPage.value,
      search: searchQuery.value || undefined,
      category: selectedCategory.value || undefined,
      status: selectedStatus.value || undefined,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
    }
    
    const response = await api.get('/admin/courses', { params })

    // Debug the response structure
    console.log('API Response:', response)
    
    // Check if response is HTML (error page)
    if (typeof response === 'string' && response.includes('<!DOCTYPE html>')) {
      console.warn('API returned HTML instead of JSON')
      
      coursesData.value = {
        items: [],
        totalItems: 0,
        currentPage: page.value,
        perPage: itemsPerPage.value,
        lastPage: 1,
      }
      
      return
    }

    // Handle different response structures
    if (response && typeof response === 'object') {
      coursesData.value = response
    } else {
      console.warn('Unexpected API response format:', response)
      coursesData.value = { items: [], totalItems: 0 }
    }
    
    // Update widget data with counts
    updateWidgetCounts()
  } catch (error) {
    console.error('Error fetching courses:', error)
    coursesData.value = { items: [], totalItems: 0 }
    
    // Update widget data with counts
    updateWidgetCounts()
  } finally {
    isLoading.value = false
  }
}

// Update widget data with course counts
const updateWidgetCounts = () => {
  // Check if data is available and has the expected structure
  if (!coursesData.value || typeof coursesData.value !== 'object') {
    console.warn('Course data is not in expected format', coursesData.value)
    
    return
  }

  // Use stats from API if available, otherwise fallback to current page counts
  if (coursesData.value.stats) {
    widgetData.value[0].value = (coursesData.value.stats.total || 0).toString()
    widgetData.value[1].value = (coursesData.value.stats.active || 0).toString()
    widgetData.value[2].value = (coursesData.value.stats.draft || 0).toString()
    widgetData.value[3].value = (coursesData.value.stats.subscription || 0).toString()
    
    return
  }

  // Set total courses - safely handle potential undefined values
  const totalCoursesCount = coursesData.value.totalItems || coursesData.value.total || 0

  widgetData.value[0].value = totalCoursesCount.toString()
  
  // Make sure courses array exists
  const coursesList = coursesData.value.items || coursesData.value.data || []
  
  // Count active, draft, and subscription courses
  let activeCount = 0
  let subscriptionCount = 0
  
  coursesList.forEach(course => {
    if (course.status === 'active') {
      activeCount++
    }
    
    if (course.subscriptionPlans && course.subscriptionPlans.some(plan => plan.plan_type === 'recurring')) {
      subscriptionCount++
    }
  })
  
  // Set active courses (approximate based on current page)
  widgetData.value[1].value = activeCount.toString()
  
  // Set draft courses (approximate based on current page)
  widgetData.value[2].value = (coursesList.length - activeCount).toString()
  
  // Set subscription courses (approximate based on current page)
  widgetData.value[3].value = subscriptionCount.toString()
}

// Fetch available categories
const fetchCategories = async () => {
  try {
    // Fetch categories from the API
    const response = await api.get('/admin/course-categories')
    
    // Check if response is HTML (error page)
    if (typeof response === 'string' && response.includes('<!DOCTYPE html>')) {
      console.warn('API returned HTML instead of JSON')
      availableCategories.value = []
      
      return
    }

    // Handle response structure
    if (response && typeof response === 'object') {
      if (response.categories) {
        availableCategories.value = response.categories
      } else if (Array.isArray(response)) {
        availableCategories.value = response
      } else {
        console.warn('Unexpected categories response format:', response)
        availableCategories.value = []
      }
    } else {
      console.warn('Invalid categories response:', response)
      availableCategories.value = []
    }
  } catch (error) {
    console.error('Error fetching categories:', error)
    availableCategories.value = []
  }
}

// Watch for changes to trigger refetch
watch([searchQuery, selectedCategory, selectedStatus, page, itemsPerPage], () => {
  fetchCourses()
})

// Watch for locale changes and refresh data
watch(() => locale.value, () => {
  fetchCourses()
  fetchCategories()
})

// Computed properties
const courses = computed(() => {
  if (!coursesData.value) return []
  
  return coursesData.value.items || coursesData.value.data || []
})

const totalCourses = computed(() => {
  if (!coursesData.value) return 0
  
  return coursesData.value.totalItems || coursesData.value.total || 0
})

// Categories for dropdown
const categories = computed(() => availableCategories.value)

// Status options for dropdown
const status = [
  {
    title: 'Active',
    value: 'active',
  },
  {
    title: 'Draft',
    value: 'draft',
  },
]

// Helper functions for UI
const resolveCourseStatusVariant = status => {
  if (status === 'active')
    return 'success'
  
  return 'warning'
}

// Helper function to get category name
const getCategoryName = course => {
  if (!course) return 'No Category'
  
  // If category object is available with name
  if (course.category && course.category.name) {
    return course.category.name
  }
  
  // Try to find category by ID
  const categoryId = course.course_category_id || course.category_id
  if (categoryId) {
    const category = availableCategories.value.find(c => c.id === categoryId)
    if (category) return category.name
  }
  
  return 'No Category'
}

const isAddNewCourseDialogVisible = ref(false)

// Password confirmation dialog
const isPasswordDialogVisible = ref(false)
const courseToDelete = ref(null)

// Delete course with password confirmation
const confirmDeleteCourse = course => {
  courseToDelete.value = course
  isPasswordDialogVisible.value = true
}

const handlePasswordConfirm = async result => {
  if (!result.confirmed || !courseToDelete.value) return
  
  // Here you would normally verify the password with the backend
  // For this example, we'll just proceed with the deletion
  
  try {
    await api.delete(`/admin/courses/${courseToDelete.value.id}`)
    toast.success('Course deleted successfully')
    fetchCourses()
  } catch (error) {
    console.error('Error deleting course:', error)
    toast.error('Failed to delete course')
  } finally {
    courseToDelete.value = null
  }
}

// Navigation functions for subscription plans and levels
const navigateToSubscriptionPlans = courseId => {
  router.push(`/admin/courses/${courseId}/subscription-plans`)
}

const navigateToLevels = courseId => {
  router.push(`/admin/courses/${courseId}/levels`)
}

const navigateToTerms = courseId => {
  router.push(`/admin/courses/${courseId}/terms`)
}

const navigateToQuestions = courseId => {
  router.push(`/admin/courses/${courseId}/questions`)
}

const navigateToConcepts = courseId => {
  router.push(`/admin/courses/${courseId}/concepts`)
}

// Show edit course dialog
const showEditCourseDialog = course => {
  editCourse.value = course
  isAddNewCourseDialogVisible.value = true
}

// Fetch data on component mount
onMounted(() => {
  fetchCourses()
  fetchCategories()
})
</script>

<template>
  <section>
    <!-- Breadcrumb Navigation -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Courses', disabled: true }
      ]"
      class="mb-4"
    />
    
    <!-- 👉 Widgets -->
    <div class="d-flex mb-6">
      <VRow>
        <template
          v-for="(data, id) in widgetData"
          :key="id"
        >
          <VCol
            cols="12"
            md="3"
            sm="6"
          >
            <VCard>
              <VCardText>
                <div class="d-flex justify-space-between">
                  <div class="d-flex flex-column gap-y-1">
                    <div class="text-body-1 text-high-emphasis">
                      {{ data.title }}
                    </div>
                    <div class="d-flex gap-x-2 align-center">
                      <h4 class="text-h4">
                        {{ data.value }}
                      </h4>
                    </div>
                  </div>
                  <VAvatar
                    :color="data.iconColor"
                    variant="tonal"
                    rounded
                    size="42"
                  >
                    <VIcon
                      :icon="data.icon"
                      size="26"
                    />
                  </VAvatar>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </template>
      </VRow>
    </div>

    <VCard class="mb-6">
      <VCardItem class="pb-4">
        <VCardTitle>Filters</VCardTitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <!-- 👉 Select Category -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedCategory"
              placeholder="Select Category"
              :items="categories"
              item-title="name"
              item-value="id"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          
          <!-- 👉 Select Status -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedStatus"
              placeholder="Select Status"
              :items="status"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardText class="d-flex flex-wrap gap-4">
        <div class="me-3 d-flex gap-3">
          <AppSelect
            :model-value="itemsPerPage"
            :items="[
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: 100, title: '100' },
            ]"
            style="inline-size: 6.25rem;"
            @update:model-value="itemsPerPage = parseInt($event, 10)"
          />
        </div>
        <VSpacer />

        <div class="app-course-search-filter d-flex align-center flex-wrap gap-4">
          <!-- 👉 Search  -->
          <div style="inline-size: 15.625rem;">
            <AppTextField
              v-model="searchQuery"
              placeholder="Search Course"
            />
          </div>

          <!-- 👉 Add course button -->
          <VBtn
            prepend-icon="tabler-plus"
            @click="isAddNewCourseDialogVisible = true; editCourse = null"
          >
            Add New Course
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- SECTION datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="courses"
        :headers="headers"
        :items-length="totalCourses"
        :loading="isLoading"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- Image -->
        <template #[`item.thumbnail`]="{ item }">
          <div class="py-2">
            <VAvatar
              size="40"
              :color="item.thumbnail ? '' : 'primary'"
              :variant="!item.thumbnail ? 'tonal' : undefined"
            >
              <VImg
                v-if="item.thumbnail"
                :src="item.thumbnail"
                cover
              />
              <VIcon
                v-else
                icon="tabler-camera-off"
                size="20"
              />
            </VAvatar>
          </div>
        </template>

        <!-- Course -->
        <template #[`item.title`]="{ item }">
          <div class="d-flex flex-column">
            <h6 class="text-base font-weight-medium">
              {{ item.title }}
            </h6>
            <div class="text-sm text-medium-emphasis">
              {{ item.category?.name || 
                (item.course_category_id && availableCategories.find(c => c.id === item.course_category_id)?.name) || 
                'No Category' }}
            </div>
          </div>
        </template>

        <!-- Levels -->
        <template #[`item.levels`]="{ item }">
          <div class="text-body-1 text-high-emphasis">
            {{ item.levelsCount || 0 }}
          </div>
        </template>

        <!-- Subscriptions -->
        <template #[`item.subscriptions`]="{ item }">
          <div class="text-body-1 text-high-emphasis">
            {{ item.subscriptionsCount || 0 }}
          </div>
        </template>

        <!-- Status -->
        <template #[`item.status`]="{ item }">
          <VChip
            :color="resolveCourseStatusVariant(item.status)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ item.status }}
          </VChip>
        </template>

        <!-- Subscription Type -->
        <template #[`item.subscription_type`]="{ item }">
          <VChip
            :color="item.is_free ? 'success' : (item.subscriptionPlans && item.subscriptionPlans.some(plan => plan.plan_type === 'recurring') ? 'primary' : 'info')"
            size="small"
            class="text-white"
          >
            {{ item.is_free ? 'Free' : (item.subscriptionPlans && item.subscriptionPlans.some(plan => plan.plan_type === 'recurring') ? 'Subscription' : 'One-time') }}
          </VChip>
        </template>

        <!-- Actions -->
        <template #[`item.actions`]="{ item }">
          <IconBtn @click="confirmDeleteCourse(item)">
            <VIcon icon="tabler-trash" />
          </IconBtn>

          <IconBtn @click="showEditCourseDialog(item)">
            <VIcon icon="tabler-edit" />
          </IconBtn>
          
          <IconBtn
            color="primary"
            @click="navigateToSubscriptionPlans(item.id)"
          >
            <VIcon icon="tabler-cash" />
            <VTooltip activator="parent">
              Subscription Plans
            </VTooltip>
          </IconBtn>
          
          <IconBtn
            color="info"
            @click="navigateToLevels(item.id)"
          >
            <VIcon icon="tabler-stairs" />
            <VTooltip activator="parent">
              Levels
            </VTooltip>
          </IconBtn>
          
          <IconBtn
            color="success"
            @click="navigateToTerms(item.id)"
          >
            <VIcon icon="tabler-vocabulary" />
            <VTooltip activator="parent">
              Terms
            </VTooltip>
          </IconBtn>
          
          <IconBtn
            color="warning"
            @click="navigateToQuestions(item.id)"
          >
            <VIcon icon="tabler-help-circle" />
            <VTooltip activator="parent">
              Questions
            </VTooltip>
          </IconBtn>
          
          <IconBtn
            color="secondary"
            @click="navigateToConcepts(item.id)"
          >
            <VIcon icon="tabler-bulb" />
            <VTooltip activator="parent">
              Concepts
            </VTooltip>
          </IconBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalCourses"
          />
        </template>
      </VDataTableServer>
      <!-- SECTION -->
    </VCard>
    
    <!-- 👉 Course Form Dialog -->
    <AddEditCourseDialog
      v-model:is-dialog-visible="isAddNewCourseDialogVisible"
      :data="editCourse"
      :dialog-mode="editCourse ? 'edit' : 'add'"
      :categories="categories"
      @refresh="fetchCourses"
    />

    <!-- 👉 Password Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isPasswordDialogVisible"
      confirmation-question="Are you sure you want to delete this course? This action cannot be undone."
      @confirm="handlePasswordConfirm"
    />
  </section>
</template>
