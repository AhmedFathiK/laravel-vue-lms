<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import api from '@/utils/api'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { requiredValidator } from '@core/utils/validators'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  conceptData: {
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
const conceptTypes = ref([])

const defaultForm = () => ({
  title: '',
  explanation: '',
  type: '',
  status: 'active',
  courseId: props.courseId,
})

const form = ref(defaultForm())

// Fetch concept types
const fetchConceptTypes = async () => {
  try {
    const response = await api.get('/admin/concepts/types')

    conceptTypes.value = response || []
  } catch (error) {
    console.error('Error fetching concept types:', error)
    toast.error('Failed to load concept types')
    conceptTypes.value = []
  }
}

onMounted(() => {
  fetchConceptTypes()
})

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.conceptData) {
      form.value = {
        title: props.conceptData.title || '',
        explanation: props.conceptData.explanation || '',
        type: props.conceptData.type || '',
        status: props.conceptData.status || 'active',
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
  apiEndpoint: computed(() => props.conceptData?.id 
    ? `/admin/courses/${props.courseId}/concepts/${props.conceptData.id}` 
    : `/admin/courses/${props.courseId}/concepts`),
  isUpdate: computed(() => !!props.conceptData?.id),
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

    <VCard :title="props.conceptData ? 'Edit Concept' : 'Add New Concept'">
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

            <!-- Type -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.type"
                :items="conceptTypes"
                label="Type"
                :rules="[requiredValidator]"
                placeholder="Select Type"
                :error-messages="validationErrors.type"
              />
            </VCol>

            <!-- Status -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.status"
                :items="[{ title: 'Active', value: 'active' }, { title: 'Inactive', value: 'inactive' }]"
                label="Status"
                placeholder="Select Status"
                :error-messages="validationErrors.status"
              />
            </VCol>

            <!-- Explanation -->
            <VCol cols="12">
              <AppTextarea
                v-model="form.explanation"
                label="Explanation"
                :rules="[requiredValidator]"
                rows="5"
                placeholder="Enter concept explanation"
                :error-messages="validationErrors.explanation"
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
                {{ props.conceptData ? 'Update' : 'Create' }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
