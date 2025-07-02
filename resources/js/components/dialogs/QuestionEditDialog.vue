<script setup>
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import api from '@/utils/api'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { requiredValidator, integerValidator } from '@/@core/utils/validators'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  question: {
    type: Object,
    required: true,
  },
  courseId: {
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'refresh',
])

const { t, locale } = useI18n()
const toast = useToast()
const formRef = ref(null)
const isSubmitting = ref(false)
const formErrors = ref({})

// Form data with default values
const formData = ref({
  id: null,
  course_id: null,
  title: '',
  question_text: '',
  type: 'mcq',
  options: [],
  correct_answer: [],
  points: 1,
  difficulty: 'medium',
  tags: [],

  correct_feedback: '',
  incorrect_feedback: '',
  media_url: null,
  media_type: 'none',

  // For fill in the blank
  blanks: [],

  // For matching
  matching_pairs: [],

  // For reordering
  reordering_items: [],

  // For writing
  grading_guidelines: '',
  min_words: 0,
  max_words: 0,
})

// Handle tag input
const newTag = ref('')

// Computed property to detect blanks for fill_blank type
const detectedBlanks = computed(() => {
  if (!['fill_blank', 'fill_blank_choices'].includes(formData.value.type))
    return []

  const regex = /\[blank\d+\]/g
  const matches = formData.value.question_text.match(regex) || []
  const uniqueMatches = [...new Set(matches)]

  // Sort by the number inside [blankX]
  uniqueMatches.sort((a, b) => {
    const numA = parseInt(a.match(/\d+/)[0], 10)
    const numB = parseInt(b.match(/\d+/)[0], 10)
    
    return numA - numB
  })

  return uniqueMatches
})

// Watch for changes in detected blanks and update the correct_answer array
watch(detectedBlanks, (newBlanks, oldBlanks) => {
  if (formData.value.type === 'fill_blank') {
    const newSize = newBlanks.length
    const currentAnswers = Array.isArray(formData.value.correct_answer) ? formData.value.correct_answer : []

    // Only update if the size of blanks has changed
    if (newSize !== currentAnswers.length) {
      const newAnswers = Array(newSize).fill(null).map(() => [])

      // Preserve existing answers
      for (let i = 0; i < Math.min(newSize, currentAnswers.length); i++) {
        // Ensure the preserved answer is an array
        newAnswers[i] = Array.isArray(currentAnswers[i]) ? currentAnswers[i] : [currentAnswers[i]]
      }
      formData.value.correct_answer = newAnswers
    }
  }
}, { immediate: true })

// Watch for changes in detected blanks and update the blanks for fill_blank_choices
watch(detectedBlanks, (newBlanks, oldBlanks) => {
  if (formData.value.type === 'fill_blank_choices') {
    const newSize = newBlanks.length
    const currentBlanks = Array.isArray(formData.value.blanks) ? formData.value.blanks : []

    if (newSize !== currentBlanks.length) {
      const newBlanksArray = Array(newSize).fill(null).map((_, index) => {
        return currentBlanks[index] || {
          placeholder: `Blank ${index + 1}`,
          options: ['', ''],
          correct_answer: '0',
        }
      })

      formData.value.blanks = newBlanksArray
    }
  }
}, { immediate: true })



// Add an option to a blank
const addBlankOption = blankIndex => {
  if (!formData.value.blanks[blankIndex].options) {
    formData.value.blanks[blankIndex].options = []
  }
  
  formData.value.blanks[blankIndex].options.push('')
}

// Remove an option from a blank
const removeBlankOption = (blankIndex, optionIndex) => {
  formData.value.blanks[blankIndex].options.splice(optionIndex, 1)
  
  // Update correct answer if it was the removed option
  const blank = formData.value.blanks[blankIndex]
  if (blank.correct_answer === optionIndex.toString()) {
    blank.correct_answer = '0' // Default to first option
  } else if (parseInt(blank.correct_answer) > optionIndex) {
    // Adjust correct answer index for removed option
    blank.correct_answer = (parseInt(blank.correct_answer) - 1).toString()
  }
}

