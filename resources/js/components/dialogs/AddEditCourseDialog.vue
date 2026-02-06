<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import AppSelect from '@core/components/app-form-elements/AppSelect.vue'
import AppTextField from '@core/components/app-form-elements/AppTextField.vue'
import AppTextarea from '@core/components/app-form-elements/AppTextarea.vue'
import { requiredValidator } from '@core/utils/validators'
import api from '@/utils/api'
import { computed, ref, watch } from 'vue'
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
  categories: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:isDialogVisible', 'refresh'])

const toast = useToast()
const refVForm = ref(null)

// Default form factory
const createDefaultForm = () => ({
  title: '',
  description: '',
  courseCategoryId: null,
  leaderboardResetFrequency: 'monthly',
  prerequisites: [],
  status: 'draft',
  finalExamId: null,
  placementExamId: null,
})

const form = ref(createDefaultForm())
const thumbnail = ref(null)
const thumbnailPreview = ref(null) // For image preview
const prerequisiteInput = ref('')
const deleteThumbnail = ref(false) // Flag to delete thumbnail
const courseExams = ref([])
const isExamsLoading = ref(false)

const fetchCourseExams = async courseId => {
  if (!courseId) {
    courseExams.value = []
    
    return
  }
  
  isExamsLoading.value = true
  try {
    const response = await api.get(`/admin/courses/${courseId}/exams`, {
      params: {
        'per_page': 100, // Get all exams for this course
      },
    })

    // Based on ExamController index, it returns pagination object
    courseExams.value = response.data || response.items || []
  } catch (error) {
    console.error('Error fetching course exams:', error)
    toast.error('Failed to fetch exams for this course')
  } finally {
    isExamsLoading.value = false
  }
}

// Reset form values
const resetFormValues = () => {
  form.value = createDefaultForm()
  thumbnail.value = null
  thumbnailPreview.value = null
  prerequisiteInput.value = ''
  deleteThumbnail.value = false
  courseExams.value = []
}

// Watch for changes in data prop
watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.data) {
      form.value = {
        title: props.data.title || '',
        description: props.data.description || '',
        courseCategoryId: props.data.courseCategoryId || props.data.categoryId || null,
        leaderboardResetFrequency: props.data.leaderboardResetFrequency || 'monthly',
        prerequisites: [...(props.data.prerequisites || [])],
        status: props.data.status || 'draft',
        finalExamId: props.data.finalExamId || null,
        placementExamId: props.data.placementExamId || null,
      }
      
      // Fetch exams for this course if editing
      if (props.data.id) {
        fetchCourseExams(props.data.id)
      }
      
      // Reset thumbnail state
      thumbnail.value = null
      thumbnailPreview.value = null
      deleteThumbnail.value = false
    } else {
      resetFormValues()
    }
  }
})

// Handle image upload
const handleImageUpload = file => {
  if (!file) {
    thumbnail.value = null
    thumbnailPreview.value = null
    
    return
  }

  // Validate file type
  const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif']
  if (!validTypes.includes(file.type)) {
    toast.error('Please upload a valid image file (JPEG, PNG, GIF)')
    thumbnail.value = null
    thumbnailPreview.value = null
    
    return
  }

  // Validate file size (max 2MB)
  if (file.size > 2 * 1024 * 1024) {
    toast.error('Image size should be less than 2MB')
    thumbnail.value = null
    thumbnailPreview.value = null
    
    return
  }

  // Create a preview URL for the selected image
  thumbnailPreview.value = URL.createObjectURL(file)
  
  // Reset delete flag if a new image is selected
  deleteThumbnail.value = false
}

// Add prerequisite
const addPrerequisite = () => {
  if (!prerequisiteInput.value.trim()) return
  
  form.value.prerequisites.push(prerequisiteInput.value.trim())
  prerequisiteInput.value = ''
}

// Remove prerequisite
const removePrerequisite = index => {
  form.value.prerequisites.splice(index, 1)
}

// Compute extra data for useCrudSubmit
const extraData = computed(() => {
  const data = {}
  
  // Add thumbnail only if a new file is selected
  if (thumbnail.value instanceof File) {
    data.thumbnail = thumbnail.value
  }
  
  // Handle thumbnail deletion
  if (deleteThumbnail.value) {
    data.deleteThumbnail = true
  }
  
  return data
})

// Custom emit for refresh
const customEmit = (event, ...args) => {
  if (event === 'saved') {
    emit('refresh', ...args)
  } else {
    emit(event, ...args)
  }
}

const { isLoading, validationErrors, onSubmit } = useCrudSubmit({
  formRef: refVForm,
  form: form,
  apiEndpoint: computed(() => props.dialogMode === 'edit'
    ? `/admin/courses/${props.data.id}` 
    : '/admin/courses'),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  emit: customEmit,
  extraData,
  isFormData: true,
  successMessage: computed(() => props.dialogMode === 'edit' ? 'Course updated successfully' : 'Course created successfully'),
})

// Entitlement options
const entitlementOptions = [
  { title: 'One-time Payment', value: 'one-time' },
  { title: 'Monthly Entitlement', value: 'monthly' },
]

// Leaderboard reset frequency options
const resetFrequencyOptions = [
  { title: 'Never Reset', value: 'never' },
  { title: 'Weekly Reset', value: 'weekly' },
  { title: 'Monthly Reset', value: 'monthly' },
]

