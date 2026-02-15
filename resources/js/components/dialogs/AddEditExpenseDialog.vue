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
  categories: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:isDialogVisible', 'saved'])

const refForm = ref(null)

const defaultForm = {
  amount: '',
  currency: 'USD',
  date: new Date().toISOString().substr(0, 10),
  categoryId: null,
  description: '',
  status: 'pending',
}

const form = ref({ ...defaultForm })

// Watch for changes in the dialog visibility
watch(
  () => props.isDialogVisible,
  isVisible => {
    if (isVisible) {
      if (props.data) {
        form.value = {
          amount: props.data.amount,
          currency: props.data.currency,
          date: props.data.date,
          categoryId: props.data.category_id,
          description: props.data.description,
          status: props.data.status,
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
    ? `/admin/expenses/${props.data.id}` 
    : '/admin/expenses'),
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

    <VCard :title="props.dialogMode === 'edit' ? 'Edit Expense' : 'Add Expense'">
      <VCardText>
        <VForm
          ref="refForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Amount -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.amount"
                label="Amount"
                type="number"
                step="0.01"
                :rules="[requiredValidator, val => val >= 0.01 || 'Amount must be greater than 0']"
                placeholder="0.00"
                :error-messages="validationErrors.amount"
              />
            </VCol>

            <!-- Currency -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.currency"
                label="Currency"
                :rules="[requiredValidator]"
                placeholder="USD"
                :error-messages="validationErrors.currency"
              />
            </VCol>

            <!-- Date -->
            <VCol
              cols="12"
              md="6"
            >
              <AppDateTimePicker
                v-model="form.date"
                label="Date"
                :rules="[requiredValidator]"
                :error-messages="validationErrors.date"
              />
            </VCol>

            <!-- Category -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.categoryId"
                label="Category"
                :items="props.categories"
                item-title="name"
                item-value="id"
                :rules="[requiredValidator]"
                placeholder="Select Category"
                :error-messages="validationErrors.categoryId"
              />
            </VCol>

            <!-- Status -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.status"
                label="Status"
                :items="[
                  { title: 'Pending', value: 'pending' },
                  { title: 'Completed', value: 'completed' }
                ]"
                :rules="[requiredValidator]"
                placeholder="Select Status"
                :error-messages="validationErrors.status"
              />
            </VCol>

            <!-- Description -->
            <VCol cols="12">
              <AppTextarea
                v-model="form.description"
                label="Description"
                placeholder="Enter expense description"
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
