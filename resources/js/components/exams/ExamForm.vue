<script setup>
import {
  defineProps,
  defineEmits,
  watch,
  onUnmounted,
  ref,
  computed,
  onMounted,
} from "vue"
import { useRoute } from "vue-router"
import { useToast } from "vue-toastification"
import api from "@/utils/api"
import { useExamForm } from "@/composables/useExamForm"
import AddEditQuestionDialog from "@/components/dialogs/AddEditQuestionDialog.vue"
import QuestionSearchDialog from "@/components/dialogs/QuestionSearchDialog.vue"
import ContextQuestionDialog from "@/components/dialogs/ContextQuestionDialog.vue"
import TiptapEditor from "@core/components/TiptapEditor.vue"
import VideoPlayer from "@/components/VideoPlayer.vue"

const props = defineProps({
  initialData: {
    type: Object,
    default: null,
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  isCreating: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(["save"])

const route = useRoute()
const courseId = computed(() => parseInt(route.params.courseid))

const {
  exam,
  activeSection,
  isAddSectionDialogVisible,
  newSectionTitle,
  addSection,
  deleteSection,
  moveSectionUp,
  moveSectionDown,
  deleteQuestion,
} = useExamForm()

const toast = useToast()

// Local state for dialogs
const isQuestionSearchVisible = ref(false)
const isContextDialogVisible = ref(false)
const isAddQuestionDialogVisible = ref(false)
const activeSectionIndexForAdd = ref(null)
const editingQuestionData = ref(null)

// Sync initial data when provided (Edit Mode)
watch(
  () => props.initialData,
  val => {
    if (val) {
      exam.value = JSON.parse(JSON.stringify(val))
    }
  },
  { immediate: true },
)

const handleSave = () => {
  emit("save", exam.value)
}

const openQuestionSearch = sectionIndex => {
  activeSectionIndexForAdd.value = sectionIndex
  isQuestionSearchVisible.value = true
}

const openContextDialog = sectionIndex => {
  activeSectionIndexForAdd.value = sectionIndex
  isContextDialogVisible.value = true
}

const openCreateQuestion = sectionIndex => {
  activeSectionIndexForAdd.value = sectionIndex
  editingQuestionData.value = null // Clear for add mode
  isAddQuestionDialogVisible.value = true
}

const openEditQuestion = (sectionIndex, questionIndex) => {
  activeSectionIndexForAdd.value = sectionIndex

  const question = exam.value.sections[sectionIndex].questions[questionIndex]


  // We need to pass the question data in the format AddEditQuestionDialog expects
  // It expects snake_case for some fields if it uses them directly, but mostly it uses props.data
  // We'll pass the question object as is, assuming it matches or the dialog handles it.
  // We might need to map conceptIds/termIds if they are not loaded.
  // For now, pass what we have.
  editingQuestionData.value = { ...question }
  isAddQuestionDialogVisible.value = true
}

const handleQuestionsAdded = questions => {
  const sIdx = activeSectionIndexForAdd.value
  if (sIdx === null || !exam.value.sections[sIdx]) return

  const allExistingIds = exam.value.sections.flatMap(s => s.questions.map(q => q.id))
  
  const currentQuestions = exam.value.sections[sIdx].questions || []
  const startOrder = currentQuestions.length

  const newQuestions = questions
    .filter(q => !allExistingIds.includes(q.id))
    .map((q, i) => ({
      id: q.id,
      type: q.type,
      questionText: q.questionText,
      points: q.points || 1,
      order: startOrder + i + 1,
    }))

  if (newQuestions.length < questions.length) {
    // Some were skipped
    const skippedCount = questions.length - newQuestions.length

    // We can show a toast or just silently skip. Given the prompt, skipping is good, maybe a notification is better.
    // Assuming toast is available via a composable or similar if needed, but for now let's just ensure they are skipped.
  }

  exam.value.sections[sIdx].questions.push(...newQuestions)
}

const handleQuestionCreated = response => {
  const sIdx = activeSectionIndexForAdd.value
  if (sIdx === null || !exam.value.sections[sIdx]) return

  // Check structure of response
  const question = response.data?.data || response.data || response
  
  if (question && question.id) {
    if (editingQuestionData.value) {
      // Edit mode: Update existing question in list
      const questions = exam.value.sections[sIdx].questions
      const idx = questions.findIndex(q => q.id === question.id)
      if (idx !== -1) {
        questions[idx] = {
          ...questions[idx],
          type: question.type,
          questionText: question.questionText,

          // Update points if provided (from Exam Builder dialog), otherwise keep existing
          points: question.points !== undefined ? question.points : questions[idx].points,
        }
      }
    } else {
      // Add mode - Check for duplicate
      const allExistingIds = exam.value.sections.flatMap(s => s.questions.map(q => q.id))
      if (allExistingIds.includes(question.id)) {
        // This shouldn't really happen if the backend and search dialog are correct, 
        // but safe to check.
        return
      }

      exam.value.sections[sIdx].questions.push({
        id: question.id,
        type: question.type,
        questionText: question.questionText,
        points: question.points || 1,
        order: (exam.value.sections[sIdx].questions.length || 0) + 1,
      })
    }
  }
}

const objectUrls = ref([])

// Placement Rules Logic
const levels = ref([])

const fetchLevels = async () => {
  if (!courseId.value) return
  try {
    const res = await api.get(`/admin/courses/${courseId.value}/levels`)

    levels.value = res.data || res
  } catch (e) {
    console.error(e)
  }
}

onMounted(() => {
  fetchLevels()
})

const addPlacementRule = () => {
  if (!exam.value.placement_rules) exam.value.placement_rules = []
  exam.value.placement_rules.push({ min: 0, max: 0, level_id: null })
}

const removePlacementRule = index => {
  if (exam.value.placement_rules) {
    exam.value.placement_rules.splice(index, 1)
  }
}

const placementRuleErrors = computed(() => {
  const rules = exam.value.placement_rules || []
  if (rules.length === 0) return []

  const errors = []
  const sortedRules = [...rules].sort((a, b) => (a.min || 0) - (b.min || 0))
  
  let expectedMin = 0
  
  for (const rule of sortedRules) {
    const min = parseFloat(rule.min || 0)
    const max = parseFloat(rule.max || 0)
    
    if (min > expectedMin) {
      errors.push(`Gap between ${expectedMin}% and ${min}%`)
    }
    
    if (max <= min) {
      errors.push(`Invalid range: ${min}-${max}`)
    }

    expectedMin = Math.max(expectedMin, max)
  }

  if (expectedMin < 100) {
    errors.push(`Rules only cover up to ${expectedMin}% (must cover 100%)`)
  }

  return errors
})

onUnmounted(() => {
  objectUrls.value.forEach(url => URL.revokeObjectURL(url))
})
</script>

<template>
  <section>
    <div class="d-flex align-center justify-space-between mb-4">
      <div class="d-flex align-center gap-4">
        <VBtn
          icon="tabler-arrow-left"
          variant="text"
          :to="`/admin/courses/${courseId}/exams`"
        />
        <h2 class="text-h4">
          {{ isCreating ? "Create New Exam" : exam.title }}
        </h2>
      </div>
      <VBtn
        color="primary"
        :loading="isLoading"
        @click="handleSave"
      >
        {{ isCreating ? "Create Exam" : "Save Settings" }}
      </VBtn>
    </div>

    <VRow>
      <!-- Exam Settings Sidebar -->
      <VCol
        cols="12"
        md="3"
      >
        <VCard
          title="Settings"
          class="mb-4"
        >
          <VCardText class="d-flex flex-column gap-4">
            <AppTextField
              v-model="exam.title"
              label="Title"
              placeholder="Enter exam title"
            />

            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model.number="exam.timeLimit"
                  label="Time Limit (min)"
                  type="number"
                  placeholder="Unlimited"
                  hint="Leave empty for no time limit"
                  persistent-hint
                />
              </VCol>
              <VCol cols="6">
                <AppTextField
                  v-model.number="exam.passingPercentage"
                  label="Passing %"
                  type="number"
                />
              </VCol>
              <VCol cols="6">
                <AppTextField
                  v-model.number="exam.maxAttempts"
                  label="Max Attempts"
                  type="number"
                  placeholder="Unlimited"
                  hint="0 for unlimited"
                  persistent-hint
                />
              </VCol>
            </VRow>
            <AppTextarea
              v-model="exam.description"
              label="Description"
              rows="2"
              placeholder="Enter exam description"
            />

            <VSwitch
              v-model="exam.isActive"
              label="Active"
            />
            <VSwitch
              v-model="exam.showAnswers"
              label="Show Answers on Completion"
            />
            <VSwitch
              v-model="exam.randomizeQuestions"
              label="Randomize Questions"
            />

            <AppSelect
              v-model="exam.status"
              :items="['draft', 'published', 'archived']"
              label="Status"
            />
          </VCardText>
        </VCard>

        <!-- Placement Rules Card -->
        <VCard
          title="Placement Rules"
          class="mb-4"
        >
          <VCardText>
            <div
              v-if="placementRuleErrors.length"
              class="mb-4"
            >
              <VAlert
                v-for="(err, i) in placementRuleErrors"
                :key="i"
                type="error"
                density="compact"
                variant="tonal"
                class="mb-1"
              >
                {{ err }}
              </VAlert>
            </div>

            <div v-if="exam.placement_rules && exam.placement_rules.length > 0">
              <div
                v-for="(rule, index) in exam.placement_rules"
                :key="index"
                class="d-flex flex-column gap-2 mb-4 p-2 border rounded"
              >
                <div class="d-flex gap-2">
                  <AppTextField
                    v-model.number="rule.min"
                    label="Min %"
                    type="number"
                    density="compact"
                    hide-details
                    style="width: 80px"
                  />
                  <AppTextField
                    v-model.number="rule.max"
                    label="Max %"
                    type="number"
                    density="compact"
                    hide-details
                    style="width: 80px"
                  />
                  <VBtn
                    icon="tabler-trash"
                    size="x-small"
                    color="error"
                    variant="text"
                    class="ms-auto"
                    @click="removePlacementRule(index)"
                  />
                </div>
                <AppSelect 
                  v-model="rule.level_id" 
                  :items="levels" 
                  item-title="title" 
                  item-value="id" 
                  label="Target Level" 
                  density="compact" 
                  hide-details 
                />
              </div>
            </div>
            <div
              v-else
              class="text-caption text-medium-emphasis mb-2"
            >
              Add rules to configure this as a placement exam.
            </div>
             
            <VBtn
              size="small"
              variant="tonal"
              block
              @click="addPlacementRule"
            >
              Add Rule
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Sections & Questions -->
      <VCol
        cols="12"
        md="9"
      >
        <div class="d-flex justify-space-between align-center mb-4">
          <h3 class="text-h5">
            Sections
          </h3>
          <VBtn
            prepend-icon="tabler-plus"
            @click="isAddSectionDialogVisible = true"
          >
            Add Section
          </VBtn>
        </div>

        <VExpansionPanels
          v-model="activeSection"
          multiple
        >
          <VExpansionPanel
            v-for="(section, sIdx) in exam.sections"
            :key="sIdx"
          >
            <VExpansionPanelTitle>
              <div class="d-flex justify-space-between w-100 align-center me-4">
                <div class="d-flex align-center gap-2">
                  <div class="d-flex flex-column">
                    <VBtn
                      icon="tabler-chevron-up"
                      size="x-small"
                      variant="text"
                      :disabled="sIdx === 0"
                      @click.stop="moveSectionUp(sIdx)"
                    />
                    <VBtn
                      icon="tabler-chevron-down"
                      size="x-small"
                      variant="text"
                      :disabled="sIdx === exam.sections.length - 1"
                      @click.stop="moveSectionDown(sIdx)"
                    />
                  </div>
                  <span class="font-weight-bold">{{ section.title }}</span>
                </div>
                <VBtn
                  icon="tabler-trash"
                  size="x-small"
                  color="error"
                  variant="text"
                  @click.stop="deleteSection(sIdx)"
                />
              </div>
            </VExpansionPanelTitle>
            <VExpansionPanelText>
              <div class="d-flex justify-end gap-2 mb-4">
                <VBtn
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-search"
                  @click="openQuestionSearch(sIdx)"
                >
                  Add Existing
                </VBtn>
                <VBtn
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-category"
                  @click="openContextDialog(sIdx)"
                >
                  Add Context Group
                </VBtn>
                <VBtn
                  size="small"
                  variant="tonal"
                  prepend-icon="tabler-plus"
                  @click="openCreateQuestion(sIdx)"
                >
                  Create New
                </VBtn>
              </div>

              <!-- Questions List -->
              <div
                v-if="section.questions?.length"
                class="d-flex flex-column gap-3"
              >
                <VCard
                  v-for="(q, qIdx) in section.questions"
                  :key="qIdx"
                  variant="outlined"
                >
                  <VCardText class="d-flex justify-space-between align-start">
                    <div>
                      <VChip
                        size="x-small"
                        color="primary"
                        class="mb-2"
                      >
                        {{ q.type.toUpperCase() }}
                      </VChip>
                      <div class="text-body-1">
                        {{ q.questionText.replace(/<[^>]*>?/gm, '') }}
                      </div>
                      <div class="d-flex align-center gap-2 mt-2">
                        <AppTextField
                          v-model.number="q.points"
                          type="number"
                          label="Points"
                          density="compact"
                          hide-details
                          style="max-width: 100px"
                          min="0"
                        />
                        <VBtn
                          icon="tabler-refresh"
                          size="x-small"
                          variant="text"
                          color="secondary"
                          @click="syncQuestionPoints(sIdx, qIdx)"
                        >
                          <VTooltip
                            activator="parent"
                            location="top"
                          >
                            Sync points from Question Bank
                          </VTooltip>
                        </VBtn>
                      </div>
                    </div>
                    <div class="d-flex gap-2">
                      <VBtn
                        icon="tabler-edit"
                        size="x-small"
                        color="primary"
                        variant="text"
                        @click="openEditQuestion(sIdx, qIdx)"
                      />
                      <VBtn
                        icon="tabler-trash"
                        size="x-small"
                        color="error"
                        variant="text"
                        @click="deleteQuestion(sIdx, qIdx)"
                      />
                    </div>
                  </VCardText>
                </VCard>
              </div>
              <div
                v-else
                class="text-center text-medium-emphasis py-4"
              >
                No questions in this section
              </div>
            </VExpansionPanelText>
          </VExpansionPanel>
        </VExpansionPanels>
      </VCol>
    </VRow>

    <!-- Add Section Dialog -->
    <VDialog
      v-model="isAddSectionDialogVisible"
      max-width="400"
    >
      <VForm @submit.prevent="addSection">
        <VCard title="Add Section">
          <VCardText>
            <AppTextField
              v-model="newSectionTitle"
              label="Section Title"
              autofocus
              :rules="[(v) => !!v || 'Title is required']"
            />
          </VCardText>
          <VCardActions>
            <VSpacer />
            <VBtn
              variant="text"
              @click="isAddSectionDialogVisible = false"
            >
              Cancel
            </VBtn>
            <VBtn
              color="primary"
              type="submit"
            >
              Add
            </VBtn>
          </VCardActions>
        </VCard>
      </VForm>
    </VDialog>

    <!-- Dialogs -->
    <QuestionSearchDialog
      v-model:is-dialog-visible="isQuestionSearchVisible"
      :course-id="courseId"
      :exclude-ids="exam.sections.flatMap(s => s.questions.map(q => q.id))"
      no-context
      @select="handleQuestionsAdded"
    />

    <ContextQuestionDialog
      v-model:is-dialog-visible="isContextDialogVisible"
      :course-id="courseId"
      :exclude-ids="exam.sections.flatMap(s => s.questions.map(q => q.id))"
      @select="handleQuestionsAdded"
    />

    <AddEditQuestionDialog
      v-if="courseId"
      v-model:is-dialog-visible="isAddQuestionDialogVisible"
      :course-id="courseId"
      :dialog-mode="editingQuestionData ? 'edit' : 'add'"
      :data="editingQuestionData"
      is-exam-context
      @refresh="handleQuestionCreated"
    />
  </section>
</template>
