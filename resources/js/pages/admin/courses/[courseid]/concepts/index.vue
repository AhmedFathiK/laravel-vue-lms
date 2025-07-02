<script setup>
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import ConceptEditDialog from '@/components/dialogs/ConceptEditDialog.vue'
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
const course = ref(null)
const concepts = ref([])
const isDialogVisible = ref(false)
const editingConcept = ref(null)

// Password confirmation dialog
const isPasswordDialogVisible = ref(false)
const conceptToDelete = ref(null)

// Route params
const courseId = computed(() => route.params.courseid)

// Pagination
const page = ref(1)
const perPage = ref(10)
const totalItems = ref(0)
const totalPages = computed(() => Math.ceil(totalItems.value / perPage.value))

// Sorting
const sortBy = ref('created_at')
const sortDesc = ref(true)

// Search
const searchQuery = ref('')

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

// Fetch concepts
const fetchConcepts = async () => {
  if (!courseId.value) return
  
  isLoading.value = true
  try {
    // Build query parameters
    const params = new URLSearchParams()
    params.append('page', page.value)
    params.append('per_page', perPage.value)
    params.append('sort_by', sortBy.value)
    params.append('sort_desc', sortDesc.value ? '1' : '0')
    
    if (searchQuery.value) {
      params.append('search', searchQuery.value)
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
    isLoading.value = false
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
    await api.delete(`/admin/concepts/${conceptToDelete.value.id}`)
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

// Watch for search changes
watch(searchQuery, () => {
  page.value = 1
  fetchConcepts()
})

// Watch for locale changes and refresh data
watch(() => locale.value, () => {
  fetchConcepts()
})

// Initialize
onMounted(() => {
  fetchCourse()
  fetchConcepts()
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
        <p class="text-h6 mb-4">Course not found</p>
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
        <!-- Search -->
        <VRow class="mb-4">
          <VCol cols="12" sm="6" md="4">
            <VTextField
              v-model="searchQuery"
              label="Search"
              density="compact"
              prepend-inner-icon="tabler-search"
              single-line
              hide-details
              variant="outlined"
            />
          </VCol>
        </VRow>
        
        <!-- Concepts Table -->
        <VTable class="text-no-wrap">
          <thead>
            <tr>
              <th>Title</th>
              <th>Type</th>
              <th>Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="concepts.length === 0">
              <td colspan="4" class="text-center pa-4">
                No concepts found. <VBtn variant="text" color="primary" @click="openAddDialog">Add a concept</VBtn>
              </td>
            </tr>
            <tr v-for="concept in concepts" :key="concept.id">
              <td>{{ concept.title }}</td>
              <td>{{ concept.type }}</td>
              <td>
                <VChip
                  :color="concept.status === 'active' ? 'success' : 'secondary'"
                  size="small"
                  label
                >
                  {{ concept.status }}
                </VChip>
              </td>
              <td class="text-center">
                <VBtn
                  icon
                  variant="text"
                  color="default"
                  size="small"
                  @click="openEditDialog(concept)"
                >
                  <VIcon size="20" icon="tabler-edit" />
                </VBtn>
                
                <VBtn
                  icon
                  variant="text"
                  color="default"
                  size="small"
                  @click="confirmDeleteConcept(concept)"
                >
                  <VIcon size="20" icon="tabler-trash" />
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
        
        <!-- Pagination -->
        <div class="d-flex align-center justify-space-between mt-4">
          <div>
            Showing {{ concepts.length }} of {{ totalItems }} concepts
          </div>
          <VPagination
            v-model="page"
            :length="totalPages"
            @update:model-value="fetchConcepts"
          />
        </div>
        
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
    <ConceptEditDialog
      v-model:is-dialog-visible="isDialogVisible"
      :concept="editingConcept"
      :course-id="courseId"
      @concept-saved="handleConceptSaved"
    />

    <!-- Deletion Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isPasswordDialogVisible"
      @confirm="handleDeleteConfirm"
    />
  </section>
</template>