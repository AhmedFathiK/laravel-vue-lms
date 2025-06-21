<script setup>
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { locale, t } = useI18n()

const isLoading = ref(false)
const course = ref(null)
const terms = ref([])
const selectedTerm = ref(null)
const isTermDetailOpen = ref(false)

// Pagination
const itemsPerPage = ref(10)
const page = ref(1)
const totalItems = ref(0)
const sortBy = ref('term')
const sortDesc = ref(false)

// Search
const searchQuery = ref('')

// Get course ID from route parameter
const courseId = computed(() => route.params.id)

// Headers for data table
const headers = [
  { title: 'Term', key: 'term' },
  { title: 'Definition', key: 'definition', sortable: false },
  { title: 'Media', key: 'media_type', sortable: false, width: '80px' },
]

// Fetch course details
const fetchCourse = async () => {
  if (!courseId.value) return
  
  try {
    isLoading.value = true

    const response = await api.get(`/learner/courses/${courseId.value}`)

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
    
    const response = await api.get(`/learner/courses/${courseId.value}/terms?${params.toString()}`)

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

// Removed getLocalizedTerm function

// Get example in current locale or fallback to English
const getLocalizedExample = example => {
  if (!example) return ''
  
  try {
    const parsedExample = typeof example === 'string' 
      ? JSON.parse(example) 
      : example
    
    return parsedExample[locale.value] || parsedExample.en || ''
  } catch (e) {
    return typeof example === 'string' ? example : ''
  }
}

// Open term details
const openTermDetail = term => {
  selectedTerm.value = term
  isTermDetailOpen.value = true
}

// Close term details
const closeTermDetail = () => {
  isTermDetailOpen.value = false
}

// Media type display functions
const hasMedia = term => {
  return term.media_type && term.media_url
}

// Check if term has an example
const hasExample = term => {
  return term.example && term.example.en && term.example.en.trim() !== ''
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
        { title: 'Courses', to: '/courses' },
        { title: course ? course.title : 'Course', to: `/courses/${courseId}` },
        { title: 'Terms', disabled: true }
      ]"
      class="mb-4"
    />

    <!-- Course Terms -->
    <VCard>
      <VCardText class="d-flex justify-space-between align-center flex-wrap">
        <h2 class="text-h5 mb-3 mb-sm-0">
          Terms for {{ course ? course.title : 'Course' }}
        </h2>
      </VCardText>

      <!-- Search -->
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

      <!-- Terms Table -->
      <VCardText>
        <VDataTable
          :headers="headers"
          :items="terms"
          :loading="isLoading"
          :items-per-page="itemsPerPage"
          :page="page"
          :items-length="totalItems"
          class="elevation-1"
          hover
          @update:options="handleOptionsChange"
          @click:row="openTermDetail"
        >
          <!-- Term column -->
          <template #[`item.term`]="{ item }">
            <div class="font-weight-medium">
              {{ item.term }}
            </div>
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
          
          <!-- Media column -->
          <template #[`item.media_type`]="{ item }">
            <VIcon 
              v-if="item.media_type === 'image'" 
              icon="tabler-photo" 
              color="primary"
            />
            <VIcon 
              v-else-if="item.media_type === 'image_audio'" 
              icon="tabler-photo-plus" 
              color="info"
            />
            <VIcon 
              v-else-if="item.media_type === 'video'" 
              icon="tabler-video" 
              color="success"
            />
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Term Detail Dialog -->
    <VDialog
      v-model="isTermDetailOpen"
      max-width="800px"
    >
      <VCard v-if="selectedTerm">
        <VCardText class="pt-6">
          <!-- Term Header -->
          <div class="d-flex justify-space-between align-center">
            <h2 class="text-h4 mb-2">
              {{ selectedTerm.term }}
            </h2>
            <VBtn
              icon
              variant="text"
              @click="closeTermDetail"
            >
              <VIcon icon="tabler-x" />
            </VBtn>
          </div>

          <!-- Translation section removed -->
          
          <!-- Definition -->
          <div class="mb-5">
            <VChip
              color="secondary"
              size="small"
              class="mb-1"
            >
              Definition
            </VChip>
            <p class="text-body-1">
              {{ getLocalizedDefinition(selectedTerm.definition) }}
            </p>
          </div>

          <!-- Media Section -->
          <div
            v-if="hasMedia(selectedTerm)"
            class="mb-5"
          >
            <VChip
              color="info"
              size="small"
              class="mb-2"
            >
              Media
            </VChip>
            
            <div class="media-container">
              <!-- Image -->
              <div
                v-if="selectedTerm.media_type === 'image'"
                class="text-center"
              >
                <img 
                  :src="selectedTerm.media_url" 
                  :alt="selectedTerm.term" 
                  class="term-media-image"
                >
              </div>
              
              <!-- Image with Audio -->
              <div
                v-else-if="selectedTerm.media_type === 'image_audio'"
                class="text-center"
              >
                <img 
                  :src="selectedTerm.media_url" 
                  :alt="selectedTerm.term" 
                  class="term-media-image mb-3"
                >
                <audio
                  v-if="selectedTerm.audio_url"
                  :src="selectedTerm.audio_url"
                  controls
                  class="w-100"
                />
              </div>
              
              <!-- Video -->
              <div
                v-else-if="selectedTerm.media_type === 'video'"
                class="text-center"
              >
                <video 
                  :src="selectedTerm.media_url" 
                  controls 
                  class="term-media-video"
                />
              </div>
            </div>
          </div>

          <!-- Examples -->
          <div
            v-if="hasExample(selectedTerm)"
            class="mb-4"
          >
            <VDivider class="my-4" />
            <h3 class="text-h6 mb-2">
              Example
            </h3>
            
            <VCard
              variant="outlined"
              class="mb-3"
            >
              <VCardText>
                <p class="text-body-1 mb-2">
                  {{ getLocalizedExample(selectedTerm.example) }}
                </p>
                <audio
                  v-if="selectedTerm.example_audio_url"
                  :src="selectedTerm.example_audio_url"
                  controls
                  class="w-100 mt-2"
                />
              </VCardText>
            </VCard>
          </div>
          
          <VDivider class="my-4" />
          
          <!-- Add to Revision Button -->
          <div class="d-flex justify-end mt-3">
            <VBtn
              color="primary"
              prepend-icon="tabler-brain"
            >
              Add to Revision
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </section>
</template>

<style scoped>
.term-media-image {
  max-width: 100%;
  max-height: 300px;
  border-radius: 8px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.term-media-video {
  max-width: 100%;
  border-radius: 8px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.media-container {
  padding: 16px;
  background-color: rgba(0, 0, 0, 0.02);
  border-radius: 8px;
}
</style>
