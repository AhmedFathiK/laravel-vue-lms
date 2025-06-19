<script setup>
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'


const router = useRouter()
const toast = useToast()
const route = useRoute()
const isLoading = ref(false)
const slideTypes = ref([])
const slides = ref([])
const course = ref(null)
const level = ref(null)
const lesson = ref(null)
const dialog = ref(false)
const deleteDialog = ref(false)
const isSubmitting = ref(false)

const editedItem = ref({
  id: null,
  lesson_id: null,
  type: 'explanation',
  content: { en: '' },
  options: [],
  correct_answer: [],
  feedback: { en: '' },
  sort_order: 0,
})

const defaultItem = {
  id: null,
  lesson_id: null,
  type: 'explanation',
  content: { en: '' },
  options: [],
  correct_answer: [],
  feedback: { en: '' },
  sort_order: 0,
}

const selectedSlide = ref(null)
const formTitle = computed(() => editedItem.value.id ? 'Edit Slide' : 'New Slide')
const reordering = ref(false)

// Get IDs from route parameters
const courseId = computed(() => route.params.courseid)
const levelId = computed(() => route.params.levelid)
const lessonId = computed(() => route.params.lessonid)

// Table headers
const headers = [
  { title: 'Order', key: 'sort_order', sortable: true, width: '80px' },
  { title: 'Type', key: 'type', sortable: true, width: '150px' },
  { title: 'Content', key: 'content', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: '120px' },
]

// Fetch slide types
const fetchSlideTypes = async () => {
  try {
    const response = await api.get('/admin/slides/types')

    slideTypes.value = response
  } catch (error) {
    console.error('Error fetching slide types:', error)
    toast.error('Failed to load slide types')
  }
}

// Fetch course details
const fetchCourse = async () => {
  if (!courseId.value) return
  
  try {
    const response = await api.get(`/admin/courses/${courseId.value}`)
    
    if (response && typeof response === 'object') {
      course.value = response
    } else {
      toast.error('Course not found')
      course.value = null
    }
  } catch (error) {
    console.error('Error fetching course:', error)
    course.value = null
  }
}

// Fetch level details
const fetchLevel = async () => {
  if (!levelId.value) return
  
  try {
    const response = await api.get(`/admin/levels/${levelId.value}`)
    
    if (response && typeof response === 'object') {
      level.value = response
    } else {
      toast.error('Level not found')
      level.value = null
    }
  } catch (error) {
    console.error('Error fetching level:', error)
    level.value = null
  }
}

// Fetch lesson details
const fetchLesson = async () => {
  if (!lessonId.value) return
  
  isLoading.value = true
  try {
    const response = await api.get(`/admin/lessons/${lessonId.value}`)
    
    if (response && typeof response === 'object') {
      lesson.value = response
    } else {
      toast.error('Lesson not found')
      lesson.value = null
    }
  } catch (error) {
    console.error('Error fetching lesson:', error)
    lesson.value = null
  } finally {
    isLoading.value = false
  }
}

// Fetch slides
const fetchSlides = async () => {
  if (!lessonId.value) return
  
  isLoading.value = true
  try {
    const response = await api.get(`/admin/lessons/${lessonId.value}/slides`)

    slides.value = response
  } catch (error) {
    console.error('Error fetching slides:', error)
    toast.error('Failed to load slides')
  } finally {
    isLoading.value = false
  }
}

// Refresh all data
const refreshData = () => {
  fetchCourse()
  fetchLevel()
  fetchLesson()
  fetchSlides()
  fetchSlideTypes()
}

// Get slide type label
const getSlideTypeLabel = type => {
  const slideType = slideTypes.value.find(t => t.value === type)
  
  return slideType ? slideType.label : type
}

// Edit item
const editItem = item => {
  editedItem.value = JSON.parse(JSON.stringify(item))
  dialog.value = true
}

// Create new item
const createItem = () => {
  editedItem.value = JSON.parse(JSON.stringify(defaultItem))
  editedItem.value.lesson_id = parseInt(lessonId.value)
  editedItem.value.sort_order = slides.value.length + 1
  dialog.value = true
}

