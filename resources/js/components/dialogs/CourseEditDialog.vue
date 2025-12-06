<script setup>
import api from '@/utils/api'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { ref, watch } from 'vue'
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
const isSubmitting = ref(false)
const isFormValid = ref(true)

// Form data
const title = ref('')
const description = ref('')
const categoryId = ref(null)
const thumbnail = ref(null)
const thumbnailPreview = ref(null) // For image preview
const isFree = ref(props.courseData?.isFree || false)
const leaderboardResetFrequency = ref('monthly') // never, weekly, monthly
const prerequisites = ref([])
const prerequisiteInput = ref('')
const status = ref('draft') // Default status is draft
const deleteThumbnail = ref(false) // Flag to delete thumbnail

// Reset form values
const resetFormValues = () => {
  title.value = ''
  description.value = ''
  categoryId.value = null
  thumbnail.value = null
  thumbnailPreview.value = null
  isFree.value = false
  leaderboardResetFrequency.value = 'monthly'
  prerequisites.value = []
  prerequisiteInput.value = ''
  status.value = 'draft'
  isFormValid.value = true
  deleteThumbnail.value = false
}

// Watch for changes in courseData prop
watch(() => props.courseData, () => {
  if (props.courseData) {
    title.value = props.courseData.title || ''
    description.value = props.courseData.description || ''
    categoryId.value = props.courseData.courseCategoryId || props.courseData.categoryId || null
    isFree.value = props.courseData.isFree || false
    leaderboardResetFrequency.value = props.courseData.leaderboardResetFrequency || 'monthly'
    prerequisites.value = props.courseData.prerequisites || []
    status.value = props.courseData.status || 'draft'
  } else {
    resetFormValues()
  }
}, { immediate: true })

// Handle dialog visibility
const onDialogVisibleUpdate = val => {
  emit('update:isDialogVisible', val)
  if (!val) {
    resetFormValues()
  }
}

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
  
  prerequisites.value.push(prerequisiteInput.value.trim())
  prerequisiteInput.value = ''
}

// Remove prerequisite
const removePrerequisite = index => {
  prerequisites.value.splice(index, 1)
}

// Submit form
const onSubmit = async () => {
  isFormValid.value = (await refVForm.value.validate()).valid
  
  if (!isFormValid.value) {
    return
  }

  // Create form data for image upload
  const formData = new FormData()

  formData.append('title', title.value)
  formData.append('description', description.value)
  formData.append('courseCategoryId', categoryId.value)
  formData.append('isFree', isFree.value ? '1' : '0')
  formData.append('leaderboardResetFrequency', leaderboardResetFrequency.value)
  formData.append('status', status.value)
  
  // Add prerequisites as JSON
  formData.append('prerequisites', JSON.stringify(prerequisites.value))
  
  // Add thumbnail only if a new file is selected
  if (thumbnail.value instanceof File) {
    formData.append('thumbnail', thumbnail.value)
  }
  
  // Handle thumbnail deletion
  if (deleteThumbnail.value) {
    formData.append('deleteThumbnail', '1')
  }

  try {
    isSubmitting.value = true
    
    // If editing, add course ID and update, otherwise create
    if (props.courseData?.id) {
      formData.append('_method', 'PUT')
      
      // Use custom axios config for FormData with file uploads
      await api.post(`/admin/courses/${props.courseData.id}`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })
      toast.success('Course updated successfully')
    } else {
      // Use custom axios config for FormData with file uploads
      await api.post('/admin/courses', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      })
      toast.success('Course created successfully')
    }
    
    // Close dialog and emit refresh event
    onDialogVisibleUpdate(false)
    emit('refresh')
  } catch (error) {
    console.error('Error saving course:', error)
    
    // Show all error messages if there are multiple
    if (error.response?.data?.errors) {
      // Get all error messages as an array of strings
      const errorMessages = Object.values(error.response.data.errors).flat()
      
      // Show each error as a separate toast
      errorMessages.forEach(message => {
        toast.error(message)
      })
    } else {
      toast.error(error.response?.data?.message || 'Failed to save course')
    }
  } finally {
    isSubmitting.value = false
  }
}

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
    @update:model-value="onDialogVisibleUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="onDialogVisibleUpdate(false)" />

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
                v-model="title"
                label="Title"
                placeholder="Enter course title"
                variant="outlined"
                :rules="[v => !!v || 'Title is required']"
                required
              />
            </VCol>

            <!-- Course Category -->
            <VCol cols="12">
              <VSelect
                v-model="categoryId"
                :items="categories"
                item-title="name"
                item-value="id"
                label="Category"
                placeholder="Select category"
                variant="outlined"
                :rules="[v => !!v || 'Category is required']"
                required
              />
            </VCol>

            <!-- Course Description -->
            <VCol cols="12">
              <VTextarea
                v-model="description"
                label="Description"
                placeholder="Enter course description"
                variant="outlined"
                rows="4"
                :rules="[v => !!v || 'Description is required']"
                required
              />
            </VCol>

            <!-- Course Status -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="status"
                :items="statusOptions"
                item-title="title"
                item-value="value"
                label="Status"
                variant="outlined"
              />
            </VCol>

            <!-- Subscription Type -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="isFree"
                label="Free Course"
                color="primary"
                hide-details
              />
            </VCol>

            <!-- Leaderboard Reset Frequency -->
            <VCol cols="12">
              <VSelect
                v-model="leaderboardResetFrequency"
                :items="resetFrequencyOptions"
                item-title="title"
                item-value="value"
                label="Leaderboard Reset Frequency"
                variant="outlined"
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
                  v-for="(prereq, index) in prerequisites"
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
          :disabled="isSubmitting"
          @click="onDialogVisibleUpdate(false)"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="onSubmit"
        >
          {{ courseData ? 'Update' : 'Create' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
