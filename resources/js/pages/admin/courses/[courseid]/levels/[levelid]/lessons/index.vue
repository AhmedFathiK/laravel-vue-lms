<script setup>
import AddEditLessonDialog from '@/components/dialogs/AddEditLessonDialog.vue'
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import api from '@/utils/api'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'lessons',
  },
})

const router = useRouter()
const toast = useToast()
const route = useRoute()

// Route params
const courseId = computed(() => route.params.courseid)
const levelId = computed(() => route.params.levelid)

// Page state - simple single state
const pageState = ref('loading') // loading, ready, error
const course = ref(null)
const level = ref(null)
const lessons = ref([])

// Dialog state
const isDialogVisible = ref(false)
const editingLesson = ref(null)
const isPasswordDialogVisible = ref(false)
const lessonToDelete = ref(null)

// Pagination & sorting
const itemsPerPage = ref(10)
const page = ref(1)
const totalItems = ref(0)
const sortBy = ref('id')
const orderBy = ref('asc')

// Headers for data table
const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'Image', key: 'thumbnail', sortable: false, width: '100px' },
  { title: 'Title', key: 'title' },
  { title: 'Video', key: 'video', sortable: false, width: '80px' },
  { title: 'Slides', key: 'slidesCount', width: '80px' },
  { title: 'Order', key: 'sortOrder', width: '80px' },
  { title: 'Free Access', key: 'isFree', width: '100px' },
  { title: 'Status', key: 'status', width: '100px' },
  { title: 'Actions', key: 'actions', sortable: false, width: '170px' },
]