// Delete item
const deleteItem = item => {
  selectedSlide.value = item
  deleteDialog.value = true
}

// Confirm delete
const confirmDelete = async () => {
  if (!selectedSlide.value) return
  
  isSubmitting.value = true
  try {
    await api.delete(`/admin/slides/${selectedSlide.value.id}`)
    toast.success('Slide deleted successfully')
    fetchSlides()
    deleteDialog.value = false
  } catch (error) {
    console.error('Error deleting slide:', error)
    toast.error('Failed to delete slide')
  } finally {
    isSubmitting.value = false
    selectedSlide.value = null
  }
}

// Save item
const save = async () => {
  isSubmitting.value = true
  
  try {
    if (editedItem.value.id) {
      // Update existing slide
      await api.put(`/admin/slides/${editedItem.value.id}`, editedItem.value)
      toast.success('Slide updated successfully')
    } else {
      // Create new slide
      await api.post('/admin/slides', editedItem.value)
      toast.success('Slide created successfully')
    }
    
    dialog.value = false
    fetchSlides()
  } catch (error) {
    console.error('Error saving slide:', error)
    toast.error('Failed to save slide')
  } finally {
    isSubmitting.value = false
  }
}

// Toggle reorder mode
const toggleReorderMode = () => {
  reordering.value = !reordering.value
}

// Save new order
const saveOrder = async () => {
  isSubmitting.value = true
  
  try {
    const order = slides.value.map(slide => slide.id)

    await api.post(`/admin/lessons/${lessonId.value}/slides/order`, { order })
    toast.success('Slide order updated successfully')
    reordering.value = false
    fetchSlides()
  } catch (error) {
    console.error('Error updating slide order:', error)
    toast.error('Failed to update slide order')
  } finally {
    isSubmitting.value = false
  }
}

// Update slide order
const updateSortOrder = () => {
  slides.value.forEach((slide, index) => {
    slide.sort_order = index + 1
  })
}

// Watch for changes in lessonId
watch(lessonId, () => {
  fetchSlides()
})

// Initialize data on component mount
onMounted(refreshData)
</script>

