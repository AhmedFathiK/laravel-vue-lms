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
  
  try {
    isLoading.value = true

    const response = await api.get(`/admin/courses/${courseId.value}`)

    course.value = response.course || response
  } catch (error) {
    console.error('Error fetching course:', error)
    toast.error('Failed to load course details')
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
      params.append('term', searchQuery.value)
    }
    
    const response = await api.get(`/admin/courses/${courseId.value}/terms?${params.toString()}`)

    terms.value = response.data || []
    totalItems.value = response.total || 0
  } catch (error) {
    console.error('Error fetching terms:', error)
    toast.error('Failed to load terms')
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

// Get definition in current locale or fallback to English
const getLocalizedDefinition = definition => {
  if (!definition) return ''
  
  try {
    const parsedDefinition = typeof definition === 'string' 
      ? JSON.parse(definition) 
      : definition
    
    return parsedDefinition[locale.value] || parsedDefinition.en || ''
  } catch (e) {
    return typeof definition === 'string' ? definition : ''
  }
}

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
  return term.example && term.example.en && term.example.en.trim() !== ''
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

// Watch for locale changes
watch(() => locale.value, () => {
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
        { title: course ? course.title : 'Course', to: `/admin/courses/${courseId}` },
        { title: 'Terms', disabled: true }
      ]"
      class="mb-4"
    />
    
    <VCard>
      <VCardText class="d-flex justify-space-between align-center flex-wrap">
        <h2 class="text-h5 mb-3 mb-sm-0">
          Terms for {{ course ? course.title : 'Course' }}
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
              {{ getLocalizedDefinition(item.definition) }}
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
