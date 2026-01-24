<script setup>
import { integerValidator, requiredValidator } from '@/@core/utils/validators'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

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
  data: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:isDialogVisible', 'save'])

const { t } = useI18n()
const formRef = ref(null)
const isFormValid = ref(true)

// Local question state
const localQuestion = ref({})

// Get default question data
const getDefaultQuestion = () => ({
  id: null,
  questionText: '',
  type: 'mcq',
  options: ['', ''],
  correctAnswer: [],
  points: 1,
  correctFeedback: '',
  incorrectFeedback: '',
  mediaUrl: null,
  mediaFile: null,
  mediaType: 'none',
  videoSource: 'direct',
  audioUrl: null,
  audioFile: null,
  blanks: [],
  matchingPairs: [],
  reorderingItems: [],
})

// Computed dialog title
const dialogTitle = computed(() =>
  props.dialogMode === 'add'
    ? 'Add Exam Question'
    : 'Edit Exam Question',
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
  { title: t('questions.media.audio', 'Audio'), value: 'audio' },
  { title: t('questions.media.imageWithAudio', 'Image with Audio'), value: 'image_with_audio' },
  { title: t('questions.media.video', 'Video'), value: 'video' },
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
      if (props.dialogMode === 'edit' && props.data && Object.keys(props.data).length > 0) {
        localQuestion.value = JSON.parse(JSON.stringify(props.data))
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
    localQuestion.value.mediaFile = null
    localQuestion.value.audioUrl = null
    localQuestion.value.audioFile = null
  } else if (newValue === 'video') {
    // Keep URL if it was already set
  } else if (newValue !== 'image_with_audio') {
    localQuestion.value.audioUrl = null
    localQuestion.value.audioFile = null
  }
})

// Watch for question type changes
watch(() => localQuestion.value.type, () => {
  initializeQuestionTypeData()
})

// Initialize data for specific question types
const initializeQuestionTypeData = () => {
  const type = localQuestion.value.type

  if (type !== 'fill_blank_choices') localQuestion.value.blanks = []
  if (type !== 'matching') localQuestion.value.matchingPairs = []
  if (type !== 'reordering') localQuestion.value.reorderingItems = []

  if (type === 'fill_blank_choices') {
    if (!localQuestion.value.blanks?.length) {
      localQuestion.value.blanks = []
    }
  } else if (type === 'matching') {
    if (!localQuestion.value.matchingPairs?.length) {
      addMatchingPair()
    }
  } else if (type === 'reordering') {
    if (!localQuestion.value.reorderingItems?.length) {
      addReorderingItem()
    }
  } else if (type === 'mcq' && !localQuestion.value.options?.length) {
    localQuestion.value.options = ['', '']
  }
}

const imagePreviewUrl = ref(null)

watch(() => localQuestion.value.mediaFile, file => {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
  }
  if (file) {
    imagePreviewUrl.value = URL.createObjectURL(file)
  } else {
    imagePreviewUrl.value = null
  }
})

const closeDialog = () => {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value)
    imagePreviewUrl.value = null
  }
  emit('update:isDialogVisible', false)
}

const submitForm = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return

  emit('save', JSON.parse(JSON.stringify(localQuestion.value)))
  closeDialog()
}

// MCQ functions
const addOption = () => {
  if (!localQuestion.value.options) localQuestion.value.options = []
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
    if (answerNum > index) return (answerNum - 1).toString()

    return answer
  })
}

