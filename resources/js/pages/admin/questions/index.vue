<template>
  <section>
    <VCard>
      <VCardText class="d-flex flex-wrap py-4 gap-4">
        <div class="d-flex align-center flex-wrap gap-4">
          <h1 class="text-h4 font-weight-medium">
            {{ $t('Questions') }}
          </h1>
        </div>

        <VSpacer />

        <div class="d-flex align-center flex-wrap gap-4">
          <VTextField
            v-model="searchQuery"
            :placeholder="$t('Search')"
            density="compact"
            class="search-input"
            prepend-inner-icon="tabler-search"
            @update:model-value="fetchQuestions"
          />

          <VSelect
            v-model="selectedType"
            :items="questionTypes"
            :label="$t('Type')"
            density="compact"
            class="w-200"
            @update:model-value="fetchQuestions"
          />

          <VSelect
            v-model="selectedDifficulty"
            :items="difficultyLevels"
            :label="$t('Difficulty')"
            density="compact"
            class="w-200"
            @update:model-value="fetchQuestions"
          />

          <VSelect
            v-model="selectedCourse"
            :items="courses"
            item-title="title"
            item-value="id"
            :label="$t('Course')"
            density="compact"
            class="w-200"
            @update:model-value="onCourseChange"
          />

          <VSelect
            v-if="selectedCourse"
            v-model="selectedLevel"
            :items="levels"
            item-title="title"
            item-value="id"
            :label="$t('Level')"
            density="compact"
            class="w-200"
            @update:model-value="onLevelChange"
          />

          <VSelect
            v-if="selectedLevel"
            v-model="selectedLesson"
            :items="lessons"
            item-title="title"
            item-value="id"
            :label="$t('Lesson')"
            density="compact"
            class="w-200"
            @update:model-value="fetchQuestions"
          />
        </div>

        <div class="d-flex align-center flex-wrap gap-4">
          <VBtn
            prepend-icon="tabler-plus"
            color="primary"
            @click="openCreateDialog"
          >
            {{ $t('Add Question') }}
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- Questions Table -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="currentPage"
        :headers="headers"
        :items="questions"
        :items-length="totalQuestions"
        :loading="loading"
        class="text-no-wrap"
        @update:options="onUpdateOptions"
      >
        <template #item.question_text="{ item }">
          <div v-html="truncateText(item.question_text)" />
        </template>

        <template #item.type="{ item }">
          <VChip
            :color="getTypeColor(item.type)"
            size="small"
            class="text-capitalize"
          >
            {{ formatQuestionType(item.type) }}
          </VChip>
        </template>

        <template #item.difficulty="{ item }">
          <VChip
            :color="getDifficultyColor(item.difficulty)"
            size="small"
            class="text-capitalize"
          >
            {{ item.difficulty }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            icon
            variant="text"
            size="small"
            color="medium-emphasis"
            @click="openEditDialog(item)"
          >
            <VIcon
              size="24"
              icon="tabler-edit"
            />
          </VBtn>

          <VBtn
            icon
            variant="text"
            size="small"
            color="medium-emphasis"
            @click="confirmDelete(item)"
          >
            <VIcon
              size="24"
              icon="tabler-trash"
            />
          </VBtn>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Create/Edit Question Dialog -->
    <QuestionDialog
      v-if="showQuestionDialog"
      v-model:show="showQuestionDialog"
      :question="selectedQuestion"
      :is-editing="isEditing"
      @question-saved="onQuestionSaved"
    />

    <!-- Delete Confirmation Dialog -->
    <VDialog
      v-model="showDeleteDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle class="text-h5">
          {{ $t('Confirm Delete') }}
        </VCardTitle>
        <VCardText>
          {{ $t('Are you sure you want to delete this question? This action cannot be undone.') }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="secondary"
            @click="showDeleteDialog = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="error"
            @click="deleteQuestion"
          >
            {{ $t('Delete') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </section>
</template>

<script setup>
import QuestionDialog from '@/components/dialogs/QuestionDialog.vue'
import { useApi } from '@/composables/useApi'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { $t } = useI18n()
const api = useApi()

// Data
const questions = ref([])
const totalQuestions = ref(0)
const loading = ref(false)
const searchQuery = ref('')
const selectedType = ref(null)
const selectedDifficulty = ref(null)
const selectedCourse = ref(null)
const selectedLevel = ref(null)
const selectedLesson = ref(null)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref([{ key: 'created_at', order: 'desc' }])
const showQuestionDialog = ref(false)
const isEditing = ref(false)
const selectedQuestion = ref(null)
const showDeleteDialog = ref(false)
const questionToDelete = ref(null)

// Options
const courses = ref([])
const levels = ref([])
const lessons = ref([])

const questionTypes = [
  { title: $t('All Types'), value: null },
  { title: $t('Multiple Choice'), value: 'mcq' },
  { title: $t('Matching'), value: 'matching' },
  { title: $t('Fill in the Blank'), value: 'fill_blank' },
  { title: $t('Reordering'), value: 'reordering' },
  { title: $t('Fill in the Blank with Choices'), value: 'fill_blank_choices' },
  { title: $t('Writing'), value: 'writing' },
]

const difficultyLevels = [
  { title: $t('All Difficulties'), value: null },
  { title: $t('Easy'), value: 'easy' },
  { title: $t('Medium'), value: 'medium' },
  { title: $t('Hard'), value: 'hard' },
]

const headers = computed(() => [
  { title: $t('ID'), key: 'id', sortable: true },
  { title: $t('Question'), key: 'question_text', sortable: false },
  { title: $t('Type'), key: 'type', sortable: true },
  { title: $t('Difficulty'), key: 'difficulty', sortable: true },
  { title: $t('Points'), key: 'points', sortable: true },
  { title: $t('Actions'), key: 'actions', sortable: false },
])

// Methods
const fetchQuestions = async () => {
  loading.value = true
  
  try {
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value,
      sort_by: sortBy.value[0]?.key || 'created_at',
      sort_direction: sortBy.value[0]?.order || 'desc',
    }
    
    if (searchQuery.value) params.search = searchQuery.value
    if (selectedType.value) params.type = selectedType.value
    if (selectedDifficulty.value) params.difficulty = selectedDifficulty.value
    if (selectedCourse.value) params.course_id = selectedCourse.value
    if (selectedLevel.value) params.level_id = selectedLevel.value
    if (selectedLesson.value) params.lesson_id = selectedLesson.value
    
    const response = await api.get('/admin/questions', { params })
    
    questions.value = response.data.data
    totalQuestions.value = response.data.total
  } catch (error) {
    console.error('Error fetching questions:', error)
  } finally {
    loading.value = false
  }
}

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
  selectedLevel.value = null
  selectedLesson.value = null
  levels.value = []
  lessons.value = []
  
  if (selectedCourse.value) {
    await fetchLevels(selectedCourse.value)
  }
  
  fetchQuestions()
}

const onLevelChange = async () => {
  selectedLesson.value = null
  lessons.value = []
  
  if (selectedLevel.value) {
    await fetchLessons(selectedLevel.value)
  }
  
  fetchQuestions()
}

const onUpdateOptions = options => {
  currentPage.value = options.page
  itemsPerPage.value = options.itemsPerPage
  sortBy.value = options.sortBy
  fetchQuestions()
}

const openCreateDialog = () => {
  selectedQuestion.value = null
  isEditing.value = false
  showQuestionDialog.value = true
}

const openEditDialog = question => {
  selectedQuestion.value = question
  isEditing.value = true
  showQuestionDialog.value = true
}

const onQuestionSaved = () => {
  fetchQuestions()
}

const confirmDelete = question => {
  questionToDelete.value = question
  showDeleteDialog.value = true
}

const deleteQuestion = async () => {
  if (!questionToDelete.value) return
  
  try {
    await api.delete(`/admin/questions/${questionToDelete.value.id}`)
    fetchQuestions()
    showDeleteDialog.value = false
  } catch (error) {
    console.error('Error deleting question:', error)
  }
}

const formatQuestionType = type => {
  switch (type) {
  case 'mcq': return $t('Multiple Choice')
  case 'matching': return $t('Matching')
  case 'fill_blank': return $t('Fill in the Blank')
  case 'reordering': return $t('Reordering')
  case 'fill_blank_choices': return $t('Fill in the Blank with Choices')
  case 'writing': return $t('Writing')
  default: return type
  }
}

const getTypeColor = type => {
  switch (type) {
  case 'mcq': return 'primary'
  case 'matching': return 'success'
  case 'fill_blank': return 'warning'
  case 'reordering': return 'info'
  case 'fill_blank_choices': return 'purple'
  case 'writing': return 'orange'
  default: return 'grey'
  }
}

const getDifficultyColor = difficulty => {
  switch (difficulty) {
  case 'easy': return 'success'
  case 'medium': return 'warning'
  case 'hard': return 'error'
  default: return 'grey'
  }
}

const truncateText = text => {
  if (!text) return ''
  
  // Handle translatable fields (objects)
  if (typeof text === 'object') {
    // Use the first available language
    const firstLang = Object.keys(text)[0]

    text = text[firstLang] || ''
  }
  
  if (text && text.length > 100) {
    return text.substring(0, 100) + '...'
  }
  
  return text
}

// Lifecycle hooks
onMounted(async () => {
  await fetchCourses()
  fetchQuestions()
})
</script>

<style lang="scss" scoped>
.w-200 {
  width: 200px;
}

.search-input {
  width: 250px;
}
</style> 