// Load all data at once
const loadData = async () => {
  pageState.value = 'loading'
  
  if (!courseId.value || !levelId.value) {
    pageState.value = 'error'
    
    return
  }
  
  try {
    // Load everything in parallel
    const [courseResponse, levelResponse, lessonsResponse] = await Promise.all([
      api.get(`/admin/courses/${courseId.value}`),
      api.get(`/admin/courses/${courseId.value}/levels/${levelId.value}`),
      api.get(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons`, {
        params: {
          sortBy: sortBy.value || undefined,
          orderBy: orderBy.value || undefined,
        },
      }),
    ])
    
    // Debug logging
    console.log('API Responses:', { 
      course: courseResponse, 
      level: levelResponse, 
      lessons: lessonsResponse, 
    })
    
    // Process data
    course.value = courseResponse
    level.value = levelResponse
    
    // Process lessons data - handle different response formats
    if (Array.isArray(lessonsResponse)) {
      // Direct array of lessons
      lessons.value = lessonsResponse.map(lesson => ({
        ...lesson,

        // Ensure title is a string
        title: lesson.title ? String(lesson.title) : '',

        // Ensure slides is an array
        slides: Array.isArray(lesson.slides) ? lesson.slides : [],
      }))
      totalItems.value = lessonsResponse.length
    } else if (lessonsResponse && typeof lessonsResponse === 'object') {
      if (Array.isArray(lessonsResponse.data)) {
        // Paginated data
        lessons.value = lessonsResponse.data.map(lesson => ({
          ...lesson,

          // Ensure title is a string
          title: lesson.title ? String(lesson.title) : '',

          // Ensure slides is an array
          slides: Array.isArray(lesson.slides) ? lesson.slides : [],
        }))
        totalItems.value = lessonsResponse.total || lessonsResponse.data.length
      } else {
        // Single object
        lessons.value = [
          {
            ...lessonsResponse,

            // Ensure title is a string
            title: lessonsResponse.title ? String(lessonsResponse.title) : '',

            // Ensure slides is an array
            slides: Array.isArray(lessonsResponse.slides) ? lessonsResponse.slides : [],
          },
        ]
        totalItems.value = 1
      }
    } else {
      // No lessons or empty response
      lessons.value = []
      totalItems.value = 0
    }
    
    pageState.value = 'ready'
  } catch (error) {
    console.error('Error loading data:', error)
    pageState.value = 'error'
    toast.error('Failed to load data')
  }
}

// Refresh only lessons data (for sorting, pagination)
const refreshLessons = async () => {
  if (!levelId.value) return
  
  try {
    const response = await api.get(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons`, {
      params: {
        sortBy: sortBy.value || undefined,
        orderBy: orderBy.value || undefined,
      },
    })
    
    // Process lessons data - handle different response formats
    if (Array.isArray(response)) {
      // Direct array of lessons
      lessons.value = response.map(lesson => ({
        ...lesson,

        // Ensure title is a string
        title: lesson.title ? String(lesson.title) : '',

        // Ensure slides is an array
        slides: Array.isArray(lesson.slides) ? lesson.slides : [],
      }))
      totalItems.value = response.length
    } else if (response && typeof response === 'object') {
      if (Array.isArray(response.data)) {
        // Paginated data
        lessons.value = response.data.map(lesson => ({
          ...lesson,

          // Ensure title is a string
          title: lesson.title ? String(lesson.title) : '',

          // Ensure slides is an array
          slides: Array.isArray(lesson.slides) ? lesson.slides : [],
        }))
        totalItems.value = response.total || response.data.length
      } else {
        // Single object
        lessons.value = [
          {
            ...response,

            // Ensure title is a string
            title: response.title ? String(response.title) : '',

            // Ensure slides is an array
            slides: Array.isArray(response.slides) ? response.slides : [],
          },
        ]
        totalItems.value = 1
      }
    } else {
      // No lessons or empty response
      lessons.value = []
      totalItems.value = 0
    }
  } catch (error) {
    console.error('Error refreshing lessons:', error)
    toast.error('Failed to refresh lessons')
  }
}

// Handle data table options
const handleOptionsChange = options => {
  // Update sort settings
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key
    orderBy.value = options.sortBy[0].order === 'desc' ? 'desc' : 'asc'
  } else {
    sortBy.value = null
    orderBy.value = null
  }
  
  // Update pagination
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  
  // Refresh data
  refreshLessons()
}

// Dialog management
const openAddDialog = () => {
  editingLesson.value = null
  isDialogVisible.value = true
}

const openEditDialog = lesson => {
  editingLesson.value = { ...lesson }
  isDialogVisible.value = true
}

// Delete lesson
const confirmDeleteLesson = lesson => {
  lessonToDelete.value = lesson
  isPasswordDialogVisible.value = true
}

const handlePasswordConfirm = async result => {
  if (!result.confirmed || !lessonToDelete.value) return
  
  try {
    await api.delete(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons/${lessonToDelete.value.id}`)
    toast.success('Lesson deleted successfully')
    refreshLessons()
  } catch (error) {
    console.error('Error deleting lesson:', error)
    toast.error('Failed to delete lesson')
  } finally {
    lessonToDelete.value = null
  }
}

// Navigation
const navigateToSlides = lessonId => {
  router.push(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons/${lessonId}/slides`)
}

// Initialize on mount - with slight delay to prevent rendering loops
onMounted(() => {
  setTimeout(() => {
    loadData()
  }, 50)
})
</script>

<template>
  <section>
    <!-- Breadcrumb Navigation -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Courses', to: '/admin/courses' },
        { title: course ? course.title : 'Course', to: `/admin/courses/${courseId}/levels` },
        { title: level ? level.title : 'Level', to: `/admin/courses/${courseId}/levels` },
        { title: 'Lessons', disabled: true }
      ]"
      class="mb-4"
    />
    
    <!-- Loading State -->
    <VCard
      v-if="pageState === 'loading'"
      class="text-center py-8"
    >
      <VCardText>
        <VProgressCircular
          indeterminate
          color="primary"
          size="48"
        />
        <div class="mt-4 text-h6">
          Loading lesson data...
        </div>
      </VCardText>
    </VCard>
    
    <!-- Error State -->
    <VCard
      v-else-if="pageState === 'error' || !level"
      class="text-center py-8"
    >
      <VCardText>
        <VIcon
          icon="tabler-alert-circle"
          color="error"
          size="large"
        />
        <div class="text-h6 mt-4">
          Level Not Found
        </div>
        <div class="mt-2">
          The requested level could not be found or you don't have permission to view it.
        </div>
        <div class="mt-4">
          <VBtn
            variant="text"
            color="primary"
            @click="router.push(`/admin/courses/${courseId}/levels`)"
          >
            ← Back to Levels
          </VBtn>
        </div>
      </VCardText>
    </VCard>
    
    <!-- Success State -->
    <VCard v-else-if="pageState === 'ready' && level">
      <VCardText class="d-flex justify-space-between align-center">
        <h2>Lessons for {{ level.title }}</h2>
        <VBtn 
          color="primary" 
          prepend-icon="tabler-plus"
          @click="openAddDialog"
        >
          Add Lesson
        </VBtn>
      </VCardText>

      <VCardText>
        <VDataTable
          :headers="headers"
          :items="lessons"
          :loading="pageState === 'loading'"
          class="text-no-wrap"
        >
          <!-- Thumbnail -->
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

          <!-- Title -->
          <template #[`item.title`]="{ item }">
            <div class="d-flex align-center gap-x-2">
              <h6 class="text-h6 font-weight-medium">
                <RouterLink
                  :to="{ 
                    name: 'admin-courses-courseid-levels-levelid-lessons-lessonid-slides', 
                    params: { courseid: courseId, levelid: levelId, lessonid: item.id }
                  }"
                  class="text-primary text-decoration-none"
                >
                  {{ item.title }}
                </RouterLink>
              </h6>
            </div>
            <div class="text-body-2 text-medium-emphasis text-truncate">
              {{ item.description }}
            </div>
          </template>
          
          <!-- Video Column -->
          <template #[`item.video`]="{ item }">
            <VIcon
              v-if="item.videoUrl"
              icon="tabler-video"
              color="success"
            />
            <VIcon
              v-else
              icon="tabler-video-off"
              color="secondary"
            />
          </template>
          
          <!-- Slides Count Column -->
          <template #[`item.slidesCount`]="{ item }">
            <VChip
              :color="item.slides && Array.isArray(item.slides) && item.slides.length > 0 ? 'primary' : 'secondary'"
              size="small"
              label
            >
              {{ item.slides && Array.isArray(item.slides) ? item.slides.length : 0 }}
            </VChip>
          </template>
          
          <!-- Free Access Column -->
          <template #[`item.isFree`]="{ item }">
            <VChip
              :color="item.isFree ? 'success' : 'error'"
              size="small"
              label
            >
              {{ item.isFree ? 'Free' : 'Premium' }}
            </VChip>
          </template>
          
          <!-- Status Column -->
          <template #[`item.status`]="{ item }">
            <VChip
              :color="item.status === 'published' ? 'success' : (item.status === 'draft' ? 'warning' : 'error')"
              size="small"
              label
              class="text-capitalize"
            >
              {{ item.status || 'Unknown' }}
            </VChip>
          </template>
          
          <!-- Actions Column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex align-center">
              <IconBtn
                color="error"
                @click="confirmDeleteLesson(item)"
              >
                <VIcon icon="tabler-trash" />
                <VTooltip activator="parent">
                  Delete
                </VTooltip>
              </IconBtn>
              
              <IconBtn
                color="warning"
                @click="openEditDialog(item)"
              >
                <VIcon icon="tabler-edit" />
                <VTooltip activator="parent">
                  Edit
                </VTooltip>
              </IconBtn>
              
              <IconBtn
                color="info"
                @click="navigateToSlides(item.id)"
              >
                <VIcon icon="tabler-slideshow" />
                <VTooltip activator="parent">
                  Manage Slides
                </VTooltip>
              </IconBtn>
            </div>
          </template>
          
          <!-- Empty state -->
          <template #no-data>
            <div class="text-center pa-4">
              <VIcon
                icon="tabler-book"
                size="48"
                color="primary"
                class="mb-3"
              />
              <div class="text-h6">
                No Lessons Found
              </div>
              <p class="mt-2">
                Get started by adding your first lesson to this level.
              </p>
              <VBtn
                color="primary"
                class="mt-2"
                @click="openAddDialog"
              >
                Add Lesson
              </VBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Dialogs -->
    <AddEditLessonDialog
      v-model:is-dialog-visible="isDialogVisible"
      :lesson-data="editingLesson"
      :level-id="levelId"
      :course-id="courseId"
      @refresh="refreshLessons"
    />
    
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isPasswordDialogVisible"
      confirmation-question="Are you sure you want to delete this lesson? All associated slides and content will also be deleted."
      confirm-title="Lesson Deleted"
      confirm-msg="The lesson has been deleted successfully."
      cancel-title="Deletion Cancelled"
      cancel-msg="The lesson was not deleted."
      @confirm="handlePasswordConfirm"
    />
  </section>
</template> 
