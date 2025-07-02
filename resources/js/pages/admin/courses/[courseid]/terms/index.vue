<script setup>
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import TermEditDialog from '@/components/dialogs/TermEditDialog.vue'
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
const terms = ref([])
const isDialogVisible = ref(false)
const editingTerm = ref(null)

// Password confirmation dialog
const isPasswordDialogVisible = ref(false)
const termToDelete = ref(null)

// Pagination
const itemsPerPage = ref(10)
const page = ref(1)
const totalItems = ref(0)
const sortBy = ref('term')
const sortDesc = ref(false)

// Search and filters
const searchQuery = ref('')

// Get course ID from route parameter
const courseId = computed(() => route.params.courseid)


// Headers for data table
const headers = [
  { title: 'ID', key: 'id', width: '80px' },
  { title: 'Term', key: 'term' },
  { title: 'Definition', key: 'definition', sortable: false },
  { title: 'Media Type', key: 'media_type', width: '120px' },
  { title: 'Example', key: 'has_example', width: '100px', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, width: '170px' },
]

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

// Fetch terms
const fetchTerms = async () => {
  if (!courseId.value) return
  
  try {
    isLoading.value = true
    
    // Build query params
    const params = new URLSearchParams({
      page: page.value,
      per_page: itemsPerPage.value,
      sort_field: sortBy.value,
      sort_direction: sortDesc.value ? 'desc' : 'asc',
    })
    
    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }
    
    const response = await api.get(`/admin/courses/${courseId.value}/terms?${params.toString()}`)

    terms.value = response.items || []
    console.log(terms.value)
    
    totalItems.value = response.total || 0
  } catch (error) {
    console.error('Error fetching terms:', error)
  } finally {
    isLoading.value = false
  }
}

// Handle table options change
const handleOptionsChange = options => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key
    sortDesc.value = options.sortBy[0].order === 'desc'
  } else {
    sortBy.value = 'term'
    sortDesc.value = false
  }
  
  fetchTerms()
}

// Open dialog for creating new term
const openAddDialog = () => {
  editingTerm.value = null
  isDialogVisible.value = true
}

// Open dialog for editing a term
const openEditDialog = term => {
  editingTerm.value = term
  isDialogVisible.value = true
}

// Delete a term
const deleteTerm = term => {
  termToDelete.value = term
  isPasswordDialogVisible.value = true
}

// Confirm term deletion
const confirmDelete = async () => {
  if (!termToDelete.value) return
  
  try {
    await api.delete(`/admin/terms/${termToDelete.value.id}`)
    toast.success('Term deleted successfully')
    fetchTerms()
  } catch (error) {
    console.error('Error deleting term:', error)
    toast.error('Failed to delete term')
  } finally {
    isPasswordDialogVisible.value = false
    termToDelete.value = null
  }
}

// Removed getLocalizedTerm function

// Format media type for display
const formatMediaType = type => {
  if (!type) return 'None'
  
  const types = {
    'image': 'Image',
    'image_audio': 'Image with Audio',
    'video': 'Video',
  }
  
  return types[type] || type
}

// Check if term has an example
const hasExample = term => {
  return term.example && term.example.trim() !== ''
}

// Count examples
const getExamplesCount = term => {
  return term.examples ? term.examples.length : 0
}

// Refresh terms after dialog submission
const handleTermSaved = () => {
  fetchTerms()
}

// Watch for search changes
watch(searchQuery, () => {
  page.value = 1
  fetchTerms()
})

// Watch for locale changes and refresh data
watch(() => locale.value, () => {
  fetchCourse()
  fetchTerms()
})

onMounted(() => {
  fetchCourse()
  fetchTerms()
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
        { title: 'Terms', disabled: true }
      ]"
      class="mb-4"
    />
    
    <VCard v-if="course">
      <VCardText class="d-flex justify-space-between align-center flex-wrap">
        <h2 class="text-h5 mb-3 mb-sm-0">
          Terms for {{ course.title }}
        </h2>
        <VBtn 
          color="primary" 
          prepend-icon="tabler-plus"
          @click="openAddDialog"
        >
          Add Term
        </VBtn>
      </VCardText>
      
      <!-- Search & Filters -->
      <VCardText>
        <VRow>
          <VCol cols="12">
            <VTextField
              v-model="searchQuery"
              label="Search Terms"
              prepend-inner-icon="tabler-search"
              single-line
              hide-details
              variant="outlined"
              density="compact"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardText>
        <VDataTable
          :headers="headers"
          :items="terms"
          :loading="isLoading"
          :items-per-page="itemsPerPage"
          :page="page"
          :items-length="totalItems"
          class="elevation-1"
          @update:options="handleOptionsChange"
        >
          <!-- Term column -->
          <template #[`item.term`]="{ item }">
            <span class="font-weight-medium">{{ item.term }}</span>
          </template>
          
          <!-- Definition column -->
          <template #[`item.definition`]="{ item }">
            <span
              class="text-truncate d-inline-block"
              style="max-width: 300px;"
            >
              {{ item.definition }}
            </span>
          </template>
          
          <!-- Media Type column -->
          <template #[`item.media_type`]="{ item }">
            <VChip
              :color="item.media_type ? 'primary' : 'secondary'"
              size="small"
              label
            >
              {{ formatMediaType(item.media_type) }}
            </VChip>
          </template>
          
          <!-- Example column -->
          <template #[`item.has_example`]="{ item }">
            <VChip
              :color="hasExample(item) ? 'success' : 'secondary'"
              size="small"
            >
              {{ hasExample(item) ? 'Yes' : 'No' }}
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
                @click="deleteTerm(item)"
              >
                <VIcon icon="tabler-trash" />
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

    <!-- Term Edit Dialog -->
    <TermEditDialog
      v-model:is-dialog-visible="isDialogVisible"
      :term="editingTerm"
      :course-id="courseId"
      @term-saved="handleTermSaved"
    />

    <!-- Deletion Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isPasswordDialogVisible"
      entity-name="term"
      :name="termToDelete ? termToDelete.term : ''"
      @confirmed="confirmDelete"
    />
  </section>
</template>
