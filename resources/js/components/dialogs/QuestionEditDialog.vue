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
  courseId: null,
  title: '',
  questionText: '',
  type: 'mcq',
  options: [],
  correctAnswer: [],
  points: 1,
  difficulty: 'medium',
  tags: [],

  correctFeedback: '',
  incorrectFeedback: '',
  mediaUrl: null,
  mediaType: 'none',

  // For fill in the blank
  blanks: [],

  // For matching
  matchingPairs: [],

  // For reordering
  reorderingItems: [],

  // For writing
  gradingGuidelines: '',
  minWords: 0,
  maxWords: 0,
})

// Handle tag input
const newTag = ref('')

// Computed property to detect blanks for fill_blank type
const detectedBlanks = computed(() => {
  if (!['fill_blank', 'fill_blank_choices'].includes(formData.value.type))
    return []

  const regex = /\[blank\d+\]/g
  const matches = formData.value.questionText.match(regex) || []
  const uniqueMatches = [...new Set(matches)]

  // Sort by the number inside [blankX]
  uniqueMatches.sort((a, b) => {
    const numA = parseInt(a.match(/\d+/)[0], 10)
    const numB = parseInt(b.match(/\d+/)[0], 10)
    
    return numA - numB
  })

  return uniqueMatches
})

// Watch for changes in detected blanks and update the correctAnswer array
watch(detectedBlanks, (newBlanks, oldBlanks) => {
  if (formData.value.type === 'fill_blank') {
    const newSize = newBlanks.length
    const currentAnswers = Array.isArray(formData.value.correctAnswer) ? formData.value.correctAnswer : []

    // Only update if the size of blanks has changed
    if (newSize !== currentAnswers.length) {
      const newAnswers = Array(newSize).fill(null).map(() => [])

      // Preserve existing answers
      for (let i = 0; i < Math.min(newSize, currentAnswers.length); i++) {
        // Ensure the preserved answer is an array
        newAnswers[i] = Array.isArray(currentAnswers[i]) ? currentAnswers[i] : [currentAnswers[i]]
      }
      formData.value.correctAnswer = newAnswers
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
          correctAnswer: '0',
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
  if (blank.correctAnswer === optionIndex.toString()) {
    blank.correctAnswer = '0' // Default to first option
  } else if (parseInt(blank.correctAnswer) > optionIndex) {
    // Adjust correct answer index for removed option
    blank.correctAnswer = (parseInt(blank.correctAnswer) - 1).toString()
  }
}

// Add a matching pair
const addMatchingPair = () => {
  if (!formData.value.matchingPairs) {
    formData.value.matchingPairs = []
  }
  
  formData.value.matchingPairs.push({
    left: '',
    right: '',
  })
  
  // Update correctAnswer to match the pairs
  updateMatchingCorrectAnswers()
}

// Remove a matching pair
const removeMatchingPair = index => {
  formData.value.matchingPairs.splice(index, 1)
  
  // Update correctAnswer to match the pairs
  updateMatchingCorrectAnswers()
}

// Update the correct answers for matching pairs
const updateMatchingCorrectAnswers = () => {
  if (!formData.value.matchingPairs) return
  
  // For matching, correctAnswer should contain the mapping
  formData.value.correctAnswer = formData.value.matchingPairs.map((pair, index) => ({
    left: index,
    right: index,
  }))
}

// Add a reordering item
const addReorderingItem = () => {
  if (!formData.value.reorderingItems) {
    formData.value.reorderingItems = []
  }
  
  formData.value.reorderingItems.push('')
  
  // Update correctAnswer to match the order
  updateReorderingCorrectAnswers()
}

// Remove a reordering item
const removeReorderingItem = index => {
  formData.value.reorderingItems.splice(index, 1)
  
  // Update correctAnswer to match the order
  updateReorderingCorrectAnswers()
}

// Move a reordering item up or down
const moveReorderingItem = (index, direction) => {
  const items = formData.value.reorderingItems
  
  if (direction === 'up' && index > 0) {
    // Swap with the item above
    [items[index], items[index - 1]] = [items[index - 1], items[index]]
  } else if (direction === 'down' && index < items.length - 1) {
    // Swap with the item below
    [items[index], items[index + 1]] = [items[index + 1], items[index]]
  }
  
  // Update correctAnswer to match the order
  updateReorderingCorrectAnswers()
}

// Update the correct answers for reordering
const updateReorderingCorrectAnswers = () => {
  if (!formData.value.reorderingItems) return
  
  // For reordering, correctAnswer should contain the indices in correct order
  formData.value.correctAnswer = formData.value.reorderingItems.map((_, index) => index.toString())
}

// Initialize data for specific question types
const initializeQuestionTypeData = () => {
  const type = formData.value.type
  
  // For writing type, extract data from options FIRST before clearing
  if (type === 'writing' && formData.value.options && typeof formData.value.options === 'object') {
    if (!formData.value.gradingGuidelines && formData.value.options.gradingGuidelines) {
      formData.value.gradingGuidelines = formData.value.options.gradingGuidelines
    }
    
    if (!formData.value.minWords && formData.value.options.minWords) {
      formData.value.minWords = formData.value.options.minWords
    }
    
    if (!formData.value.maxWords && formData.value.options.maxWords) {
      formData.value.maxWords = formData.value.options.maxWords
    }
  }
  
  // Clear existing data if not defined
  if (!Array.isArray(formData.value.options)) {
    formData.value.options = []
  }
  
  if (!Array.isArray(formData.value.correctAnswer)) {
    formData.value.correctAnswer = []
  }
  
  if (!Array.isArray(formData.value.blanks)) {
    formData.value.blanks = []
  }
  
  if (!Array.isArray(formData.value.matchingPairs)) {
    formData.value.matchingPairs = []
  }
  
  if (!Array.isArray(formData.value.reorderingItems)) {
    formData.value.reorderingItems = []
  }
  
  // Initialize specific type data
  if (type === 'fill_blank_choices') {
    // For fill in the blank with choices, initialize blanks from options
    if (!formData.value.blanks.length && Array.isArray(formData.value.options) && formData.value.options.length > 0) {
      formData.value.blanks = formData.value.options
    }

  } else if (type === 'matching') {
    // For matching, initialize pairs from options
    if (!formData.value.matchingPairs.length && Array.isArray(formData.value.options) && formData.value.options.length > 0) {
      formData.value.matchingPairs = formData.value.options
    } else if (!formData.value.matchingPairs.length) {
      // Add default pair if none exists
      addMatchingPair()
    }
  } else if (type === 'reordering') {
    // For reordering, initialize items from options
    if (!formData.value.reorderingItems.length && Array.isArray(formData.value.options) && formData.value.options.length > 0) {
      formData.value.reorderingItems = formData.value.options
    } else if (!formData.value.reorderingItems.length) {
      // Add default item if none exists
      addReorderingItem()
    }
  } else if (type === 'writing') {
    
    // For writing, initialize grading guidelines and word limits from options
    if (!formData.value.gradingGuidelines && formData.value.options && formData.value.options.gradingGuidelines) {
      formData.value.gradingGuidelines = formData.value.options.gradingGuidelines
    }
    
    if (!formData.value.minWords && formData.value.options && formData.value.options.minWords) {
      formData.value.minWords = formData.value.options.minWords
    }
    
    if (!formData.value.maxWords && formData.value.options && formData.value.options.maxWords) {
      formData.value.maxWords = formData.value.options.maxWords
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
    formData.value.mediaUrl = URL.createObjectURL(mediaFile.value)
  }
}

// Clear media when media type changes
watch(() => formData.value.mediaType, newValue => {
  if (newValue === 'none') {
    formData.value.mediaUrl = null
    formData.value.audioUrl = null
    mediaFile.value = null
  } else if (newValue === 'video') {
    // Clear file upload data when switching to video (URL only)
    mediaFile.value = null
  } else if (newValue !== 'image_with_audio') {
    // Clear audio URL when switching to a type that doesn't use audio
    formData.value.audioUrl = null
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
    courseId: props.courseId,
    title: '',
    questionText: '',   
    type: 'mcq',
    options: [],
    correctAnswer: [],
    points: 1,
    difficulty: 'medium',
    tags: [],
    correctFeedback: '',
    incorrectFeedback: '',
    mediaUrl: null,
    mediaType: 'none',
    audioUrl: null,
    blanks: [],
    matchingPairs: [],
    reorderingItems: [],
    gradingGuidelines: '',
    minWords: 0,
    maxWords: 0,
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
    console.log(newQuestion)
    console.log(formData.value )
    


    // Ensure tags is an array
    if (!formData.value.tags) {
      formData.value.tags = []
    }

    // Ensure correctAnswer is an array for mcq
    if (formData.value.type === 'mcq' && !Array.isArray(formData.value.correctAnswer)) {
      formData.value.correctAnswer = []
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

function appendFormData(formData, key, value) {
  if (value === null || value === undefined) return

  // If the value is an array, recurse through it
  if (Array.isArray(value)) {
    value.forEach((item, index) => {
      appendFormData(formData, `${key}[${index}]`, item)
    })
    
    return
  }

  // If the value is an object (matchingPairs, correctAnswer)
  if (typeof value === "object") {
    Object.entries(value).forEach(([childKey, childValue]) => {
      appendFormData(formData, `${key}[${childKey}]`, childValue)
    })
    
    return
  }

  // Primitive value (string/number/bool)
  formData.append(key, value)
}


// Submit form
const submitForm = async () => {
  if (formRef.value) {
    const { valid } = await formRef.value.validate()
    if (!valid) return
  }

  isSubmitting.value = true
  formErrors.value = {}

  try {
    const questionData = formData.value
    const formDataObj = new FormData()

    Object.entries(questionData).forEach(([key, value]) => {
      if (key !== "mediaFile") {
        appendFormData(formDataObj, key, value)
      }
    })

    // Handle media upload
    if (
      mediaFile.value &&
      (questionData.mediaType === 'image' || questionData.mediaType === 'image_with_audio')
    ) {
      formDataObj.append('media', mediaFile.value)
    }

    // Update or create
    if (questionData.id) {
      formDataObj.append('_method', 'PUT')
      await api.post(`/admin/courses/${props.courseId}/questions/${questionData.id}`, formDataObj)
      toast.success('Question updated successfully')
    } else {
      formDataObj.append('courseId', props.courseId)

      await api.post(`/admin/courses/${props.courseId}/questions`, formDataObj)
      toast.success('Question created successfully')
    }

    emit('refresh')
    closeDialog()
  } catch (error) {
    if (error.response?.status === 422) {
      formErrors.value = error.response.data.errors || {}
      toast.error('Please correct the validation errors.')
    } else {
      toast.error(error.response?.data?.message || 'Failed to save question')
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
  if (formData.value.correctAnswer.includes(index.toString())) {
    const answerIndex = formData.value.correctAnswer.indexOf(index.toString())

    formData.value.correctAnswer.splice(answerIndex, 1)
  }
  
  // Adjust correct answers for removed option
  formData.value.correctAnswer = formData.value.correctAnswer
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
  const correctAnswers = formData.value.correctAnswer || []
  
  if (correctAnswers.includes(indexStr)) {
    // Remove from correct answers
    const answerIndex = correctAnswers.indexOf(indexStr)

    formData.value.correctAnswer.splice(answerIndex, 1)
  } else {
    // Add to correct answers
    formData.value.correctAnswer.push(indexStr)
  }
}

// Check if an option is marked as correct
const isCorrectAnswer = index => {
  return formData.value.correctAnswer?.includes(index.toString())
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
                v-model="formData.questionText"
                label="Question Text"
                :rules="[requiredValidator]"
                :error-messages="formErrors.questionText"
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
                v-model="formData.mediaType"
                label="Media Type"
                :items="mediaTypes"
                item-title="title"
                item-value="value"
                :error-messages="formErrors.mediaType"
                class="mb-3"
              />
            </VCol>
            
            <!-- Media Upload Fields -->
            <VCol
              v-if="formData.mediaType === 'image' || formData.mediaType === 'image_with_audio'"
              cols="12"
              md="6"
            >
              <VFileInput
                label="Upload Image"
                accept="image/*"
                :error-messages="formErrors.media"
                prepend-icon="tabler-upload"
                :hint="formData.mediaUrl ? 'Image selected' : 'Select an image file'"
                persistent-hint
                @update:model-value="handleFileUpload"
              />
              <div
                v-if="formData.mediaUrl"
                class="mt-2"
              >
                <img
                  :src="formData.mediaUrl"
                  style="max-height: 150px; max-width: 100%;"
                >
              </div>
            </VCol>
            
            <!-- Audio URL for Image with Audio -->
            <VCol
              v-if="formData.mediaType === 'image_with_audio'"
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.audioUrl"
                label="Audio URL"
                placeholder="Enter URL for audio file"
                :error-messages="formErrors.audioUrl"
              />
            </VCol>
            
            <VCol
              v-if="formData.mediaType === 'video'"
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.mediaUrl"
                label="Video URL"
                placeholder="Enter URL for video file"
                :error-messages="formErrors.mediaUrl"
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
                v-model="formData.correctFeedback"
                label="Correct Feedback (Optional)"
                placeholder="Message to show when the user answers correctly"
                rows="2"
                :error-messages="formErrors.correctFeedback"
              />
            </VCol>
            
            <!-- Incorrect Feedback -->
            <VCol cols="12">
              <AppTextarea
                v-model="formData.incorrectFeedback"
                label="Incorrect Feedback (Optional)"
                placeholder="Message to show when the user answers incorrectly"
                rows="2"
                :error-messages="formErrors.incorrectFeedback"
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
                :key="`mcq-validation-${formData.options.length}-${formData.correctAnswer.length}`"
                :model-value="formData"
                :rules="[
                  () => formData.options.length >= 2 || 'MCQ questions must have at least 2 options.',
                  () => formData.correctAnswer.length > 0 || 'Please select at least one correct answer.'
                ]"
                class="mb-2"
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
                v-if="formErrors.correctAnswer"
                color="error"
                variant="tonal"
                class="mb-2"
              >
                {{ formErrors.correctAnswer }}
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
                    :model-value="Array.isArray(formData.correctAnswer[index]) ? formData.correctAnswer[index].join('\n') : ''"
                    :label="`Answers for ${blank}`"
                    placeholder="Enter possible answers, one per line..."
                    rows="3"
                    @update:model-value="formData.correctAnswer[index] = $event.split('\n')"
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
                      v-model="blank.correctAnswer"
                      hide-details
                    >
                      <VInput
                        :key="`blank-options-validation-${blankIndex}-${blank.options.length}`"
                        :model-value="blank.options"
                        :rules="[v => v.length >= 2 || 'There must be at least 2 options.']"
                        class="mb-2"
                      />
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

                <div v-if="formData.blanks.length < 1">
                  <p class="text-medium-emphasis">
                    No blanks detected. Please add blanks like <code>[blank1]</code> to the question text above.
                  </p>
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
                :key="`matching-validation-${formData.matchingPairs.length}`"
                :model-value="formData.matchingPairs"
                :rules="[v => v.length >= 2 || 'Matching questions must have at least 2 pairs.']"
                class="mt-2"
              />
              
              <div class="mb-4">
                <div 
                  v-for="(pair, pairIndex) in formData.matchingPairs || []" 
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
                  v-if="!formData.matchingPairs?.length"
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
                :key="`reordering-validation-${formData.reorderingItems.length}`"
                :model-value="formData.reorderingItems"
                :rules="[v => v.length >= 2 || 'Reordering questions must have at least 2 items.']"
                class="mt-2"
              />
              <div class="mb-4">
                <div 
                  v-for="(item, itemIndex) in formData.reorderingItems || []" 
                  :key="itemIndex"
                  class="d-flex align-center mb-2"
                >
                  <div class="me-2 pa-2 bg-primary-lighten-5 rounded">
                    {{ itemIndex + 1 }}
                  </div>
                  
                  <AppTextField
                    v-model="formData.reorderingItems[itemIndex]"
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
                      :disabled="itemIndex === formData.reorderingItems.length - 1"
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
                v-model="formData.gradingGuidelines"
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
                    v-model="formData.minWords"
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
                    v-model="formData.maxWords"
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
