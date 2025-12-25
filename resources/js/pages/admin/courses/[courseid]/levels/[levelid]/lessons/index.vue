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
const isReorderMode = ref(false)
const isSavingOrder = ref(false)

// Pagination & sorting
const itemsPerPage = ref(10)
const page = ref(1)
const totalItems = ref(0)
const sortBy = ref('id')
const orderBy = ref('asc')

// Headers for data table
const headers = computed(() => {
  const h = [
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

  if (isReorderMode.value) {
    h.unshift({ title: 'Move', key: 'move', sortable: false, width: '50px' })
  }

  return h
})

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

// Reordering logic
const toggleReorderMode = async () => {
  if (isReorderMode.value) {
    isReorderMode.value = false
    refreshLessons()
  } else {
    // When entering reorder mode, load all lessons without pagination
    try {
      pageState.value = 'loading'

      const response = await api.get(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons`, {
        params: {
          sortBy: 'sort_order',
          orderBy: 'asc',
          perPage: 1000, // Load all
        },
      })
      
      if (Array.isArray(response)) {
        lessons.value = response
      } else if (response && response.data) {
        lessons.value = response.data
      }
      
      isReorderMode.value = true
      pageState.value = 'ready'
    } catch (error) {
      console.error('Error entering reorder mode:', error)
      toast.error('Failed to load lessons for reordering')
      pageState.value = 'ready'
    }
  }
}

const saveOrder = async () => {
  isSavingOrder.value = true
  try {
    await api.post(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons/order`, {
      order: lessons.value.map(l => l.id),
    })
    
    toast.success('Lessons order updated successfully')
    isReorderMode.value = false
    refreshLessons()
  } catch (error) {
    console.error('Error saving order:', error)
    toast.error('Failed to update lessons order')
  } finally {
    isSavingOrder.value = false
  }
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
        { title: course ? course.title : 'Course', disabled: true },
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
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <h2>Lessons for {{ level.title }}</h2>
        <div class="d-flex gap-2">
          <template v-if="isReorderMode">
            <VBtn
              color="success"
              :loading="isSavingOrder"
              @click="saveOrder"
            >
              Save Order
            </VBtn>
            <VBtn
              variant="outlined"
              color="secondary"
              @click="toggleReorderMode"
            >
              Cancel
            </VBtn>
          </template>
          <template v-else>
            <VBtn
              color="secondary"
              variant="tonal"
              prepend-icon="tabler-arrows-sort"
              @click="toggleReorderMode"
            >
              Reorder
            </VBtn>
            <VBtn 
              color="primary" 
              prepend-icon="tabler-plus"
              @click="openAddDialog"
            >
              Add Lesson
            </VBtn>
          </template>
        </div>
      </VCardText>

      <VCardText v-if="isReorderMode">
        <div class="reorder-list-container border rounded">
          <SlickList
            v-model:list="lessons"
            use-drag-handle
            axis="y"
            class="list-group"
            helper-class="slick-helper"
          >
            <SlickItem
              v-for="(lesson, index) in lessons"
              :key="lesson.id"
              :index="index"
              class="list-group-item d-flex align-center pa-4 border-bottom"
            >
              <DragHandle class="me-4" />
              <VAvatar
                size="40"
                class="me-4"
                :color="lesson.thumbnail ? '' : 'primary'"
                :variant="!lesson.thumbnail ? 'tonal' : undefined"
              >
                <VImg
                  v-if="lesson.thumbnail"
                  :src="lesson.thumbnail"
                  cover
                />
                <VIcon
                  v-else
                  icon="tabler-camera-off"
                  size="20"
                />
              </VAvatar>
              <div class="flex-grow-1">
                <div class="text-h6">
                  {{ lesson.title }}
                </div>
                <div class="text-body-2 text-medium-emphasis">
                  {{ lesson.description }}
                </div>
              </div>
              <VChip
                size="small"
                label
                class="ms-4"
              >
                Order: {{ index + 1 }}
              </VChip>
            </SlickItem>
          </SlickList>
        </div>
      </VCardText>

      <VCardText v-else>
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
      :dialog-mode="editingLesson ? 'edit' : 'add'"
      :data="editingLesson"
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

<style scoped>
.reorder-list-container {
  max-height: 60vh;
  overflow-y: auto;
}

.list-group {
  padding: 0;
  margin: 0;
  list-style: none;
}

.list-group-item {
  background-color: rgb(var(--v-theme-surface));
  transition: background-color 0.2s;
}

.list-group-item:last-child {
  border-bottom: none !important;
}

.list-group-item:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

.slick-helper {
  z-index: 9999;
  box-shadow: 0 5px 15px rgba(0,0,0,0.3);
  background-color: rgb(var(--v-theme-surface));
  width: 100%;
  display: flex;
  align-items: center;
  padding: 1rem;
  border-radius: 4px;
}
</style> 
