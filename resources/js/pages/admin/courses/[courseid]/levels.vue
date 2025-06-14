<script setup>
import LevelEditDialog from '@/components/dialogs/LevelEditDialog.vue'
import PasswordConfirmDialog from '@/components/dialogs/PasswordConfirmDialog.vue'
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'


const router = useRouter()
const toast = useToast()
const route = useRoute()
const { locale } = useI18n()
const isLoading = ref(false)
const course = ref(null)
const levels = ref([])
const isDialogVisible = ref(false)
const editingLevel = ref(null)

// Password confirmation dialog
const isPasswordDialogVisible = ref(false)
const levelToDelete = ref(null)

// Pagination
const itemsPerPage = ref(10)
const page = ref(1)
const totalItems = ref(0)
const sortBy = ref('sort_order')
const sortDesc = ref(false)

// Get course ID from route parameter
const courseId = computed(() => route.params.courseid)

// Headers for data table
const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Title', key: 'title' },
  { title: 'Description', key: 'description', sortable: false },
  { title: 'Order', key: 'sort_order' },
  { title: 'Free Access', key: 'is_free' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false },
]

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
      sortField: sortBy.value,
      sortDirection: sortDesc.value ? 'desc' : 'asc',
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
    sortDesc.value = options.sortBy[0].order === 'desc'
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
    await api.delete(`/admin/levels/${levelToDelete.value.id}`)
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
      <VCardText class="d-flex justify-space-between align-center">
        <h2>Levels for {{ course.title }}</h2>
        <VBtn 
          color="primary" 
          prepend-icon="tabler-plus"
          @click="openAddDialog"
        >
          Add Level
        </VBtn>
      </VCardText>

      <VCardText>
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
          <template #[`item.is_free`]="{ item }">
            <VChip
              :color="item.is_free ? 'success' : 'error'"
              size="small"
            >
              {{ formatBoolean(item.is_free) }}
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
              </VBtn>
              <VBtn
                icon
                variant="text"
                color="error"
                size="small"
                @click="confirmDeleteLevel(item)"
              >
                <VIcon icon="tabler-trash" />
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
    <LevelEditDialog
      v-model:is-dialog-visible="isDialogVisible"
      :level-data="editingLevel"
      :course-id="courseId"
      @refresh="refreshData"
    />
    
    <!-- Password Confirmation Dialog -->
    <PasswordConfirmDialog
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
