<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { requiredValidator } from '@core/utils/validators'
import { computed, nextTick, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  categoryData: {
    type: Object,
    default: () => null,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'saved'])

const refForm = ref(null)

const defaultForm = () => ({
  name: '',
  description: '',
  isActive: true,
  sortOrder: 0,
})

const form = ref(defaultForm())

// Watch for changes in the category prop
watch(
  () => props.isDialogVisible,
  isVisible => {
    if (isVisible) {
      if (props.categoryData) {
        form.value = {
          name: props.categoryData.name || '',
          description: props.categoryData.description || '',
          isActive: props.categoryData.isActive ?? true,
          sortOrder: props.categoryData.sortOrder ?? 0,
        }
      } else {
        form.value = defaultForm()
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
  apiEndpoint: computed(() => props.categoryData?.id 
    ? `/admin/course-categories/${props.categoryData.id}` 
    : '/admin/course-categories'),
  isUpdate: computed(() => !!props.categoryData?.id),
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

    <VCard :title="props.categoryData ? 'Edit Category' : 'Add Category'">
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
                rows="3"
                :error-messages="validationErrors.description"
              />
            </VCol>

            <!-- Sort Order -->
            <VCol cols="12">
              <AppTextField
                v-model="form.sortOrder"
                label="Sort Order"
                type="number"
                placeholder="0"
                :error-messages="validationErrors.sortOrder"
              />
            </VCol>

            <!-- Is Active -->
            <VCol cols="12">
              <VCheckbox
                v-model="form.isActive"
                label="Is Active"
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
                :disabled="isLoading"
                @click="$emit('update:isDialogVisible', false)"
              >
                Cancel
              </VBtn>
              
              <VBtn
                type="submit"
                :loading="isLoading"
              >
                {{ props.categoryData ? 'Update' : 'Create' }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template> 
