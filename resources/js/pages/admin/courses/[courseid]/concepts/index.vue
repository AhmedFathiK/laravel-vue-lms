<script setup>
import AddEditConceptDialog from '@/components/dialogs/AddEditConceptDialog.vue'
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'

const router = useRouter()
const toast = useToast()
const route = useRoute()
const { locale } = useI18n()

// Removed locale reference
const isLoading = ref(false)
const isTableLoading = ref(false)
const course = ref(null)
const concepts = ref([])
const conceptCategories = ref([])
const isDialogVisible = ref(false)
const editingConcept = ref(null)

// Headers
const headers = [
  { title: 'Title', key: 'title' },
  { title: 'Category', key: 'category.title' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
]

// Password confirmation dialog
const isPasswordDialogVisible = ref(false)
const conceptToDelete = ref(null)

// Route params
const courseId = computed(() => route.params.courseid)

// Pagination
const page = ref(1)
const perPage = ref(10)
const totalItems = ref(0)

// Sorting
const sortBy = ref('title')
const sortDesc = ref(false)

// Search
const searchQuery = ref('')
const categoryFilter = ref(null)

const debouncedFetch = useDebounceFn(() => {
  page.value = 1
  fetchConcepts()
}, 500)

watch(searchQuery, () => {
  debouncedFetch()
})

watch(categoryFilter, () => {
  page.value = 1
  fetchConcepts()
})

// Fetch course details
const fetchCourse = async () => {
  if (!courseId.value) return
  
  isLoading.value = true
  try {
    const response = await api.get(`/admin/courses/${courseId.value}`)
    
    // The API returns the course directly, not wrapped in a data property
    if (response && typeof response === 'object') {
      course.value = response.course || response
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

// Fetch concept categories
const fetchConceptCategories = async () => {
  if (!courseId.value) return
  
  try {
    const response = await api.get(`/admin/courses/${courseId.value}/concept-categories`)

    conceptCategories.value = response.map(cat => ({
      title: cat.title,
      value: cat.id,
    }))
  } catch (error) {
    console.error('Error fetching concept categories:', error)
  }
}

// Fetch concepts
const fetchConcepts = async (options = {}) => {
  if (!courseId.value) return
  
  // Update sorting and pagination if options are provided
  if (options.page) page.value = options.page
  if (options.itemsPerPage) perPage.value = options.itemsPerPage
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key
    sortDesc.value = options.sortBy[0].order === 'desc'
  }

  isTableLoading.value = true
  try {
    // Build query parameters
    const params = new URLSearchParams()

    params.append('page', page.value)
    params.append('perPage', perPage.value)
    params.append('sortBy', sortBy.value)
    params.append('sortDesc', sortDesc.value ? '1' : '0')
    
    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }

    if (categoryFilter.value) {
      params.append('categoryId', categoryFilter.value)
    }
    
    // Make API request
    const response = await api.get(`/admin/courses/${courseId.value}/concepts?${params.toString()}`)
    
    // Handle different response formats
    if (response && typeof response === 'object') {
      if (Array.isArray(response)) {
        // Direct array response
        concepts.value = response
        totalItems.value = response.length
      } else if (response.data && Array.isArray(response.data)) {
        // Paginated response
        concepts.value = response.data
        totalItems.value = response.total || response.data.length
      } else {
        // Single object response
        concepts.value = [response]
        totalItems.value = 1
      }
    } else {
      console.warn('Unexpected API response format:', response)
      concepts.value = []
      totalItems.value = 0
    }
  } catch (error) {
    console.error('Error fetching concepts:', error)
    concepts.value = []
    totalItems.value = 0
  } finally {
    isTableLoading.value = false
  }
}

// Open dialog for adding new concept
const openAddDialog = () => {
  editingConcept.value = null
  isDialogVisible.value = true
}

// Open dialog for editing concept
const openEditDialog = concept => {
  editingConcept.value = { ...concept }
  isDialogVisible.value = true
}

// Confirm deletion of concept
const confirmDeleteConcept = concept => {
  conceptToDelete.value = concept
  isPasswordDialogVisible.value = true
}

// Delete concept after confirmation
const handleDeleteConfirm = async () => {
  if (!conceptToDelete.value) return
  
  try {
    await api.delete(`/admin/courses/${courseId.value}/concepts/${conceptToDelete.value.id}`)
    toast.success('Concept deleted successfully')
    fetchConcepts()
  } catch (error) {
    console.error('Error deleting concept:', error)
    toast.error(error.response?.data?.message || 'Failed to delete concept')
  } finally {
    conceptToDelete.value = null
    isPasswordDialogVisible.value = false
  }
}

// Refresh concepts after dialog submission
const handleConceptSaved = () => {
  fetchConcepts()
}



// Watch for locale changes and refresh data
watch(() => locale.value, () => {
  fetchConcepts()
  fetchConceptCategories()
})

// Initialize
onMounted(() => {
  fetchCourse()
  fetchConceptCategories()
})
</script>

<template>
  <section>
    <!-- Breadcrumb Navigation -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Courses', to: '/admin/courses' },
        { title: course?.title || 'Course', disabled: true },
        { title: 'Concepts', disabled: true },
      ]"
      class="mb-4"
    />
    
    <VCard v-if="isLoading">
      <VCardText class="d-flex justify-center align-center pa-10">
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </VCardText>
    </VCard>
    
    <VCard v-else-if="!course">
      <VCardText class="text-center pa-10">
        <p class="text-h6 mb-4">
          Course not found
        </p>
        <VBtn
          color="primary"
          variant="text"
          @click="router.push('/admin/courses')"
        >
          ← Back to Course List
        </VBtn>
      </VCardText>
    </VCard>
    
    <VCard v-else>
      <VCardItem>
        <VCardTitle>{{ course.title }} - Concepts</VCardTitle>
        
        <template #append>
          <div class="d-flex gap-4">
            <VBtn
              color="secondary"
              variant="tonal"
              prepend-icon="tabler-category"
              :to="`/admin/courses/${courseId}/concepts/categories`"
            >
              Manage Categories
            </VBtn>
            <VBtn
              prepend-icon="tabler-plus"
              @click="openAddDialog"
            >
              Add Concept
            </VBtn>
          </div>
        </template>
      </VCardItem>
      
      <VDivider />
      
      <VCardText>
        <!-- Search & Filter -->
        <VRow class="mb-4">
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <VTextField
              v-model="searchQuery"
              placeholder="Search concepts..."
              density="compact"
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
              variant="outlined"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <VSelect
              v-model="categoryFilter"
              :items="conceptCategories"
              label="Filter by Category"
              density="compact"
              clearable
              hide-details
              variant="outlined"
            />
          </VCol>
        </VRow>
        
        <!-- Concepts Table -->
        <VDataTableServer
          v-model:page="page"
          v-model:items-per-page="perPage"
          :headers="headers"
          :items="concepts"
          :items-length="totalItems"
          :loading="isTableLoading"
          class="text-no-wrap"
          @update:options="fetchConcepts"
        >
          <!-- Category -->
          <template #[`item.category.title`]="{ item }">
            {{ item.category?.title || 'None' }}
          </template>

          <!-- Actions -->
          <template #[`item.actions`]="{ item }">
            <VBtn
              icon
              variant="text"
              color="default"
              size="small"
              @click="openEditDialog(item)"
            >
              <VIcon
                size="20"
                icon="tabler-edit"
              />
            </VBtn>
            
            <VBtn
              icon
              variant="text"
              color="default"
              size="small"
              @click="confirmDeleteConcept(item)"
            >
              <VIcon
                size="20"
                icon="tabler-trash"
              />
            </VBtn>
          </template>

          <!-- No Data -->
          <template #no-data>
            <div class="text-center pa-4">
              No concepts found. <VBtn
                variant="text"
                color="primary"
                @click="openAddDialog"
              >
                Add a concept
              </VBtn>
            </div>
          </template>
        </VDataTableServer>
        
        <!-- Back Button -->
        <div class="mt-6">
          <VBtn
            color="primary"
            variant="text"
            @click="router.push('/admin/courses')"
          >
            ← Back to Course List
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Concept Edit Dialog -->
    <AddEditConceptDialog
      v-model:is-dialog-visible="isDialogVisible"
      :data="editingConcept"
      :dialog-mode="editingConcept ? 'edit' : 'add'"
      :course-id="courseId"
      @saved="handleConceptSaved"
    />

    <!-- Deletion Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isPasswordDialogVisible"
      confirmation-question="Are you sure you want to delete this concept? This action cannot be undone."
      @confirm="handleDeleteConfirm"
    />
  </section>
</template>
