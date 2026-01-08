<script setup>
import AddEditLevelDialog from '@/components/dialogs/AddEditLevelDialog.vue'
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'levels',
  },
})

const router = useRouter()
const toast = useToast()
const route = useRoute()
const { locale } = useI18n()
const isLoading = ref(false)
const course = ref(null)
const levels = ref([])
const isDialogVisible = ref(false)
const editingLevel = ref(null)
const isReorderMode = ref(false)
const isSavingOrder = ref(false)

// Password confirmation dialog
const isPasswordDialogVisible = ref(false)
const levelToDelete = ref(null)

// Pagination
const itemsPerPage = ref(10)
const page = ref(1)
const totalItems = ref(0)
const sortBy = ref('id')
const orderBy = ref('asc')

// Get course ID from route parameter
const courseId = computed(() => route.params.courseid)

// Headers for data table
const headers = computed(() => {
  const h = [
    { title: 'ID', key: 'id', width: '80px' },
    { title: 'Title', key: 'title' },
    { title: 'Description', key: 'description', sortable: false },
    { title: 'Order', key: 'sortOrder', width: '80px' },
    { title: 'Free Access', key: 'isFree', width: '120px' },
    { title: 'Status', key: 'status', width: '120px' },
    { title: 'Actions', key: 'actions', sortable: false, width: '170px' },
  ]

  if (isReorderMode.value) {
    h.unshift({ title: 'Move', key: 'move', sortable: false, width: '50px' })
  }

  return h
})

// Fetch course details
const fetchCourse = async () => {
  if (!courseId.value) return
  
  isLoading.value = true
  try {
    const response = await api.get(`/admin/courses/${courseId.value}`)
    
    // The API returns the course directly, not wrapped in a data property
    if (response && typeof response === 'object') {
      course.value = response
    } else {
      toast.error('Course not found')
      course.value = null
    }
  } catch (error) {
    console.error('Error fetching course:', error)
    course.value = null
  } finally {
    isLoading.value = false
  }
}

// Fetch levels for the course
const fetchLevels = async () => {
  if (!courseId.value) return
  
  isLoading.value = true
  try {
    // Add pagination and sorting parameters
    const params = {
      page: page.value,
      perPage: itemsPerPage.value,
      sortBy: sortBy.value || undefined,
      orderBy: orderBy.value || undefined,
    }
    
    const response = await api.get(`/admin/courses/${courseId.value}/levels`, { params })
    
    // Handle different response structures (paginated or array)
    if (response && typeof response === 'object') {
      if (Array.isArray(response)) {
        // If API returns an array directly
        levels.value = response
        totalItems.value = response.length
      } else if (response.data) {
        // If API returns paginated data
        levels.value = response.data
        totalItems.value = response.total || response.data.length
      } else {
        // Fallback
        levels.value = []
        totalItems.value = 0
      }
    } else {
      levels.value = []
      totalItems.value = 0
    }
    
    if (!levels.value.length) {
      console.log('No levels found for this course')
    }
  } catch (error) {
    console.error('Error fetching levels:', error)
    levels.value = []
    totalItems.value = 0
  } finally {
    isLoading.value = false
  }
}

// Handle data table options change (pagination, sorting)
const handleOptionsChange = options => {
  if (options.page) {
    page.value = options.page
  }
  
  if (options.itemsPerPage) {
    itemsPerPage.value = options.itemsPerPage
  }
  
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key
    orderBy.value = options.sortBy[0].order === 'desc' ? 'desc' : 'asc'
  }else{
    sortBy.value = null
    orderBy.value = null
  }
  
  fetchLevels()
}

// Delete level with password confirmation
const confirmDeleteLevel = level => {
  levelToDelete.value = level
  isPasswordDialogVisible.value = true
}

const handlePasswordConfirm = async result => {
  if (!result.confirmed || !levelToDelete.value) return
  
  // Here you would normally verify the password with the backend
  // For this example, we'll just proceed with the deletion
  
  try {
    await api.delete(`/admin/courses/${courseId.value}/levels/${levelToDelete.value.id}`)
    toast.success('Level deleted successfully')
    fetchLevels()
  } catch (error) {
    console.error('Error deleting level:', error)
    toast.error('Failed to delete level')
  } finally {
    levelToDelete.value = null
  }
}

// Open dialog for adding new level
const openAddDialog = () => {
  editingLevel.value = null
  isDialogVisible.value = true
}

// Open dialog for editing level
const openEditDialog = level => {
  editingLevel.value = { ...level }
  isDialogVisible.value = true
}

// Format boolean as Yes/No
const formatBoolean = value => {
  return value ? 'Yes' : 'No'
}

// Resolve status variant for chip color
const resolveStatusVariant = status => {
  if (status === 'published') return 'success'
  if (status === 'archived') return 'error'
  
  return 'warning' // draft
}

// Refresh data
const refreshData = () => {
  fetchLevels()
}

// Reordering logic
const toggleReorderMode = async () => {
  if (isReorderMode.value) {
    isReorderMode.value = false
    fetchLevels()
  } else {
    // When entering reorder mode, load all levels without pagination
    isLoading.value = true
    try {
      const response = await api.get(`/admin/courses/${courseId.value}/levels`, {
        params: {
          sortBy: 'sort_order',
          orderBy: 'asc',
          perPage: 1000, // Load all
        },
      })
      
      if (Array.isArray(response)) {
        levels.value = response
      } else if (response && response.data) {
        levels.value = response.data
      }
      
      isReorderMode.value = true
    } catch (error) {
      console.error('Error entering reorder mode:', error)
      toast.error('Failed to load levels for reordering')
    } finally {
      isLoading.value = false
    }
  }
}