// Add a matching pair
const addMatchingPair = () => {
  if (!formData.value.matching_pairs) {
    formData.value.matching_pairs = []
  }
  
  formData.value.matching_pairs.push({
    left: '',
    right: '',
  })
  
  // Update correct_answer to match the pairs
  updateMatchingCorrectAnswers()
}

// Remove a matching pair
const removeMatchingPair = index => {
  formData.value.matching_pairs.splice(index, 1)
  
  // Update correct_answer to match the pairs
  updateMatchingCorrectAnswers()
}

// Update the correct answers for matching pairs
const updateMatchingCorrectAnswers = () => {
  if (!formData.value.matching_pairs) return
  
  // For matching, correct_answer should contain the mapping
  formData.value.correct_answer = formData.value.matching_pairs.map((pair, index) => ({
    left: index,
    right: index,
  }))
}

// Add a reordering item
const addReorderingItem = () => {
  if (!formData.value.reordering_items) {
    formData.value.reordering_items = []
  }
  
  formData.value.reordering_items.push('')
  
  // Update correct_answer to match the order
  updateReorderingCorrectAnswers()
}

// Remove a reordering item
const removeReorderingItem = index => {
  formData.value.reordering_items.splice(index, 1)
  
  // Update correct_answer to match the order
  updateReorderingCorrectAnswers()
}

// Move a reordering item up or down
const moveReorderingItem = (index, direction) => {
  const items = formData.value.reordering_items
  
  if (direction === 'up' && index > 0) {
    // Swap with the item above
    [items[index], items[index - 1]] = [items[index - 1], items[index]]
  } else if (direction === 'down' && index < items.length - 1) {
    // Swap with the item below
    [items[index], items[index + 1]] = [items[index + 1], items[index]]
  }
  
  // Update correct_answer to match the order
  updateReorderingCorrectAnswers()
}

// Update the correct answers for reordering
const updateReorderingCorrectAnswers = () => {
  if (!formData.value.reordering_items) return
  
  // For reordering, correct_answer should contain the indices in correct order
  formData.value.correct_answer = formData.value.reordering_items.map((_, index) => index.toString())
}

// Initialize data for specific question types
const initializeQuestionTypeData = () => {
  const type = formData.value.type
  
  // Clear existing data if not defined
  if (!Array.isArray(formData.value.options)) {
    formData.value.options = []
  }
  
  if (!Array.isArray(formData.value.correct_answer)) {
    formData.value.correct_answer = []
  }
  
  if (!Array.isArray(formData.value.blanks)) {
    formData.value.blanks = []
  }
  
  if (!Array.isArray(formData.value.matching_pairs)) {
    formData.value.matching_pairs = []
  }
  
  if (!Array.isArray(formData.value.reordering_items)) {
    formData.value.reordering_items = []
  }
  
  // Initialize specific type data
  if (type === 'fill_blank_choices') {
    // For fill in the blank with choices, initialize blanks from options
    if (!formData.value.blanks.length && Array.isArray(formData.value.options) && formData.value.options.length > 0) {
      formData.value.blanks = formData.value.options
    } else if (!formData.value.blanks.length) {
      // Add default blank if none exists
      addBlank()
    }
  } else if (type === 'matching') {
    // For matching, initialize pairs from options
    if (!formData.value.matching_pairs.length && Array.isArray(formData.value.options) && formData.value.options.length > 0) {
      formData.value.matching_pairs = formData.value.options
    } else if (!formData.value.matching_pairs.length) {
      // Add default pair if none exists
      addMatchingPair()
    }
  } else if (type === 'reordering') {
    // For reordering, initialize items from options
    if (!formData.value.reordering_items.length && Array.isArray(formData.value.options) && formData.value.options.length > 0) {
      formData.value.reordering_items = formData.value.options
    } else if (!formData.value.reordering_items.length) {
      // Add default item if none exists
      addReorderingItem()
    }
  } else if (type === 'writing') {
    // For writing, initialize grading guidelines and word limits from options
    if (!formData.value.grading_guidelines && formData.value.options && formData.value.options.grading_guidelines) {
      formData.value.grading_guidelines = formData.value.options.grading_guidelines
    }
    
    if (!formData.value.min_words && formData.value.options && formData.value.options.min_words) {
      formData.value.min_words = formData.value.options.min_words
    }
    
    if (!formData.value.max_words && formData.value.options && formData.value.options.max_words) {
      formData.value.max_words = formData.value.options.max_words
    }
  }
}

