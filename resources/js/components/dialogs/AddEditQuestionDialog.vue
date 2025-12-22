<script setup>
import { integerValidator, requiredValidator } from '@/@core/utils/validators'
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  dialogMode: {
    type: String,
    default: 'add',
    validator: value => ['add', 'edit'].includes(value),
  },
  question: {
    type: Object,
    default: () => ({}),
  },
  courseId: {
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'refresh'])

const { t } = useI18n()
const toast = useToast()
const formRef = ref(null)
const isFormValid = ref(true)

// Local question state
const localQuestion = ref({})

// Handle tag input
const newTag = ref('')

// For file uploads
const mediaFile = ref(null)

// Get default question data
const getDefaultQuestion = () => ({
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
})

// Computed dialog title
const dialogTitle = computed(() =>
  props.dialogMode === 'add'
    ? t('questions.dialog.addNewQuestion', 'Add New Question')
    : t('questions.dialog.editQuestion', 'Edit Question'),
)

// Question types
const questionTypes = computed(() => [
  { title: t('questions.types.multipleChoice', 'Multiple Choice'), value: 'mcq' },
  { title: t('questions.types.matching', 'Matching'), value: 'matching' },
  { title: t('questions.types.fillBlank', 'Fill in the Blank'), value: 'fill_blank' },
  { title: t('questions.types.fillBlankChoices', 'Fill in the Blank with Choices'), value: 'fill_blank_choices' },
  { title: t('questions.types.reordering', 'Reordering'), value: 'reordering' },
  { title: t('questions.types.writing', 'Writing'), value: 'writing' },
])

const mediaTypes = computed(() => [
  { title: t('questions.media.none', 'None'), value: 'none' },
  { title: t('questions.media.image', 'Image'), value: 'image' },
  { title: t('questions.media.imageWithAudio', 'Image with Audio'), value: 'image_with_audio' },
  { title: t('questions.media.video', 'Video'), value: 'video' },
])

const difficultyLevels = computed(() => [
  { title: t('questions.difficulty.easy', 'Easy'), value: 'easy' },
  { title: t('questions.difficulty.medium', 'Medium'), value: 'medium' },
  { title: t('questions.difficulty.hard', 'Hard'), value: 'hard' },
])

// Computed property to detect blanks for fill_blank type
const detectedBlanks = computed(() => {
  if (!['fill_blank', 'fill_blank_choices'].includes(localQuestion.value.type))
    return []

  const regex = /\[blank\d+\]/g
  const matches = localQuestion.value.questionText?.match(regex) || []
  const uniqueMatches = [...new Set(matches)]

  uniqueMatches.sort((a, b) => {
    const numA = parseInt(a.match(/\d+/)[0], 10)
    const numB = parseInt(b.match(/\d+/)[0], 10)
    
    return numA - numB
  })

  return uniqueMatches
})

// Watch for dialog visibility changes
watch(
  () => props.isDialogVisible,
  newValue => {
    if (newValue) {
      // formErrors reset handled by useCrudSubmit automatically on submit, but we can't access it here easily unless we extract it
      // actually validationErrors is exposed from useCrudSubmit, we can reset it if needed, but it resets on submit.
      // To reset on open, we might need to expose a reset function or just let it be.
      // useCrudSubmit doesn't expose reset.
      
      newTag.value = ''
      mediaFile.value = null
      
      if (props.dialogMode === 'edit' && props.question && Object.keys(props.question).length > 0) {
        localQuestion.value = JSON.parse(JSON.stringify(props.question))
        initializeQuestionTypeData()
      } else {
        localQuestion.value = getDefaultQuestion()
      }
      isFormValid.value = true
    } else {
      localQuestion.value = {}
    }
  },
  { immediate: true },
)

// Watch for changes in detected blanks - fill_blank
watch(detectedBlanks, newBlanks => {
  if (localQuestion.value.type === 'fill_blank') {
    const newSize = newBlanks.length
    const currentAnswers = Array.isArray(localQuestion.value.correctAnswer) ? localQuestion.value.correctAnswer : []

    if (newSize !== currentAnswers.length) {
      const newAnswers = Array(newSize).fill(null).map(() => [])
      for (let i = 0; i < Math.min(newSize, currentAnswers.length); i++) {
        newAnswers[i] = Array.isArray(currentAnswers[i]) ? currentAnswers[i] : [currentAnswers[i]]
      }
      localQuestion.value.correctAnswer = newAnswers
    }
  }
}, { immediate: true })

