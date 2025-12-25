<script setup>
import api from '@/utils/api'
import LessonSlideCard from '@/views/lessons/LessonSlideCard.vue'
import { v4 as uuidv4 } from 'uuid'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { SlickItem, SlickList } from 'vue-slicksort'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'slides',
  },
})

const router = useRouter()
const toast = useToast()
const route = useRoute()
const isLoading = ref(false)

const slideTypes = ref([
  { value: "mcq", label: "Multiple Choice", description: "Select one or more correct answers from options", isQuestion: true },
  { value: "matching", label: "Matching Pairs", description: "Match items from two columns", isQuestion: true },
  { value: "reordering", label: "Reordering", description: "Arrange items in the correct order", isQuestion: true },
  { value: "fill_blank", label: "Fill in the Blank", description: "Complete sentences by typing missing words", isQuestion: true },
  { value: "fill_blank_choices", label: "Fill in the Blank (with choices)", description: "Complete sentences by selecting from options", isQuestion: true },
  { value: "drag_and_drop", label: "Drag and Drop", description: "Drag items to their correct places", isQuestion: true },
  { value: "term", label: "Term", description: "Vocabulary term with definition", isQuestion: false },
  { value: "explanation", label: "Explanation", description: "Text explanation or content", isQuestion: false },
])

const slides = ref([])
const course = ref(null)
const level = ref(null)
const lesson = ref(null)
const isSlideEditDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)

const editedItem = ref({
  id: null,
  "lesson_id": null,
  type: 'explanation',
  content: '',
  options: [],
  "correct_answer": [],
  "sort_order": 0,
  "question_id": null,
  "term_id": null,
})

const defaultItem = {
  id: null,
  "lesson_id": null,
  type: 'explanation',
  content: '',
  options: [],
  "correct_answer": [],
  "sort_order": 0,
  "question_id": null,
  "term_id": null,
}

const selectedSlide = ref(null)
const reordering = ref(false)
const isSubmitting = ref(false)

// Get IDs from route parameters
const courseId = computed(() => route.params.courseid)
const levelId = computed(() => route.params.levelid)
const lessonId = computed(() => route.params.lessonid)


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
    const response = await api.get(`/admin/courses/${courseId.value}/levels/${levelId.value}`)
    
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
    const response = await api.get(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons/${lessonId.value}`)
    
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
    const response = await api.get(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons/${lessonId.value}/slides`)

    slides.value = response
    addRandomKeyToSlides(slides.value)
  } catch (error) {
    console.error('Error fetching slides:', error)
    toast.error('Failed to load slides')
  } finally {
    isLoading.value = false
  }
}

const addRandomKeyToSlides = slides => {
  for (let i = 0; i < slides.length; i++) {
    const slide = slides[i]

    slide.randomKey = uuidv4()
    
  }
}


// Refresh all data
const refreshData = () => {
  fetchCourse()
  fetchLevel()
  fetchLesson()
  fetchSlides()
}

// Get slide type label
const getSlideTypeLabel = type => {
  const slideType = slideTypes.value.find(t => t.value === type)
  
  return slideType ? slideType.label : type
}

// Edit item
const editItem = item => {
  console.log(item)
  
  editedItem.value = JSON.parse(JSON.stringify(item))
  isSlideEditDialogVisible.value = true
}

// Create new item
const createItem = () => {
  editedItem.value = JSON.parse(JSON.stringify(defaultItem))
  editedItem.value["lesson_id"] = parseInt(lessonId.value)
  editedItem.value["sort_order"] = slides.value.length + 1
  isSlideEditDialogVisible.value = true
}

// Delete item
const deleteItem = item => {
  selectedSlide.value = item
  isDeleteDialogVisible.value = true
}

// Confirm delete
const confirmDelete = async () => {
  if (!selectedSlide.value) return
  
  try {
    await api.delete(`/admin/slides/${selectedSlide.value.id}`)
    toast.success('Slide deleted successfully')
    isDeleteDialogVisible.value = false
    fetchSlides()
  } catch (error) {
    toast.error('Failed to delete slide')
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

    reordering.value = false
    await api.put(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons/${lessonId.value}/slides/order`, { order })
    toast.success('Slide order updated successfully')
  } catch (error) {
    console.error('Error updating slide order:', error)
    toast.error('Failed to update slide order')
    reordering.value = true
  } finally {
    isSubmitting.value = false
  }
}

// Update slide order
const updateSortOrder = () => {
  slides.value.forEach((slide, index) => {
    slide["sort_order"] = index + 1
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
        { title: course ? course.title : 'Course', disabled: true },
        { title: level ? level.title : 'Level', to: `/admin/courses/${courseId}/levels` },
        { title: lesson ? lesson.title : 'Lesson', to: `/admin/courses/${courseId}/levels/${levelId}/lessons` },
        { title: lesson ? 'Slides' : '', disabled: true }
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
            prepend-icon="tabler-arrows-sort"
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
      <VCardText>
        <SlickList 
          v-if="slides.length > 0"
          v-model:list="slides"
          axis="xy"
          helper-class="sortable-helper"
          use-drag-handle
        >
          <VRow>
            <VCol
              v-for="(slide, index) in slides"
              :key="slide.randomKey"
              cols="12"
              sm="6"
              lg="4"
            >
              <SlickItem
                class="sortable-list-item"
                :index="index"
              >
                <LessonSlideCard
                  :data="slide"
                  :slide-number="index + 1"
                  :reordering="reordering"
                  @click:edit="editItem(slide)"
                  @click:delete="deleteItem(slide)"
                />
              </SlickItem>
            </VCol>
          </VRow>
        </SlickList>
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
    <!-- Use AddEditSlideDialog component -->
    <AddEditSlideDialog
      v-model:is-dialog-visible="isSlideEditDialogVisible"
      :slide-data="editedItem"
      :dialog-mode="editedItem.id ? 'edit' : 'add'"
      :lesson-id="lessonId"
      :course-id="courseId"
      :level-id="levelId"
      :slide-types="slideTypes"
      @refresh="fetchSlides"
    />

    <!-- Delete Confirmation Dialog -->
    <ConfirmDialog
      v-model:is-dialog-visible="isDeleteDialogVisible"
      confirmation-question="Are you sure you want to delete this slide?"
      confirm-title="Success"
      confirm-msg="Slide deleted."
      cancel-title="Cancel"
      cancel-msg="Slide not deleted."
      @confirm="confirmDelete"
    />
  </section>
</template>