// Question types
const questionTypes = [
  { title: 'Multiple Choice', value: 'mcq' },
  { title: 'Matching', value: 'matching' },
  { title: 'Fill in the Blank', value: 'fill_blank' },
  { title: 'Fill in the Blank with Choices', value: 'fill_blank_choices' },
  { title: 'Reordering', value: 'reordering' },
  { title: 'Writing', value: 'writing' },
]

const mediaTypes = [
  { title: 'None', value: 'none' },
  { title: 'Image', value: 'image' },
  { title: 'Image with Audio', value: 'image_with_audio' },
  { title: 'Video', value: 'video' },
]

// For file uploads
const mediaFile = ref(null)

// Handle file selection
const handleFileUpload = file => {
  mediaFile.value = file || null
  
  // If a file is selected, create a temporary URL for preview
  if (mediaFile.value) {
    formData.value.media_url = URL.createObjectURL(mediaFile.value)
  }
}

// Clear media when media type changes
watch(() => formData.value.media_type, newValue => {
  if (newValue === 'none') {
    formData.value.media_url = null
    formData.value.audio_url = null
    mediaFile.value = null
  } else if (newValue === 'video') {
    // Clear file upload data when switching to video (URL only)
    mediaFile.value = null
  } else if (newValue !== 'image_with_audio') {
    // Clear audio URL when switching to a type that doesn't use audio
    formData.value.audio_url = null
  }
})

// Difficulty levels
const difficultyLevels = [
  { title: 'Easy', value: 'easy' },
  { title: 'Medium', value: 'medium' },
  { title: 'Hard', value: 'hard' },
]

// Function to reset the form to its default state for creating a new question
const resetForm = () => {
  formData.value = {
    id: null,
    course_id: props.courseId,
    title: '',
    question_text: '',
    type: 'mcq',
    options: [],
    correct_answer: [],
    points: 1,
    difficulty: 'medium',
    tags: [],
    correct_feedback: '',
    incorrect_feedback: '',
    media_url: null,
    media_type: 'none',
    audio_url: null,
    blanks: [],
    matching_pairs: [],
    reordering_items: [],
    grading_guidelines: '',
    min_words: 0,
    max_words: 0,
  }

  // Reset other related state
  formErrors.value = {}
  newTag.value = ''

  // Initialize data for the default question type
  initializeQuestionTypeData()
}

// Watch for changes in the question prop
watch(() => props.question, newQuestion => {
  if (newQuestion && newQuestion.id) {
    // Editing an existing question
    // Reset form errors
    formErrors.value = {}
    
    // Reset newTag input
    newTag.value = ''
    
    // Clone to avoid direct modification of props
    formData.value = JSON.parse(JSON.stringify(newQuestion))



    // Ensure tags is an array
    if (!formData.value.tags) {
      formData.value.tags = []
    }

    // Ensure correct_answer is an array for mcq
    if (formData.value.type === 'mcq' && !Array.isArray(formData.value.correct_answer)) {
      formData.value.correct_answer = []
    }

    // Ensure options is an array for mcq
    if (formData.value.type === 'mcq' && !Array.isArray(formData.value.options)) {
      formData.value.options = []
    }
    
    // Ensure points has a valid value
    if (!formData.value.points) {
      formData.value.points = 1
    }
    
    // Initialize data for specific question types
    initializeQuestionTypeData()
  } else {
    // Creating a new question, so reset the form
    resetForm()
  }
}, { immediate: true, deep: true })