const toggleCorrectAnswer = index => {
  const indexStr = index.toString()
  if (!localQuestion.value.correctAnswer) localQuestion.value.correctAnswer = []
  
  if (localQuestion.value.correctAnswer.includes(indexStr)) {
    const answerIndex = localQuestion.value.correctAnswer.indexOf(indexStr)

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
  if (!localQuestion.value.blanks[blankIndex].options) localQuestion.value.blanks[blankIndex].options = []
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
  if (!localQuestion.value.matchingPairs) localQuestion.value.matchingPairs = []
  localQuestion.value.matchingPairs.push({ left: '', right: '' })
  updateMatchingCorrectAnswers()
}

const removeMatchingPair = index => {
  localQuestion.value.matchingPairs.splice(index, 1)
  updateMatchingCorrectAnswers()
}

const updateMatchingCorrectAnswers = () => {
  if (!localQuestion.value.matchingPairs) return
  localQuestion.value.correctAnswer = localQuestion.value.matchingPairs.map((_, index) => ({
    left: index,
    right: index,
  }))
}

// Reordering functions
const addReorderingItem = () => {
  if (!localQuestion.value.reorderingItems) localQuestion.value.reorderingItems = []
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
        <VForm
          ref="formRef"
          v-model="isFormValid"
          @submit.prevent="submitForm"
        >
          <div class="mb-6">
            <p class="text-overline text-primary mb-3">
              Basic Information
            </p>
            <VRow>
              <VCol cols="12">
                <AppTextarea
                  v-model="localQuestion.questionText"
                  label="Question Text"
                  :rules="[requiredValidator]"
                  placeholder="Enter the question text"
                  rows="3"
                  variant="outlined"
                  density="comfortable"
                />
                <div class="text-caption text-medium-emphasis mt-1">
                  Use [blank1], [blank2] etc. for fill in the blanks.
                </div>
              </VCol>
              
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="localQuestion.type"
                  label="Question Type"
                  :items="questionTypes"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="tabler-list"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="localQuestion.marks"
                  label="Marks"
                  type="number"
                  min="1"
                  :rules="[requiredValidator, integerValidator]"
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
              Media
            </p>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="localQuestion.mediaType"
                  label="Media Type"
                  :items="mediaTypes"
                  item-title="title"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              
              <VCol
                v-if="localQuestion.mediaType === 'image' || localQuestion.mediaType === 'image_with_audio'"
                cols="12"
                md="6"
              >
                <VLabel class="mb-1 text-body-2">
                  Question Image
                </VLabel>
                <VFileInput
                  v-model="localQuestion.mediaFile"
                  label="Select Image"
                  accept="image/*"
                  variant="outlined"
                  density="comfortable"
                  prepend-icon=""
                  prepend-inner-icon="tabler-camera"
                  @update:model-value="localQuestion.mediaUrl = null"
                />
                <div
                  v-if="localQuestion.mediaUrl || localQuestion.mediaFile"
                  class="mt-2 border rounded pa-2 d-flex align-center gap-4"
                >
                  <VImg
                    :src="localQuestion.mediaFile ? imagePreviewUrl : localQuestion.mediaUrl"
                    width="80"
                    height="80"
                    cover
                    class="rounded"
                  />
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ localQuestion.mediaFile ? 'New Image Selected' : 'Current Image' }}
                    </div>
                    <VBtn
                      size="x-small"
                      color="error"
                      variant="text"
                      class="px-0"
                      @click="localQuestion.mediaFile = null; localQuestion.mediaUrl = null"
                    >
                      Remove
                    </VBtn>
                  </div>
                </div>
              </VCol>
              
              <VCol
                v-if="localQuestion.mediaType === 'image_with_audio'"
                cols="12"
                md="6"
              >
                <VLabel class="mb-1 text-body-2">
                  Question Audio
                </VLabel>
                <VFileInput
                  v-model="localQuestion.audioFile"
                  label="Select Audio"
                  accept="audio/*"
                  variant="outlined"
                  density="comfortable"
                  prepend-icon=""
                  prepend-inner-icon="tabler-volume"
                  @update:model-value="localQuestion.audioUrl = null"
                />
                <div
                  v-if="localQuestion.audioUrl || localQuestion.audioFile"
                  class="mt-2 border rounded pa-2 d-flex align-center gap-4"
                >
                  <VIcon
                    icon="tabler-volume"
                    size="large"
                  />
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ localQuestion.audioFile ? 'New Audio Selected' : 'Current Audio' }}
                    </div>
                    <VBtn
                      size="x-small"
                      color="error"
                      variant="text"
                      class="px-0"
                      @click="localQuestion.audioFile = null; localQuestion.audioUrl = null"
                    >
                      Remove
                    </VBtn>
                  </div>
                </div>
              </VCol>
              
              <VCol
                v-if="localQuestion.mediaType === 'audio'"
                cols="12"
                md="6"
              >
                <VLabel class="mb-1 text-body-2">
                  Question Audio
                </VLabel>
                <VFileInput
                  v-model="localQuestion.mediaFile"
                  label="Select Audio"
                  accept="audio/*"
                  variant="outlined"
                  density="comfortable"
                  prepend-icon=""
                  prepend-inner-icon="tabler-volume"
                  @update:model-value="localQuestion.mediaUrl = null"
                />
                <div
                  v-if="localQuestion.mediaUrl || localQuestion.mediaFile"
                  class="mt-2 border rounded pa-2 d-flex align-center gap-4"
                >
                  <VIcon
                    icon="tabler-volume"
                    size="large"
                  />
                  <div>
                    <div class="text-caption text-medium-emphasis">
                      {{ localQuestion.mediaFile ? 'New Audio Selected' : 'Current Audio' }}
                    </div>
                    <VBtn
                      size="x-small"
                      color="error"
                      variant="text"
                      class="px-0"
                      @click="localQuestion.mediaFile = null; localQuestion.mediaUrl = null"
                    >
                      Remove
                    </VBtn>
                  </div>
                </div>
              </VCol>
              
              <VCol
                v-if="localQuestion.mediaType === 'video'"
                cols="12"
                md="6"
              >
                <AppSelect
                  v-model="localQuestion.videoSource"
                  label="Video Source"
                  :items="[
                    { title: 'Direct Link / Upload', value: 'direct' },
                    { title: 'YouTube', value: 'youtube' },
                    { title: 'Vimeo', value: 'vimeo' },
                  ]"
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
                  label="Video URL"
                  placeholder="Enter video URL"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
            </VRow>
          </div>

          <VDivider class="my-6" />

          <div class="mb-6">
            <p class="text-overline text-primary mb-3">
              Question Content
            </p>

            <!-- MCQ -->
            <div v-if="localQuestion.type === 'mcq'">
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>Multiple Choice Options</h4>
                <VBtn
                  size="small"
                  prepend-icon="tabler-plus"
                  variant="tonal"
                  @click="addOption"
                >
                  Add Option
                </VBtn>
              </div>
              
              <VInput
                :key="`mcq-validation-${localQuestion.options?.length}-${localQuestion.correctAnswer?.length}`"
                :model-value="localQuestion"
                :rules="[
                  () => (localQuestion.options?.length || 0) >= 2 || t('questions.validation.minOptions', 'There must be at least 2 options.'),
                  () => (localQuestion.correctAnswer?.length || 0) > 0 || t('questions.validation.selectCorrect', 'Please select at least one correct answer')
                ]"
                class="mb-2"
              />

              <div
                v-for="(option, index) in localQuestion.options"
                :key="index"
                class="d-flex align-center gap-2 mb-2"
              >
                <VCheckbox
                  :model-value="isCorrectAnswer(index)"
                  label="Correct"
                  color="success"
                  hide-details
                  class="me-2"
                  @update:model-value="toggleCorrectAnswer(index)"
                />
                <AppTextField
                  v-model="localQuestion.options[index]"
                  placeholder="Option text"
                  variant="outlined"
                  density="compact"
                  hide-details
                  class="flex-grow-1"
                />
                <VBtn
                  icon="tabler-trash"
                  size="x-small"
                  color="error"
                  variant="text"
                  @click="removeOption(index)"
                />
              </div>
            </div>

            <!-- Matching -->
            <div v-if="localQuestion.type === 'matching'">
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>Matching Pairs</h4>
                <VBtn
                  size="small"
                  prepend-icon="tabler-plus"
                  variant="tonal"
                  @click="addMatchingPair"
                >
                  Add Pair
                </VBtn>
              </div>

              <VInput
                :key="`matching-validation-${localQuestion.matchingPairs?.length}`"
                :model-value="localQuestion.matchingPairs"
                :rules="[v => (v?.length || 0) >= 2 || t('questions.validation.minPairs', 'Matching questions must have at least 2 pairs.')]"
                class="mt-2"
              />

              <div
                v-for="(pair, index) in localQuestion.matchingPairs"
                :key="index"
                class="d-flex align-center gap-2 mb-2"
              >
                <AppTextField
                  v-model="pair.left"
                  placeholder="Left side"
                  variant="outlined"
                  density="compact"
                  hide-details
                />
                <VIcon icon="tabler-arrows-left-right" />
                <AppTextField
                  v-model="pair.right"
                  placeholder="Right side"
                  variant="outlined"
                  density="compact"
                  hide-details
                />
                <VBtn
                  icon="tabler-trash"
                  size="x-small"
                  color="error"
                  variant="text"
                  @click="removeMatchingPair(index)"
                />
              </div>
            </div>

            <!-- Fill in the Blank -->
            <div v-if="localQuestion.type === 'fill_blank'">
              <h4>Fill in the Blank Answers</h4>
              <VAlert
                color="info"
                variant="tonal"
                class="mb-4"
              >
                For each blank, enter all possible correct answers, with each answer on a new line.
              </VAlert>
              <div
                v-for="(blank, index) in detectedBlanks"
                :key="index"
                class="mb-4"
              >
                <AppTextarea
                  :model-value="Array.isArray(localQuestion.correctAnswer[index]) ? localQuestion.correctAnswer[index].join('\n') : ''"
                  :label="`Correct Answers for ${blank}`"
                  placeholder="Enter possible answers, one per line..."
                  rows="3"
                  variant="outlined"
                  density="comfortable"
                  @update:model-value="localQuestion.correctAnswer[index] = $event.split('\n')"
                />
              </div>
              <div
                v-if="!detectedBlanks.length"
                class="text-center py-4 text-medium-emphasis"
              >
                Add [blank1] to your question text to define blanks.
              </div>
            </div>

            <!-- Fill in the Blank with Choices -->
            <div v-if="localQuestion.type === 'fill_blank_choices'">
              <h4>Blanks and Options</h4>
              <VAlert
                color="info"
                variant="tonal"
                class="mb-4"
              >
                For each blank, add the possible choices and select the correct one.
              </VAlert>
              <div
                v-for="(blank, index) in detectedBlanks"
                :key="index"
                class="mb-6 pa-4 border rounded"
              >
                <div class="d-flex justify-space-between align-center mb-4">
                  <div class="text-subtitle-1 font-weight-bold">
                    {{ blank }}
                  </div>
                  <VBtn
                    size="x-small"
                    variant="tonal"
                    prepend-icon="tabler-plus"
                    @click="addBlankOption(index)"
                  >
                    Add Option
                  </VBtn>
                </div>
                <div
                  v-for="(opt, oIdx) in localQuestion.blanks[index]?.options"
                  :key="oIdx"
                  class="d-flex align-center gap-2 mb-2"
                >
                  <VRadio
                    :model-value="localQuestion.blanks[index].correctAnswer === oIdx.toString()"
                    hide-details
                    @click="localQuestion.blanks[index].correctAnswer = oIdx.toString()"
                  />
                  <AppTextField
                    v-model="localQuestion.blanks[index].options[oIdx]"
                    placeholder="Option text"
                    variant="outlined"
                    density="compact"
                    hide-details
                  />
                  <VBtn
                    icon="tabler-trash"
                    size="x-small"
                    color="error"
                    variant="text"
                    @click="removeBlankOption(index, oIdx)"
                  />
                </div>
              </div>
              <div
                v-if="!detectedBlanks.length"
                class="text-center py-4 text-medium-emphasis"
              >
                Add [blank1] to your question text to define blanks.
              </div>
            </div>

            <!-- Reordering -->
            <div v-if="localQuestion.type === 'reordering'">
              <div class="d-flex justify-space-between align-center mb-2">
                <h4>Reordering Items (in correct order)</h4>
                <VBtn
                  size="small"
                  prepend-icon="tabler-plus"
                  variant="tonal"
                  @click="addReorderingItem"
                >
                  Add Item
                </VBtn>
              </div>

              <VInput
                :key="`reordering-validation-${localQuestion.reorderingItems?.length}`"
                :model-value="localQuestion.reorderingItems"
                :rules="[v => (v?.length || 0) >= 2 || t('questions.validation.minItems', 'Reordering questions must have at least 2 items.')]"
                class="mt-2"
              />

              <div
                v-for="(item, index) in localQuestion.reorderingItems"
                :key="index"
                class="d-flex align-center gap-2 mb-2"
              >
                <div class="d-flex flex-column">
                  <VBtn
                    icon="tabler-chevron-up"
                    size="x-small"
                    variant="text"
                    :disabled="index === 0"
                    @click="moveReorderingItem(index, 'up')"
                  />
                  <VBtn
                    icon="tabler-chevron-down"
                    size="x-small"
                    variant="text"
                    :disabled="index === localQuestion.reorderingItems.length - 1"
                    @click="moveReorderingItem(index, 'down')"
                  />
                </div>
                <AppTextField
                  v-model="localQuestion.reorderingItems[index]"
                  placeholder="Item text"
                  variant="outlined"
                  density="compact"
                  hide-details
                />
                <VBtn
                  icon="tabler-trash"
                  size="x-small"
                  color="error"
                  variant="text"
                  @click="removeReorderingItem(index)"
                />
              </div>
            </div>

            <!-- Writing -->
            <div v-if="localQuestion.type === 'writing'">
              <AppTextarea
                v-model="localQuestion.gradingGuidelines"
                label="Grading Guidelines"
                placeholder="Enter guidelines for the instructor"
                rows="4"
                variant="outlined"
                density="comfortable"
              />
            </div>
          </div>

          <VDivider class="my-6" />

          <div>
            <p class="text-overline text-primary mb-3">
              Feedback
            </p>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextarea
                  v-model="localQuestion.correctFeedback"
                  label="Correct Feedback"
                  placeholder="Shown when the answer is correct"
                  rows="2"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppTextarea
                  v-model="localQuestion.incorrectFeedback"
                  label="Incorrect Feedback"
                  placeholder="Shown when the answer is incorrect"
                  rows="2"
                  variant="outlined"
                  density="comfortable"
                />
              </VCol>
            </VRow>
          </div>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          @click="submitForm"
        >
          {{ props.dialogMode === 'add' ? 'Add Question' : 'Save Changes' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>