<template>
  <section>
    <!-- Breadcrumb Navigation -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Courses', to: '/admin/courses' },
        { title: course ? course.title : 'Course', to: `/admin/courses/${courseId}` },
        { title: level ? level.title : 'Level', to: `/admin/courses/${courseId}/levels/${levelId}` },
        { title: 'Lessons', to: `/admin/courses/${courseId}/levels/${levelId}/lessons` },
        { title: lesson ? lesson.title : 'Slides', disabled: true }
      ]"
      class="mb-4"
    />
    
    <VCard
      v-if="isLoading"
      class="text-center py-8"
    >
      <VCardText>
        <VProgressCircular
          indeterminate
          color="primary"
        />
        <div class="mt-4">
          Loading lesson details...
        </div>
      </VCardText>
    </VCard>
    
    <VCard v-else-if="lesson">
      <VCardText class="d-flex justify-space-between align-center flex-wrap">
        <h2>Slides for {{ lesson.title }}</h2>
        <div>
          <VBtn 
            v-if="reordering"
            color="success" 
            class="me-2"
            :loading="isSubmitting"
            @click="saveOrder"
          >
            Save Order
          </VBtn>
          <VBtn 
            v-if="reordering"
            variant="outlined"
            @click="reordering = false"
          >
            Cancel
          </VBtn>
          <VBtn 
            v-else
            color="secondary" 
            class="me-2"
            prepend-icon="tabler-sort"
            @click="toggleReorderMode"
          >
            Reorder
          </VBtn>
          <VBtn 
            v-if="!reordering"
            color="primary" 
            prepend-icon="tabler-plus"
            @click="createItem"
          >
            Add Slide
          </VBtn>
        </div>
      </VCardText>

      <VCardText
        v-if="slides.length === 0"
        class="text-center pa-12"
      >
        <VIcon
          icon="tabler-presentation"
          size="64"
          color="primary"
        />
        <h3 class="mt-4">
          No Slides Yet
        </h3>
        <p class="mt-2">
          This lesson doesn't have any slides yet. Click the "Add Slide" button to create your first slide.
        </p>
      </VCardText>

      <VDataTable
        v-else
        :headers="headers"
        :items="slides"
        :items-per-page="10"
        class="elevation-1"
      >
        <!-- Type column -->
        <template #item.type="{ item }">
          <VChip size="small">
            {{ getSlideTypeLabel(item.type) }}
          </VChip>
        </template>

        <!-- Content column -->
        <template #item.content="{ item }">
          <div
            class="text-truncate"
            style="max-width: 500px;"
          >
            {{ item.content }}
          </div>
        </template>

        <!-- Actions column -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-end">
            <VBtn
              icon
              variant="text"
              size="small"
              color="primary"
              @click="editItem(item)"
            >
              <VIcon size="20">
                tabler-pencil
              </VIcon>
            </VBtn>
            
            <VBtn
              icon
              variant="text"
              size="small"
              color="error"
              @click="deleteItem(item)"
            >
              <VIcon size="20">
                tabler-trash
              </VIcon>
            </VBtn>
          </div>
        </template>
      </VDataTable>
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
          Lesson Not Found
        </div>
        <div class="mt-2">
          The requested lesson could not be found or you don't have permission to view it.
        </div>
        <div class="mt-2">
          <VBtn
            variant="text"
            color="primary"
            @click="router.push(`/admin/courses/${courseId}/levels/${levelId}/lessons`)"
          >
            ← Back to Lessons
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Create/Edit Dialog -->
    <VDialog
      v-model="dialog"
      max-width="600px"
      persistent
    >
      <VCard>
        <VCardTitle>
          <span class="text-h5">{{ formTitle }}</span>
        </VCardTitle>

        <VCardText>
          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="editedItem.type"
                :items="slideTypes"
                item-title="label"
                item-value="value"
                label="Slide Type"
                required
              >
                <template #item="{ item, props }">
                  <VListItem v-bind="props">
                    <VListItemTitle>{{ item.label }}</VListItemTitle>
                    <VListItemSubtitle>{{ item.description }}</VListItemSubtitle>
                  </VListItem>
                </template>
              </VSelect>
            </VCol>
            
            <VCol cols="12">
              <VTextarea
                v-model="editedItem.content.en"
                label="Content (English)"
                required
                rows="4"
                auto-grow
              />
            </VCol>
            
            <VCol
              v-if="['mcq', 'matching_pairs', 'reordering', 'fill_blank_choices'].includes(editedItem.type)"
              cols="12"
            >
              <VTextField
                v-model="editedItem.options"
                label="Options (comma separated)"
                hint="Enter options separated by commas"
              />
            </VCol>
            
            <VCol
              v-if="['mcq', 'matching_pairs', 'reordering', 'fill_blank', 'fill_blank_choices'].includes(editedItem.type)"
              cols="12"
            >
              <VTextField
                v-model="editedItem.correct_answer"
                label="Correct Answer"
                hint="For MCQ: index of correct option(s). For matching: pairs of indices."
              />
            </VCol>
            
            <VCol cols="12">
              <VTextarea
                v-model="editedItem.feedback.en"
                label="Feedback (English)"
                rows="2"
                auto-grow
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            color="blue-darken-1"
            variant="text"
            @click="dialog = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="blue-darken-1"
            variant="text"
            :loading="isSubmitting"
            @click="save"
          >
            Save
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Delete Confirmation Dialog -->
    <VDialog
      v-model="deleteDialog"
      max-width="500px"
    >
      <VCard>
        <VCardTitle class="text-h5">
          Delete Slide
        </VCardTitle>
        <VCardText>
          Are you sure you want to delete this slide? This action cannot be undone.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="blue-darken-1"
            variant="text"
            @click="deleteDialog = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="error"
            variant="text"
            :loading="isSubmitting"
            @click="confirmDelete"
          >
            Delete
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </section>
</template> 
