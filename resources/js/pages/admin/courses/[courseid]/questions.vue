<script setup>
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import QuestionEditDialog from '@/components/dialogs/QuestionEditDialog.vue'
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'questions',
  },
})

const toast = useToast()
const { locale } = useI18n()
const router = useRouter()
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
const sortBy = ref([{ key: 'created_at', order: 'desc' }])
const selectedRows = ref([])

const questionsData = ref({
  data: [],
  total: 0,
  currentPage: 1,
  perPage: 10,
  lastPage: 1,
})

const availableTags = ref([])

// Edit and delete dialogs
const isEditDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const editQuestion = ref({})
const questionToDelete = ref(null)

// Question types mapping
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

// Data table headers
const headers = [
  { title: 'Question', key: 'question_text' },
  { title: 'Type', key: 'type' },
  { title: 'Difficulty', key: 'difficulty' },
  { title: 'Points', key: 'points' },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Stats for widget cards
const widgetData = ref([
  {
    title: 'Total Questions',
    value: '0',
    icon: 'tabler-list',
    iconColor: 'primary',
  },
  {
    title: 'Multiple Choice',
    value: '0',
    icon: 'tabler-checkbox',
    iconColor: 'success',
  },
  {
    title: 'Fill in the Blank',
    value: '0',
    icon: 'tabler-input',
    iconColor: 'warning',
  },
  {
    title: 'Writing',
    value: '0',
    icon: 'tabler-pencil',
    iconColor: 'info',
  },
])

// Fetch course details
const fetchCourse = async () => {
  if (!courseId.value) return
  
  try {
    isLoading.value = true

    const response = await api.get(`/admin/courses/${courseId.value}`)

    course.value = response.course || response
  } catch (error) {
    console.error('Error fetching course:', error)
    toast.error('Failed to load course details')
  } finally {
    isLoading.value = false
  }
}

// Fetch questions
const fetchQuestions = async () => {
  isLoading.value = true
  try {
    const params = {
      "course_id": courseId.value,
      search: searchQuery.value || undefined,
      type: selectedType.value || undefined,
      difficulty: selectedDifficulty.value || undefined,
      tags: selectedTag.value || undefined,
      "sort_by": sortBy.value[0]?.key || 'created_at',
      "sort_direction": sortBy.value[0]?.order || 'desc',
      page: page.value,
      "per_page": itemsPerPage.value,
    }
    
    const response = await api.get(`/admin/courses/${courseId.value}/questions`, { params })
    
    if (response && typeof response === 'object') {
      questionsData.value = response
      updateWidgetCounts()
      extractTags(response.data || [])
    } else {
      console.warn('Unexpected API response format:', response)
      questionsData.value = { data: [], total: 0 }
    }
  } catch (error) {
    console.error('Error fetching questions:', error)
    toast.error('Failed to load questions')
    questionsData.value = { data: [], total: 0 }
  } finally {
    isLoading.value = false
  }
}

// Update widget stats
const updateWidgetCounts = () => {
  if (!questionsData.value || !questionsData.value.data) return
  
  const questions = questionsData.value.data
  const totalCount = questionsData.value.total || questions.length
  
  // Count by type
  const mcqCount = questions.filter(q => q.type === 'mcq').length
  const fillBlankCount = questions.filter(q => q.type === 'fill_blank' || q.type === 'fill_blank_choices').length
  const writingCount = questions.filter(q => q.type === 'writing').length
  
  // Update widget values
  widgetData.value[0].value = totalCount.toString()
  widgetData.value[1].value = mcqCount.toString()
  widgetData.value[2].value = fillBlankCount.toString()
  widgetData.value[3].value = writingCount.toString()
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
  editQuestion.value = {
    "course_id": courseId.value,
    type: 'mcq',
    difficulty: 'medium',
    points: 1,
    "question_text": '',
    options: [],
    "correct_answer": [],
    tags: [],

  }
  isEditDialogVisible.value = true
}

// Edit question
const onEditQuestion = question => {
  editQuestion.value = { ...question }
  isEditDialogVisible.value = true
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
    toast.success('Question deleted successfully')
    fetchQuestions()
  } catch (error) {
    console.error('Error deleting question:', error)
    toast.error(error.response?.data?.message || 'Failed to delete question')
  }
}

