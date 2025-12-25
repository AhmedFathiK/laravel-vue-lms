<script setup>
import AppServerSideAutocomplete from '@/@core/components/app-form-elements/AppServerSideAutocomplete.vue'
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  dialogMode: {
    type: String,
    required: true,
    validator: value => ['add', 'edit'].includes(value),
  },
  data: {
    type: Object,
    default: () => ({
      id: null,
      lessonId: null,
      type: 'explanation',
      title: '',
      content: '',
      sortOrder: 0,
    }),
  },
  lessonId: { type: [Number, String], required: true },
  courseId: { type: [Number, String], required: true },
  levelId: { type: [Number, String], required: true },
  slideTypes: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:isDialogVisible', 'refresh'])

const { t } = useI18n()
const toast = useToast()
const selectedQuestion = ref(null)
const selectedTerm = ref(null)
const refForm = ref(null)

const createDefaultForm = () => ({
  id: null,
  lessonId: parseInt(props.lessonId),
  type: 'explanation',
  title: '',
  questionId: null,
  termId: null,
  content: '',
  sortOrder: 0,
})

const formData = ref(createDefaultForm())

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.data && props.data.id) {
      formData.value = JSON.parse(JSON.stringify(props.data))
      selectedQuestion.value = props.data.questionId ? props.data.question : null
      selectedTerm.value = props.data.termId ? props.data.term : null
      
      if (!formData.value.content) formData.value.content = ''
      if (!formData.value.title) formData.value.title = ''
      if (!formData.value.lessonId) formData.value.lessonId = parseInt(props.lessonId)
    } else {
      formData.value = createDefaultForm()
      selectedQuestion.value = null
      selectedTerm.value = null
    }
  }
})

const closeDialog = () => {
  emit('update:isDialogVisible', false)
  selectedQuestion.value = null
  selectedTerm.value = null
}

const customEmit = (event, ...args) => {
  if (event === 'saved') {
    emit('refresh', ...args)
  } else {
    emit(event, ...args)
  }
}

const { isLoading: isSubmitting, validationErrors: formErrors, onSubmit: submitForm } = useCrudSubmit({
  formRef: refForm,
  form: formData,
  apiEndpoint: computed(() => props.dialogMode === 'edit'
    ? `/admin/courses/${props.courseId}/levels/${props.levelId}/lessons/${props.lessonId}/slides/${formData.value.id}`
    : `/admin/courses/${props.courseId}/levels/${props.levelId}/lessons/${props.lessonId}/slides`),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  emit: customEmit,
  isFormData: false,
  successMessage: computed(() => props.dialogMode === 'edit' ? 'Slide updated successfully' : 'Slide created successfully'),
})

const dialogTitle = computed(() => props.dialogMode === 'edit' ? 'Edit Slide' : 'New Slide')
const getSlideTypeLabel = type => props.slideTypes.find(t => t.value === type)?.label || type

const isQuestionType = computed(() => {
  const typeMeta = props.slideTypes.find(type => type.value === formData.value.type)
  
  return !!typeMeta?.isQuestion
})

watch(selectedQuestion, newQuestion => {
  if (newQuestion) {
    formData.value.questionId = newQuestion.id
  } else {
    formData.value.questionId = null
  }
})

watch(selectedTerm, newTerm => {
  if (newTerm) {
    formData.value.termId = newTerm.id
  } else {
    formData.value.termId = null
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
        <VForm 
          ref="refForm"
          @submit.prevent="submitForm"
        >
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
                <template #item="{ item, itemProps }">
                  <VListItem v-bind="itemProps">
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
                item-title="questionText"
                item-value="id"
                return-object
                :error-messages="formErrors.questionId"
              >
                <template #item="{ props:itemProps, item }">
                  <VListItem
                    v-bind="itemProps"
                    :prepend-avatar="['image', 'image_with_audio'].includes(item.raw.mediaType) && item.raw.mediaUrl ? item.raw.mediaUrl : null"
                    :subtitle="item.raw.questionText"
                    :title="item.raw.title"
                  >
                    <template v-if="item.raw.type == 'mcq'">
                      <span>Answers:</span>
                      <ul>
                        <li
                          v-for="(answer, index) in item.raw.options"
                          :key="index"
                        >
                          {{ index + 1 }}. {{ answer }} | correct: {{ item.raw.correctAnswer[index] == 0 ? 'No' : 'Yes' }}
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
                          {{ index + 1 }}. Placeholder: {{ answer.placeholder }} | Choices: {{ answer.options.join(', ') }} | Correct Choice: {{ answer.options[answer.correctAnswer] }}
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
                    <strong>Selected Question:</strong> {{ selectedQuestion.questionText }}
                  </div>
                  <VBtn
                    icon
                    variant="text"
                    size="small"
                    @click="selectedQuestion = null"
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
                :error-messages="formErrors.termId"
              >
                <template #item="{ item, props: itemProps }">
                  <VListItem
                    v-bind="itemProps"
                    :prepend-avatar="['image', 'image_with_audio'].includes(item.raw.mediaType) && item.raw.mediaUrl ? item.raw.mediaUrl : null"
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
          {{ props.dialogMode === 'edit' ? 'Update' : 'Create' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
