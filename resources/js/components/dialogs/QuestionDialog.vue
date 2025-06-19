<template>
  <VDialog
    v-model="showDialog"
    max-width="900"
    persistent
    scrollable
  >
    <VCard>
      <VCardTitle class="d-flex justify-space-between align-center">
        {{ isEditing ? $t('Edit Question') : $t('Add Question') }}
        <VBtn
          icon
          variant="text"
          color="default"
          @click="closeDialog"
        >
          <VIcon
            size="24"
            icon="tabler-x"
          />
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText>
        <VForm
          ref="form"
          @submit.prevent="saveQuestion"
        >
          <VRow>
            <VCol cols="12">
              <h3 class="text-h6 mb-2">
                {{ $t('Basic Information') }}
              </h3>
              <VDivider class="mb-4" />
            </VCol>

            <!-- Question Type -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.type"
                :items="questionTypes"
                :label="$t('Question Type')"
                :rules="[v => !!v || $t('Question type is required')]"
                required
              />
            </VCol>

            <!-- Difficulty -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.difficulty"
                :items="difficultyLevels"
                :label="$t('Difficulty Level')"
                :rules="[v => !!v || $t('Difficulty is required')]"
                required
              />
            </VCol>

            <!-- Points -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model.number="formData.points"
                type="number"
                :label="$t('Points')"
                :rules="[v => v >= 0 || $t('Points must be a positive number')]"
                required
              />
            </VCol>

            <!-- Course, Level, Lesson Selection -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.course_id"
                :items="courses"
                item-title="title"
                item-value="id"
                :label="$t('Course')"
                @update:model-value="onCourseChange"
              />
            </VCol>

            <VCol
              v-if="formData.course_id"
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.level_id"
                :items="levels"
                item-title="title"
                item-value="id"
                :label="$t('Level')"
                @update:model-value="onLevelChange"
              />
            </VCol>

            <VCol
              v-if="formData.level_id"
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.lesson_id"
                :items="lessons"
                item-title="title"
                item-value="id"
                :label="$t('Lesson')"
              />
            </VCol>

            <!-- Question Text -->
            <VCol cols="12">
              <VTextField
                v-model="formData.question_text.en"
                :label="$t('Question Text (English)')"
                :rules="[v => !!v || $t('Question text is required')]"
                required
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="formData.question_text.es"
                :label="$t('Question Text (Spanish)')"
              />
            </VCol>

            <!-- Tags -->
            <VCol cols="12">
              <VCombobox
                v-model="formData.tags"
                :items="availableTags"
                :label="$t('Tags')"
                multiple
                chips
                closable-chips
              />
            </VCol>

            <!-- Media URL and Type -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="formData.media_url"
                :label="$t('Media URL')"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.media_type"
                :items="mediaTypes"
                :label="$t('Media Type')"
                :disabled="!formData.media_url"
              />
            </VCol>

            <!-- Explanation -->
            <VCol cols="12">
              <VTextField
                v-model="formData.explanation.en"
                :label="$t('Explanation (English)')"
              />
            </VCol>

            <VCol cols="12">
              <VTextField
                v-model="formData.explanation.es"
                :label="$t('Explanation (Spanish)')"
              />
            </VCol>

            <!-- Question Type Specific Fields -->
            <VCol cols="12">
              <h3 class="text-h6 mb-2">
                {{ $t('Question Content') }}
              </h3>
              <VDivider class="mb-4" />
            </VCol>

            <!-- Multiple Choice Question -->
            <template v-if="formData.type === 'mcq'">
              <VCol cols="12">
                <div class="d-flex justify-space-between align-center mb-4">
                  <h4 class="text-subtitle-1">
                    {{ $t('Options') }}
                  </h4>
                  <VBtn
                    size="small"
                    color="primary"
                    prepend-icon="tabler-plus"
                    @click="addMcqOption"
                  >
                    {{ $t('Add Option') }}
                  </VBtn>
                </div>

                <div
                  v-for="(option, index) in mcqOptions"
                  :key="index"
                  class="mb-4 pa-4 border rounded"
                >
                  <div class="d-flex justify-space-between align-center mb-2">
                    <h5 class="text-subtitle-2">
                      {{ $t('Option') }} {{ index + 1 }}
                    </h5>
                    <VBtn
                      icon
                      variant="text"
                      color="error"
                      size="small"
                      @click="removeMcqOption(index)"
                    >
                      <VIcon
                        size="20"
                        icon="tabler-trash"
                      />
                    </VBtn>
                  </div>
                  
                  <VRow>
                    <VCol cols="12">
                      <VTextField
                        v-model="option.text.en"
                        :label="$t('Option Text (English)')"
                        :rules="[v => !!v || $t('Option text is required')]"
                      />
                    </VCol>
                    <VCol cols="12">
                      <VTextField
                        v-model="option.text.es"
                        :label="$t('Option Text (Spanish)')"
                      />
                    </VCol>
                    <VCol cols="12">
                      <VSwitch
                        v-model="option.is_correct"
                        :label="$t('Correct Answer')"
                        color="success"
                      />
                    </VCol>
                  </VRow>
                </div>
              </VCol>
            </template>

            <!-- Fill in the Blank -->
            <template v-else-if="formData.type === 'fill_blank'">
              <VCol cols="12">
                <div class="d-flex justify-space-between align-center mb-4">
                  <h4 class="text-subtitle-1">
                    {{ $t('Answers') }}
                  </h4>
                  <VBtn
                    size="small"
                    color="primary"
                    prepend-icon="tabler-plus"
                    @click="addFillBlankAnswer"
                  >
                    {{ $t('Add Answer') }}
                  </VBtn>
                </div>

                <div
                  v-for="(answer, index) in fillBlankAnswers"
                  :key="index"
                  class="mb-2"
                >
                  <div class="d-flex gap-2">
                    <VTextField
                      v-model="fillBlankAnswers[index]"
                      :label="`${$t('Acceptable Answer')} ${index + 1}`"
                    />
                    <VBtn
                      icon
                      variant="text"
                      color="error"
                      @click="removeFillBlankAnswer(index)"
                    >
                      <VIcon
                        size="20"
                        icon="tabler-trash"
                      />
                    </VBtn>
                  </div>
                </div>

                <VSwitch
                  v-model="formData.case_sensitive"
                  :label="$t('Case Sensitive')"
                  color="primary"
                  class="mt-4"
                />
              </VCol>
            </template>

            <!-- Matching -->
            <template v-else-if="formData.type === 'matching'">
              <VCol cols="12">
                <div class="d-flex justify-space-between align-center mb-4">
                  <h4 class="text-subtitle-1">
                    {{ $t('Matching Pairs') }}
                  </h4>
                  <VBtn
                    size="small"
                    color="primary"
                    prepend-icon="tabler-plus"
                    @click="addMatchingPair"
                  >
                    {{ $t('Add Pair') }}
                  </VBtn>
                </div>

                <div
                  v-for="(pair, index) in matchingPairs"
                  :key="index"
                  class="mb-4 pa-4 border rounded"
                >
                  <div class="d-flex justify-space-between align-center mb-2">
                    <h5 class="text-subtitle-2">
                      {{ $t('Pair') }} {{ index + 1 }}
                    </h5>
                    <VBtn
                      icon
                      variant="text"
                      color="error"
                      size="small"
                      @click="removeMatchingPair(index)"
                    >
                      <VIcon
                        size="20"
                        icon="tabler-trash"
                      />
                    </VBtn>
                  </div>
                  
                  <VRow>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <VTextField
                        v-model="pair.left.en"
                        :label="$t('Left Item (English)')"
                        :rules="[v => !!v || $t('Left item is required')]"
                      />
                      <VTextField
                        v-model="pair.left.es"
                        :label="$t('Left Item (Spanish)')"
                        class="mt-2"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="6"
                    >
                      <VTextField
                        v-model="pair.right.en"
                        :label="$t('Right Item (English)')"
                        :rules="[v => !!v || $t('Right item is required')]"
                      />
                      <VTextField
                        v-model="pair.right.es"
                        :label="$t('Right Item (Spanish)')"
                        class="mt-2"
                      />
                    </VCol>
                  </VRow>
                </div>
              </VCol>
            </template>

            <!-- Reordering -->
            <template v-else-if="formData.type === 'reordering'">
              <VCol cols="12">
                <div class="d-flex justify-space-between align-center mb-4">
                  <h4 class="text-subtitle-1">
                    {{ $t('Items to Reorder') }}
                  </h4>
                  <VBtn
                    size="small"
                    color="primary"
                    prepend-icon="tabler-plus"
                    @click="addReorderingItem"
                  >
                    {{ $t('Add Item') }}
                  </VBtn>
                </div>

                <div
                  v-for="(item, index) in reorderingItems"
                  :key="index"
                  class="mb-4 pa-4 border rounded"
                >
                  <div class="d-flex justify-space-between align-center mb-2">
                    <h5 class="text-subtitle-2">
                      {{ $t('Item') }} {{ index + 1 }}
                    </h5>
                    <VBtn
                      icon
                      variant="text"
                      color="error"
                      size="small"
                      @click="removeReorderingItem(index)"
                    >
                      <VIcon
                        size="20"
                        icon="tabler-trash"
                      />
                    </VBtn>
                  </div>
                  
                  <VRow>
                    <VCol
                      cols="12"
                      md="8"
                    >
                      <VTextField
                        v-model="item.text.en"
                        :label="$t('Item Text (English)')"
                        :rules="[v => !!v || $t('Item text is required')]"
                      />
                      <VTextField
                        v-model="item.text.es"
                        :label="$t('Item Text (Spanish)')"
                        class="mt-2"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      md="4"
                    >
                      <VTextField
                        v-model.number="item.position"
                        type="number"
                        :label="$t('Correct Position')"
                        :rules="[v => v >= 0 || $t('Position must be a positive number')]"
                      />
                    </VCol>
                  </VRow>
                </div>
              </VCol>
            </template>

            <!-- Writing -->
            <template v-else-if="formData.type === 'writing'">
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model.number="formData.word_limit"
                  type="number"
                  :label="$t('Word Limit')"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="formData.rubric.en"
                  :label="$t('Rubric (English)')"
                />
              </VCol>
              <VCol cols="12">
                <VTextField
                  v-model="formData.rubric.es"
                  :label="$t('Rubric (Spanish)')"
                />
              </VCol>
            </template>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="loading"
          @click="saveQuestion"
        >
          {{ isEditing ? $t('Update') : $t('Create') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<script setup>
import { useApi } from '@/composables/useApi'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  show: {
    type: Boolean,
    required: true,
  },
  question: {
    type: Object,
    default: null,
  },
  isEditing: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:show', 'question-saved'])

const { $t } = useI18n()
const api = useApi()
const form = ref(null)
const loading = ref(false)

const showDialog = computed({
  get: () => props.show,
  set: value => emit('update:show', value),
})

// Data for form
const formData = ref({
  question_text: { en: '', es: '' },
  type: 'mcq',
  difficulty: 'medium',
  points: 1,
  course_id: null,
  level_id: null,
  lesson_id: null,
  tags: [],
  explanation: { en: '', es: '' },
  media_url: '',
  media_type: '',

  // Type-specific fields
  case_sensitive: false,
  word_limit: null,
  rubric: { en: '', es: '' },
})

// Options for form selects
const courses = ref([])
const levels = ref([])
const lessons = ref([])
const availableTags = ref([])

const questionTypes = [
  { title: $t('Multiple Choice'), value: 'mcq' },
  { title: $t('Matching'), value: 'matching' },
  { title: $t('Fill in the Blank'), value: 'fill_blank' },
  { title: $t('Reordering'), value: 'reordering' },
  { title: $t('Fill in the Blank with Choices'), value: 'fill_blank_choices' },
  { title: $t('Writing'), value: 'writing' },
]

const difficultyLevels = [
  { title: $t('Easy'), value: 'easy' },
  { title: $t('Medium'), value: 'medium' },
  { title: $t('Hard'), value: 'hard' },
]

const mediaTypes = [
  { title: $t('Image'), value: 'image' },
  { title: $t('Audio'), value: 'audio' },
  { title: $t('Video'), value: 'video' },
]

// Type-specific data
const mcqOptions = ref([])
const fillBlankAnswers = ref([])
const matchingPairs = ref([])
const reorderingItems = ref([])

// Methods
const fetchCourses = async () => {
  try {
    const response = await api.get('/admin/courses')

    courses.value = response.data.data
  } catch (error) {
    console.error('Error fetching courses:', error)
  }
}

const fetchLevels = async courseId => {
  if (!courseId) {
    levels.value = []
    
    return
  }
  
  try {
    const response = await api.get(`/admin/courses/${courseId}/levels`)

    levels.value = response.data
  } catch (error) {
    console.error('Error fetching levels:', error)
  }
}

const fetchLessons = async levelId => {
  if (!levelId) {
    lessons.value = []
    
    return
  }
  
  try {
    const response = await api.get(`/admin/levels/${levelId}/lessons`)

    lessons.value = response.data
  } catch (error) {
    console.error('Error fetching lessons:', error)
  }
}

const onCourseChange = async () => {
  formData.value.level_id = null
  formData.value.lesson_id = null
  levels.value = []
  lessons.value = []
  
  if (formData.value.course_id) {
    await fetchLevels(formData.value.course_id)
  }
}

const onLevelChange = async () => {
  formData.value.lesson_id = null
  lessons.value = []
  
  if (formData.value.level_id) {
    await fetchLessons(formData.value.level_id)
  }
}

// MCQ methods
const addMcqOption = () => {
  mcqOptions.value.push({
    text: { en: '', es: '' },
    is_correct: false,
  })
}

const removeMcqOption = index => {
  mcqOptions.value.splice(index, 1)
}

// Fill in the blank methods
const addFillBlankAnswer = () => {
  fillBlankAnswers.value.push('')
}

const removeFillBlankAnswer = index => {
  fillBlankAnswers.value.splice(index, 1)
}

// Matching methods
const addMatchingPair = () => {
  matchingPairs.value.push({
    left: { en: '', es: '' },
    right: { en: '', es: '' },
  })
}

const removeMatchingPair = index => {
  matchingPairs.value.splice(index, 1)
}

// Reordering methods
const addReorderingItem = () => {
  reorderingItems.value.push({
    text: { en: '', es: '' },
    position: reorderingItems.value.length,
  })
}

const removeReorderingItem = index => {
  reorderingItems.value.splice(index, 1)
  
  // Update positions
  reorderingItems.value.forEach((item, idx) => {
    if (item.position >= index) {
      item.position = idx
    }
  })
}

// Initialize form data based on question type
const initializeTypeSpecificData = () => {
  switch (formData.value.type) {
  case 'mcq':
    if (props.question && props.question.options) {
      mcqOptions.value = props.question.options.map(option => ({
        text: option.text || { en: '', es: '' },
        is_correct: option.is_correct || false,
      }))
    } else {
      mcqOptions.value = [
        { text: { en: '', es: '' }, is_correct: false },
        { text: { en: '', es: '' }, is_correct: false },
      ]
    }
    break
      
  case 'fill_blank':
    if (props.question && props.question.correct_answer) {
      fillBlankAnswers.value = props.question.correct_answer.answers || ['']
    } else {
      fillBlankAnswers.value = ['']
    }
    break
      
  case 'matching':
    if (props.question && props.question.options && props.question.options.pairs) {
      matchingPairs.value = props.question.options.pairs.map(pair => ({
        left: pair.left || { en: '', es: '' },
        right: pair.right || { en: '', es: '' },
      }))
    } else {
      matchingPairs.value = [
        { left: { en: '', es: '' }, right: { en: '', es: '' } },
        { left: { en: '', es: '' }, right: { en: '', es: '' } },
      ]
    }
    break
      
  case 'reordering':
    if (props.question && props.question.options && props.question.options.items) {
      reorderingItems.value = props.question.options.items.map(item => ({
        text: item.text || { en: '', es: '' },
        position: item.position || 0,
      }))
    } else {
      reorderingItems.value = [
        { text: { en: '', es: '' }, position: 0 },
        { text: { en: '', es: '' }, position: 1 },
      ]
    }
    break
  }
}

// Reset form
const resetForm = () => {
  formData.value = {
    question_text: { en: '', es: '' },
    type: 'mcq',
    difficulty: 'medium',
    points: 1,
    course_id: null,
    level_id: null,
    lesson_id: null,
    tags: [],
    explanation: { en: '', es: '' },
    media_url: '',
    media_type: '',
    case_sensitive: false,
    word_limit: null,
    rubric: { en: '', es: '' },
  }
  
  mcqOptions.value = [
    { text: { en: '', es: '' }, is_correct: false },
    { text: { en: '', es: '' }, is_correct: false },
  ]
  fillBlankAnswers.value = ['']
  matchingPairs.value = [
    { left: { en: '', es: '' }, right: { en: '', es: '' } },
    { left: { en: '', es: '' }, right: { en: '', es: '' } },
  ]
  reorderingItems.value = [
    { text: { en: '', es: '' }, position: 0 },
    { text: { en: '', es: '' }, position: 1 },
  ]
}

// Load question data if editing
const loadQuestionData = () => {
  if (!props.question) return
  
  formData.value = {
    question_text: props.question.question_text || { en: '', es: '' },
    type: props.question.type || 'mcq',
    difficulty: props.question.difficulty || 'medium',
    points: props.question.points || 1,
    course_id: props.question.course_id || null,
    level_id: props.question.level_id || null,
    lesson_id: props.question.lesson_id || null,
    tags: props.question.tags || [],
    explanation: props.question.explanation || { en: '', es: '' },
    media_url: props.question.media_url || '',
    media_type: props.question.media_type || '',
    case_sensitive: props.question.case_sensitive || false,
    word_limit: props.question.word_limit || null,
    rubric: props.question.rubric || { en: '', es: '' },
  }
  
  // Load related data
  if (formData.value.course_id) {
    fetchLevels(formData.value.course_id)
  }
  
  if (formData.value.level_id) {
    fetchLessons(formData.value.level_id)
  }
  
  initializeTypeSpecificData()
}

// Prepare data for saving
const prepareDataForSave = () => {
  const data = { ...formData.value }
  
  // Prepare options based on question type
  switch (data.type) {
  case 'mcq':
    data.options = mcqOptions.value
    data.correct_answer = mcqOptions.value
      .filter(option => option.is_correct)
      .map(option => option.text)
    break
      
  case 'fill_blank':
    data.options = null
    data.correct_answer = { answers: fillBlankAnswers.value }
    break
      
  case 'matching':
    data.options = { pairs: matchingPairs.value }
    data.correct_answer = matchingPairs.value.map(pair => ({
      left: pair.left,
      right: pair.right,
    }))
    break
      
  case 'reordering':
    data.options = { items: reorderingItems.value }
    data.correct_answer = reorderingItems.value
      .sort((a, b) => a.position - b.position)
      .map(item => item.text)
    break
      
  case 'writing':
    data.options = null
    data.correct_answer = null
    break
  }
  
  return data
}

const saveQuestion = async () => {
  const valid = await form.value?.validate()
  
  if (!valid) return
  
  loading.value = true
  
  try {
    const data = prepareDataForSave()
    
    if (props.isEditing && props.question) {
      await api.put(`/admin/questions/${props.question.id}`, data)
    } else {
      await api.post('/admin/questions', data)
    }
    
    emit('question-saved')
    closeDialog()
  } catch (error) {
    console.error('Error saving question:', error)
  } finally {
    loading.value = false
  }
}

const closeDialog = () => {
  showDialog.value = false
  resetForm()
}

// Watch for changes in question type to initialize appropriate data
watch(() => formData.value.type, () => {
  initializeTypeSpecificData()
})

// Watch for dialog opening to load data
watch(() => props.show, newVal => {
  if (newVal && props.isEditing && props.question) {
    loadQuestionData()
  } else if (newVal) {
    resetForm()
  }
})

// Lifecycle hooks
onMounted(async () => {
  await fetchCourses()
  
  if (props.isEditing && props.question) {
    loadQuestionData()
  } else {
    resetForm()
  }
})
</script>

<style lang="scss" scoped>
.border {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style> 
