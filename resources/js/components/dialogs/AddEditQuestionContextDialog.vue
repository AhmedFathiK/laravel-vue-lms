<script setup>
import api from '@/utils/api'
import TiptapEditor from '@core/components/TiptapEditor.vue'
import { requiredValidator } from '@core/utils/validators'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  mode: {
    type: String,
    default: 'add', // 'add' or 'edit'
  },
  context: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:isDialogOpen', 'save'])

const { t } = useI18n()

const isLoading = ref(false)
const refVForm = ref()

const form = ref({
  title: '',
  content: '',
  contextType: 'text_passage', // Default to text passage
  mediaUrl: '',
  audioUrl: '',
  videoSource: 'direct', // 'direct', 'youtube', 'vimeo'
  mediaInputType: 'file', // 'file' or 'url'
  audioInputType: 'file', // 'file' or 'url'
})

const contextTypes = [
  { title: 'Text Passage', value: 'text_passage' },
  { title: 'Image', value: 'image' },
  { title: 'Image with Audio', value: 'image_with_audio' },
  { title: 'Audio', value: 'audio' },
  { title: 'Video', value: 'video' },
]

const videoSources = [
  { title: 'Direct Link / Upload', value: 'direct' },
  { title: 'YouTube', value: 'youtube' },
  { title: 'Vimeo', value: 'vimeo' },
]

const mediaFile = ref(null)
const audioFile = ref(null)

watch(
  () => props.isDialogOpen,
  isOpen => {
    if (isOpen) {
      mediaFile.value = null
      audioFile.value = null
      
      if (props.mode === 'edit' && props.context) {
        form.value = {
          title: props.context.title || '',
          content: props.context.content || '',
          contextType: props.context.contextType || 'text_passage',
          mediaUrl: props.context.mediaUrl || '',
          audioUrl: props.context.audioUrl || '',
          videoSource: props.context.videoSource || 'direct',
          mediaInputType: props.context.mediaUrl && !props.context.mediaUrl.startsWith('/storage/') ? 'url' : 'file',
          audioInputType: props.context.audioUrl && !props.context.audioUrl.startsWith('/storage/') ? 'url' : 'file',
        }
      } else {
        form.value = {
          title: '',
          content: '',
          contextType: 'text_passage',
          mediaUrl: '',
          audioUrl: '',
          videoSource: 'direct',
          mediaInputType: 'file',
          audioInputType: 'file',
        }
      }
    }
  },
)

const closeDialog = () => {
  emit('update:isDialogOpen', false)
}

const onSave = async () => {
  const { valid } = await refVForm.value?.validate()
  if (!valid) return
  
  if (form.value.contextType === 'text_passage' && !form.value.content) {
    return
  }

  const formData = new FormData()

  formData.append('title', form.value.title)
  formData.append('context_type', form.value.contextType)

  if (form.value.contextType === 'text_passage') {
    formData.append('content', form.value.content)
  } else if (['image', 'image_with_audio'].includes(form.value.contextType)) {
    if (form.value.mediaInputType === 'file' && mediaFile.value) {
      formData.append('media_file', mediaFile.value)
    } else if (form.value.mediaInputType === 'url') {
      formData.append('media_url', form.value.mediaUrl)
    }
      
    if (form.value.contextType === 'image_with_audio') {
      if (form.value.audioInputType === 'file' && audioFile.value) {
        formData.append('audio_file', audioFile.value)
      } else if (form.value.audioInputType === 'url') {
        formData.append('audio_url', form.value.audioUrl)
      }
    }
  } else if (form.value.contextType === 'audio') {
    if (form.value.audioInputType === 'file' && audioFile.value) {
      formData.append('media_file', audioFile.value)
    } else if (form.value.audioInputType === 'url') {
      formData.append('media_url', form.value.audioUrl)
    }
  } else if (form.value.contextType === 'video') {
    formData.append('video_source', form.value.videoSource)
    if (form.value.videoSource === 'direct') {
      if (form.value.mediaInputType === 'file' && mediaFile.value) {
        formData.append('media_file', mediaFile.value)
      } else if (form.value.mediaInputType === 'url') {
        formData.append('media_url', form.value.mediaUrl)
      }
    } else {
      formData.append('media_url', form.value.mediaUrl)
    }
  }

  // Handle updates vs creates (PUT vs POST with FormData is tricky in Laravel, usually use POST with _method=PUT)
  if (props.mode === 'edit') {
    formData.append('_method', 'PUT')
  }

  emit('save', formData)
}

const handleMediaFileSelect = event => {
  const file = event.target.files[0]
  if (file) mediaFile.value = file
}

