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
  levelData: {
    type: Object,
    default: () => null,
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

const isFree = ref(false)
const status = ref('draft')

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

  isFree.value = false
  status.value = 'draft'
  isFormValid.value = true
}

// Watch for changes in levelData prop
watch(() => props.levelData, () => {
  if (props.levelData) {
    title.value = props.levelData.title || ''
    description.value = props.levelData.description || ''
    isFree.value = props.levelData.is_free || false
    status.value = props.levelData.status || 'draft'
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
    title: title.value,
    description: description.value,
    "is_free": isFree.value,
    status: status.value,
    "course_id": props.courseId,
  }

  try {
    isSubmitting.value = true
    
    // If editing, update existing level, otherwise create new level
    if (props.levelData?.id) {
      await api.put(`/admin/courses/${props.courseId}/levels/${props.levelData.id}`, formData)
      toast.success('Level updated successfully')
    } else {
      await api.post(`/admin/courses/${props.courseId}/levels`, formData)
      toast.success('Level created successfully')
    }
    
    // Close dialog and emit refresh event
    onDialogVisibleUpdate(false)
    emit('refresh')
  } catch (error) {
    console.error('Error saving level:', error)
    
    // Show all error messages if there are multiple
    if (error.response?.data?.errors) {
      // Get all error messages as an array of strings
      const errorMessages = Object.values(error.response.data.errors).flat()
      
      // Show each error as a separate toast
      errorMessages.forEach(message => {
        toast.error(message)
      })
    } else {
      toast.error(error.response?.data?.message || 'Failed to save level')
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="600px"
    persistent
    @update:model-value="onDialogVisibleUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="onDialogVisibleUpdate(false)" />

    <!-- Dialog Content -->
    <VCard :title="levelData ? 'Edit Level' : 'Add New Level'">
      <VCardText>
        <VForm
          ref="refVForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Level Title -->
            <VCol cols="12">
              <AppTextField
                v-model="title"
                label="Title"
                placeholder="Enter level title"
                :rules="[v => !!v || 'Title is required']"
                required
              />
            </VCol>

            <!-- Level Description -->
            <VCol cols="12">
              <AppTextarea
                v-model="description"
                label="Description"
                placeholder="Enter level description"
                rows="4"
              />
            </VCol>

            <!-- Free Access -->
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

            <!-- Level Status -->
            <VCol cols="12">
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
          {{ levelData ? 'Update' : 'Create' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template> 