const saveOrder = async () => {
  isSavingOrder.value = true
  try {
    await api.post(`/admin/courses/${courseId.value}/levels/order`, {
      order: levels.value.map(l => l.id),
    })
    
    toast.success('Levels order updated successfully')
    isReorderMode.value = false
    fetchLevels()
  } catch (error) {
    console.error('Error saving order:', error)
    toast.error('Failed to update levels order')
  } finally {
    isSavingOrder.value = false
  }
}

// Watch for locale changes and refresh data
watch(() => locale.value, () => {
  fetchCourse()
  fetchLevels()
})

onMounted(() => {
  fetchCourse()
  fetchLevels()
})
</script>

<template>
  <section>
    <!-- Breadcrumb Navigation -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Courses', to: '/admin/courses' },
        { title: course ? course.title : 'Course Details', disabled: true }
      ]"
      class="mb-4"
    />
    
    <VCard v-if="course">
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <h2>Levels for {{ course.title }}</h2>
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
              Add Level
            </VBtn>
          </template>
        </div>
      </VCardText>

      <VCardText v-if="isReorderMode">
        <div class="reorder-list-container border rounded">
          <SlickList
            v-model:list="levels"
            use-drag-handle
            axis="y"
            class="list-group"
            helper-class="slick-helper"
          >
            <SlickItem
              v-for="(level, index) in levels"
              :key="level.id"
              :index="index"
              class="list-group-item d-flex align-center pa-4 border-bottom"
            >
              <DragHandle class="me-4" />
              <div class="flex-grow-1">
                <div class="text-h6">
                  {{ level.title }}
                </div>
                <div class="text-body-2 text-medium-emphasis">
                  {{ level.description }}
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
          :items="levels"
          :loading="isLoading"
          :items-per-page="itemsPerPage"
          :page="page"
          :items-length="totalItems"
          class="elevation-1"
          @update:options="handleOptionsChange"
        >
          <!-- Title column -->
          <template #[`item.title`]="{ item }">
            <span class="font-weight-medium">{{ item.title }}</span>
          </template>
          
          <!-- Free access column -->
          <template #[`item.isFree`]="{ item }">
            <VChip
              :color="item.isFree ? 'success' : 'error'"
              size="small"
            >
              {{ formatBoolean(item.isFree) }}
            </VChip>
          </template>
          
          <!-- Status column -->
          <template #[`item.status`]="{ item }">
            <VChip
              :color="resolveStatusVariant(item.status)"
              size="small"
            >
              {{ item.status }}
            </VChip>
          </template>

          <!-- Actions column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex gap-2">
              <VBtn
                icon
                variant="text"
                color="primary"
                size="small"
                @click="openEditDialog(item)"
              >
                <VIcon icon="tabler-edit" />
                <VTooltip activator="parent">
                  Edit
                </VTooltip>
              </VBtn>
              <VBtn
                icon
                variant="text"
                color="error"
                size="small"
                @click="confirmDeleteLevel(item)"
              >
                <VIcon icon="tabler-trash" />
                <VTooltip activator="parent">
                  Delete
                </VTooltip>
              </VBtn>
              <VBtn
                icon
                variant="text"
                color="info"
                size="small"
                @click="router.push(`/admin/courses/${courseId}/levels/${item.id}/lessons`)"
              >
                <VIcon icon="tabler-book" />
                <VTooltip activator="parent">
                  Manage Lessons
                </VTooltip>
              </VBtn>
            </div>
          </template>
          
          <!-- No data display -->
          <template #no-data>
            <div class="text-center pa-4">
              <p class="text-subtitle-1">
                No levels found for this course
              </p>
              <VBtn 
                color="primary" 
                class="mt-4"
                @click="openAddDialog"
              >
                Create First Level
              </VBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <VCard
      v-else-if="isLoading"
      class="text-center py-8"
    >
      <VCardText>
        <VProgressCircular
          indeterminate
          color="primary"
        />
        <div class="mt-4">
          Loading course details...
        </div>
      </VCardText>
    </VCard>
    
    <VCard
      v-else
      class="text-center py-8"
    >
      <VCardText>
        <VIcon
          icon="tabler-alert-circle"
          color="error"
          size="large"
        />
        <div class="text-h6 mt-4">
          Course Not Found
        </div>
        <div class="mt-2">
          The requested course could not be found or you don't have permission to view it.
        </div>
        <div class="mt-2">
          <button @click="router.push('/admin/courses')">
            ← Back to Course List
          </button>
        </div>
      </VCardText>
    </VCard>

    <!-- Level Edit Dialog -->
    <AddEditLevelDialog
      v-model:is-dialog-visible="isDialogVisible"
      :data="editingLevel"
      :dialog-mode="editingLevel ? 'edit' : 'add'"
      :course-id="courseId"
      @refresh="refreshData"
    />
    
    <!-- Password Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isPasswordDialogVisible"
      confirmation-question="Are you sure you want to delete this level? All associated lessons will also be deleted."
      confirm-title="Level Deleted"
      confirm-msg="The level has been deleted successfully."
      cancel-title="Deletion Cancelled"
      cancel-msg="The level was not deleted."
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