// Status options
const statusOptions = [
  { title: 'Draft', value: 'draft' },
  { title: 'Published', value: 'published' },
  { title: 'Archived', value: 'archived' },
]
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="700px"
    persistent
    @update:model-value="val => $emit('update:isDialogVisible', val)"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <!-- Dialog Content -->
    <VCard :title="props.dialogMode === 'edit' ? 'Edit Course' : 'Add New Course'">
      <VCardText>
        <VForm
          ref="refVForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Course Title -->
            <VCol
              cols="12"
              md="8"
            >
              <AppTextField
                v-model="form.title"
                label="Title"
                placeholder="Enter course title"
                :rules="[requiredValidator]"
                :error-messages="validationErrors.title"
                required
              />
            </VCol>

            <!-- Course Category -->
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.courseCategoryId"
                :items="categories"
                item-title="name"
                item-value="id"
                label="Category"
                placeholder="Select category"
                :rules="[requiredValidator]"
                :error-messages="validationErrors.courseCategoryId"
                required
              />
            </VCol>

            <!-- Course Description -->
            <VCol cols="12">
              <AppTextarea
                v-model="form.description"
                label="Description"
                placeholder="Enter course description"
                rows="3"
                :rules="[requiredValidator]"
                :error-messages="validationErrors.description"
                required
              />
            </VCol>

            <!-- Course Status -->
            <VCol
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.status"
                :items="statusOptions"
                item-title="title"
                item-value="value"
                label="Status"
                :error-messages="validationErrors.status"
              />
            </VCol>

            <!-- Final Exam Selection -->
            <VCol
              v-if="props.dialogMode === 'edit'"
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.finalExamId"
                :items="courseExams"
                item-title="title"
                item-value="id"
                label="Final Exam"
                placeholder="Select Final Exam"
                :loading="isExamsLoading"
                clearable
                :error-messages="validationErrors.finalExamId"
              />
            </VCol>

            <!-- Placement Exam Selection -->
            <VCol
              v-if="props.dialogMode === 'edit'"
              cols="12"
              md="4"
            >
              <AppSelect
                v-model="form.placementExamId"
                :items="courseExams"
                item-title="title"
                item-value="id"
                label="Placement Test"
                placeholder="Select Placement Test"
                :loading="isExamsLoading"
                clearable
                :error-messages="validationErrors.placementExamId"
              />
            </VCol>

            <!-- Leaderboard Reset Frequency -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.leaderboardResetFrequency"
                :items="resetFrequencyOptions"
                item-title="title"
                item-value="value"
                label="Leaderboard Reset Frequency"
                :error-messages="validationErrors.leaderboardResetFrequency"
              />
            </VCol>

            <!-- Thumbnail -->
            <VCol
              cols="12"
              md="6"
            >
              <VLabel class="mb-1 text-body-2 text-high-emphasis">
                Thumbnail
              </VLabel>
              <VFileInput
                v-model="thumbnail"
                accept="image/*"
                label="Select Image"
                variant="outlined"
                prepend-icon="tabler-upload"
                :error-messages="validationErrors.thumbnail"
                @update:model-value="handleImageUpload"
              />
              
              <!-- Preview of selected image -->
              <div
                v-if="thumbnailPreview"
                class="mt-2"
              >
                <VImg
                  :src="thumbnailPreview"
                  height="80"
                  contain
                  class="bg-grey-lighten-2 rounded"
                />
              </div>
              
              <!-- Current image from server -->
              <div
                v-else-if="props.data?.thumbnail && !deleteThumbnail"
                class="mt-2 d-flex align-center gap-2"
              >
                <VImg
                  :src="props.data.thumbnail"
                  height="80"
                  width="80"
                  contain
                  class="bg-grey-lighten-2 rounded"
                />
                <VBtn
                  color="error"
                  size="small"
                  variant="text"
                  icon="tabler-trash"
                  @click="deleteThumbnail = true"
                />
              </div>
              
              <!-- Message when thumbnail is marked for deletion -->
              <div
                v-else-if="deleteThumbnail"
                class="mt-2"
              >
                <VAlert
                  color="warning"
                  variant="tonal"
                  density="compact"
                  class="py-1 px-2 text-caption"
                >
                  Will be removed.
                  <VBtn
                    size="x-small"
                    variant="text"
                    class="ms-1"
                    @click="deleteThumbnail = false"
                  >
                    Undo
                  </VBtn>
                </VAlert>
              </div>
            </VCol>

            <!-- Prerequisites -->
            <VCol cols="12">
              <VLabel class="mb-1 text-body-2 text-high-emphasis">
                Prerequisites (Optional)
              </VLabel>
              <div class="d-flex gap-2">
                <AppTextField
                  v-model="prerequisiteInput"
                  placeholder="Enter prerequisite"
                  class="flex-grow-1"
                  @keydown.enter.prevent="addPrerequisite"
                />
                <VBtn
                  color="primary"
                  variant="tonal"
                  @click="addPrerequisite"
                >
                  Add
                </VBtn>
              </div>
              
              <!-- Prerequisites List -->
              <div class="mt-2 d-flex flex-wrap gap-1">
                <VChip
                  v-for="(prereq, index) in form.prerequisites"
                  :key="index"
                  size="small"
                  closable
                  @click:close="removePrerequisite(index)"
                >
                  {{ prereq }}
                </VChip>
              </div>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardText class="d-flex justify-end flex-wrap gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isLoading"
          @click="$emit('update:isDialogVisible', false)"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :loading="isLoading"
          @click="onSubmit"
        >
          {{ props.dialogMode === 'edit' ? 'Update' : 'Create' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