// Watch for changes in detected blanks - fill_blank_choices
watch(detectedBlanks, newBlanks => {
  if (localQuestion.value.type === 'fill_blank_choices') {
    const newSize = newBlanks.length
    const currentBlanks = Array.isArray(localQuestion.value.blanks) ? localQuestion.value.blanks : []

    if (newSize !== currentBlanks.length) {
      const newBlanksArray = Array(newSize).fill(null).map((_, index) => {
        return currentBlanks[index] || {
          placeholder: `Blank ${index + 1}`,
          options: ['', ''],
          correctAnswer: '0',
        }
      })

      localQuestion.value.blanks = newBlanksArray
    }
  }
}, { immediate: true })

// Watch for media type changes
watch(() => localQuestion.value.mediaType, newValue => {
  if (newValue === 'none') {
    localQuestion.value.mediaUrl = null
    localQuestion.value.audioUrl = null
    mediaFile.value = null
  } else if (newValue === 'video') {
    mediaFile.value = null
  } else if (newValue !== 'image_with_audio') {
    localQuestion.value.audioUrl = null
  }
})

// Watch for question type changes
watch(() => localQuestion.value.type, () => {
  initializeQuestionTypeData()
})

// Initialize data for specific question types
const initializeQuestionTypeData = () => {
  const type = localQuestion.value.type

  if (type === 'writing' && localQuestion.value.options && typeof localQuestion.value.options === 'object') {
    if (!localQuestion.value.gradingGuidelines && localQuestion.value.options.gradingGuidelines) {
      localQuestion.value.gradingGuidelines = localQuestion.value.options.gradingGuidelines
    }
    if (!localQuestion.value.minWords && localQuestion.value.options.minWords) {
      localQuestion.value.minWords = localQuestion.value.options.minWords
    }
    if (!localQuestion.value.maxWords && localQuestion.value.options.maxWords) {
      localQuestion.value.maxWords = localQuestion.value.options.maxWords
    }
  }

  if (!Array.isArray(localQuestion.value.options)) {
    localQuestion.value.options = []
  }
  if (!Array.isArray(localQuestion.value.correctAnswer)) {
    localQuestion.value.correctAnswer = []
  }
  if (!Array.isArray(localQuestion.value.blanks)) {
    localQuestion.value.blanks = []
  }
  if (!Array.isArray(localQuestion.value.matchingPairs)) {
    localQuestion.value.matchingPairs = []
  }
  if (!Array.isArray(localQuestion.value.reorderingItems)) {
    localQuestion.value.reorderingItems = []
  }

  if (type === 'fill_blank_choices') {
    if (!localQuestion.value.blanks.length && Array.isArray(localQuestion.value.options) && localQuestion.value.options.length > 0) {
      localQuestion.value.blanks = localQuestion.value.options
    }
  } else if (type === 'matching') {
    if (!localQuestion.value.matchingPairs.length && Array.isArray(localQuestion.value.options) && localQuestion.value.options.length > 0) {
      localQuestion.value.matchingPairs = localQuestion.value.options
    } else if (!localQuestion.value.matchingPairs.length) {
      addMatchingPair()
    }
  } else if (type === 'reordering') {
    if (!localQuestion.value.reorderingItems.length && Array.isArray(localQuestion.value.options) && localQuestion.value.options.length > 0) {
      localQuestion.value.reorderingItems = localQuestion.value.options
    } else if (!localQuestion.value.reorderingItems.length) {
      addReorderingItem()
    }
  }
}

// Handle file selection
const handleFileUpload = file => {
  mediaFile.value = file || null
  if (mediaFile.value) {
    localQuestion.value.mediaUrl = URL.createObjectURL(mediaFile.value)
  }
}

// Close dialog
const closeDialog = () => {
  emit('update:isDialogVisible', false)
}

// Prepare extra data for useCrudSubmit
const extraData = computed(() => {
  const data = {}
  
  if (
    mediaFile.value &&
    (localQuestion.value.mediaType === 'image' || localQuestion.value.mediaType === 'image_with_audio')
  ) {
    data.media = mediaFile.value
  }
  
  // courseId is already in localQuestion if not edited, but let's ensure it for add mode if missing?
  // Actually getDefaultQuestion sets it.
  
  return data
})

