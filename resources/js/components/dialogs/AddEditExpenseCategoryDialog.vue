<script setup>
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import { requiredValidator } from '@core/utils/validators'

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
})

const emit = defineEmits(['update:isDialogVisible', 'saved'])

const refForm = ref(null)

const defaultForm = {
  name: '',
  description: '',
}

const form = ref({ ...defaultForm })

// Watch for changes in the dialog visibility
watch(
  () => props.isDialogVisible,
  isVisible => {
    if (isVisible) {
      if (props.data) {
        form.value = {
          name: props.data.name || '',
          description: props.data.description || '',
        }
      } else {
        form.value = { ...defaultForm }
      }
      
      nextTick(() => {
        refForm.value?.resetValidation()
      })
    }
  },
)

const { isLoading, validationErrors, onSubmit } = useCrudSubmit({
  formRef: refForm,
  form: form,
  apiEndpoint: computed(() => props.dialogMode === 'edit' 
    ? `/admin/expense-categories/${props.data.id}` 
    : '/admin/expense-categories'),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  emit,
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="600"
    @update:model-value="val => $emit('update:isDialogVisible', val)"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard :title="props.dialogMode === 'edit' ? 'Edit Expense Category' : 'Add Expense Category'">
      <VCardText>
        <VForm
          ref="refForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Name -->
            <VCol cols="12">
              <AppTextField
                v-model="form.name"
                label="Name"
                :rules="[requiredValidator]"
                placeholder="Enter category name"
                :error-messages="validationErrors.name"
              />
            </VCol>

            <!-- Description -->
            <VCol cols="12">
              <AppTextarea
                v-model="form.description"
                label="Description"
                placeholder="Enter category description"
                :error-messages="validationErrors.description"
                rows="3"
              />
            </VCol>
          </VRow>

          <div class="d-flex gap-4 justify-end mt-4">
            <VBtn
              color="secondary"
              variant="tonal"
              @click="$emit('update:isDialogVisible', false)"
            >
              Cancel
            </VBtn>
            <VBtn
              type="submit"
              :loading="isLoading"
            >
              {{ props.dialogMode === 'edit' ? 'Update' : 'Add' }}
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