// Get question type display name
const getQuestionTypeLabel = type => {
  const typeObj = questionTypes.find(t => t.value === type)
  
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

// Watch locale change
watch(() => locale.value, () => {
  fetchQuestions()
})
</script>

<template>
  <section>
    <!-- Breadcrumb Navigation -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Courses', to: '/admin/courses' },
        { title: course ? course.title : 'Course', disabled: true },
        { title: 'Questions', disabled: true }
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
            
            <VCardSubtitle class="pt-1">
              {{ widget.subtitle }}
            </VCardSubtitle>
            
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
              placeholder="Search Questions"
              prepend-inner-icon="tabler-search"
              single-line
              hide-details
              class="mb-2"
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
              label="Question Type"
              :items="questionTypes"
              item-title="title"
              item-value="value"
              clearable
              hide-details
              class="flex-grow-1"
            />
            
            <VSelect
              v-model="selectedDifficulty"
              density="compact"
              label="Difficulty"
              :items="difficultyLevels"
              item-title="title"
              item-value="value"
              clearable
              hide-details
              class="flex-grow-1"
            />
            
            <VSelect
              v-model="selectedTag"
              density="compact"
              label="Tag"
              :items="availableTags"
              item-title="title"
              item-value="value"
              clearable
              hide-details
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
          prepend-icon="tabler-plus"
          class="ms-auto"
          @click="addNewQuestion"
        >
          Add New Question
        </VBtn>
      </VCardText>

      <VDivider />

      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        v-model:sort-by="sortBy"
        v-model:selected="selectedRows"
        :headers="headers"
        :items="questionsData.data"
        :items-length="questionsData.total"
        :loading="isLoading"
        @update:options="updateOptions"
      >
        <!-- Question text column -->
        <!-- eslint-disable-next-line vue/valid-v-slot -->
        <template #item.question_text="{ item }">
          <div v-if="item.type == 'mcq'">
            {{ item.question_text }}
            <ol
              type="a"
              class="ms-5"
            >
              <li
                v-for="(option, index) in item.options"
                :key="index"
              >
                {{ option }}
              </li>
            </ol>
          </div>
          <div v-else-if="item.type == 'fill_blank'">
            {{ item.question_text }}
          </div>
          <div v-else-if="item.type == 'matching'">
            {{ item.question_text }}
            <ul class="ms-5">
              <li
                v-for="(pair, index) in item.options"
                :key="index"
              >
                Left: {{ pair.left }} | Right: {{ pair.right }}
              </li>
            </ul>
          </div>
          <div v-else-if="item.type == 'fill_blank_choices'">
            {{ item.question_text }}
            <ul class="ms-5">
              <li
                v-for="(option, index) in item.options"
                :key="index"
              >
                blank {{ index + 1 }}:<br>
                <ul class="ms-2">
                  <li>
                    Placeholder: {{ option.placeholder }}
                  </li>
                  <li>
                    Choices: {{ option.options.join(', ') }}
                  </li>
                  <li>
                    Correct Choice: {{ option.options[option.correct_answer] }}
                  </li>
                </ul>
              </li>
            </ul>
          </div>
          <div v-else-if="item.type == 'reordering'">
            {{ item.question_text }}
            <ol
              type="1"
              class="ms-5"
            >
              <li
                v-for="(option, index) in item.options"
                :key="index"
              >
                {{ option }}
              </li>
            </ol>
          </div>
          <div v-else>
            {{ item.question_text }}
          </div>
        </template>

        <!-- Type column -->
        <!-- eslint-disable-next-line vue/valid-v-slot -->
        <template #item.type="{ item }">
          <VChip
            :color="item.type === 'mcq' ? 'success' : item.type === 'writing' ? 'info' : 'warning'"
            size="small"
            class="text-capitalize"
          >
            {{ getQuestionTypeLabel(item.type) }}
          </VChip>
        </template>

        <!-- Difficulty column -->
        <!-- eslint-disable-next-line vue/valid-v-slot -->
        <template #item.difficulty="{ item }">
          <VChip
            :color="item.difficulty === 'easy' ? 'success' : item.difficulty === 'medium' ? 'warning' : 'error'"
            size="small"
            class="text-capitalize"
          >
            {{ item.difficulty }}
          </VChip>
        </template>

        <!-- Actions column -->
        <!-- eslint-disable-next-line vue/valid-v-slot -->
        <template #item.actions="{ item }">
          <div class="d-flex gap-1">
            <VBtn
              icon
              variant="text"
              size="small"
              color="medium-emphasis"
              @click="onEditQuestion(item)"
            >
              <VIcon icon="tabler-edit" />
            </VBtn>
            
            <VBtn
              icon
              variant="text"
              size="small"
              color="medium-emphasis"
              @click="confirmDelete(item)"
            >
              <VIcon icon="tabler-trash" />
            </VBtn>
          </div>
        </template>

        <!-- Empty state -->
        <template #no-data>
          <div class="text-center pa-6">
            <h4 class="text-h4 mb-2">
              No questions found
            </h4>
            <p class="mb-4">
              Add new questions to create a question bank for this course.
            </p>
            <VBtn
              prepend-icon="tabler-plus"
              @click="addNewQuestion"
            >
              Add New Question
            </VBtn>
          </div>
        </template>
      </VDataTableServer>

      <VDivider />

      <!-- Pagination (if needed in addition to the built-in pagination) -->
      <VCardText class="d-flex flex-wrap justify-space-between">
        <div>{{ questionsData.data?.length || 0 }} of {{ questionsData.total || 0 }} questions</div>
      </VCardText>
    </VCard>

    <!-- Question Edit Dialog -->
    
    <QuestionEditDialog
      v-model:is-dialog-visible="isEditDialogVisible"
      :question="editQuestion"
      :course-id="courseId"
      @refresh="fetchQuestions"
    /> 
   

    <!-- Delete Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isDeleteDialogVisible"
      confirmation-question="Are you sure you want to delete this question? This action cannot be undone."
      confirm-title="Question Deleted"
      confirm-msg="The question has been deleted successfully."
      @confirm="deleteQuestion"
    />
  </section>
</template> 