// Close dialog and reset form
const closeDialog = () => {
  emit('update:isDialogVisible', false)
  formErrors.value = {}
  newTag.value = ''
}

// Submit form
const submitForm = async () => {
  if (formRef.value) {
    const { valid } = await formRef.value.validate()
    if (!valid) {
      return
    }
  }

  isSubmitting.value = true
  formErrors.value = {}

  try {
    const questionData = JSON.parse(JSON.stringify(formData.value))

    // Create FormData object for file uploads
    const formDataObj = new FormData()
    
    // Add all form fields to FormData
    Object.keys(questionData).forEach(key => {
      if (key !== 'mediaFile') { // Skip the file input reference
        if (typeof questionData[key] === 'object' && questionData[key] !== null) {
          formDataObj.append(key, JSON.stringify(questionData[key]))
        } else if (questionData[key] !== null) {
          formDataObj.append(key, questionData[key])
        }
      }
    })
    
    // Add media file if it exists (only for image uploads)
    if (mediaFile.value && (questionData.media_type === 'image' || questionData.media_type === 'image_with_audio')) {
      formDataObj.append('media', mediaFile.value)
    }
    
    // For video type, we're using URL directly so no file upload is needed
    // For image_with_audio, we're using audio_url directly
    // Both are already included in the formDataObj from the loop above

    if (questionData.id) {
      // Update existing question
      await api.put(`/admin/courses/${props.courseId}/questions/${questionData.id}`, formDataObj, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })
      toast.success('Question updated successfully')
    } else {
      // Create new question
      formDataObj.append('course_id', props.courseId)
      await api.post(`/admin/courses/${props.courseId}/questions`, formDataObj, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })
      toast.success('Question created successfully')
    }
    emit('refresh')
    closeDialog()
  } catch (error) {
    console.error('Error saving question:', error)
    if (error.response && error.response.status === 422) {
      if (error.response.data && error.response.data.errors) {
        formErrors.value = error.response.data.errors
      }
      toast.error('Please correct the validation errors.')
    } else {
      const message = error.response?.data?.message || 'Failed to save question'

      toast.error(message)
    }
  } finally {
    isSubmitting.value = false
  }
}

// Add new option for multiple choice
const addOption = () => {
  if (!formData.value.options) {
    formData.value.options = []
  }
  formData.value.options.push('')
}

// Remove option
const removeOption = index => {
  formData.value.options.splice(index, 1)
  
  // Also remove from correct answers if selected
  if (formData.value.correct_answer.includes(index.toString())) {
    const answerIndex = formData.value.correct_answer.indexOf(index.toString())

    formData.value.correct_answer.splice(answerIndex, 1)
  }
  
  // Adjust correct answers for removed option
  formData.value.correct_answer = formData.value.correct_answer
    .map(answer => {
      const answerNum = parseInt(answer)
      if (answerNum > index) {
        return (answerNum - 1).toString()
      }
      
      return answer
    })
}

// Toggle correct answer for an option
const toggleCorrectAnswer = index => {
  const indexStr = index.toString()
  const correctAnswers = formData.value.correct_answer || []
  
  if (correctAnswers.includes(indexStr)) {
    // Remove from correct answers
    const answerIndex = correctAnswers.indexOf(indexStr)

    formData.value.correct_answer.splice(answerIndex, 1)
  } else {
    // Add to correct answers
    formData.value.correct_answer.push(indexStr)
  }
}

// Check if an option is marked as correct
const isCorrectAnswer = index => {
  return formData.value.correct_answer?.includes(index.toString())
}

