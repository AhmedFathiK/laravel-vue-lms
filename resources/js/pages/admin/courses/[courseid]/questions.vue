<script setup>
import AddEditQuestionDialog from '@/components/dialogs/AddEditQuestionDialog.vue'
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'questions',
  },
})

const { t } = useI18n()
const toast = useToast()
const route = useRoute()

// Get course ID from route parameter
const courseId = computed(() => route.params.courseid)

// Data refs
const searchQuery = ref('')
const selectedType = ref(null)
const selectedDifficulty = ref(null)
const selectedTag = ref(null)
const isLoading = ref(false)
const course = ref(null)
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref([{ key: 'createdAt', order: 'desc' }])

const questionsData = ref({
  data: [],
  total: 0,
  currentPage: 1,
  perPage: 10,
  lastPage: 1,
})

const availableTags = ref([])

// Edit and delete dialogs
const isDialogOpen = ref(false)
const dialogMode = ref('add')
const isDeleteDialogVisible = ref(false)
const editQuestion = ref({})
const questionToDelete = ref(null)

// Question types mapping (reactive for i18n)
const questionTypes = computed(() => [
  { title: t('questions.types.multipleChoice', 'Multiple Choice'), value: 'mcq' },
  { title: t('questions.types.matching', 'Matching'), value: 'matching' },
  { title: t('questions.types.fillBlank', 'Fill in the Blank'), value: 'fill_blank' },
  { title: t('questions.types.fillBlankChoices', 'Fill in the Blank with Choices'), value: 'fill_blank_choices' },
  { title: t('questions.types.reordering', 'Reordering'), value: 'reordering' },
  { title: t('questions.types.writing', 'Writing'), value: 'writing' },
])

// Difficulty levels (reactive for i18n)
const difficultyLevels = computed(() => [
  { title: t('questions.difficulty.easy', 'Easy'), value: 'easy' },
  { title: t('questions.difficulty.medium', 'Medium'), value: 'medium' },
  { title: t('questions.difficulty.hard', 'Hard'), value: 'hard' },
])

// Data table headers (reactive for i18n)
const headers = computed(() => [
  { title: t('questions.table.id', 'ID'), key: 'id' },
  { title: t('questions.table.question', 'Question'), key: 'questionText' },
  { title: t('questions.table.type', 'Type'), key: 'type' },
  { title: t('questions.table.difficulty', 'Difficulty'), key: 'difficulty' },
  { title: t('questions.table.points', 'Points'), key: 'points' },
  { title: t('questions.table.actions', 'Actions'), key: 'actions', sortable: false },
])

// Computed total for data table
const totalQuestions = computed(() => questionsData.value.total || 0)

// Stats for widget cards (reactive for i18n)
const widgetData = computed(() => {
  const questions = questionsData.value.data || []
  const totalCount = questionsData.value.total || 0
  const mcqCount = questions.filter(q => q.type === 'mcq').length
  const fillBlankCount = questions.filter(q => q.type === 'fill_blank' || q.type === 'fill_blank_choices').length
  const writingCount = questions.filter(q => q.type === 'writing').length

  return [
    {
      title: t('questions.widgets.totalQuestions', 'Total Questions'),
      value: totalCount.toString(),
      icon: 'tabler-list',
      iconColor: 'primary',
    },
    {
      title: t('questions.widgets.multipleChoice', 'Multiple Choice'),
      value: mcqCount.toString(),
      icon: 'tabler-checkbox',
      iconColor: 'success',
    },
    {
      title: t('questions.widgets.fillInBlank', 'Fill in the Blank'),
      value: fillBlankCount.toString(),
      icon: 'tabler-forms',
      iconColor: 'warning',
    },
    {
      title: t('questions.widgets.writing', 'Writing'),
      value: writingCount.toString(),
      icon: 'tabler-pencil',
      iconColor: 'info',
    },
  ]
})

// Fetch course details
const fetchCourse = async () => {
  if (!courseId.value) return
  
  try {
    isLoading.value = true

    const response = await api.get(`/admin/courses/${courseId.value}`)

    course.value = response.course || response
  } catch (error) {
    console.error('Error fetching course:', error)
    toast.error(t('questions.errors.failedToLoadCourse', 'Failed to load course details'))
  } finally {
    isLoading.value = false
  }
}

