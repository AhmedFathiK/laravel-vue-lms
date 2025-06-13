<script setup>
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

const emit = defineEmits(['update:isDialogVisible', 'submit'])

const toast = useToast()

// Form data
const title = ref('')
const description = ref('')
const price = ref(0)
const categoryId = ref(null)
const coverImage = ref(null)
const subscriptionType = ref('one-time') // one-time or monthly
const leaderboardResetFrequency = ref('monthly') // never, weekly, monthly
const prerequisites = ref([])
const prerequisiteInput = ref('')

// Reset form values
const resetFormValues = () => {
  title.value = ''
  description.value = ''
  price.value = 0
  categoryId.value = null
  coverImage.value = null
  subscriptionType.value = 'one-time'
  leaderboardResetFrequency.value = 'monthly'
  prerequisites.value = []
  prerequisiteInput.value = ''
}

// Watch for changes in courseData prop
watch(() => props.courseData, () => {
  if (props.courseData) {
    title.value = props.courseData.title || ''
    description.value = props.courseData.description || ''
    price.value = props.courseData.price || 0
    categoryId.value = props.courseData.course_category_id || props.courseData.category_id || null
    subscriptionType.value = props.courseData.subscription_type || 'one-time'
    leaderboardResetFrequency.value = props.courseData.leaderboard_reset_frequency || 'monthly'
    prerequisites.value = props.courseData.prerequisites || []
  } else {
    resetFormValues()
  }
}, { immediate: true })

// Handle dialog visibility
const onDialogVisibleUpdate = val => {
  emit('update:isDialogVisible', val)
  if (!val)
    resetFormValues()
}

// Handle image upload
const handleImageUpload = event => {
  const file = event.target.files[0]
  if (!file) return

  // Validate file type
  const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif']
  if (!validTypes.includes(file.type)) {
    toast.error('Please upload a valid image file (JPEG, PNG, GIF)')
    
    return
  }

  // Validate file size (max 2MB)
  if (file.size > 2 * 1024 * 1024) {
    toast.error('Image size should be less than 2MB')
    
    return
  }

  coverImage.value = file
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
const onSubmit = () => {
  // Validate form
  if (!title.value.trim()) {
    toast.error('Title is required')
    
    return
  }

  if (!description.value.trim()) {
    toast.error('Description is required')
    
    return
  }

  if (!categoryId.value) {
    toast.error('Category is required')
    
    return
  }

  // Create form data for image upload
  const formData = new FormData()

  formData.append('title', title.value)
  formData.append('description', description.value)
  formData.append('price', price.value)
  formData.append('course_category_id', categoryId.value)
  formData.append('subscription_type', subscriptionType.value)
  formData.append('leaderboard_reset_frequency', leaderboardResetFrequency.value)
  
  // Add prerequisites as JSON
  formData.append('prerequisites', JSON.stringify(prerequisites.value))
  
  // Add cover image if available
  if (coverImage.value instanceof File) {
    formData.append('cover_image', coverImage.value)
  }

  // If editing, add course ID
  if (props.courseData?.id) {
    formData.append('_method', 'PUT')
  }

  emit('submit', formData)
}

// Reset form
const resetForm = () => {
  resetFormValues()
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
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="700px"
    persistent
    @update:model-value="onDialogVisibleUpdate"
  >
    <VCard>
      <VCardTitle class="text-h5">
        {{ courseData ? 'Edit Course' : 'Add New Course' }}
      </VCardTitle>

      <VCardText>
        <VForm @submit.prevent="onSubmit">
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

            <!-- Course Price -->
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="price"
                label="Price"
                placeholder="Enter course price"
                variant="outlined"
                type="number"
                min="0"
                step="0.01"
                :rules="[v => v >= 0 || 'Price cannot be negative']"
              />
            </VCol>

            <!-- Subscription Type -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="subscriptionType"
                :items="subscriptionOptions"
                item-title="title"
                item-value="value"
                label="Subscription Type"
                variant="outlined"
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

            <!-- Cover Image -->
            <VCol cols="12">
              <VLabel>Cover Image</VLabel>
              <VFileInput
                accept="image/*"
                label="Select Image"
                variant="outlined"
                prepend-icon="tabler-upload"
                @change="handleImageUpload"
              />
              <div
                v-if="props.courseData?.cover_image"
                class="mt-2"
              >
                <small>Current image: {{ props.courseData.cover_image }}</small>
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

      <VCardActions>
        <VSpacer />
        <VBtn
          color="error"
          variant="text"
          @click="onDialogVisibleUpdate(false)"
        >
          Cancel
        </VBtn>
        <VBtn
          color="secondary"
          variant="text"
          @click="resetForm"
        >
          Reset
        </VBtn>
        <VBtn
          color="primary"
          @click="onSubmit"
        >
          {{ courseData ? 'Update' : 'Create' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
