<script setup>
import AppServerSideAutocomplete from '@/@core/components/app-form-elements/AppServerSideAutocomplete.vue'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import api from '@/utils/api'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  slideData: {
    type: Object,
    default: () => ({
      id: null,
      lesson_id: null,
      type: 'explanation',
      title: '',
      content: '',
      sort_order: 0,
    }),
  },
  lessonId: { type: [Number, String], required: true },
  courseId: { type: [Number, String], required: true },
  levelId: { type: [Number, String], required: true },
  slideTypes: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:isDialogVisible', 'refresh'])

const { t, locale } = useI18n()
const toast = useToast()
const isSubmitting = ref(false)
const formErrors = ref({})
const selectedQuestion = ref(null)
const selectedTerm = ref(null)

const formData = ref({
  id: null,
  lesson_id: null,
  type: 'explanation',
  title: '',
  question_id: null,
  term_id: null,
  content: '',
  sort_order: 0,
})

watch(() => props.slideData, newSlideData => {
  if (newSlideData) {
    formErrors.value = {}
    selectedQuestion.value = null
    selectedTerm.value = null
    formData.value = JSON.parse(JSON.stringify(newSlideData))
    selectedQuestion.value = newSlideData.question_id ? newSlideData.question : null
    selectedTerm.value = newSlideData.term_id ? newSlideData.term : null
    if (!formData.value.content) formData.value.content = ''
    if (!formData.value.title) formData.value.title = ''
    if (!formData.value.lesson_id) formData.value.lesson_id = parseInt(props.lessonId)
  }
}, { immediate: true, deep: true })

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  formErrors.value = {}
  selectedQuestion.value = null
  selectedTerm.value = null
}

const submitForm = async () => {
  isSubmitting.value = true
  formErrors.value = {}

  try {
    const slideData = JSON.parse(JSON.stringify(formData.value))

    if (slideData.id) {
      // Update existing slide
      await api.put(`/admin/courses/${props.courseId}/levels/${props.levelId}/lessons/${props.lessonId}/slides/${slideData.id}`, slideData)
      toast.success('Slide updated successfully')
    } else {
      // Create new slide
      await api.post(`/admin/courses/${props.courseId}/levels/${props.levelId}/lessons/${props.lessonId}/slides`, slideData)
      toast.success('Slide created successfully')
    }
    
    emit('refresh')
    closeDialog()
  } catch (error) {
    console.error('Error saving slide:', error)
    if (error.response?.data?.errors) {
      formErrors.value = error.response.data.errors
    } else {
      toast.error(error.response?.data?.message || 'Failed to save slide')
    }
  } finally {
    isSubmitting.value = false
  }
}

const dialogTitle = computed(() => formData.value.id ? 'Edit Slide' : 'New Slide')
const getSlideTypeLabel = type => props.slideTypes.find(t => t.value === type)?.label || type

const isQuestionType = computed(() => {
  const typeMeta = props.slideTypes.find(type => type.value === formData.value.type)
  
  return !!typeMeta?.isQuestion
})

watch(selectedQuestion, newQuestion => {
  if (newQuestion) {
    formData.value.question_id = newQuestion.id
  }
})

