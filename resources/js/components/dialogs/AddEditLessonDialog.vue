<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { integerValidator, requiredValidator } from '@core/utils/validators'
import { computed, nextTick, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

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

const toast = useToast()
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
const thumbnail = ref(null)
const thumbnailPreview = ref(null)
const deleteThumbnail = ref(false)

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

      // Reset thumbnail state
      thumbnail.value = null
      thumbnailPreview.value = null
      deleteThumbnail.value = false
    } else {
      form.value = defaultForm()
      thumbnail.value = null
      thumbnailPreview.value = null
      deleteThumbnail.value = false
    }

    nextTick(() => {
      refForm.value?.resetValidation()
    })
  }
})

// Handle image upload
const handleImageUpload = file => {
  if (!file) {
    thumbnail.value = null
    thumbnailPreview.value = null

    return
  }

  // Validate file type
  const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif']
  if (!validTypes.includes(file.type)) {
    toast.error('Please upload a valid image file (JPEG, PNG, GIF)')
    thumbnail.value = null
    thumbnailPreview.value = null

    return
  }

  // Validate file size (max 2MB)
  if (file.size > 2 * 1024 * 1024) {
    toast.error('Image size should be less than 2MB')
    thumbnail.value = null
    thumbnailPreview.value = null

    return
  }

  // Create a preview URL for the selected image
  thumbnailPreview.value = URL.createObjectURL(file)

  // Reset delete flag if a new image is selected
  deleteThumbnail.value = false
}

// Compute extra data for useCrudSubmit
const extraData = computed(() => {
  const data = {}

  // Add thumbnail only if a new file is selected
  if (thumbnail.value instanceof File) {
    data.thumbnail = thumbnail.value
  }

  // Handle thumbnail deletion
  if (deleteThumbnail.value) {
    data.deleteThumbnail = true
  }

  return data
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
  isFormData: true,
  extraData,
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

            <!-- Thumbnail -->
            <VCol cols="12">
              <VLabel class="mb-1">
                Lesson Image
              </VLabel>
              <VFileInput
                v-model="thumbnail"
                accept="image/*"
                label="Select Image"
                variant="outlined"
                density="compact"
                prepend-icon="tabler-upload"
                :error-messages="validationErrors.thumbnail"
                @update:model-value="handleImageUpload"
              />
              
              <!-- Preview of selected image -->
              <div
                v-if="thumbnailPreview"
                class="mt-2"
              >
                <VImg
                  :src="thumbnailPreview"
                  height="150"
                  cover
                  class="bg-grey-lighten-2 rounded mt-2"
                />
                <div class="mt-2">
                  <VChip
                    color="primary"
                    size="small"
                    class="me-2"
                  >
                    New image selected
                  </VChip>
                </div>
              </div>
              
              <!-- Current image from server -->
              <div
                v-else-if="props.lessonData?.thumbnail && !deleteThumbnail"
                class="mt-2"
              >
                <VImg
                  :src="props.lessonData.thumbnail"
                  height="150"
                  cover
                  class="bg-grey-lighten-2 rounded mt-2"
                />
                <div class="mt-2">
                  <VBtn
                    color="error"
                    size="small"
                    variant="outlined"
                    prepend-icon="tabler-trash"
                    @click="deleteThumbnail = true"
                  >
                    Remove Image
                  </VBtn>
                </div>
              </div>
              
              <!-- Message when thumbnail is marked for deletion -->
              <div
                v-else-if="deleteThumbnail"
                class="mt-2"
              >
                <VAlert
                  type="warning"
                  variant="tonal"
                  density="compact"
                  class="mb-2"
                >
                  Current image will be removed upon saving.
                </VAlert>
                <VBtn
                  color="secondary"
                  size="small"
                  variant="outlined"
                  prepend-icon="tabler-refresh"
                  @click="deleteThumbnail = false"
                >
                  Undo Remove
                </VBtn>
              </div>

              <!-- Placeholder when no image -->
              <div
                v-else
                class="mt-2"
              >
                <VAvatar
                  size="150"
                  color="primary"
                  variant="tonal"
                >
                  <VIcon
                    icon="tabler-camera-off"
                    size="48"
                  />
                </VAvatar>
              </div>
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
                {{ props.dialogMode === 'edit' ? 'Update' : 'Create' }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template> 