const customEmit = (event, ...args) => {
  if (event === 'saved') {
    emit('refresh', ...args)
  } else {
    emit(event, ...args)
  }
}

const { isLoading: isSubmitting, validationErrors: formErrors, onSubmit: submitForm } = useCrudSubmit({
  formRef: formRef,
  form: localQuestion,
  apiEndpoint: computed(() => props.dialogMode === 'edit'
    ? `/admin/courses/${props.courseId}/questions/${localQuestion.value.id}`
    : `/admin/courses/${props.courseId}/questions`),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  emit: customEmit,
  extraData,
  isFormData: true,
  successMessage: computed(() => props.dialogMode === 'edit' 
    ? t('questions.success.questionUpdated', 'Question updated successfully')
    : t('questions.success.questionCreated', 'Question created successfully')),
})

// MCQ functions
const addOption = () => {
  if (!localQuestion.value.options) {
    localQuestion.value.options = []
  }
  localQuestion.value.options.push('')
}

const removeOption = index => {
  localQuestion.value.options.splice(index, 1)
  if (localQuestion.value.correctAnswer.includes(index.toString())) {
    const answerIndex = localQuestion.value.correctAnswer.indexOf(index.toString())

    localQuestion.value.correctAnswer.splice(answerIndex, 1)
  }
  localQuestion.value.correctAnswer = localQuestion.value.correctAnswer.map(answer => {
    const answerNum = parseInt(answer)
    if (answerNum > index) {
      return (answerNum - 1).toString()
    }
    
    return answer
  })
}

const toggleCorrectAnswer = index => {
  const indexStr = index.toString()
  const correctAnswers = localQuestion.value.correctAnswer || []
  if (correctAnswers.includes(indexStr)) {
    const answerIndex = correctAnswers.indexOf(indexStr)

    localQuestion.value.correctAnswer.splice(answerIndex, 1)
  } else {
    localQuestion.value.correctAnswer.push(indexStr)
  }
}

const isCorrectAnswer = index => {
  return localQuestion.value.correctAnswer?.includes(index.toString())
}

// Fill blank with choices functions
const addBlankOption = blankIndex => {
  if (!localQuestion.value.blanks[blankIndex].options) {
    localQuestion.value.blanks[blankIndex].options = []
  }
  localQuestion.value.blanks[blankIndex].options.push('')
}

const removeBlankOption = (blankIndex, optionIndex) => {
  localQuestion.value.blanks[blankIndex].options.splice(optionIndex, 1)

  const blank = localQuestion.value.blanks[blankIndex]
  if (blank.correctAnswer === optionIndex.toString()) {
    blank.correctAnswer = '0'
  } else if (parseInt(blank.correctAnswer) > optionIndex) {
    blank.correctAnswer = (parseInt(blank.correctAnswer) - 1).toString()
  }
}

// Matching functions
const addMatchingPair = () => {
  if (!localQuestion.value.matchingPairs) {
    localQuestion.value.matchingPairs = []
  }
  localQuestion.value.matchingPairs.push({ left: '', right: '' })
  updateMatchingCorrectAnswers()
}

const removeMatchingPair = index => {
  localQuestion.value.matchingPairs.splice(index, 1)
  updateMatchingCorrectAnswers()
}

const updateMatchingCorrectAnswers = () => {
  if (!localQuestion.value.matchingPairs) return
  localQuestion.value.correctAnswer = localQuestion.value.matchingPairs.map((pair, index) => ({
    left: index,
    right: index,
  }))
}

// Reordering functions
const addReorderingItem = () => {
  if (!localQuestion.value.reorderingItems) {
    localQuestion.value.reorderingItems = []
  }
  localQuestion.value.reorderingItems.push('')
  updateReorderingCorrectAnswers()
}

const removeReorderingItem = index => {
  localQuestion.value.reorderingItems.splice(index, 1)
  updateReorderingCorrectAnswers()
}

const moveReorderingItem = (index, direction) => {
  const items = localQuestion.value.reorderingItems
  if (direction === 'up' && index > 0) {
    [items[index], items[index - 1]] = [items[index - 1], items[index]]
  } else if (direction === 'down' && index < items.length - 1) {
    [items[index], items[index + 1]] = [items[index + 1], items[index]]
  }
  updateReorderingCorrectAnswers()
}

const updateReorderingCorrectAnswers = () => {
  if (!localQuestion.value.reorderingItems) return
  localQuestion.value.correctAnswer = localQuestion.value.reorderingItems.map((_, index) => index.toString())
}

