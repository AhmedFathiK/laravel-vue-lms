<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { requiredValidator } from '@core/utils/validators'
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  courseData: {
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
  isFree: false,
  leaderboardResetFrequency: 'monthly',
  prerequisites: [],
  status: 'draft',
})

const form = ref(createDefaultForm())
const thumbnail = ref(null)
const thumbnailPreview = ref(null) // For image preview
const prerequisiteInput = ref('')
const deleteThumbnail = ref(false) // Flag to delete thumbnail

// Reset form values
const resetFormValues = () => {
  form.value = createDefaultForm()
  thumbnail.value = null
  thumbnailPreview.value = null
  prerequisiteInput.value = ''
  deleteThumbnail.value = false
}

// Watch for changes in courseData prop
watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.courseData) {
      form.value = {
        title: props.courseData.title || '',
        description: props.courseData.description || '',
        courseCategoryId: props.courseData.courseCategoryId || props.courseData.categoryId || null,
        isFree: props.courseData.isFree || false,
        leaderboardResetFrequency: props.courseData.leaderboardResetFrequency || 'monthly',
        prerequisites: props.courseData.prerequisites || [],
        status: props.courseData.status || 'draft',
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
  apiEndpoint: computed(() => props.courseData?.id 
    ? `/admin/courses/${props.courseData.id}` 
    : '/admin/courses'),
  isUpdate: computed(() => !!props.courseData?.id),
  emit: customEmit,
  extraData,
  isFormData: true,
  successMessage: computed(() => props.courseData?.id ? 'Course updated successfully' : 'Course created successfully'),
})

// Subscription options
const subscriptionOptions = [
  { title: 'One-time Payment', value: 'one-time' },
  { title: 'Monthly Subscription', value: 'monthly' },
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
    <VCard :title="courseData ? 'Edit Course' : 'Add New Course'">
      <VCardText>
        <VForm
          ref="refVForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Course Title -->
            <VCol cols="12">
              <VTextField
                v-model="form.title"
                label="Title"
                placeholder="Enter course title"
                variant="outlined"
                :rules="[requiredValidator]"
                :error-messages="validationErrors.title"
                required
              />
            </VCol>

            <!-- Course Category -->
            <VCol cols="12">
              <VSelect
                v-model="form.courseCategoryId"
                :items="categories"
                item-title="name"
                item-value="id"
                label="Category"
                placeholder="Select category"
                variant="outlined"
                :rules="[requiredValidator]"
                :error-messages="validationErrors.courseCategoryId"
                required
              />
            </VCol>

            <!-- Course Description -->
            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Description"
                placeholder="Enter course description"
                variant="outlined"
                rows="4"
                :rules="[requiredValidator]"
                :error-messages="validationErrors.description"
                required
              />
            </VCol>

            <!-- Course Status -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.status"
                :items="statusOptions"
                item-title="title"
                item-value="value"
                label="Status"
                variant="outlined"
                :error-messages="validationErrors.status"
              />
            </VCol>

            <!-- Subscription Type -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="form.isFree"
                label="Free Course"
                color="primary"
                hide-details
              />
            </VCol>

            <!-- Leaderboard Reset Frequency -->
            <VCol cols="12">
              <VSelect
                v-model="form.leaderboardResetFrequency"
                :items="resetFrequencyOptions"
                item-title="title"
                item-value="value"
                label="Leaderboard Reset Frequency"
                variant="outlined"
                :error-messages="validationErrors.leaderboardResetFrequency"
              />
            </VCol>

            <!-- Thumbnail -->
            <VCol cols="12">
              <VLabel>Thumbnail</VLabel>
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
                  height="100"
                  contain
                  class="bg-grey-lighten-2 rounded mt-2"
                />
                <div class="mt-2">
                  <VChip
                    color="primary"
                    size="small"
                    class="me-2"
                  >
                    New image selected
                  </VChip>
                </div>
              </div>
              
              <!-- Current image from server -->
              <div
                v-else-if="props.courseData?.thumbnail && !deleteThumbnail"
                class="mt-2"
              >
                <VImg
                  :src="props.courseData.thumbnail"
                  height="100"
                  contain
                  class="bg-grey-lighten-2 rounded mt-2"
                />
                <div class="mt-2">
                  <VBtn
                    color="error"
                    size="small"
                    variant="outlined"
                    prepend-icon="tabler-trash"
                    @click="deleteThumbnail = true"
                  >
                    Remove Thumbnail
                  </VBtn>
                </div>
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
                >
                  Thumbnail will be removed when you save the course.
                  <VBtn
                    size="x-small"
                    class="ms-2"
                    @click="deleteThumbnail = false"
                  >
                    Undo
                  </VBtn>
                </VAlert>
              </div>

              <!-- Placeholder when no image -->
              <div
                v-else
                class="mt-2"
              >
                <VAvatar
                  size="100"
                  color="primary"
                  variant="tonal"
                >
                  <VIcon
                    icon="tabler-camera-off"
                    size="32"
                  />
                </VAvatar>
              </div>
            </VCol>

            <!-- Prerequisites -->
            <VCol cols="12">
              <VLabel>Prerequisites (Optional)</VLabel>
              <div class="d-flex gap-2">
                <VTextField
                  v-model="prerequisiteInput"
                  placeholder="Enter prerequisite"
                  variant="outlined"
                  class="flex-grow-1"
                />
                <VBtn
                  color="primary"
                  @click="addPrerequisite"
                >
                  Add
                </VBtn>
              </div>
              
              <!-- Prerequisites List -->
              <div class="mt-2">
                <VChip
                  v-for="(prereq, index) in form.prerequisites"
                  :key="index"
                  class="ma-1"
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
          {{ courseData ? 'Update' : 'Create' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
