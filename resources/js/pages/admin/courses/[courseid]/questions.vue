<script setup>
import AddEditQuestionDialog from '@/components/dialogs/AddEditQuestionDialog.vue'
import AddEditQuestionContextDialog from '@/components/dialogs/AddEditQuestionContextDialog.vue'
import QuestionSearchDialog from '@/components/dialogs/QuestionSearchDialog.vue'
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

// Tabs state
const currentTab = ref('questions')

// Data refs
const searchQuery = ref('')
const selectedType = ref(null)
const selectedDifficulty = ref(null)
const selectedTag = ref(null)
const isLoading = ref(false)
const course = ref(null)

// Questions pagination
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

// Contexts pagination
const contextsPage = ref(1)
const contextsItemsPerPage = ref(10)
const contextsSortBy = ref([{ key: 'createdAt', order: 'desc' }])

const contextsData = ref({
  data: [],
  total: 0,
})

const availableTags = ref([])

// Edit and delete dialogs
const isDialogOpen = ref(false)
const dialogMode = ref('add')
const isDeleteDialogVisible = ref(false)
const editQuestion = ref({})
const questionToDelete = ref(null)

// Context dialogs
const isContextDialogOpen = ref(false)
const contextDialogMode = ref('add')
const editContext = ref({})
const isContextDeleteDialogVisible = ref(false)
const contextToDelete = ref(null)

const showQuestionsForContext = ref(null)

// Add questions to context dialog
const isAddQuestionsToContextOpen = ref(false)
const activeContext = ref(null)

// ... existing code ...

// Headers for contexts
const contextHeaders = computed(() => [
  { title: t('questions.context.table.id', 'ID'), key: 'id' },
  { title: t('questions.context.table.title', 'Title'), key: 'title' },
  { title: t('questions.context.table.media', 'Context Type'), key: 'contextType' },
  { title: t('questions.context.table.questionsCount', 'Questions'), key: 'questionsCount' },
  { title: t('questions.context.table.actions', 'Actions'), key: 'actions', sortable: false },
])

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

