<script setup>
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { ref } from 'vue'

const props = defineProps({
  confirmationQuestion: {
    type: String,
    required: true,
  },
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  confirmTitle: {
    type: String,
    default: 'Action Confirmed',
  },
  confirmMsg: {
    type: String,
    default: 'Your action has been completed successfully.',
  },
  cancelTitle: {
    type: String,
    default: 'Action Cancelled',
  },
  cancelMsg: {
    type: String,
    default: 'Your action has been cancelled.',
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'confirm',
])

const deleteText = ref('')
const deleteTextError = ref('')
const isSubmitting = ref(false)
const unsubscribed = ref(false)
const cancelled = ref(false)

const updateModelValue = val => {
  emit('update:isDialogVisible', val)
  if (!val) {
    deleteText.value = ''
    deleteTextError.value = ''
  }
}

const onConfirmation = () => {
  if (!deleteText.value) {
    deleteTextError.value = 'Confirmation text is required'

    return
  }
  
  if (deleteText.value !== 'Delete') {
    deleteTextError.value = 'Please type "Delete" exactly as shown'

    return
  }
  
  isSubmitting.value = true
  
  // Emit the confirmation
  emit('confirm', { confirmed: true })
  
  // Reset and close
  deleteText.value = ''
  deleteTextError.value = ''
  isSubmitting.value = false
  updateModelValue(false)
  unsubscribed.value = true
}

const onCancel = () => {
  emit('confirm', { confirmed: false })
  deleteText.value = ''
  deleteTextError.value = ''
  emit('update:isDialogVisible', false)
  cancelled.value = true
}
</script>

<template>
  <!-- 👉 Deletion Confirm Dialog -->
  <VDialog
    max-width="500"
    :model-value="props.isDialogVisible"
    @update:model-value="updateModelValue"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="onCancel" />
    <VCard>
      <VCardTitle class="text-h5 pa-6 pb-0">
        Confirm Deletion
      </VCardTitle>
      
      <VCardText class="text-center px-10 pt-0 pb-4">
        <VBtn
          icon
          variant="outlined"
          color="error"
          class="my-4"
          style="block-size: 88px;inline-size: 88px; pointer-events: none;"
        >
          <span class="text-5xl">!</span>
        </VBtn>

        <h6 class="text-lg font-weight-medium mb-4">
          {{ props.confirmationQuestion }}
        </h6>
        
        <AppTextField
          v-model="deleteText"
          label="Confirmation"
          placeholder="Type 'Delete' to confirm"
          :error-messages="deleteTextError"
          @keyup.enter="onConfirmation"
        />
      </VCardText>

      <VCardText class="d-flex align-center justify-center gap-2 mt-4">
        <VBtn
          color="error"
          variant="elevated"
          :loading="props.loading"
          @click="onConfirmation"
        >
          Delete
        </VBtn>

        <VBtn
          color="secondary"
          variant="tonal"
          :disabled="props.loading"
          @click="onCancel"
        >
          Cancel
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template> 
