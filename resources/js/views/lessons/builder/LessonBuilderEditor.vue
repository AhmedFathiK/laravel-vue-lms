<script setup>
import { ref, watch, computed } from 'vue'
import AppServerSideAutocomplete from '@/@core/components/app-form-elements/AppServerSideAutocomplete.vue'
import AddEditQuestionDialog from '@/components/dialogs/AddEditQuestionDialog.vue'
import AddEditTermDialog from '@/components/dialogs/AddEditTermDialog.vue'
import api from '@/utils/api'
import { useToast } from 'vue-toastification'

import { parseApiError } from '@/utils/apiErrorHandler'

const props = defineProps({
  slide: { type: Object, required: true },
  courseId: { type: [Number, String], required: true },
  levelId: { type: [Number, String], required: true },
  lessonId: { type: [Number, String], required: true },
})

const emit = defineEmits(['update', 'refresh', 'delete'])

const toast = useToast()
const isSaving = ref(false)
const formData = ref({})
const validationErrors = ref({})
const selectedQuestion = ref(null)
const selectedTerm = ref(null)

const isQuestionDialogVisible = ref(false)
const isTermDialogVisible = ref(false)

const slideTypes = [
  { value: "mcq", label: "Multiple Choice", isQuestion: true },
  { value: "matching", label: "Matching Pairs", isQuestion: true },
  { value: "reordering", label: "Reordering", isQuestion: true },
  { value: "fill_blank", label: "Fill in the Blank", isQuestion: true },
  { value: "fill_blank_choices", label: "Fill in the Blank (with choices)", isQuestion: true },
  { value: "term", label: "Term", isQuestion: false },
  { value: "explanation", label: "Explanation", isQuestion: false },
]

watch(() => props.slide, (newSlide) => {
  if (newSlide) {
    formData.value = JSON.parse(JSON.stringify(newSlide))
    selectedQuestion.value = newSlide.question || null
    selectedTerm.value = newSlide.term || null
    validationErrors.value = {}
  }
}, { immediate: true })

const isQuestionType = computed(() => {
  const typeMeta = slideTypes.find(t => t.value === formData.value.type)
  return !!typeMeta?.isQuestion
})

const isTermType = computed(() => formData.value.type === 'term')

const saveSlide = async () => {
  isSaving.value = true
  validationErrors.value = {}
  try {
    const payload = {
      ...formData.value,
      question_id: selectedQuestion.value?.id || null,
      term_id: selectedTerm.value?.id || null
    }

    const response = await api.put(
      `/admin/courses/${props.courseId}/levels/${props.levelId}/lessons/${props.lessonId}/slides/${formData.value.id}`,
      payload
    )

    emit('update', response)
    toast.success('Slide saved')
  } catch (error) {
    const parsed = parseApiError(error)
    if (parsed.status === 422 && parsed.errors) {
      validationErrors.value = parsed.errors
      toast.error(parsed.message || 'Validation failed')
    } else {
      console.error('Error saving slide:', error)
      toast.error('Failed to save slide')
    }
  } finally {
    isSaving.value = false
  }
}

const deleteSlide = () => {
  emit('delete', formData.value.id)
}

const handleQuestionCreated = (response) => {
  const question = response.data?.data || response.data || response
  selectedQuestion.value = question
  isQuestionDialogVisible.value = false
  toast.success('Question created and linked')
}

const handleTermCreated = (response) => {
  const term = response.data?.data || response.data || response
  selectedTerm.value = term
  isTermDialogVisible.value = false
  toast.success('Term created and linked')
}
</script>