// Fetch questions
const fetchQuestions = async () => {
  isLoading.value = true
  try {
    const params = {
      courseId: courseId.value,
      search: searchQuery.value || undefined,
      type: selectedType.value || undefined,
      difficulty: selectedDifficulty.value || undefined,
      tags: selectedTag.value || undefined,
      sortBy: sortBy.value[0]?.key || 'createdAt',
      sortDirection: sortBy.value[0]?.order || 'desc',
      page: page.value,
      perPage: itemsPerPage.value,
    }
    
    const response = await api.get(`/admin/courses/${courseId.value}/questions`, { params })
    
    if (response && typeof response === 'object') {
      questionsData.value = response
      extractTags(response.data || [])
    } else {
      questionsData.value = { data: [], total: 0 }
    }
  } catch (error) {
    console.error('Error fetching questions:', error)
    toast.error(t('questions.errors.failedToLoadQuestions', 'Failed to load questions'))
    questionsData.value = { data: [], total: 0 }
  } finally {
    isLoading.value = false
  }
}

// Extract unique tags from questions
const extractTags = questions => {
  const tags = new Set()

  questions.forEach(question => {
    if (question.tags && Array.isArray(question.tags)) {
      question.tags.forEach(tag => {
        if (tag) tags.add(tag)
      })
    }
  })
  availableTags.value = Array.from(tags).map(tag => ({ title: tag, value: tag }))
}

// Update data table options
const updateOptions = options => {
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy
  }
  fetchQuestions()
}

// Add new question
const addNewQuestion = () => {
  dialogMode.value = 'add'
  editQuestion.value = {}
  isDialogOpen.value = true
}

// Edit question
const onEditQuestion = question => {
  dialogMode.value = 'edit'
  editQuestion.value = JSON.parse(JSON.stringify(question))
  isDialogOpen.value = true
}

// Delete question
const confirmDelete = question => {
  questionToDelete.value = question
  isDeleteDialogVisible.value = true
}

const deleteQuestion = async () => {
  if (!questionToDelete.value) return
  
  try {
    await api.delete(`/admin/courses/${courseId.value}/questions/${questionToDelete.value.id}`)
    toast.success(t('questions.success.questionDeleted', 'Question deleted successfully'))
    fetchQuestions()
  } catch (error) {
    console.error('Error deleting question:', error)
    toast.error(error.response?.data?.message || t('questions.errors.failedToDelete', 'Failed to delete question'))
  }
}

// Get question type display name
const getQuestionTypeLabel = type => {
  const typeObj = questionTypes.value.find(t => t.value === type)
  
  return typeObj ? typeObj.title : type
}

// Initialize
onMounted(() => {
  fetchCourse()
  fetchQuestions()
})

// Watch for changes to refetch
watch([searchQuery, selectedType, selectedDifficulty, selectedTag, page, itemsPerPage], () => {
  fetchQuestions()
})
</script>

