<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { integerValidator, requiredValidator } from '@core/utils/validators'
import { computed, nextTick, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import VideoPlayer from '@/components/VideoPlayer.vue'

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

const videoTypeOptions = [
  { title: 'No Video', value: null },
  { title: 'YouTube', value: 'youtube' },
  { title: 'Vimeo', value: 'vimeo' },
  { title: 'Hosted (Direct URL)', value: 'hosted' },
]

const defaultForm = () => ({
  title: '',
  description: '',
  videoUrl: '',
  videoType: null,
  status: 'draft',
  reshowIncorrectSlides: false,
  reshowCount: 1,
  requireCorrectAnswers: false,
  levelId: props.levelId,
  courseId: props.courseId,
  isFree: false,
})

const form = ref(defaultForm())
const thumbnail = ref(null)
const thumbnailPreview = ref(null)
const deleteThumbnail = ref(false)

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.data) {
      // Exclude thumbnail and other non-form fields to prevent validation errors or large payloads
      const { thumbnail: _, slides: __, ...lessonData } = props.data

      form.value = {
        ...defaultForm(),
        ...lessonData,

        // Map snake_case to camelCase for form fields
        videoUrl: lessonData.video_url || lessonData.videoUrl || '',
        videoType: lessonData.video_type || lessonData.videoType || null,
        reshowIncorrectSlides: lessonData.reshow_incorrect_slides ?? lessonData.reshowIncorrectSlides ?? false,
        reshowCount: lessonData.reshow_count ?? lessonData.reshowCount ?? 1,
        requireCorrectAnswers: lessonData.require_correct_answers ?? lessonData.requireCorrectAnswers ?? false,
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
  const imageFile = Array.isArray(file) ? file[0] : file

  if (!imageFile) {
    thumbnail.value = null
    thumbnailPreview.value = null

    return
  }

  // Validate file type
  const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif']
  if (!validTypes.includes(imageFile.type)) {
    toast.error('Please upload a valid image file (JPEG, PNG, GIF)')
    thumbnail.value = null
    thumbnailPreview.value = null

    return
  }

  // Validate file size (max 2MB)
  if (imageFile.size > 2 * 1024 * 1024) {
    toast.error('Image size should be less than 2MB')
    thumbnail.value = null
    thumbnailPreview.value = null

    return
  }

  // Create a preview URL for the selected image
  thumbnailPreview.value = URL.createObjectURL(imageFile)

  // Reset delete flag if a new image is selected
  deleteThumbnail.value = false
}

// Video Validation and Preview
const videoValidation = computed(() => {
  if (!form.value.videoType) return true
  if (!form.value.videoUrl) return 'Video URL is required'

  const url = form.value.videoUrl
  
  if (form.value.videoType === 'youtube') {
    // Basic YouTube regex
    const youtubeRegex = /^(?:https?:\/\/)?(?:www\.)?(?:youtube\.com|youtu\.be)\/.+$/

    return youtubeRegex.test(url) || 'Invalid YouTube URL'
  }
  
  if (form.value.videoType === 'vimeo') {
    // Basic Vimeo regex
    const vimeoRegex = /^(?:https?:\/\/)?(?:www\.)?vimeo\.com\/.+$/
    
    return vimeoRegex.test(url) || 'Invalid Vimeo URL'
  }

  if (form.value.videoType === 'hosted') {
    // Basic URL regex and extension check (optional but good for UX)
    const urlRegex = /^(?:https?:\/\/)?[\da-z-]+(?:\.[\da-z-]+)*\.[a-z]{2,}(?:\/.*)?$/

    return urlRegex.test(url) || 'Invalid URL format'
  }

  return true
})

const videoEmbedUrl = computed(() => {
  if (!form.value.videoUrl || !form.value.videoType) return null
  if (videoValidation.value !== true) return null

  const url = form.value.videoUrl

  if (form.value.videoType === 'youtube') {
    let videoId = ''
    if (url.includes('youtu.be')) {
      videoId = url.split('/').pop()
    } else if (url.includes('v=')) {
      videoId = url.split('v=')[1].split('&')[0]
    } else if (url.includes('embed')) {
      videoId = url.split('/').pop()
    }
    if (videoId) return `https://www.youtube.com/embed/${videoId}`
  }

  if (form.value.videoType === 'vimeo') {
    let videoId = ''
    let hash = ''

    try {
      const urlObj = new URL(url.startsWith('http') ? url : `https://${url}`)

      if (urlObj.hostname.includes('player.vimeo.com')) {
        videoId = urlObj.pathname.split('/').filter(Boolean).pop()
        hash = urlObj.searchParams.get('h') || ''
      } else if (urlObj.hostname.includes('vimeo.com')) {
        const segments = urlObj.pathname.split('/').filter(Boolean)
        if (segments.length > 0) {
          videoId = segments[0]
          if (segments.length > 1) {
            hash = segments[1]
          }
        }
      }
    } catch (e) {
      const segments = url.split('/').filter(Boolean)

      videoId = segments[0] || ''
      hash = segments[1] || ''
    }

    if (videoId) {
      let embed = `https://player.vimeo.com/video/${videoId}`
      if (hash) {
        embed += `?h=${hash}`
      }
      
      return embed
    }
  }

  if (form.value.videoType === 'hosted') {
    return url
  }

  return null
})

// Compute extra data for useCrudSubmit
const extraData = computed(() => {
  const data = {}

  const file = Array.isArray(thumbnail.value) ? thumbnail.value[0] : thumbnail.value

  // Add thumbnail only if a new file is selected
  if (file instanceof File) {
    data.thumbnail = file
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
  apiEndpoint: computed(() => props.dialogMode === 'edit' 
    ? `/admin/courses/${props.courseId}/levels/${props.levelId}/lessons/${props.data.id}` 
    : `/admin/courses/${props.courseId}/levels/${props.levelId}/lessons`),
  isUpdate: computed(() => props.dialogMode === 'edit'),
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

    <VCard :title="props.dialogMode === 'edit' ? 'Edit Lesson' : 'Add New Lesson'">
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

            <!-- Video Type -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.videoType"
                :items="videoTypeOptions"
                label="Video Source"
                placeholder="Select Video Source"
                :error-messages="validationErrors.videoType"
                clearable
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

            <!-- Is Free -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="form.isFree"
                label="Is Free?"
                hint="Allow access without paid subscription"
                persistent-hint
              />
            </VCol>

            <!-- Video URL -->
            <VCol
              v-if="form.videoType"
              cols="12"
            >
              <AppTextField
                v-model="form.videoUrl"
                :label="form.videoType === 'hosted' ? 'Direct Video URL' : 'Video Link'"
                :placeholder="form.videoType === 'youtube' ? 'https://youtube.com/...' : 'Enter URL'"
                :rules="[videoValidation]"
                :error-messages="validationErrors.videoUrl"
                hint="Enter the full URL of the video"
                persistent-hint
              />
            </VCol>

            <!-- Video Preview -->
            <VCol
              v-if="form.videoUrl && form.videoType && videoValidation === true"
              cols="12"
            >
              <VLabel class="mb-1">
                Video Preview
              </VLabel>
              <div class="video-preview-container rounded border overflow-hidden">
                <VideoPlayer
                  :key="form.videoUrl"
                  :src="form.videoUrl"
                  :type="form.videoType"
                />
              </div>
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
                v-else-if="props.data?.thumbnail && !deleteThumbnail"
                class="mt-2"
              >
                <VImg
                  :src="props.data.thumbnail"
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
