<script setup>
import api from '@/utils/api'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  term: {
    type: Object,
    default: () => null,
  },
  courseId: {
    type: [String, Number],
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'term-saved'])

const toast = useToast()
const isSubmitting = ref(false)
const formRef = ref(null)
const isFormValid = ref(true)

// Media preview
const mediaPreview = ref(null)
const audioPreview = ref(null)
const exampleAudioPreview = ref(null)

// Form data
const formData = ref({
  course_id: props.courseId,
  term: '',
  definition: '',
  media_url: '',
  media_type: '',
  audio_url: '',
  example: '',
  example_audio_url: '',
})

// Media type options
const mediaTypeOptions = [
  { title: 'None', value: '' },
  { title: 'Image', value: 'image' },
  { title: 'Image with Audio', value: 'image_audio' },
  { title: 'Video', value: 'video' },
]

// Check if we're in edit mode
const isEditMode = computed(() => !!props.term)

// Reset form when dialog visibility changes
watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    resetForm()
    if (props.term) {
      formData.value = {
        course_id: props.courseId,
        term: props.term.term || '',
        definition: props.term.definition || '',
        media_url: props.term.media_url || '',
        media_type: props.term.media_type || '',
        audio_url: props.term.audio_url || '',
        example: props.term.example || '',
        example_audio_url: props.term.example_audio_url || '',
      }
      if (props.term.media_url) mediaPreview.value = props.term.media_url
      if (props.term.audio_url) audioPreview.value = props.term.audio_url
      if (props.term.example_audio_url) exampleAudioPreview.value = props.term.example_audio_url
    }
  }
}, { immediate: true })

// Reset form fields to default
const resetForm = () => {
  formData.value = {
    course_id: props.courseId,
    term: '',
    definition: '',
    media_url: '',
    media_type: '',
    audio_url: '',
    example: '',
    example_audio_url: '',
  }
  
  mediaPreview.value = null
  audioPreview.value = null
  exampleAudioPreview.value = null
}

// Submit the form
const submitForm = async () => {
  const { valid } = await formRef.value.validate()
  
  if (!valid) {
    toast.error('Please fix form errors before submitting')
    
    return
  }
  
  isSubmitting.value = true
  
  try {
    if (isEditMode.value) {
      // Update existing term
      await api.put(`/admin/courses/${props.courseId}/terms/${props.term.id}`, formData.value)
      toast.success('Term updated successfully')
    } else {
      // Create new term
      await api.post(`/admin/courses/${props.courseId}/terms`, formData.value)
      toast.success('Term created successfully')
    }
    
    emit('term-saved')
    dialogModelValueUpdate(false)
  } catch (error) {
    console.error('Error submitting term:', error)
    toast.error('Failed to save term')
  } finally {
    isSubmitting.value = false
  }
}

// Handle media upload
const onMediaUrlChange = () => {
  if (formData.value.media_url) {
    mediaPreview.value = formData.value.media_url
  } else {
    mediaPreview.value = null
  }
}

// Handle audio upload
const onAudioUrlChange = () => {
  if (formData.value.audio_url) {
    audioPreview.value = formData.value.audio_url
  } else {
    audioPreview.value = null
  }
}

// Handle example audio upload
const onExampleAudioUrlChange = () => {
  if (formData.value.example_audio_url) {
    exampleAudioPreview.value = formData.value.example_audio_url
  } else {
    exampleAudioPreview.value = null
  }
}

// Close dialog
const dialogModelValueUpdate = val => {
  emit('update:isDialogVisible', val)
}

// Form validation rules
const requiredValidator = v => !!v || 'This field is required'

// Determine if audio field should be shown
const showAudioField = computed(() => {
  return formData.value.media_type === 'image_audio'
})
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="800px"
    @update:model-value="dialogModelValueUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-sm-6 pa-4">
      <VCardItem>
        <VCardTitle>
          {{ isEditMode ? 'Edit Term' : 'Add New Term' }}
        </VCardTitle>
      </VCardItem>

      <VCardText>
        <VForm
          ref="formRef"
          v-model="isFormValid"
          @submit.prevent="submitForm"
        >
          <VRow>
            <!-- Term -->
            <VCol cols="12">
              <VTextField
                v-model="formData.term"
                label="Term"
                :rules="[requiredValidator]"
                required
              />
            </VCol>
            
            <!-- Definition -->
            <VCol cols="12">
              <VTextarea
                v-model="formData.definition"
                label="Definition"
                :rules="[requiredValidator]"
                required
                auto-grow
                rows="3"
              />
            </VCol>
            
            <!-- Media Type -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.media_type"
                label="Media Type"
                :items="mediaTypeOptions"
                item-title="title"
                item-value="value"
              />
            </VCol>
            
            <!-- Media URL -->
            <VCol
              v-if="formData.media_type"
              cols="12"
              md="6"
            >
              <VTextField
                v-model="formData.media_url"
                label="Media URL"
                @update:model-value="onMediaUrlChange"
              />
            </VCol>
            
            <!-- Audio URL (for image_audio type) -->
            <VCol
              v-if="showAudioField"
              cols="12"
              md="6"
            >
              <VTextField
                v-model="formData.audio_url"
                label="Audio URL"
                @update:model-value="onAudioUrlChange"
              />
            </VCol>
            
            <!-- Media Preview -->
            <VCol
              v-if="mediaPreview"
              cols="12"
              class="d-flex justify-center"
            >
              <div class="media-preview">
                <div v-if="formData.media_type === 'image' || formData.media_type === 'image_audio'">
                  <img 
                    :src="mediaPreview" 
                    alt="Media Preview" 
                    style="max-width: 100%; max-height: 200px;"
                  >
                </div>
                <div v-else-if="formData.media_type === 'video'">
                  <video 
                    :src="mediaPreview" 
                    controls 
                    style="max-width: 100%; max-height: 200px;"
                  />
                </div>
              </div>
            </VCol>
            
            <!-- Audio Preview -->
            <VCol
              v-if="showAudioField && audioPreview"
              cols="12"
            >
              <audio
                :src="audioPreview"
                controls
              />
            </VCol>
          </VRow>
          
          <!-- Example Section -->
          <VDivider class="my-5" />
          
          <h4 class="text-h6 mb-3">
            Example
          </h4>
          
          <VRow>
            <!-- Example Text -->
            <VCol cols="12">
              <VTextarea
                v-model="formData.example"
                label="Example"
                auto-grow
                rows="2"
              />
            </VCol>
            
            <!-- Example Audio URL -->
            <VCol cols="12">
              <VTextField
                v-model="formData.example_audio_url"
                label="Example Audio URL"
                @update:model-value="onExampleAudioUrlChange"
              />
            </VCol>
            
            <!-- Example Audio Preview -->
            <VCol
              v-if="exampleAudioPreview"
              cols="12"
            >
              <audio
                :src="exampleAudioPreview"
                controls
              />
            </VCol>
          </VRow>
          
          <VDivider class="my-5" />
          
          <!-- Form Buttons -->
          <div class="d-flex justify-end mt-3">
            <VBtn
              color="secondary"
              variant="outlined"
              class="me-3"
              @click="dialogModelValueUpdate(false)"
            >
              Cancel
            </VBtn>
            <VBtn
              color="primary"
              :loading="isSubmitting"
              type="submit"
              :disabled="!isFormValid"
            >
              {{ isEditMode ? 'Update' : 'Create' }}
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.media-preview {
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  padding: 8px;
  display: flex;
  justify-content: center;
  align-items: center;
}
</style>
