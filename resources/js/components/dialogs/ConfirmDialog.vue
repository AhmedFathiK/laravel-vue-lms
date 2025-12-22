<script setup>
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'

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
    default: 'Confirmed',
  },
  confirmMsg: {
    type: String,
    default: 'Action completed successfully',
  },
  cancelTitle: {
    type: String,
    default: 'Cancelled',
  },
  cancelMsg: {
    type: String,
    default: 'Action cancelled',
  },

  // Allow parent to handle async confirmation
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'confirm',
])

const updateModelValue = val => {
  emit('update:isDialogVisible', val)
}

const onConfirmation = () => {
  emit('confirm', true)

  // We don't close immediately here to allow parent to show loading state
  // Parent should close dialog after async operation
}

const onCancel = () => {
  emit('confirm', false)
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    max-width="500"
    :model-value="props.isDialogVisible"
    @update:model-value="updateModelValue"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="onCancel" />
    
    <VCard class="text-center px-10 py-6">
      <VCardText>
        <VBtn
          icon
          variant="outlined"
          color="warning"
          class="my-4"
          style="block-size: 88px; inline-size: 88px; pointer-events: none;"
        >
          <span class="text-5xl">!</span>
        </VBtn>

        <h6 class="text-h6 font-weight-medium">
          {{ props.confirmationQuestion }}
        </h6>
      </VCardText>

      <VCardText class="d-flex align-center justify-center gap-2">
        <VBtn
          variant="elevated"
          :loading="props.loading"
          @click="onConfirmation"
        >
          Confirm
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
