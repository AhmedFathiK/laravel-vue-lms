<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { integerValidator, requiredValidator } from '@core/utils/validators'
import { computed, nextTick, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  lessonData: {
    type: Object,
    default: () => null,
  },
  levelId: {
    type: [Number, String],
    required: true,
  },
  courseId: {
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'refresh'])

const refForm = ref(null)

const statusOptions = [
  { title: 'Draft', value: 'draft' },
  { title: 'Published', value: 'published' },
  { title: 'Archived', value: 'archived' },
]

const defaultForm = () => ({
  title: '',
  description: '',
  videoUrl: '',
  isFree: false,
  status: 'draft',
  reshowIncorrectSlides: false,
  reshowCount: 1,
  requireCorrectAnswers: false,
  levelId: props.levelId,
  courseId: props.courseId,
})

const form = ref(defaultForm())

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.lessonData) {
      form.value = {
        title: props.lessonData.title || '',
        description: props.lessonData.description || '',
        videoUrl: props.lessonData.videoUrl || '',
        isFree: !!props.lessonData.isFree,
        status: props.lessonData.status || 'draft',
        reshowIncorrectSlides: !!props.lessonData.reshowIncorrectSlides,
        reshowCount: props.lessonData.reshowCount || 1,
        requireCorrectAnswers: !!props.lessonData.requireCorrectAnswers,
        levelId: props.levelId,
        courseId: props.courseId,
      }
    } else {
      form.value = defaultForm()
    }

    nextTick(() => {
      refForm.value?.resetValidation()
    })
  }
})

const customEmit = (event, ...args) => {
  if (event === 'saved') {
    emit('refresh', ...args)
  } else {
    emit(event, ...args)
  }
}

const { isLoading: submitting, validationErrors, onSubmit: submit } = useCrudSubmit({
  formRef: refForm,
  form: form,
  apiEndpoint: computed(() => props.lessonData?.id 
    ? `/admin/courses/${props.courseId}/levels/${props.levelId}/lessons/${props.lessonData.id}` 
    : `/admin/courses/${props.courseId}/levels/${props.levelId}/lessons`),
  isUpdate: computed(() => !!props.lessonData?.id),
  isFormData: false,
  emit: customEmit,
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="800"
    @update:model-value="val => $emit('update:isDialogVisible', val)"
  >
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard :title="props.lessonData ? 'Edit Lesson' : 'Add New Lesson'">
      <VCardText>
        <VForm
          ref="refForm"
          @submit.prevent="submit"
        >
          <VRow>
            <!-- Title -->
            <VCol cols="12">
              <AppTextField
                v-model="form.title"
                label="Title"
                :rules="[requiredValidator]"
                placeholder="Enter lesson title"
                :error-messages="validationErrors.title"
              />
            </VCol>

            <!-- Description -->
            <VCol cols="12">
              <AppTextarea
                v-model="form.description"
                label="Description"
                rows="3"
                placeholder="Enter lesson description"
                :error-messages="validationErrors.description"
              />
            </VCol>

            <!-- Video URL -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.videoUrl"
                label="Video URL"
                placeholder="Enter video URL"
                :error-messages="validationErrors.videoUrl"
              />
            </VCol>

            <!-- Status -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.status"
                :items="statusOptions"
                label="Status"
                placeholder="Select Status"
                :error-messages="validationErrors.status"
              />
            </VCol>

            <!-- Settings -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.reshowCount"
                label="Reshow Count"
                type="number"
                :rules="[integerValidator]"
                :error-messages="validationErrors.reshowCount"
              />
            </VCol>

            <!-- Switches -->
            <VCol
              cols="12"
              md="6"
              class="d-flex flex-column gap-2"
            >
              <VSwitch
                v-model="form.isFree"
                label="Free Lesson"
              />
              <VSwitch
                v-model="form.reshowIncorrectSlides"
                label="Reshow Incorrect Slides"
              />
              <VSwitch
                v-model="form.requireCorrectAnswers"
                label="Require Correct Answers"
              />
            </VCol>

            <!-- Actions -->
            <VCol
              cols="12"
              class="d-flex justify-end gap-2"
            >
              <VBtn
                color="secondary"
                variant="tonal"
                :disabled="submitting"
                @click="$emit('update:isDialogVisible', false)"
              >
                Cancel
              </VBtn>
              
              <VBtn
                type="submit"
                :loading="submitting"
              >
                {{ props.lessonData ? 'Update' : 'Create' }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template> 
