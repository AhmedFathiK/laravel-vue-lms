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
  dialogMode: {
    type: String,
    required: true,
    validator: value => ['add', 'edit'].includes(value),
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

const refForm = ref(null)

const defaultForm = () => ({
  title: '',
  description: '',
  isFree: false,
  status: 'draft',
  courseId: props.courseId,
})

const form = ref(defaultForm())

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.levelData) {
      form.value = {
        title: props.levelData.title || '',
        description: props.levelData.description || '',
        isFree: !!props.levelData.isFree,
        status: props.levelData.status || 'draft',
        courseId: props.courseId,
      }
    } else {
      form.value = defaultForm()
    }

    nextTick(() => {
      refForm.value?.resetValidation()
    })
  }
})

// Custom emit to map 'saved' to 'refresh'
const customEmit = (event, ...args) => {
  if (event === 'saved') {
    emit('refresh', ...args)
  } else {
    emit(event, ...args)
  }
}

const { isLoading, validationErrors, onSubmit } = useCrudSubmit({
  formRef: refForm,
  form: form,
  apiEndpoint: computed(() => props.dialogMode === 'edit'
    ? `/admin/courses/${props.courseId}/levels/${props.levelData.id}` 
    : `/admin/courses/${props.courseId}/levels`),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  isFormData: false, // JSON mode
  emit: customEmit,
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="600"
    @update:model-value="val => $emit('update:isDialogVisible', val)"
  >
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard :title="props.dialogMode === 'edit' ? 'Edit Level' : 'Add New Level'">
      <VCardText>
        <VForm
          ref="refForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Title -->
            <VCol cols="12">
              <AppTextField
                v-model="form.title"
                label="Title"
                :rules="[requiredValidator]"
                placeholder="Enter level title"
                :error-messages="validationErrors.title"
              />
            </VCol>

            <!-- Description -->
            <VCol cols="12">
              <AppTextarea
                v-model="form.description"
                label="Description"
                placeholder="Enter level description"
                rows="3"
                :error-messages="validationErrors.description"
              />
            </VCol>

            <!-- Status -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.status"
                :items="[{ title: 'Draft', value: 'draft' }, { title: 'Published', value: 'published' }, { title: 'Archived', value: 'archived' }]"
                label="Status"
                placeholder="Select Status"
                :error-messages="validationErrors.status"
              />
            </VCol>

            <!-- Free Switch -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="form.isFree"
                label="Free Level"
                :error-messages="validationErrors.isFree"
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
                {{ props.dialogMode === 'edit' ? 'Update' : 'Create' }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template> 
