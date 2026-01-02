<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { requiredValidator } from '@core/utils/validators'
import { computed, nextTick, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  dialogMode: {
    type: String,
    required: true,
    validator: value => ['add', 'edit'].includes(value),
  },
  data: {
    type: Object,
    default: () => null,
  },
  courseId: {
    type: [String, Number],
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'saved'])

const refForm = ref(null)

// Media type options
const mediaTypeOptions = [
  { title: 'None', value: '' },
  { title: 'Image', value: 'image' },
  { title: 'Image with Audio', value: 'image_with_audio' },
  { title: 'Video', value: 'video' },
]

const defaultForm = () => ({
  courseId: props.courseId,
  term: '',
  definition: '',
  mediaUrl: null, // File or string
  mediaType: '',
  audioUrl: null, // File or string
  example: '',
  exampleTranslation: '',
  exampleAudioUrl: null, // File or string
})

const form = ref(defaultForm())

// Helper for file inputs (not fully implemented in UI but prepared in logic)
const mediaFile = ref(null)
const audioFile = ref(null)
const exampleAudioFile = ref(null)

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.data) {
      form.value = {
        ...defaultForm(),
        ...props.data,
        courseId: props.courseId,
      }
    } else {
      form.value = defaultForm()
    }
    
    // Reset file refs
    mediaFile.value = null
    audioFile.value = null
    exampleAudioFile.value = null

    nextTick(() => {
      refForm.value?.resetValidation()
    })
  }
})

// Extra data mapping for files
const extraData = computed(() => {
  const data = {}
  
  const getFile = val => Array.isArray(val) ? val[0] : val

  if (mediaFile.value) data.mediaFile = getFile(mediaFile.value)
  if (audioFile.value) data.audioFile = getFile(audioFile.value)
  if (exampleAudioFile.value) data.exampleAudioFile = getFile(exampleAudioFile.value)
  
  return data
})

const { isLoading, validationErrors, onSubmit } = useCrudSubmit({
  formRef: refForm,
  form: form,
  apiEndpoint: computed(() => props.termData?.id 
    ? `/admin/courses/${props.courseId}/terms/${props.termData.id}` 
    : `/admin/courses/${props.courseId}/terms`),
  isUpdate: computed(() => !!props.termData?.id),
  extraData: extraData, // Pass computed files
  isFormData: true, // Always use FormData for file uploads
  emit,
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="800"
    @update:model-value="val => $emit('update:isDialogVisible', val)"
  >
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard :title="props.termData ? 'Edit Term' : 'Add New Term'">
      <VCardText>
        <VForm
          ref="refForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Term -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.term"
                label="Term"
                :rules="[requiredValidator]"
                placeholder="Enter term"
                :error-messages="validationErrors.term"
              />
            </VCol>

            <!-- Definition -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.definition"
                label="Definition"
                :rules="[requiredValidator]"
                placeholder="Enter definition"
                :error-messages="validationErrors.definition"
              />
            </VCol>

            <!-- Media Type -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.mediaType"
                label="Media Type"
                :items="mediaTypeOptions"
                placeholder="Select media type"
                :error-messages="validationErrors.mediaType"
              />
            </VCol>

            <!-- Example -->
            <VCol cols="12">
              <AppTextarea
                v-model="form.example"
                label="Example Sentence"
                rows="2"
                placeholder="Enter example sentence"
                :error-messages="validationErrors.example"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="form.exampleTranslation"
                label="Example Translation"
                placeholder="Enter translation"
                :error-messages="validationErrors.exampleTranslation"
              />
            </VCol>

            <!-- Media File Upload -->
            <VCol
              v-if="form.mediaType && form.mediaType !== ''"
              cols="12"
            >
              <VFileInput
                v-model="mediaFile"
                :label="form.mediaType === 'video' ? 'Video File' : 'Image File'"
                :accept="form.mediaType === 'video' ? 'video/*' : 'image/*'"
                prepend-icon="tabler-file"
                clearable
              />
              <div
                v-if="form.mediaUrl && !mediaFile"
                class="mt-1 text-caption"
              >
                Current: <a
                  :href="form.mediaUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                >View Media</a>
              </div>
            </VCol>

            <!-- Audio File Upload (for Image with Audio) -->
            <VCol
              v-if="form.mediaType === 'image_with_audio'"
              cols="12"
            >
              <VFileInput
                v-model="audioFile"
                label="Audio File"
                accept="audio/*"
                prepend-icon="tabler-microphone"
                clearable
              />
              <div
                v-if="form.audioUrl && !audioFile"
                class="mt-1 text-caption"
              >
                Current: <a
                  :href="form.audioUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                >View Audio</a>
              </div>
            </VCol>

            <!-- Example Audio Upload -->
            <VCol cols="12">
              <VFileInput
                v-model="exampleAudioFile"
                label="Example Audio"
                accept="audio/*"
                prepend-icon="tabler-volume"
                clearable
              />
              <div
                v-if="form.exampleAudioUrl && !exampleAudioFile"
                class="mt-1 text-caption"
              >
                Current: <a
                  :href="form.exampleAudioUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                >View Example Audio</a>
              </div>
            </VCol>
            <VCol
              cols="12"
              class="d-flex justify-end gap-2"
            >
              <VBtn
                color="secondary"
                variant="tonal"
                :disabled="isLoading"
                @click="$emit('update:isDialogVisible', false)"
              >
                Cancel
              </VBtn>
              
              <VBtn
                type="submit"
                :loading="isLoading"
              >
                {{ props.dialogMode === 'edit' ? 'Update' : 'Create' }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