<template>
  <section>
    <!-- Breadcrumb Navigation -->
    <VBreadcrumbs
      :items="[
        { title: t('questions.breadcrumb.admin', 'Admin'), disabled: true },
        { title: t('questions.breadcrumb.courses', 'Courses'), to: '/admin/courses' },
        { title: course ? course.title : t('questions.breadcrumb.course', 'Course'), disabled: true },
        { title: t('questions.breadcrumb.questions', 'Questions'), disabled: true }
      ]"
      class="mb-4"
    />

    <VRow>
      <!-- Stats Widgets -->
      <VCol
        v-for="widget in widgetData"
        :key="widget.title"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>{{ widget.title }}</VCardTitle>
            
            <template #append>
              <VAvatar
                :color="widget.iconColor"
                rounded
                variant="tonal"
              >
                <VIcon :icon="widget.icon" />
              </VAvatar>
            </template>
            
            <div class="d-flex align-center mt-2">
              <h3 class="text-h3">
                {{ widget.value }}
              </h3>
            </div>
          </VCardItem>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filters -->
    <VCard class="mb-6 mt-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="4"
          >
            <AppTextField
              v-model="searchQuery"
              density="compact"
              :placeholder="t('questions.filters.searchPlaceholder', 'Search Questions')"
              prepend-inner-icon="tabler-search"
              single-line
              hide-details
              variant="outlined"
            />
          </VCol>
          
          <VCol
            cols="12"
            sm="8"
            class="d-flex gap-4"
          >
            <VSelect
              v-model="selectedType"
              density="compact"
              :label="t('questions.filters.questionType', 'Question Type')"
              :items="questionTypes"
              item-title="title"
              item-value="value"
              clearable
              hide-details
              variant="outlined"
              class="flex-grow-1"
            />
            
            <VSelect
              v-model="selectedDifficulty"
              density="compact"
              :label="t('questions.filters.difficulty', 'Difficulty')"
              :items="difficultyLevels"
              item-title="title"
              item-value="value"
              clearable
              hide-details
              variant="outlined"
              class="flex-grow-1"
            />
            
            <VSelect
              v-model="selectedTag"
              density="compact"
              :label="t('questions.filters.tag', 'Tag')"
              :items="availableTags"
              item-title="title"
              item-value="value"
              clearable
              hide-details
              variant="outlined"
              class="flex-grow-1"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Questions Data Table -->
    <VCard>
      <VCardText class="d-flex flex-wrap py-4">
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          class="ms-auto"
          @click="addNewQuestion"
        >
          {{ t('questions.page.addNewQuestion', 'Add New Question') }}
        </VBtn>
      </VCardText>

      <VDivider />
      
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        v-model:sort-by="sortBy"
        :headers="headers"
        :items="questionsData.data"
        :items-length="totalQuestions"
        :loading="isLoading"
        @update:options="updateOptions"
      >
        <!-- Question text column -->
        <template #[`item.questionText`]="{ item }">
          <div class="py-2">
            <div class="font-weight-medium mb-1">
              {{ item.questionText }}
            </div>
            
            <!-- MCQ -->
            <ol
              v-if="item.type === 'mcq'"
              type="a"
              class="ms-5 text-caption"
            >
              <li
                v-for="(option, index) in item.options"
                :key="index"
              >
                {{ option }}
              </li>
            </ol>
            
            <!-- Matching -->
            <ul
              v-else-if="item.type === 'matching'"
              class="ms-5 text-caption"
            >
              <li
                v-for="(pair, index) in item.options"
                :key="index"
              >
                {{ pair.left }} → {{ pair.right }}
              </li>
            </ul>
            
            <!-- Fill blank with choices -->
            <ul
              v-else-if="item.type === 'fill_blank_choices'"
              class="ms-5 text-caption"
            >
              <li
                v-for="(option, index) in item.options"
                :key="index"
              >
                Blank {{ index + 1 }}: {{ option.options.join(', ') }}
              </li>
            </ul>
            
            <!-- Reordering -->
            <ol
              v-else-if="item.type === 'reordering'"
              type="1"
              class="ms-5 text-caption"
            >
              <li
                v-for="(option, index) in item.options"
                :key="index"
              >
                {{ option }}
              </li>
            </ol>
          </div>
        </template>

        <!-- Type column -->
        <template #[`item.type`]="{ item }">
          <VChip
            :color="item.type === 'mcq' ? 'success' : item.type === 'writing' ? 'info' : 'warning'"
            size="small"
          >
            {{ getQuestionTypeLabel(item.type) }}
          </VChip>
        </template>

        <!-- Difficulty column -->
        <template #[`item.difficulty`]="{ item }">
          <VChip
            :color="item.difficulty === 'easy' ? 'success' : item.difficulty === 'medium' ? 'warning' : 'error'"
            size="small"
            class="text-capitalize"
          >
            {{ item.difficulty }}
          </VChip>
        </template>

        <!-- Actions column -->
        <template #[`item.actions`]="{ item }">
          <div class="d-flex gap-1">
            <IconBtn @click="onEditQuestion(item)">
              <VIcon icon="tabler-edit" />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ t('common.edit', 'Edit') }}
              </VTooltip>
            </IconBtn>
            
            <IconBtn @click="confirmDelete(item)">
              <VIcon icon="tabler-trash" />
              <VTooltip
                activator="parent"
                location="top"
              >
                {{ t('common.delete', 'Delete') }}
              </VTooltip>
            </IconBtn>
          </div>
        </template>

        <!-- Empty state -->
        <template #no-data>
          <div class="text-center pa-6">
            <h4 class="text-h4 mb-2">
              {{ t('questions.table.noQuestions', 'No questions found') }}
            </h4>
            <p class="mb-4">
              {{ t('questions.table.noQuestionsDesc', 'Add new questions to create a question bank for this course') }}
            </p>
            <VBtn
              color="primary"
              prepend-icon="tabler-plus"
              @click="addNewQuestion"
            >
              {{ t('questions.page.addNewQuestion', 'Add New Question') }}
            </VBtn>
          </div>
        </template>
      </VDataTableServer>

      <VDivider />

      <VCardText class="d-flex flex-wrap justify-space-between">
        <div>
          {{ t('questions.table.showing', '{count} of {total} questions', {
            count: questionsData.data?.length || 0,
            total: totalQuestions
          }) }}
        </div>
      </VCardText>
    </VCard>

    <!-- Question Edit Dialog -->
    <AddEditQuestionDialog
      v-model:is-dialog-visible="isDialogOpen"
      :dialog-mode="dialogMode"
      :question="editQuestion"
      :course-id="courseId"
      @refresh="fetchQuestions"
    />

    <!-- Delete Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isDeleteDialogVisible"
      :confirmation-question="t('questions.delete.confirmQuestion', 'Are you sure you want to delete this question? This action cannot be undone.')"
      @confirm="deleteQuestion"
    />
  </section>
</template>
