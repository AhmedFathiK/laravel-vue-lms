<script setup>
import api from '@/utils/api'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { ref, watch } from 'vue'
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
const refVForm = ref(null)
const isSubmitting = ref(false)
const isFormValid = ref(true)

// Form data
const title = ref('')
const description = ref('')
const videoUrl = ref('')
const isFree = ref(false)
const status = ref('draft')
const reshowIncorrectSlides = ref(false)
const reshowCount = ref(1)
const requireCorrectAnswers = ref(false)

// Status options
const statusOptions = [
  { title: 'Draft', value: 'draft' },
  { title: 'Published', value: 'published' },
  { title: 'Archived', value: 'archived' },
]

// Reset form values
const resetFormValues = () => {
  title.value = ''
  description.value = ''
  videoUrl.value = ''
  isFree.value = false
  status.value = 'draft'
  reshowIncorrectSlides.value = false
  reshowCount.value = 1
  requireCorrectAnswers.value = false
  isFormValid.value = true
}

// Watch for changes in lessonData prop
watch(() => props.lessonData, () => {
  if (props.lessonData) {
    title.value = props.lessonData.title || ''
    description.value = props.lessonData.description || ''
    videoUrl.value = props.lessonData.video_url || ''
    isFree.value = props.lessonData.is_free || false
    status.value = props.lessonData.status || 'draft'
    reshowIncorrectSlides.value = props.lessonData.reshow_incorrect_slides || false
    reshowCount.value = props.lessonData.reshow_count || 1
    requireCorrectAnswers.value = props.lessonData.require_correct_answers || false
  } else {
    resetFormValues()
  }
}, { immediate: true })

// Handle dialog visibility
const onDialogVisibleUpdate = val => {
  emit('update:isDialogVisible', val)
  if (!val) {
    resetFormValues()
  }
}

// Submit form
const onSubmit = async () => {
  isFormValid.value = (await refVForm.value.validate()).valid
  
  if (!isFormValid.value) {
    return
  }

  // Prepare data for submission
  const formData = {
    title: {
      en: title.value,
    },
    description: {
      en: description.value,
    },
    "video_url": videoUrl.value,
    "is_free": isFree.value,
    status: status.value,
    "level_id": props.levelId,
    "reshow_incorrect_slides": reshowIncorrectSlides.value,
    "reshow_count": reshowCount.value,
    "require_correct_answers": requireCorrectAnswers.value,
  }

  try {
    isSubmitting.value = true

    // If editing, update existing lesson, otherwise create new lesson
    if (props.lessonData?.id) {
      await api.put(`/admin/courses/${props.courseId}/levels/${props.levelId}/lessons/${props.lessonData.id}`, formData)
      toast.success('Lesson updated successfully')
    } else {
      await api.post(`/admin/courses/${props.courseId}/levels/${props.levelId}/lessons`, formData)
      toast.success('Lesson created successfully')
    }
    
    // Close dialog and emit refresh event
    onDialogVisibleUpdate(false)
    emit('refresh')
  } catch (error) {
    console.error('Error saving lesson:', error)
    
    // Show all error messages if there are multiple
    if (error.response?.data?.errors) {
      // Get all error messages as an array of strings
      const errorMessages = Object.values(error.response.data.errors).flat()
      
      // Show each error as a separate toast
      errorMessages.forEach(message => {
        toast.error(message)
      })
    } else {
      toast.error(error.response?.data?.message || 'Failed to save lesson')
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="700px"
    persistent
    @update:model-value="onDialogVisibleUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="onDialogVisibleUpdate(false)" />

    <!-- Dialog Content -->
    <VCard :title="lessonData ? 'Edit Lesson' : 'Add New Lesson'">
      <VCardText>
        <VForm
          ref="refVForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Lesson Title -->
            <VCol cols="12">
              <AppTextField
                v-model="title"
                label="Title"
                placeholder="Enter lesson title"
                :rules="[v => !!v || 'Title is required']"
                required
              />
            </VCol>

            <!-- Lesson Description -->
            <VCol cols="12">
              <AppTextarea
                v-model="description"
                label="Description"
                placeholder="Enter lesson description"
                rows="4"
              />
            </VCol>

            <!-- Video URL -->
            <VCol cols="12">
              <AppTextField
                v-model="videoUrl"
                label="Video URL"
                placeholder="Enter video URL (YouTube, Vimeo, etc.)"
              />
            </VCol>

            <!-- Status and Free Access -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="status"
                :items="statusOptions"
                item-title="title"
                item-value="value"
                label="Status"
                :rules="[v => !!v || 'Status is required']"
                required
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="isFree"
                label="Free Access"
                color="success"
              />
            </VCol>

            <!-- Advanced Settings -->
            <VCol cols="12">
              <VDivider class="my-2" />
              <div class="text-h6 mb-3">
                Advanced Settings
              </div>
            </VCol>

            <!-- Reshow Incorrect Slides -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="reshowIncorrectSlides"
                label="Reshow Incorrect Slides"
                color="warning"
              />
            </VCol>

            <!-- Reshow Count -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model.number="reshowCount"
                type="number"
                label="Reshow Count"
                placeholder="Number of times to reshow"
                min="1"
                max="10"
                :disabled="!reshowIncorrectSlides"
              />
            </VCol>

            <!-- Require Correct Answers -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="requireCorrectAnswers"
                label="Require Correct Answers"
                color="error"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardText class="d-flex justify-end flex-wrap gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="onDialogVisibleUpdate(false)"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="onSubmit"
        >
          {{ lessonData ? 'Update' : 'Create' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template> 