const handleAudioFileSelect = event => {
  const file = event.target.files[0]
  if (file) audioFile.value = file
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogOpen"
    max-width="800"
    @update:model-value="closeDialog"
  >
    <VCard :title="mode === 'add' ? t('questions.context.addTitle', 'Add Question Context') : t('questions.context.editTitle', 'Edit Question Context')">
      <VCardText>
        <VForm
          ref="refVForm"
          @submit.prevent="onSave"
        >
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="form.title"
                :label="t('questions.context.title', 'Title')"
                placeholder="Enter context title"
                :rules="[requiredValidator]"
                required
              />
            </VCol>

            <VCol cols="12">
              <AppSelect
                v-model="form.contextType"
                :label="t('questions.context.type', 'Context Type')"
                :items="contextTypes"
                :rules="[requiredValidator]"
                required
              />
            </VCol>

            <!-- Text Passage Editor -->
            <VCol
              v-if="form.contextType === 'text_passage'"
              cols="12"
            >
              <VLabel class="mb-1 text-body-2 text-high-emphasis">
                {{ t('questions.context.content', 'Content') }}*
              </VLabel>
              <TiptapEditor
                v-model="form.content"
                placeholder="Enter context content/text"
                class="border rounded"
              />
            </VCol>

            <!-- Image/Video Upload/URL Selection -->
            <template v-if="['image', 'image_with_audio', 'video'].includes(form.contextType)">
              <VCol
                v-if="form.contextType === 'video'"
                cols="12"
              >
                <AppSelect
                  v-model="form.videoSource"
                  :label="t('questions.context.videoSource', 'Video Source')"
                  :items="videoSources"
                  :rules="[requiredValidator]"
                />
              </VCol>

              <VCol
                v-if="form.contextType !== 'video' || form.videoSource === 'direct'"
                cols="12"
              >
                <VLabel class="mb-1">
                  {{ t('questions.context.mediaInputType', 'Media Input Method') }}
                </VLabel>
                <VRadioGroup
                  v-model="form.mediaInputType"
                  inline
                >
                  <VRadio
                    label="Upload File"
                    value="file"
                  />
                  <VRadio
                    label="Direct URL"
                    value="url"
                  />
                </VRadioGroup>
              </VCol>

              <VCol
                v-if="(form.contextType !== 'video' || form.videoSource === 'direct') && form.mediaInputType === 'file'"
                cols="12"
              >
                <VLabel class="mb-1">
                  {{ form.contextType === 'video' ? 'Video File' : 'Image File' }}
                </VLabel>
                <VFileInput
                  :label="form.contextType === 'video' ? 'Video File' : 'Image File'"
                  :prepend-icon="form.contextType === 'video' ? 'tabler-video' : 'tabler-camera'"
                  :accept="form.contextType === 'video' ? 'video/*' : 'image/*'"
                  @change="handleMediaFileSelect"
                />
              </VCol>

              <VCol
                v-if="form.mediaInputType === 'url' || (form.contextType === 'video' && form.videoSource !== 'direct')"
                cols="12"
              >
                <AppTextField
                  v-model="form.mediaUrl"
                  :label="form.contextType === 'video' ? 'Video URL' : 'Image URL'"
                  :placeholder="form.contextType === 'video' 
                    ? (form.videoSource === 'youtube' ? 'https://youtube.com/watch?v=...' : form.videoSource === 'vimeo' ? 'https://vimeo.com/...' : 'https://example.com/video.mp4') 
                    : 'https://example.com/image.jpg'"
                  :rules="[requiredValidator]"
                />
              </VCol>
            </template>

            <!-- Audio Upload/URL -->
            <template v-if="['audio', 'image_with_audio'].includes(form.contextType)">
              <VCol cols="12">
                <VLabel class="mb-1">
                  {{ t('questions.context.audioInputType', 'Audio Input Method') }}
                </VLabel>
                <VRadioGroup
                  v-model="form.audioInputType"
                  inline
                >
                  <VRadio
                    label="Upload File"
                    value="file"
                  />
                  <VRadio
                    label="Direct URL"
                    value="url"
                  />
                </VRadioGroup>
              </VCol>

              <VCol
                v-if="form.audioInputType === 'file'"
                cols="12"
              >
                <VLabel class="mb-1">
                  Audio File
                </VLabel>
                <VFileInput
                  label="Audio File"
                  prepend-icon="tabler-volume"
                  accept="audio/*"
                  @change="handleAudioFileSelect"
                />
              </VCol>

              <VCol
                v-if="form.audioInputType === 'url'"
                cols="12"
              >
                <AppTextField
                  v-model="form.audioUrl"
                  label="Audio URL"
                  placeholder="https://example.com/audio.mp3"
                  :rules="[requiredValidator]"
                />
              </VCol>
            </template>
          </VRow>
        </VForm>
      </VCardText>

      <VCardText class="d-flex justify-end gap-3 flex-wrap">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
        >
          {{ t('common.cancel', 'Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="isLoading"
          @click="onSave"
        >
          {{ t('common.save', 'Save') }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
