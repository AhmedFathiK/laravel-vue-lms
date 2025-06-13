<script setup>
import CourseEditDialog from '@/components/dialogs/CourseEditDialog.vue'
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()

// 👉 Store
const searchQuery = ref('')
const selectedCategory = ref(null)
const selectedStatus = ref(null)
const isLoading = ref(false)
const editCourse = ref(null)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('created_at')
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
  courses: [],
  totalCourses: 0,
  currentPage: 1,
  perPage: 10,
  lastPage: 1,
})

// Headers for data table
const headers = [
  {
    title: 'Course',
    key: 'course',
  },
  {
    title: 'Price',
    key: 'price',
    sortable: true,
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
        courses: [],
        totalCourses: 0,
        currentPage: page.value,
        perPage: itemsPerPage.value,
        lastPage: 1,
      }
      
      return
    }

    // Handle different response structures
    if (response && typeof response === 'object') {
      // Process courses to handle translatable fields and category relationship
      if (response.courses) {
        // Process courses to handle translatable fields
        const processedCourses = response.courses.map(course => {
          // Process translatable fields if needed
          if (typeof course.title === 'object') {
            const locale = document.documentElement.lang || 'en'

            course.title = course.title[locale] || Object.values(course.title)[0]
          }
          
          if (typeof course.description === 'object') {
            const locale = document.documentElement.lang || 'en'

            course.description = course.description[locale] || Object.values(course.description)[0]
          }
          
          // Process category relationship
          if (course.category && typeof course.category.name === 'object') {
            const locale = document.documentElement.lang || 'en'

            course.category.name = course.category.name[locale] || Object.values(course.category.name)[0]
          }
          
          return course
        })
        
        coursesData.value = {
          ...response,
          courses: processedCourses,
        }
      } else {
        coursesData.value = response
      }
    } else {
      console.warn('Unexpected API response format:', response)
      coursesData.value = { courses: [], totalCourses: 0 }
    }
    
    // Update widget data with counts
    updateWidgetCounts()
  } catch (error) {
    console.error('Error fetching courses:', error)
    coursesData.value = { courses: [], totalCourses: 0 }
    
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

  // Set total courses - safely handle potential undefined values
  const totalCoursesCount = coursesData.value.totalCourses || coursesData.value.total || 0

  widgetData.value[0].value = totalCoursesCount.toString()
  
  // Make sure courses array exists
  const coursesList = coursesData.value.courses || coursesData.value.data || []
  
  // Count active, draft, and subscription courses
  let activeCount = 0
  let subscriptionCount = 0
  
  coursesList.forEach(course => {
    if (course.status === 'active') {
      activeCount++
    }
    
    if (course.subscription_type === 'monthly') {
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
        // Process categories to handle translatable fields
        availableCategories.value = response.categories.map(category => {
          // If name is a JSON object with language keys, use the current locale or fallback to first available
          if (typeof category.name === 'object') {
            const locale = document.documentElement.lang || 'en'

            category.name = category.name[locale] || Object.values(category.name)[0]
          }
          
          return category
        })
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

// Computed properties
const courses = computed(() => {
  if (!coursesData.value) return []
  
  return coursesData.value.courses || coursesData.value.data || []
})

const totalCourses = computed(() => {
  if (!coursesData.value) return 0
  
  return coursesData.value.totalCourses || coursesData.value.total || 0
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

const formatPrice = price => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(price)
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

// Add new course
const addNewCourse = async courseData => {
  try {
    const response = await api.post('/admin/courses', courseData)
    
    toast.success('Course created successfully')
    fetchCourses()
    
    return response
  } catch (error) {
    console.error('Error creating course:', error)
    
    // Show all error messages if there are multiple
    if (error.response?.data?.errors) {
      // Get all error messages as an array of strings
      const errorMessages = Object.values(error.response.data.errors).flat()
      
      // Show each error as a separate toast
      errorMessages.forEach(message => {
        toast.error(message)
      })
    } else {
      toast.error(error.response?.data?.message || 'Failed to create course')
    }
    
    throw error // Re-throw to handle in the form component
  }
}

// Edit course
const editCourseData = async (courseData, id) => {
  try {
    const response = await api.post(`/admin/courses/${id}`, courseData)
    
    toast.success('Course updated successfully')
    fetchCourses()
    editCourse.value = null
    
    return response
  } catch (error) {
    console.error('Error updating course:', error)
    
    // Show all error messages if there are multiple
    if (error.response?.data?.errors) {
      // Get all error messages as an array of strings
      const errorMessages = Object.values(error.response.data.errors).flat()
      
      // Show each error as a separate toast
      errorMessages.forEach(message => {
        toast.error(message)
      })
    } else {
      toast.error(error.response?.data?.message || 'Failed to update course')
    }
    
    throw error // Re-throw to handle in the form component
  }
}

// Delete course
const deleteCourse = async id => {
  if (!confirm('Are you sure you want to delete this course?')) return

  try {
    const response = await api.delete(`/admin/courses/${id}`)

    toast.success('Course deleted successfully')
    
    // Delete from selectedRows
    const index = selectedRows.value.findIndex(row => row === id)
    if (index !== -1)
      selectedRows.value.splice(index, 1)
    
    // Refetch courses
    fetchCourses()
  } catch (error) {
    console.error('Error deleting course:', error)
    toast.error(error.response?.data?.message || 'Failed to delete course')
  }
}

// Toggle course status
const toggleCourseStatus = async course => {
  try {
    const response = await api.post(`/admin/courses/${course.id}/toggle-status`)

    toast.success(`Course status ${course.status === 'active' ? 'set to draft' : 'activated'} successfully`)
    fetchCourses()
  } catch (error) {
    console.error('Error toggling course status:', error)
    toast.error(error.response?.data?.message || 'Failed to update course status')
  }
}

// Handle course form submission
const handleCourseFormSubmit = formData => {
  if (editCourse.value) {
    editCourseData(formData, editCourse.value.id)
  } else {
    addNewCourse(formData)
  }
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
        <!-- Course -->
        <template #[`item.course`]="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              size="48"
              rounded
              :image="item.cover_image || item.thumbnail || ''"
              class="bg-light-primary"
            >
              <VIcon
                v-if="!item.cover_image && !item.thumbnail"
                icon="tabler-book"
                size="24"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <h6 class="text-base">
                {{ item.title }}
              </h6>
              <div class="text-sm text-medium-emphasis">
                {{ item.category?.name || 
                  (item.course_category_id && availableCategories.find(c => c.id === item.course_category_id)?.name) || 
                  'No Category' }}
              </div>
            </div>
          </div>
        </template>

        <!-- 👉 Price -->
        <template #[`item.price`]="{ item }">
          <div class="text-body-1 text-high-emphasis">
            {{ formatPrice(item.price) }}
          </div>
          <div class="text-sm text-medium-emphasis">
            {{ item.subscription_type === 'monthly' ? 'Monthly' : 'One-time' }}
          </div>
        </template>

        <!-- Levels -->
        <template #[`item.levels`]="{ item }">
          <div class="text-body-1 text-high-emphasis">
            {{ item.levels?.length || 0 }}
          </div>
        </template>

        <!-- Subscriptions -->
        <template #[`item.subscriptions`]="{ item }">
          <div class="text-body-1 text-high-emphasis">
            {{ item.subscriptions_count || 0 }}
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

        <!-- Actions -->
        <template #[`item.actions`]="{ item }">
          <IconBtn @click="deleteCourse(item.id)">
            <VIcon icon="tabler-trash" />
          </IconBtn>

          <IconBtn @click="showEditCourseDialog(item)">
            <VIcon icon="tabler-edit" />
          </IconBtn>

          <IconBtn @click="toggleCourseStatus(item)">
            <VIcon :icon="item.status === 'active' ? 'tabler-eye-off' : 'tabler-eye'" />
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
    <CourseEditDialog
      v-model:is-dialog-visible="isAddNewCourseDialogVisible"
      :course-data="editCourse"
      :categories="categories"
      @submit="handleCourseFormSubmit"
    />
  </section>
</template>
