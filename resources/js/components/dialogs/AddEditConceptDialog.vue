<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import api from '@/utils/api'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import TiptapEditor from '@core/components/TiptapEditor.vue'
import { requiredValidator } from '@core/utils/validators'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

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
  courseId: {
    type: [String, Number],
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'saved'])

const toast = useToast()
const refForm = ref(null)
const conceptCategories = ref([])

const defaultForm = () => ({
  title: '',
  explanation: '',
  categoryId: null,
  courseId: props.courseId,
})

const form = ref(defaultForm())

// Fetch concept categories
const fetchConceptCategories = async () => {
  try {
    const response = await api.get(`/admin/courses/${props.courseId}/concept-categories`)

    conceptCategories.value = response.map(cat => ({
      title: cat.title,
      value: cat.id,
    }))
  } catch (error) {
    console.error('Error fetching concept categories:', error)
    toast.error('Failed to load concept categories')
  }
}

onMounted(() => {
  fetchConceptCategories()
})

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.data) {
      form.value = {
        title: props.data.title || '',
        explanation: props.data.explanation || '',
        categoryId: props.data.categoryId || null,
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

const { isLoading, validationErrors, onSubmit } = useCrudSubmit({
  formRef: refForm,
  form: form,
  apiEndpoint: computed(() => props.dialogMode === 'edit'
    ? `/admin/courses/${props.courseId}/concepts/${props.data.id}` 
    : `/admin/courses/${props.courseId}/concepts`),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  emit,
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="800"
    @update:model-value="val => $emit('update:isDialogVisible', val)"
  >
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard :title="props.dialogMode === 'edit' ? 'Edit Concept' : 'Add New Concept'">
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
                placeholder="Enter concept title"
                :error-messages="validationErrors.title"
              />
            </VCol>

            <!-- Category -->
            <VCol cols="12">
              <AppSelect
                v-model="form.categoryId"
                :items="conceptCategories"
                label="Category"
                placeholder="Select Category"
                clearable
                :error-messages="validationErrors.categoryId"
              />
            </VCol>

            <!-- Explanation -->
            <VCol cols="12">
              <VLabel class="mb-1 text-body-2 text-high-emphasis">
                Explanation
              </VLabel>
              <TiptapEditor
                v-model="form.explanation"
                placeholder="Enter concept explanation"
                class="border rounded"
              />
              <div
                v-if="validationErrors.explanation"
                class="v-messages text-error mt-1"
              >
                {{ validationErrors.explanation[0] }}
              </div>
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