// Tag functions
const addTag = () => {
  if (newTag.value.trim() && !localQuestion.value.tags.includes(newTag.value.trim())) {
    localQuestion.value.tags.push(newTag.value.trim())
    newTag.value = ''
  }
}

const removeTag = tag => {
  const index = localQuestion.value.tags.indexOf(tag)
  if (index !== -1) {
    localQuestion.value.tags.splice(index, 1)
  }
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="900px"
    persistent
    scrollable
    @update:model-value="closeDialog"
  >
    <DialogCloseBtn @click="closeDialog" />
    
    <VCard class="pa-2">
      <VCardTitle class="text-h5 font-weight-bold pa-6 pb-4">
        {{ dialogTitle }}
      </VCardTitle>
      
      <VDivider />

      <VCardText
        class="pa-6"
        style="max-height: 70vh; overflow-y: auto;"
      >
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
          v-model="isFormValid"
          @submit.prevent="submitForm"
        >
          <div class="mb-6">
            <p class="text-overline text-primary mb-3">
              {{ t('questions.dialog.basicInformation', 'Basic Information') }}
            </p>
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="localQuestion.title"
                  :label="t('questions.dialog.title', 'Title (Optional)')"
                  :placeholder="t('questions.dialog.titlePlaceholder', 'Enter an optional title for the question')"
                  :error-messages="formErrors.title"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              
              <VCol cols="12">
                <AppTextarea
                  v-model="localQuestion.questionText"
                  :label="t('questions.dialog.questionText', 'Question Text')"
                  :rules="[requiredValidator]"
                  :error-messages="formErrors.questionText"
                  :placeholder="t('questions.dialog.questionTextPlaceholder', 'Enter the question text')"
                  rows="3"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="localQuestion.type"
                  :label="t('questions.dialog.questionType', 'Question Type')"
                  :items="questionTypes"
                  item-title="title"
                  item-value="value"
                  :error-messages="formErrors.type"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="tabler-list"
                />
              </VCol>
              
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="localQuestion.difficulty"
                  :label="t('questions.dialog.difficulty', 'Difficulty')"
                  :items="difficultyLevels"
                  item-title="title"
                  item-value="value"
                  :error-messages="formErrors.difficulty"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="tabler-chart-bar"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="localQuestion.points"
                  :label="t('questions.dialog.points', 'Points')"
                  type="number"
                  min="1"
                  :rules="[requiredValidator, integerValidator, v => v > 0 || t('validation.minValue', 'Must be at least {min}', { min: 1 })]"
                  :error-messages="formErrors.points"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="tabler-star"
                />
              </VCol>
            </VRow>
          </div>

          <VDivider class="my-6" />

          <div class="mb-6">
            <p class="text-overline text-primary mb-3">
              {{ t('questions.dialog.media', 'Media') }}
            </p>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="localQuestion.mediaType"
                  :label="t('questions.dialog.mediaType', 'Media Type')"
                  :items="mediaTypes"
                  item-title="title"
                  item-value="value"
                  :error-messages="formErrors.mediaType"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              
              <VCol
                v-if="localQuestion.mediaType === 'image' || localQuestion.mediaType === 'image_with_audio'"
                cols="12"
                md="6"
              >
                <VFileInput
                  :label="t('questions.dialog.uploadImage', 'Upload Image')"
                  accept="image/*"
                  :error-messages="formErrors.media"
                  prepend-icon="tabler-upload"
                  :hint="localQuestion.mediaUrl ? t('questions.dialog.imageSelected', 'Image selected') : t('questions.dialog.selectImage', 'Select an image file')"
                  persistent-hint
                  variant="outlined"
                  density="comfortable"
                  @update:model-value="handleFileUpload"
                />
                <div
                  v-if="localQuestion.mediaUrl"
                  class="mt-2"
                >
                  <img
                    :src="localQuestion.mediaUrl"
                    style="max-height: 150px; max-width: 100%;"
                    :alt="t('questions.dialog.imagePreview', 'Image preview')"
                  >
                </div>
              </VCol>
              
              <VCol
                v-if="localQuestion.mediaType === 'image_with_audio'"
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="localQuestion.audioUrl"
                  :label="t('questions.dialog.audioUrl', 'Audio URL')"
                  :placeholder="t('questions.dialog.audioUrlPlaceholder', 'Enter URL for audio file')"
                  :error-messages="formErrors.audioUrl"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              
              <VCol
                v-if="localQuestion.mediaType === 'video'"
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="localQuestion.mediaUrl"
                  :label="t('questions.dialog.videoUrl', 'Video URL')"
                  :placeholder="t('questions.dialog.videoUrlPlaceholder', 'Enter URL for video file')"
                  :error-messages="formErrors.mediaUrl"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
            </VRow>
          </div>

          <VDivider class="my-6" />

          <div class="mb-6">
            <p class="text-overline text-primary mb-3">
              {{ t('questions.dialog.questionContent', 'Question Content') }}
            </p>

            <div v-if="localQuestion.type === 'mcq'">
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>{{ t('questions.dialog.mcqOptions', 'Multiple Choice Options') }}</h4>
                <VBtn
                  size="small"
                  prepend-icon="tabler-plus"
                  @click="addOption"
                >
                  {{ t('questions.dialog.addOption', 'Add Option') }}
                </VBtn>
              </div>
              <VInput
                :key="`mcq-validation-${localQuestion.options.length}-${localQuestion.correctAnswer.length}`"
                :model-value="localQuestion"
                :rules="[
                  () => localQuestion.options.length >= 2 || t('questions.validation.minOptions', 'There must be at least 2 options.'),
                  () => localQuestion.correctAnswer.length > 0 || t('questions.validation.selectCorrect', 'Please select at least one correct answer')
                ]"
                class="mb-2"
              />
              
              <div
                v-for="(option, index) in localQuestion.options"
                :key="index"
                class="d-flex align-center mb-2"
              >
                <VCheckbox
                  :model-value="isCorrectAnswer(index)"
                  :label="t('questions.dialog.correct', 'Correct')"
                  color="success"
                  class="me-2"
                  hide-details
                  @update:model-value="toggleCorrectAnswer(index)"
                />
                
                <AppTextField
                  v-model="localQuestion.options[index]"
                  :placeholder="t('questions.dialog.optionPlaceholder', 'Option {num}', { num: index + 1 })"
                  class="flex-grow-1"
                  :rules="[requiredValidator]"
                  variant="outlined"
                  density="comfortable"
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
                v-if="!localQuestion.options.length"
                block
                variant="outlined"
                @click="addOption"
              >
                {{ t('questions.dialog.addFirstOption', 'Add First Option') }}
              </VBtn>
            </div>

            <div v-if="localQuestion.type === 'fill_blank'">
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>{{ t('questions.dialog.fillBlankAnswers', 'Fill in the Blank Answers') }}</h4>
              </div>

              <VAlert
                color="info"
                variant="tonal"
                class="mb-4"
              >
                {{ t('questions.dialog.fillBlankInfo', 'For each blank, enter all possible correct answers, with each answer on a new line.') }}
              </VAlert>

              <div v-if="detectedBlanks.length > 0">
                <div
                  v-for="(blank, index) in detectedBlanks"
                  :key="blank"
                  class="mb-4"
                >
                  <AppTextarea
                    :model-value="Array.isArray(localQuestion.correctAnswer[index]) ? localQuestion.correctAnswer[index].join('\n') : ''"
                    :label="t('questions.dialog.fillBlankAnswerLabel', { blankPlaceholder: blank })"
                    :placeholder="t('questions.dialog.fillBlankPlaceholder', 'Enter possible answers, one per line...')"
                    rows="3"
                    variant="outlined"
                    density="comfortable"
                    @update:model-value="localQuestion.correctAnswer[index] = $event.split('\n')"
                  />
                </div>
              </div>

              <div v-else>
                <p class="text-medium-emphasis">
                  {{ t('questions.dialog.noBlanks', 'No blanks detected. Please add blanks like [blank1] to the question text above.') }}
                </p>
              </div>
            </div>

            <div v-if="localQuestion.type === 'fill_blank_choices'">
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>{{ t('questions.dialog.fillBlankChoices', 'Fill in the Blank with Choices') }}</h4>
              </div>
              
              <VAlert
                color="info"
                variant="tonal"
                class="mb-4"
              >
                <p>{{ t('questions.dialog.fillBlankChoiceInfo1', 'Use [blank1], [blank2], etc. in your question text to indicate blanks.') }}</p>
                <p>{{ t('questions.dialog.fillBlankChoiceInfo2', 'Then define options for each blank below.') }}</p>
              </VAlert>
              
              <div class="mb-4">
                <h4 class="mb-2">
                  {{ t('questions.dialog.blanksAndOptions', 'Blanks and Options') }}
                </h4>
                
                <div 
                  v-for="(blank, blankIndex) in localQuestion.blanks || []" 
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
                          :label="t('questions.dialog.placeholder', 'Placeholder text (optional)')"
                          :placeholder="t('questions.dialog.placeholderExample', 'e.g. \'Select the correct city\'')"
                          variant="outlined"
                          density="comfortable"
                        />
                      </VCol>
                    </VRow>
                  </div>
                  
                  <div>
                    <h6 class="mb-2">
                      {{ t('questions.dialog.options', 'Options') }}
                    </h6>
                    <VRadioGroup
                      v-model="blank.correctAnswer"
                      hide-details
                    >
                      <VInput
                        :key="`blank-options-validation-${blankIndex}-${blank.options.length}`"
                        :model-value="blank.options"
                        :rules="[v => v.length >= 2 || t('questions.validation.minOptions', 'There must be at least 2 options.')]"
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
                          :placeholder="t('questions.dialog.optionPlaceholder', 'Option {num}', { num: optIndex + 1 })"
                          class="flex-grow-1"
                          hide-details
                          variant="outlined"
                          density="comfortable"
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
                      {{ t('questions.dialog.addOption', 'Add Option') }}
                    </VBtn>
                  </div>
                </div>

                <div v-if="!localQuestion.blanks || localQuestion.blanks.length < 1">
                  <p class="text-medium-emphasis">
                    {{ t('questions.dialog.noBlanks', 'No blanks detected. Please add blanks like [blank1] to the question text above.') }}
                  </p>
                </div>
              </div>
            </div>

            <div v-if="localQuestion.type === 'matching'">
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>{{ t('questions.dialog.matchingPairs', 'Matching Pairs') }}</h4>
              </div>
              <VInput
                :key="`matching-validation-${localQuestion.matchingPairs.length}`"
                :model-value="localQuestion.matchingPairs"
                :rules="[v => v.length >= 2 || t('questions.validation.minPairs', 'Matching questions must have at least 2 pairs.')]"
                class="mt-2"
              />
              
              <div class="mb-4">
                <div 
                  v-for="(pair, pairIndex) in localQuestion.matchingPairs || []" 
                  :key="pairIndex"
                  class="d-flex align-center mb-2"
                >
                  <AppTextField
                    v-model="pair.left"
                    :placeholder="t('questions.dialog.leftItem', 'Left item')"
                    class="flex-grow-1 me-2"
                    hide-details
                    variant="outlined"
                    density="comfortable"
                  />
                  
                  <VIcon
                    icon="tabler-arrow-right"
                    class="mx-2"
                  />
                  
                  <AppTextField
                    v-model="pair.right"
                    :placeholder="t('questions.dialog.rightItem', 'Right item')"
                    class="flex-grow-1 me-2"
                    hide-details
                    variant="outlined"
                    density="comfortable"
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
                  v-if="!localQuestion.matchingPairs?.length"
                  block
                  variant="outlined"
                  class="mt-2"
                  @click="addMatchingPair"
                >
                  {{ t('questions.dialog.addFirstPair', 'Add First Pair') }}
                </VBtn>
                <VBtn
                  v-else
                  prepend-icon="tabler-plus"
                  class="mt-2"
                  @click="addMatchingPair"
                >
                  {{ t('questions.dialog.addPair', 'Add Pair') }}
                </VBtn>
              </div>
            </div>

            <div v-if="localQuestion.type === 'reordering'">
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>{{ t('questions.dialog.reorderingItems', 'Reordering Items') }}</h4>
                <p class="text-caption">
                  {{ t('questions.dialog.reorderingInfo', 'Add items in the correct order. They will be randomized for the student.') }}
                </p>
              </div>
              <VInput
                :key="`reordering-validation-${localQuestion.reorderingItems.length}`"
                :model-value="localQuestion.reorderingItems"
                :rules="[v => v.length >= 2 || t('questions.validation.minItems', 'Reordering questions must have at least 2 items.')]"
                class="mt-2"
              />
              <div class="mb-4">
                <div 
                  v-for="(item, itemIndex) in localQuestion.reorderingItems || []" 
                  :key="itemIndex"
                  class="d-flex align-center mb-2"
                >
                  <div class="me-2 pa-2 bg-primary-lighten-5 rounded">
                    {{ itemIndex + 1 }}
                  </div>
                  
                  <AppTextField
                    v-model="localQuestion.reorderingItems[itemIndex]"
                    :placeholder="t('questions.dialog.itemPlaceholder', 'Item {num}', { num: itemIndex + 1 })"
                    class="flex-grow-1"
                    hide-details
                    variant="outlined"
                    density="comfortable"
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
                      :disabled="itemIndex === localQuestion.reorderingItems.length - 1"
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
                  {{ t('questions.dialog.addItem', 'Add Item') }}
                </VBtn>
              </div>
            </div>

            <div v-if="localQuestion.type === 'writing'">
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>{{ t('questions.dialog.writingQuestion', 'Writing Question') }}</h4>
              </div>
              
              <VAlert
                color="info"
                variant="tonal"
                class="mb-4"
              >
                {{ t('questions.dialog.writingInfo', 'Writing questions require manual grading. You can provide guidelines for grading below.') }}
              </VAlert>
              
              <AppTextarea
                v-model="localQuestion.gradingGuidelines"
                :label="t('questions.dialog.gradingGuidelines', 'Grading Guidelines')"
                :placeholder="t('questions.dialog.gradingGuidelinesPlaceholder', 'Enter guidelines for grading this writing question')"
                rows="4"
                variant="outlined"
                density="comfortable"
              />
              
              <VRow class="mt-4">
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localQuestion.minWords"
                    :label="t('questions.dialog.minWords', 'Minimum Words')"
                    type="number"
                    min="0"
                    variant="outlined"
                    density="comfortable"
                  />
                </VCol>
                
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="localQuestion.maxWords"
                    :label="t('questions.dialog.maxWords', 'Maximum Words')"
                    type="number"
                    min="0"
                    variant="outlined"
                    density="comfortable"
                  />
                </VCol>
              </VRow>
            </div>
          </div>

          <VDivider class="my-6" />

          <div class="mb-4">
            <p class="text-overline text-primary mb-3">
              {{ t('questions.dialog.feedbackAndTags', 'Feedback & Tags') }}
            </p>
            <VRow>
              <VCol cols="12">
                <AppTextarea
                  v-model="localQuestion.correctFeedback"
                  :label="t('questions.dialog.correctFeedback', 'Correct Feedback (Optional)')"
                  :placeholder="t('questions.dialog.correctFeedbackPlaceholder', 'Message to show when the user answers correctly')"
                  rows="2"
                  :error-messages="formErrors.correctFeedback"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              
              <VCol cols="12">
                <AppTextarea
                  v-model="localQuestion.incorrectFeedback"
                  :label="t('questions.dialog.incorrectFeedback', 'Incorrect Feedback (Optional)')"
                  :placeholder="t('questions.dialog.incorrectFeedbackPlaceholder', 'Message to show when the user answers incorrectly')"
                  rows="2"
                  :error-messages="formErrors.incorrectFeedback"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              
              <VCol cols="12">
                <VRow no-gutters>
                  <VCol cols="9">
                    <AppTextField
                      v-model="newTag"
                      :label="t('questions.dialog.tags', 'Tags')"
                      class="me-2"
                      :placeholder="t('questions.dialog.tagsPlaceholder', 'Add tags (press Enter)')"
                      variant="outlined"
                      density="comfortable"
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
                      {{ t('questions.dialog.addTag', 'Add Tag') }}
                    </VBtn>
                  </VCol>
                </VRow>
                
                <div class="d-flex flex-wrap gap-1 mt-2">
                  <VChip
                    v-for="(tag, index) in localQuestion.tags"
                    :key="index"
                    closable
                    @click:close="removeTag(tag)"
                  >
                    {{ tag }}
                  </VChip>
                </div>
              </VCol>
            </VRow>
          </div>
        </VForm>
      </VCardText>
      
      <VDivider />

      <VCardActions class="pa-6 pt-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          size="large"
          :disabled="isSubmitting"
          @click="closeDialog"
        >
          {{ t('common.cancel', 'Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          size="large"
          :loading="isSubmitting"
          :disabled="!isFormValid"
          @click="submitForm"
        >
          <VIcon
            start
            icon="tabler-check"
          />
          {{ props.dialogMode === 'add' 
            ? t('questions.dialog.create', 'Create') 
            : t('questions.dialog.update', 'Update') 
          }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