<template>
  <div class="lesson-builder-editor d-flex flex-column">
    <div class="d-flex justify-space-between align-center mb-6 flex-shrink-0 flex-wrap gap-4">
      <div class="text-h5 font-weight-bold">Slide Settings</div>
      <div class="d-flex gap-2">
        <VBtn
          color="error"
          variant="tonal"
          prepend-icon="tabler-trash"
          size="small"
          @click="deleteSlide"
        >
          Delete
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSaving"
          prepend-icon="tabler-device-floppy"
          size="small"
          @click="saveSlide"
        >
          Save Changes
        </VBtn>
      </div>
    </div>

    <div class="editor-content-area flex-grow-1 pa-1">
      <VRow>
        <VCol cols="12" lg="8">
          <VLabel class="mb-1">Slide Title</VLabel>
          <AppTextField
            v-model="formData.title"
            placeholder="Enter slide title"
            :error-messages="validationErrors.title"
            class="mb-4"
          />

          <VLabel class="mb-1">Content</VLabel>
          <TiptapEditor
            v-model="formData.content"
            :error-messages="validationErrors.content"
            class="border rounded basic-editor mb-4"
            style="min-height: 200px;"
          />
        </VCol>

        <VCol cols="12" lg="4">
          <VCard variant="outlined" class="mb-4">
            <VCardText>
              <VLabel class="mb-1">Slide Type</VLabel>
              <VSelect
                v-model="formData.type"
                :items="slideTypes"
                item-title="label"
                item-value="value"
                :error-messages="validationErrors.type"
                density="comfortable"
                class="mb-4"
              />

              <div v-if="isQuestionType">
                <VDivider class="mb-4" />
                <VLabel class="mb-1">Linked Question</VLabel>
                <AppServerSideAutocomplete
                  v-model="selectedQuestion"
                  :api-link="`/admin/courses/${courseId}/questions/select-fields`"
                  api-method="get"
                  :api-request-data="{ type: formData.type }"
                  api-search-key="search"
                  label="Search Question"
                  item-title="questionText"
                  item-value="id"
                  return-object
                  :error-messages="validationErrors.question_id"
                  class="mb-2"
                />
                <div v-if="selectedQuestion" class="text-caption pa-2 bg-light-secondary rounded">
                  <strong>ID:</strong> #{{ selectedQuestion.id }}<br>
                  <strong>Text:</strong> {{ selectedQuestion.questionText }}
                </div>
                <VBtn
                  block
                  variant="outlined"
                  color="primary"
                  size="small"
                  class="mt-2"
                  prepend-icon="tabler-plus"
                  @click="isQuestionDialogVisible = true"
                >
                  Create New Question
                </VBtn>
              </div>

              <div v-if="isTermType">
                <VDivider class="mb-4" />
                <VLabel class="mb-1">Linked Term</VLabel>
                <AppServerSideAutocomplete
                  v-model="selectedTerm"
                  :api-link="`/admin/courses/${courseId}/terms/select-fields`"
                  api-method="get"
                  api-search-key="search"
                  label="Search Term"
                  item-title="term"
                  item-value="id"
                  return-object
                  :error-messages="validationErrors.term_id"
                  class="mb-2"
                />
                <div v-if="selectedTerm" class="text-caption pa-2 bg-light-secondary rounded">
                  <strong>ID:</strong> #{{ selectedTerm.id }}<br>
                  <strong>Meaning:</strong> {{ selectedTerm.meaning }}
                </div>
                <VBtn
                  block
                  variant="outlined"
                  color="primary"
                  size="small"
                  class="mt-2"
                  prepend-icon="tabler-plus"
                  @click="isTermDialogVisible = true"
                >
                  Create New Term
                </VBtn>
              </div>
            </VCardText>
          </VCard>

          <VCard v-if="isQuestionType" variant="outlined">
            <VCardText>
              <div class="text-subtitle-2 mb-2">Feedback Content</div>
              <AppTextField
                v-model="formData.feedback_sentence"
                label="Target Language"
                :error-messages="validationErrors.feedback_sentence"
                class="mb-2"
              />
              <AppTextField
                v-model="formData.feedback_translation"
                label="Source Language"
                :error-messages="validationErrors.feedback_translation"
              />
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>


    <!-- Inline Dialogs -->
    <AddEditQuestionDialog
      v-model:is-dialog-visible="isQuestionDialogVisible"
      dialog-mode="add"
      :course-id="courseId"
      @refresh="handleQuestionCreated"
    />

    <AddEditTermDialog
      v-model:is-dialog-visible="isTermDialogVisible"
      dialog-mode="add"
      :course-id="courseId"
      @saved="handleTermCreated"
    />
  </div>
</template>

<style scoped>
.lesson-builder-editor {
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}

.editor-content-area {
  overflow-y: visible;
}
</style>
