<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { requiredValidator } from '@core/utils/validators'
import api from '@/utils/api'
import { computed, nextTick, ref, watch } from 'vue'
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
    type: [Number, String],
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'refresh'])

const toast = useToast()
const refForm = ref(null)

const defaultForm = () => ({
  title: '',
  description: '',
  status: 'draft',
  courseId: props.courseId,
  finalExamId: null,
  isFree: false,
})

const form = ref(defaultForm())
const levelExams = ref([])
const isExamsLoading = ref(false)

const fetchCourseExams = async () => {
  isExamsLoading.value = true
  try {
    const response = await api.get(`/admin/courses/${props.courseId}/exams`, {
      params: {
        'per_page': 100,
      },
    })

    levelExams.value = response.data || response.items || []
  } catch (error) {
    console.error('Error fetching course exams:', error)
    toast.error('Failed to fetch exams for this course')
  } finally {
    isExamsLoading.value = false
  }
}

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.data) {
      form.value = {
        title: props.data.title || '',
        description: props.data.description || '',
        status: props.data.status || 'draft',
        courseId: props.courseId,
        finalExamId: props.data.finalExamId || null,
      }
    } else {
      form.value = defaultForm()
    }
    
    fetchCourseExams()

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
    ? `/admin/courses/${props.courseId}/levels/${props.data.id}` 
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

            <!-- Is Free -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="form.isFree"
                label="Is Free?"
                hint="Allow access without paid subscription"
                persistent-hint
              />
            </VCol>

            <!-- Final Exam Selection -->
            <VCol
              v-if="props.dialogMode === 'edit'"
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.finalExamId"
                :items="levelExams"
                item-title="title"
                item-value="id"
                label="Final Exam"
                placeholder="Select Final Exam"
                :loading="isExamsLoading"
                clearable
                :error-messages="validationErrors.finalExamId"
                hint="Only exams belonging to this course are shown"
                persistent-hint
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