const deleteQuestion = async result => {
  if (!result || !result.confirmed) return
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

// Fetch contexts
const fetchContexts = async () => {
  if (currentTab.value !== 'contexts') return
  
  isLoading.value = true
  try {
    const params = {
      search: searchQuery.value || undefined,
      page: contextsPage.value,
      perPage: contextsItemsPerPage.value,
    }
    
    const response = await api.get(`/admin/courses/${courseId.value}/question-contexts`, { params })
    
    contextsData.value = response
  } catch (error) {
    console.error('Error fetching contexts:', error)
    toast.error(t('questions.errors.failedToLoadContexts', 'Failed to load question contexts'))
  } finally {
    isLoading.value = false
  }
}

// Add new context
const addNewContext = () => {
  contextDialogMode.value = 'add'
  editContext.value = {}
  isContextDialogOpen.value = true
}

// Edit context
const onEditContext = context => {
  contextDialogMode.value = 'edit'
  editContext.value = JSON.parse(JSON.stringify(context))
  isContextDialogOpen.value = true
}

// Save context
const saveContext = async formData => {
  try {
    if (contextDialogMode.value === 'add') {
      await api.post(`/admin/courses/${courseId.value}/question-contexts`, formData)
      toast.success(t('questions.success.contextCreated', 'Question context created successfully'))
    } else {
      // For FormData updates (with files), we MUST use POST with _method=PUT 
      // because PHP/Laravel cannot parse multipart/form-data with the PUT method directly.
      // The _method=PUT is already appended inside AddEditQuestionContextDialog.vue
      await api.post(`/admin/courses/${courseId.value}/question-contexts/${editContext.value.id}`, formData)
      toast.success(t('questions.success.contextUpdated', 'Question context updated successfully'))
    }
    isContextDialogOpen.value = false
    fetchContexts()
  } catch (error) {
    console.error('Error saving context:', error)
    toast.error(error.response?.data?.message || t('questions.errors.failedToSaveContext', 'Failed to save question context'))
  }
}

// Delete context
const confirmDeleteContext = context => {
  contextToDelete.value = context
  isContextDeleteDialogVisible.value = true
}

const deleteContext = async result => {
  if (!result || !result.confirmed) return
  if (!contextToDelete.value) return
  
  try {
    await api.delete(`/admin/courses/${courseId.value}/question-contexts/${contextToDelete.value.id}`)
    toast.success(t('questions.success.contextDeleted', 'Question context deleted successfully'))
    fetchContexts()
  } catch (error) {
    console.error('Error deleting context:', error)
    toast.error(error.response?.data?.message || t('questions.errors.failedToDeleteContext', 'Failed to delete question context'))
  }
}

// Add questions to context
const openAddQuestionsToContext = context => {
  activeContext.value = context
  isAddQuestionsToContextOpen.value = true
}

const handleQuestionsToContextAdded = async selectedQuestions => {
  if (!activeContext.value) return
  
  try {
    await api.post(`/admin/courses/${courseId.value}/question-contexts/${activeContext.value.id}/questions`, {
      question_ids: selectedQuestions.map(q => q.id),
    })
    toast.success(t('questions.success.questionsAddedToContext', 'Questions added to context successfully'))
    fetchContexts()
  } catch (error) {
    console.error('Error adding questions to context:', error)
    toast.error(error.response?.data?.message || t('questions.errors.failedToAddQuestionsToContext', 'Failed to add questions to context'))
  }
}

const removeQuestionFromContext = async (context, question) => {
  try {
    await api.delete(`/admin/courses/${courseId.value}/question-contexts/${context.id}/questions/${question.id}`)
    toast.success(t('questions.success.questionRemovedFromContext', 'Question removed from context successfully'))
    fetchContexts()
  } catch (error) {
    console.error('Error removing question from context:', error)
    toast.error(error.response?.data?.message || t('questions.errors.failedToRemoveQuestionFromContext', 'Failed to remove question from context'))
  }
}

// Initialize
onMounted(() => {
  fetchCourse()
  if (currentTab.value === 'questions') {
    fetchQuestions()
  } else {
    fetchContexts()
  }
})

// Watch for changes to refetch
watch([searchQuery, selectedType, selectedDifficulty, selectedTag, page, itemsPerPage], () => {
  if (currentTab.value === 'questions') {
    fetchQuestions()
  }
})

watch([contextsPage, contextsItemsPerPage, currentTab], () => {
  if (currentTab.value === 'contexts') {
    fetchContexts()
  } else if (currentTab.value === 'questions' && questionsData.value.data.length === 0) {
    fetchQuestions()
  }
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

    <!-- Tabs -->
    <VTabs
      v-model="currentTab"
      class="mb-4"
    >
      <VTab value="questions">
        {{ t('questions.tabs.questions', 'Questions') }}
      </VTab>
      <VTab value="contexts">
        {{ t('questions.tabs.contexts', 'Question Contexts') }}
      </VTab>
    </VTabs>

    <VWindow v-model="currentTab">
      <!-- Questions Tab -->
      <VWindowItem value="questions">
        <!-- Stats Widgets -->
        <VRow>
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
                <AppSelect
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
                
                <AppSelect
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
                
                <AppSelect
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
            <!-- ... existing data table content ... -->
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
                    v-for="(option, index) in (item.content?.options || item.options)"
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
                    v-for="(pair, index) in (item.content?.pairs || item.options)"
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
                    v-for="(option, index) in (item.content?.blanks || item.options)"
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
                    v-for="(option, index) in (item.content?.items || item.options)"
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
                <VTooltip location="top">
                  <template #activator="{ props }">
                    <IconBtn
                      v-bind="props"
                      @click="onEditQuestion(item)"
                    >
                      <VIcon icon="tabler-edit" />
                    </IconBtn>
                  </template>
                  {{ t('common.edit', 'Edit') }}
                </VTooltip>
            
                <VTooltip location="top">
                  <template #activator="{ props }">
                    <IconBtn
                      v-bind="props"
                      @click="confirmDelete(item)"
                    >
                      <VIcon icon="tabler-trash" />
                    </IconBtn>
                  </template>
                  {{ t('common.delete', 'Delete') }}
                </VTooltip>
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
      </VWindowItem>

      <!-- Contexts Tab -->
      <VWindowItem value="contexts">
        <VCard>
          <VCardText class="d-flex flex-wrap py-4">
            <VBtn
              color="primary"
              prepend-icon="tabler-plus"
              class="ms-auto"
              @click="addNewContext"
            >
              {{ t('questions.page.addNewContext', 'Add New Context') }}
            </VBtn>
          </VCardText>

          <VDivider />

          <VDataTableServer
            v-model:items-per-page="contextsItemsPerPage"
            v-model:page="contextsPage"
            v-model:sort-by="contextsSortBy"
            :headers="contextHeaders"
            :items="contextsData.data"
            :items-length="contextsData.total || 0"
            :loading="isLoading"
          >
            <!-- Context Type column -->
            <template #[`item.contextType`]="{ item }">
              <VChip
                color="primary"
                size="small"
                class="text-capitalize"
              >
                {{ item.contextType?.replace('_', ' ') }}
              </VChip>
            </template>

            <!-- Questions Count column -->
            <template #[`item.questionsCount`]="{ item }">
              <div class="d-flex align-center gap-2">
                <span>{{ item.questions?.length || 0 }}</span>
                <VTooltip location="top">
                  <template #activator="{ props }">
                    <IconBtn
                      v-bind="props"
                      size="small"
                      color="primary"
                      variant="tonal"
                      @click="openAddQuestionsToContext(item)"
                    >
                      <VIcon
                        icon="tabler-plus"
                        size="18"
                      />
                    </IconBtn>
                  </template>
                  {{ t('questions.page.addQuestionsToContext', 'Add Questions to Context') }}
                </VTooltip>
                
                <VTooltip
                  v-if="item.questions?.length"
                  location="top"
                >
                  <template #activator="{ props }">
                    <IconBtn
                      v-bind="props"
                      size="small"
                      color="secondary"
                      variant="tonal"
                      @click="showQuestionsForContext = showQuestionsForContext === item.id ? null : item.id"
                    >
                      <VIcon
                        :icon="showQuestionsForContext === item.id ? 'tabler-chevron-up' : 'tabler-chevron-down'"
                        size="18"
                      />
                    </IconBtn>
                  </template>
                  {{ showQuestionsForContext === item.id ? t('common.hideDetails', 'Hide Details') : t('common.showDetails', 'Show Details') }}
                </VTooltip>
              </div>

              <VExpandTransition>
                <div
                  v-if="showQuestionsForContext === item.id && item.questions?.length"
                  class="mt-3 pa-2 bg-var-theme-background rounded border"
                >
                  <VList
                    density="compact"
                    class="bg-transparent"
                  >
                    <VListItem
                      v-for="question in item.questions"
                      :key="question.id"
                      class="px-0 mb-1"
                    >
                      <template #prepend>
                        <VChip
                          size="x-small"
                          color="primary"
                          class="me-2"
                        >
                          {{ question.type }}
                        </VChip>
                      </template>
                      
                      <VListItemTitle
                        class="text-caption font-weight-medium text-truncate"
                        style="max-width: 250px;"
                      >
                        <!-- Handle multilingual question text safely -->
                        {{ typeof question.questionText === 'object' ? (question.questionText.en || question.questionText.ar || 'No text') : (question.questionText || 'No text') }}
                      </VListItemTitle>
                      
                      <template #append>
                        <div class="d-flex align-center">
                          <IconBtn
                            size="x-small"
                            color="info"
                            variant="text"
                            @click="onEditQuestion(question)"
                          >
                            <VIcon
                              icon="tabler-eye"
                              size="16"
                            />
                          </IconBtn>
                          <IconBtn
                            size="x-small"
                            color="error"
                            variant="text"
                            @click="removeQuestionFromContext(item, question)"
                          >
                            <VIcon
                              icon="tabler-x"
                              size="16"
                            />
                          </IconBtn>
                        </div>
                      </template>
                    </VListItem>
                  </VList>
                </div>
              </VExpandTransition>
            </template>

            <!-- Actions column -->
            <template #[`item.actions`]="{ item }">
              <div class="d-flex gap-1">
                <VTooltip location="top">
                  <template #activator="{ props }">
                    <IconBtn
                      v-bind="props"
                      @click="onEditContext(item)"
                    >
                      <VIcon icon="tabler-edit" />
                    </IconBtn>
                  </template>
                  {{ t('common.edit', 'Edit') }}
                </VTooltip>
            
                <VTooltip location="top">
                  <template #activator="{ props }">
                    <IconBtn
                      v-bind="props"
                      @click="confirmDeleteContext(item)"
                    >
                      <VIcon icon="tabler-trash" />
                    </IconBtn>
                  </template>
                  {{ t('common.delete', 'Delete') }}
                </VTooltip>
              </div>
            </template>

            <!-- Empty state -->
            <template #no-data>
              <div class="text-center pa-6">
                <h4 class="text-h4 mb-2">
                  {{ t('questions.table.noContexts', 'No contexts found') }}
                </h4>
                <p class="mb-4">
                  {{ t('questions.table.noContextsDesc', 'Add new question contexts to group questions together') }}
                </p>
                <VBtn
                  color="primary"
                  prepend-icon="tabler-plus"
                  @click="addNewContext"
                >
                  {{ t('questions.page.addNewContext', 'Add New Context') }}
                </VBtn>
              </div>
            </template>
          </VDataTableServer>
        </VCard>
      </VWindowItem>
    </VWindow>

    <!-- Dialogs -->
    <AddEditQuestionDialog
      v-model:is-dialog-visible="isDialogOpen"
      :dialog-mode="dialogMode"
      :data="editQuestion"
      :course-id="courseId"
      @refresh="fetchQuestions"
    />

    <AddEditQuestionContextDialog
      v-model:is-dialog-open="isContextDialogOpen"
      :mode="contextDialogMode"
      :context="editContext"
      @save="saveContext"
    />

    <DeletionConfirmDialog
      v-model:is-dialog-visible="isDeleteDialogVisible"
      :title="t('questions.dialogs.deleteTitle', 'Delete Question')"
      :message="t('questions.dialogs.deleteMessage', 'Are you sure you want to delete this question? This action cannot be undone.')"
      @confirm="deleteQuestion"
    />

    <DeletionConfirmDialog
      v-model:is-dialog-visible="isContextDeleteDialogVisible"
      :title="t('questions.dialogs.deleteContextTitle', 'Delete Context')"
      :message="t('questions.dialogs.deleteContextMessage', 'Are you sure you want to delete this context? This action cannot be undone.')"
      @confirm="deleteContext"
    />

    <QuestionSearchDialog
      v-model:is-dialog-visible="isAddQuestionsToContextOpen"
      :course-id="courseId"
      :exclude-ids="activeContext?.questions?.map(q => q.id) || []"
      no-context
      @select="handleQuestionsToContextAdded"
    />
  </section>
</template>
