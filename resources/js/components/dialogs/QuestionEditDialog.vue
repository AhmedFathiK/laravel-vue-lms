<script setup>
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  question: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'submit',
])

const { t, locale } = useI18n()
const isSubmitting = ref(false)
const formErrors = ref({})

// Form data with default values
const formData = ref({
  id: null,
  course_id: null,
  level_id: null,
  lesson_id: null,
  question_text: { en: '' },
  type: 'mcq',
  options: [],
  correct_answer: [],
  points: 1,
  difficulty: 'medium',
  tags: [],
  explanation: { en: '' },
  media_url: null,
  media_type: null,

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

// Text area for fill in the blank answers (one per line)
const blankAnswersText = ref('')

// Update fill in the blank answers from textarea
const updateBlankAnswers = text => {
  // Split by new lines and filter empty lines
  const answers = text.split('\n').filter(line => line.trim() !== '')

  formData.value.correct_answer = answers
}

// Initialize blank answers text from existing data
const initializeBlankAnswersText = () => {
  if (formData.value.type === 'fill_blank' && Array.isArray(formData.value.correct_answer)) {
    blankAnswersText.value = formData.value.correct_answer.join('\n')
  } else {
    blankAnswersText.value = ''
  }
}

// Add a new blank for fill in the blank with choices
const addBlank = () => {
  if (!formData.value.blanks) {
    formData.value.blanks = []
  }
  
  formData.value.blanks.push({
    placeholder: '',
    options: ['', ''],
    correct_answer: '0', // Default first option as correct
  })
}

// Remove a blank
const removeBlank = index => {
  formData.value.blanks.splice(index, 1)
}

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
  if (type === 'fill_blank') {
    // For fill in the blank, initialize the text area with correct answers
    initializeBlankAnswersText()
  } else if (type === 'fill_blank_choices') {
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

// Difficulty levels
const difficultyLevels = [
  { title: 'Easy', value: 'easy' },
  { title: 'Medium', value: 'medium' },
  { title: 'Hard', value: 'hard' },
]

// Watch for changes in the question prop
watch(() => props.question, newQuestion => {
  if (newQuestion) {
    // Reset form errors
    formErrors.value = {}
    
    // Reset newTag input
    newTag.value = ''
    
    // Clone to avoid direct modification of props
    formData.value = JSON.parse(JSON.stringify(newQuestion))
    
    // Ensure question_text has required structure
    if (!formData.value.question_text) {
      formData.value.question_text = { en: '' }
    }
    
    // Ensure explanation has required structure
    if (!formData.value.explanation) {
      formData.value.explanation = { en: '' }
    }
    
    // Ensure options is an array
    if (!Array.isArray(formData.value.options)) {
      formData.value.options = []
    }
    
    // Ensure correct_answer is an array
    if (!Array.isArray(formData.value.correct_answer)) {
      formData.value.correct_answer = []
    }
    
    // Ensure tags is an array
    if (!Array.isArray(formData.value.tags)) {
      formData.value.tags = []
    }
    
    // Ensure points has a valid value
    if (!formData.value.points) {
      formData.value.points = 1
    }
    
    // Initialize data for specific question types
    initializeQuestionTypeData()
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
  // Basic validation
  formErrors.value = {}
  
  if (!formData.value.question_text?.en?.trim()) {
    formErrors.value.question_text = 'Question text is required'
    
    return
  }
  
  // Type-specific validation
  if (formData.value.type === 'mcq') {
    if (!formData.value.options || formData.value.options.length < 2) {
      formErrors.value.options = 'At least 2 options are required'
      
      return
    }
    
    if (!formData.value.correct_answer || formData.value.correct_answer.length === 0) {
      formErrors.value.correct_answer = 'At least one correct answer is required'
      
      return
    }
  }
  
  // Set submitting state
  isSubmitting.value = true
  
  try {
    // Emit submit event with form data
    emit('submit', { ...formData.value })
    
    // Close dialog
    closeDialog()
  } catch (error) {
    console.error('Error submitting question:', error)

    // Show generic error
    formErrors.value.general = 'Failed to save question'
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

// Handle tag input
const newTag = ref('')

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
        
        <VForm @submit.prevent="submitForm">
          <VRow>
            <!-- Question Text -->
            <VCol cols="12">
              <AppTextField
                v-model="formData.question_text.en"
                label="Question Text"
                placeholder="Enter the question text"
                :error-messages="formErrors.question_text"
                autofocus
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
                    placeholder="Add tags (press Enter)"
                    @keyup.enter="addTag"
                  />
                </VCol>
                <VCol cols="3">
                  <VBtn
                    color="primary"
                    class="ml-2 mt-1"
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
                <h4>Fill in the Blank</h4>
                <p class="text-caption">
                  Use [blank] in your question text to indicate where blanks should appear.
                </p>
              </div>
              
              <VAlert
                color="info"
                variant="tonal"
                class="mb-4"
              >
                <p>Example: "The capital of France is [blank]."</p>
                <p>Then define the correct answers below.</p>
              </VAlert>
              
              <h4 class="mb-2">
                Correct Answers
              </h4>
              <p class="text-caption mb-2">
                Add all possible correct answers, one per line:
              </p>
              
              <AppTextarea
                v-model="blankAnswersText"
                rows="4"
                placeholder="Enter one correct answer per line (e.g. Paris)"
                @update:model-value="updateBlankAnswers"
              />
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
                    <h5>Blank #{{ blankIndex + 1 }}</h5>
                    <VBtn
                      size="small"
                      color="error"
                      variant="text"
                      icon
                      @click="removeBlank(blankIndex)"
                    >
                      <VIcon icon="tabler-trash" />
                    </VBtn>
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
                
                <VBtn
                  prepend-icon="tabler-plus"
                  class="mt-2"
                  @click="addBlank"
                >
                  Add Blank
                </VBtn>
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