const isTermType = computed(() => formData.value.type === 'term')
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="600px"
    persistent
    @update:model-value="val => emit('update:isDialogVisible', val)"
  >
    <DialogCloseBtn @click="closeDialog" />
    <VCard :title="dialogTitle">
      <VCardText>
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
            <VCol cols="12">
              <AppTextField
                v-model="formData.title"
                label="Title"
                :error-messages="formErrors.title"
                maxlength="255"
              />
            </VCol>
            <VCol cols="12">
              <VLabel
                class="mb-1 text-body-2 text-wrap"
                style="line-height: 15px;"
              >
                Content
              </VLabel>
              <TiptapEditor
                v-model="formData.content"
                :error-messages="formErrors.content"
                class="border rounded basic-editor"
              />
            </VCol>
            <VCol cols="12">
              <VSelect
                v-model="formData.type"
                :items="slideTypes"
                item-title="label"
                item-value="value"
                label="Slide Type"
                :error-messages="formErrors.type"
                required
              >
                <template #item="{ item, props }">
                  <VListItem v-bind="props">
                    <VListItemTitle>{{ item.label }}</VListItemTitle>
                    <VListItemSubtitle>{{ item.description }}</VListItemSubtitle>
                  </VListItem>
                </template>
              </VSelect>
            </VCol>

            <VCol
              v-if="isQuestionType"
              cols="12"
            >
              <AppServerSideAutocomplete
                v-model="selectedQuestion"
                :api-link="`/admin/courses/${courseId}/questions/select-fields`"
                api-method="get"
                :api-request-data="{ type: formData.type }"
                api-search-key="search"
                :minimum-search-chars="1"
                label="Select Question"
                item-title="question_text"
                item-value="id"
                return-object
                :error-messages="formErrors.question_id"
              >
                <template #item="{ props:itemProps, item }">
                  <VListItem
                    v-bind="itemProps"
                    :prepend-avatar="['image', 'image_with_audio'].includes(item.raw.media_type) && item.raw.media_url ? item.raw.media_url : null"
                    :subtitle="item.raw.question_text"
                    :title="item.raw.title"
                  >
                    <template v-if="item.raw.type == 'mcq'">
                      <span>Answers:</span>
                      <ul>
                        <li
                          v-for="(answer, index) in item.raw.options"
                          :key="index"
                        >
                          {{ index + 1 }}. {{ answer }} | correct: {{ item.raw.correct_answer[index] == 0 ? 'No' : 'Yes' }}
                        </li>
                      </ul>
                    </template>
                    <template v-if="item.raw.type == 'fill_blank_choices'">
                      <span>Answers:</span>
                      <ul>
                        <li
                          v-for="(answer, index) in item.raw.options"
                          :key="index"
                        >
                          {{ index + 1 }}. Placeholder: {{ answer.placeholder }} | Choices: {{ answer.options.join(', ') }} | Correct Choice: {{ answer.options[answer.correct_answer] }}
                        </li>
                      </ul>
                    </template>
                    <template v-if="item.raw.type == 'true_false'">
                      <span>Answers:</span>
                      <ul>
                        <li
                          v-for="(answer, index) in item.raw.answers"
                          :key="index"
                        >
                          {{ index + 1 }}. {{ answer.text }} | correct: {{ answer.correct }}
                        </li>
                      </ul>
                    </template>
                    <template v-if="item.raw.type == 'matching'">
                      <span>Answers:</span>
                      <ul>
                        <li
                          v-for="(answer, index) in item.raw.options"
                          :key="index"
                        >
                          {{ index + 1 }}. Left: {{ answer.left }} | Right: {{ answer.right }}
                        </li>
                      </ul>
                    </template>
                    <template v-if="item.raw.type == 'reordering'">
                      <ul>
                        <li
                          v-for="(answer, index) in item.raw.options"
                          :key="index"
                        >
                          {{ index + 1 }}. {{ answer }}
                        </li>
                      </ul>
                    </template>
                  </VListItem>
                </template>
              </AppServerSideAutocomplete>
              <div
                v-if="selectedQuestion"
                class="mt-2 pa-2 border rounded"
              >
                <div class="d-flex align-center justify-space-between">
                  <div>
                    <strong>Selected Question:</strong> {{ selectedQuestion.question_text }}
                  </div>
                  <VBtn
                    icon
                    variant="text"
                    size="small"
                    @click="selectedQuestion = undefined"
                  >
                    <VIcon icon="tabler-x" />
                  </VBtn>
                </div>
                <div class="mt-1">
                  <small>Type: {{ selectedQuestion.type }} | Difficulty: {{ selectedQuestion.difficulty }}</small>
                </div>
              </div>
            </VCol>

            <VCol
              v-if="isTermType"
              cols="12"
            >
              <AppServerSideAutocomplete
                v-model="selectedTerm"
                :api-link="`/admin/courses/${courseId}/terms/select-fields`"
                api-method="get"
                api-search-key="search"
                :minimum-search-chars="1"
                label="Select Term"
                item-title="term"
                item-value="id"
                return-object
                :error-messages="formErrors.term_id"
              >
                <template #item="{ item, props }">
                  <VListItem
                    v-bind="props"
                    :prepend-avatar="['image', 'image_with_audio'].includes(item.raw.media_type) && item.raw.media_url ? item.raw.media_url : null"
                  >
                    <VListItemTitle>{{ item.term }}</VListItemTitle>
                    <VListItemSubtitle>{{ item.definition }}</VListItemSubtitle>
                  </VListItem>
                </template>
              </AppServerSideAutocomplete>

              <div
                v-if="selectedTerm"
                class="mt-2 pa-2 border rounded"
              >
                <div class="d-flex align-center justify-space-between">
                  <div><strong>Selected Term:</strong> {{ selectedTerm.term }}</div>
                  <VBtn
                    icon
                    variant="text"
                    size="small"
                    @click="selectedTerm = null"
                  >
                    <VIcon icon="tabler-x" />
                  </VBtn>
                </div>
                <div class="mt-1">
                  <small>Definition: {{ selectedTerm.definition }}</small>
                </div>
              </div>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardText class="d-flex justify-end flex-wrap gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="submitForm"
        >
          {{ formData.id ? 'Update' : 'Create' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