const addTag = () => {
  if (newTag.value.trim() && !formData.value.tags.includes(newTag.value.trim())) {
    formData.value.tags.push(newTag.value.trim())
    newTag.value = ''
  }
}

// Remove a tag
const removeTag = tag => {
  const index = formData.value.tags.indexOf(tag)
  if (index !== -1) {
    formData.value.tags.splice(index, 1)
  }
}

// Computed property to determine dialog title
const dialogTitle = computed(() => {
  return formData.value.id ? 'Edit Question' : 'Add New Question'
})

// Reset options when changing question type
watch(() => formData.value.type, newType => {
  // Initialize appropriate data structures for the question type
  initializeQuestionTypeData()
})
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="800"
    persistent
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <!-- Dialog Close Btn -->
    <DialogCloseBtn @click="closeDialog" />
    
    <VCard :title="dialogTitle">
      <VCardText>
        <!-- Form Error -->
        <VAlert
          v-if="formErrors.general"
          color="error"
          variant="tonal"
          class="mb-4"
        >
          {{ formErrors.general }}
        </VAlert>
        
        <VForm
          ref="formRef"
          @submit.prevent="submitForm"
        >
          <VRow>
            <!-- Question Title -->
            <VCol cols="12">
              <AppTextField
                v-model="formData.title"
                label="Title (Optional)"
                placeholder="Enter an optional title for the question"
                :error-messages="formErrors.title"
              />
            </VCol>
            
            <!-- Question Text -->
            <VCol cols="12">
              <AppTextarea
                v-model="formData.question_text"
                label="Question Text"
                :rules="[requiredValidator]"
                :error-messages="formErrors.question_text"
                placeholder="Enter the question text"
                rows="3"
              />
            </VCol>
            
            <!-- Question Type -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.type"
                label="Question Type"
                :items="questionTypes"
                item-title="title"
                item-value="value"
                :error-messages="formErrors.type"
                class="mb-3"
              />
            </VCol>
            
            <!-- Difficulty -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.difficulty"
                label="Difficulty"
                :items="difficultyLevels"
                item-title="title"
                item-value="value"
                :error-messages="formErrors.difficulty"
                class="mb-3"
              />
            </VCol>
            
            <!-- Media Type Selection -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="formData.media_type"
                label="Media Type"
                :items="mediaTypes"
                item-title="title"
                item-value="value"
                :error-messages="formErrors.media_type"
                class="mb-3"
              />
            </VCol>
            
            <!-- Media Upload Fields -->
            <VCol
              v-if="formData.media_type === 'image' || formData.media_type === 'image_with_audio'"
              cols="12"
              md="6"
            >
              <VFileInput
                label="Upload Image"
                accept="image/*"
                :error-messages="formErrors.media"
                prepend-icon="tabler-upload"
                :hint="formData.media_url ? 'Image selected' : 'Select an image file'"
                persistent-hint
                @update:model-value="handleFileUpload"
              />
              <div
                v-if="formData.media_url"
                class="mt-2"
              >
                <img
                  :src="formData.media_url"
                  style="max-height: 150px; max-width: 100%;"
                >
              </div>
            </VCol>
            
            <!-- Audio URL for Image with Audio -->
            <VCol
              v-if="formData.media_type === 'image_with_audio'"
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.audio_url"
                label="Audio URL"
                placeholder="Enter URL for audio file"
                :error-messages="formErrors.audio_url"
              />
            </VCol>
            
            <VCol
              v-if="formData.media_type === 'video'"
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.media_url"
                label="Video URL"
                placeholder="Enter URL for video file"
                :error-messages="formErrors.media_url"
              />
            </VCol>
            
            <!-- Points -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.points"
                label="Points"
                type="number"
                min="1"
                :rules="[requiredValidator, integerValidator, v => v > 0 || 'Points must be at least 1']"
                :error-messages="formErrors.points"
              />
            </VCol>
            
            <!-- Tags -->
            <VCol
              cols="12"
              md="6"
            >
              <VRow no-gutters>
                <VCol cols="9">
                  <AppTextField
                    v-model="newTag"
                    label="Tags"
                    class="me-2"
                    placeholder="Add tags (press Enter)"
                    @keyup.enter="addTag"
                  />
                </VCol>
                <VCol cols="3">
                  <VBtn
                    width="100%"
                    color="primary"
                    class="mt-6"
                    @click="addTag"
                  >
                    Add Tag
                  </VBtn>
                </VCol>
              </VRow>
              
              <div class="d-flex flex-wrap gap-1 mt-2">
                <VChip
                  v-for="(tag, index) in formData.tags"
                  :key="index"
                  closable
                  @click:close="removeTag(tag)"
                >
                  {{ tag }}
                </VChip>
              </div>
            </VCol>
            
            <!-- Correct Feedback -->
            <VCol cols="12">
              <AppTextarea
                v-model="formData.correct_feedback"
                label="Correct Feedback (Optional)"
                placeholder="Message to show when the user answers correctly"
                rows="2"
                :error-messages="formErrors.correct_feedback"
              />
            </VCol>
            
            <!-- Incorrect Feedback -->
            <VCol cols="12">
              <AppTextarea
                v-model="formData.incorrect_feedback"
                label="Incorrect Feedback (Optional)"
                placeholder="Message to show when the user answers incorrectly"
                rows="2"
                :error-messages="formErrors.incorrect_feedback"
              />
            </VCol>
            
            <!-- Multiple Choice Options (show only for MCQ type) -->
            <VCol
              v-if="formData.type === 'mcq'"
              cols="12"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>Multiple Choice Options</h4>
                <VBtn
                  size="small"
                  prepend-icon="tabler-plus"
                  @click="addOption"
                >
                  Add Option
                </VBtn>
              </div>
              <VInput
                :model-value="formData"
                :rules="[
                  () => formData.options.length >= 2 || 'MCQ questions must have at least 2 options.',
                  () => formData.correct_answer.length > 0 || 'Please select at least one correct answer.'
                ]"
                class="mt-2"
              />
              
              <VAlert
                v-if="formErrors.options"
                color="error"
                variant="tonal"
                class="mb-2"
              >
                {{ formErrors.options }}
              </VAlert>
              
              <VAlert
                v-if="formErrors.correct_answer"
                color="error"
                variant="tonal"
                class="mb-2"
              >
                {{ formErrors.correct_answer }}
              </VAlert>
              
              <div
                v-for="(option, index) in formData.options"
                :key="index"
                class="d-flex align-center mb-2"
              >
                <VCheckbox
                  :model-value="isCorrectAnswer(index)"
                  label="Correct"
                  color="success"
                  class="me-2"
                  hide-details
                  @update:model-value="toggleCorrectAnswer(index)"
                />
                
                <AppTextField
                  v-model="formData.options[index]"
                  :placeholder="`Option ${index + 1}`"
                  class="flex-grow-1"
                  :rules="[requiredValidator]"
                  hide-details
                />
                
                <VBtn
                  icon
                  variant="text"
                  color="error"
                  size="small"
                  class="ms-2"
                  @click="removeOption(index)"
                >
                  <VIcon icon="tabler-trash" />
                </VBtn>
              </div>
              
              <VBtn
                v-if="!formData.options.length"
                block
                variant="outlined"
                @click="addOption"
              >
                Add First Option
              </VBtn>
            </VCol>
            
            <!-- Fill in the Blank -->
            <VCol
              v-if="formData.type === 'fill_blank'"
              cols="12"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>Fill in the Blank Answers</h4>
              </div>

              <VAlert
                color="info"
                variant="tonal"
                class="mb-4"
              >
                For each blank, enter all possible correct answers, with each answer on a new line.
              </VAlert>

              <div v-if="detectedBlanks.length > 0">
                <div
                  v-for="(blank, index) in detectedBlanks"
                  :key="blank"
                  class="mb-4"
                >
                  <AppTextarea
                    :model-value="Array.isArray(formData.correct_answer[index]) ? formData.correct_answer[index].join('\n') : ''"
                    :label="`Answers for ${blank}`"
                    placeholder="Enter possible answers, one per line..."
                    rows="3"
                    @update:model-value="formData.correct_answer[index] = $event.split('\n')"
                  />
                </div>
              </div>

              <div v-else>
                <p class="text-medium-emphasis">
                  No blanks detected. Please add blanks like <code>[blank1]</code> to the question text above.
                </p>
              </div>
            </VCol>
            
            <!-- Fill in the Blank with Choices -->
            <VCol
              v-if="formData.type === 'fill_blank_choices'"
              cols="12"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>Fill in the Blank with Choices</h4>
              </div>
              
              <VAlert
                color="info"
                variant="tonal"
                class="mb-4"
              >
                <p>Use [blank1], [blank2], etc. in your question text to indicate blanks.</p>
                <p>Then define options for each blank below.</p>
              </VAlert>
              
              <div class="mb-4">
                <h4 class="mb-2">
                  Blanks and Options
                </h4>
                
                <div 
                  v-for="(blank, blankIndex) in formData.blanks || []" 
                  :key="blankIndex"
                  class="mb-4 pa-4 border rounded"
                >
                  <div class="d-flex justify-space-between align-center mb-2">
                    <h5>{{ detectedBlanks[blankIndex] || `Blank #${blankIndex + 1}` }}</h5>
                  </div>
                  
                  <div class="mb-2">
                    <VRow>
                      <VCol cols="12">
                        <AppTextField
                          v-model="blank.placeholder"
                          label="Placeholder text (optional)"
                          placeholder="e.g. 'Select the correct city'"
                        />
                      </VCol>
                    </VRow>
                  </div>
                  
                  <div>
                    <h6 class="mb-2">
                      Options
                    </h6>
                    <VRadioGroup
                      v-model="blank.correct_answer"
                      hide-details
                    >
                      <div
                        v-for="(option, optIndex) in blank.options"
                        :key="optIndex"
                        class="d-flex align-center mb-2"
                      >
                        <VRadio
                          :value="optIndex.toString()"
                          color="success"
                          class="me-2"
                          hide-details
                        />
                        
                        <AppTextField
                          v-model="blank.options[optIndex]"
                          :placeholder="`Option ${optIndex + 1}`"
                          class="flex-grow-1"
                          hide-details
                        />
                        
                        <VBtn
                          icon
                          variant="text"
                          color="error"
                          size="small"
                          class="ms-2"
                          @click="removeBlankOption(blankIndex, optIndex)"
                        >
                          <VIcon icon="tabler-trash" />
                        </VBtn>
                      </div>
                    </VRadioGroup>
                    
                    <VBtn
                      size="small"
                      prepend-icon="tabler-plus"
                      class="mt-2"
                      @click="addBlankOption(blankIndex)"
                    >
                      Add Option
                    </VBtn>
                  </div>
                </div>
              </div>
            </VCol>
            
            <!-- Matching -->
            <VCol
              v-if="formData.type === 'matching'"
              cols="12"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>Matching Pairs</h4>
              </div>
              <VInput
                :model-value="formData.matching_pairs"
                :rules="[v => v.length >= 2 || 'Matching questions must have at least 2 pairs.']"
                class="mt-2"
              />
              
              <div class="mb-4">
                <div 
                  v-for="(pair, pairIndex) in formData.matching_pairs || []" 
                  :key="pairIndex"
                  class="d-flex align-center mb-2"
                >
                  <AppTextField
                    v-model="pair.left"
                    placeholder="Left item"
                    class="flex-grow-1 me-2"
                    hide-details
                  />
                  
                  <VIcon
                    icon="tabler-arrow-right"
                    class="mx-2"
                  />
                  
                  <AppTextField
                    v-model="pair.right"
                    placeholder="Right item"
                    class="flex-grow-1 me-2"
                    hide-details
                  />
                  
                  <VBtn
                    icon
                    variant="text"
                    color="error"
                    size="small"
                    @click="removeMatchingPair(pairIndex)"
                  >
                    <VIcon icon="tabler-trash" />
                  </VBtn>
                </div>
                
                <VBtn
                  v-if="!formData.matching_pairs?.length"
                  block
                  variant="outlined"
                  class="mt-2"
                  @click="addMatchingPair"
                >
                  Add First Pair
                </VBtn>
                <VBtn
                  v-else
                  prepend-icon="tabler-plus"
                  class="mt-2"
                  @click="addMatchingPair"
                >
                  Add Pair
                </VBtn>
              </div>
            </VCol>
            
            <!-- Reordering -->
            <VCol
              v-if="formData.type === 'reordering'"
              cols="12"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>Reordering Items</h4>
                <p class="text-caption">
                  Add items in the correct order. They will be randomized for the student.
                </p>
              </div>
              <VInput
                :model-value="formData.reordering_items"
                :rules="[v => v.length >= 2 || 'Reordering questions must have at least 2 items.']"
                class="mt-2"
              />
              <div class="mb-4">
                <div 
                  v-for="(item, itemIndex) in formData.reordering_items || []" 
                  :key="itemIndex"
                  class="d-flex align-center mb-2"
                >
                  <div class="me-2 pa-2 bg-primary-lighten-5 rounded">
                    {{ itemIndex + 1 }}
                  </div>
                  
                  <AppTextField
                    v-model="formData.reordering_items[itemIndex]"
                    :placeholder="`Item ${itemIndex + 1}`"
                    class="flex-grow-1"
                    hide-details
                  />
                  
                  <div class="d-flex">
                    <VBtn
                      icon
                      variant="text"
                      color="primary"
                      size="small"
                      class="ms-2"
                      :disabled="itemIndex === 0"
                      @click="moveReorderingItem(itemIndex, 'up')"
                    >
                      <VIcon icon="tabler-chevron-up" />
                    </VBtn>
                    
                    <VBtn
                      icon
                      variant="text"
                      color="primary"
                      size="small"
                      :disabled="itemIndex === formData.reordering_items.length - 1"
                      @click="moveReorderingItem(itemIndex, 'down')"
                    >
                      <VIcon icon="tabler-chevron-down" />
                    </VBtn>
                    
                    <VBtn
                      icon
                      variant="text"
                      color="error"
                      size="small"
                      @click="removeReorderingItem(itemIndex)"
                    >
                      <VIcon icon="tabler-trash" />
                    </VBtn>
                  </div>
                </div>
                
                <VBtn
                  prepend-icon="tabler-plus"
                  class="mt-2"
                  @click="addReorderingItem"
                >
                  Add Item
                </VBtn>
              </div>
            </VCol>
            
            <!-- Writing -->
            <VCol
              v-if="formData.type === 'writing'"
              cols="12"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>Writing Question</h4>
              </div>
              
              <VAlert
                color="info"
                variant="tonal"
                class="mb-4"
              >
                Writing questions require manual grading. You can provide guidelines for grading below.
              </VAlert>
              
              <AppTextarea
                v-model="formData.grading_guidelines"
                label="Grading Guidelines"
                placeholder="Enter guidelines for grading this writing question"
                rows="4"
              />
              
              <VRow class="mt-4">
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="formData.min_words"
                    label="Minimum Words"
                    type="number"
                    min="0"
                  />
                </VCol>
                
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="formData.max_words"
                    label="Maximum Words"
                    type="number"
                    min="0"
                  />
                </VCol>
              </VRow>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
      
      <VCardText class="d-flex justify-end flex-wrap gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        
        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="submitForm"
        >
          {{ formData.id ? 'Update' : 'Create' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
