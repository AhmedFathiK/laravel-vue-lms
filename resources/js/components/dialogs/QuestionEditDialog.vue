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
})

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
  }
}, { immediate: true, deep: true })

// Close dialog and reset form
const closeDialog = () => {
  emit('update:isDialogVisible', false)
  formErrors.value = {}
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
  if (newType !== 'mcq') {
    formData.value.options = []
    formData.value.correct_answer = []
  }
})
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="800"
    persistent
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <VCard>
      <!-- Dialog Close Btn -->
      <DialogCloseBtn @click="closeDialog" />
      
      <VCardItem>
        <VCardTitle>{{ dialogTitle }}</VCardTitle>
      </VCardItem>
      
      <VDivider />
      
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
            
            <!-- Explanation -->
            <VCol cols="12">
              <AppTextarea
                v-model="formData.explanation.en"
                label="Explanation (Optional)"
                placeholder="Enter explanation for the correct answer"
                rows="3"
              />
            </VCol>
            
            <!-- Media URL -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.media_url"
                label="Media URL (Optional)"
                placeholder="Enter URL for image, audio, or video"
              />
            </VCol>
            
            <!-- Media Type -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.media_type"
                label="Media Type"
                :items="[
                  { title: 'None', value: null },
                  { title: 'Image', value: 'image' },
                  { title: 'Audio', value: 'audio' },
                  { title: 'Video', value: 'video' },
                ]"
                item-title="title"
                item-value="value"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
      
      <VDivider />
      
      <VCardText class="d-flex justify-end pt-5">
        <VBtn
          variant="tonal"
          color="secondary"
          class="me-4"
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        
        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="submitForm"
        >
          Save
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template> 
