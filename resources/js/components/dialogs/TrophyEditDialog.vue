
<script setup>
import api from '@/utils/api'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { ref, watch, computed } from 'vue'
import { useToast } from 'vue-toastification'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  dialogMode: {
    type: String,
    default: 'add',
    validator: value => ['add', 'edit'].includes(value),
  },
  trophy: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:isDialogVisible', 'trophySaved'])

const { t } = useI18n()
const toast = useToast()
const formRef = ref(null)
const isFormValid = ref(true)
const isSubmitting = ref(false)

// Local trophy state
const localTrophy = ref({})

// File upload
const iconFile = ref(null)
const iconPreview = ref(null)

// Data for dropdowns
const triggerTypes = ref([])
const rarityLevels = ref([])
const courses = ref([])

// Form validation rules
const rules = {
  required: value => !!value || t('validation.required', 'Required'),
  number: value => !isNaN(Number(value)) || t('validation.mustBeNumber', 'Must be a number'),
  minValue: min => value => Number(value) >= min || t('validation.minValue', `Must be at least ${min}`, { min }),
}

// Computed dialog title
const dialogTitle = computed(() => 
  props.dialogMode === 'add' 
    ? t('trophies.dialog.addNewTrophy', 'Add New Trophy') 
    : t('trophies.dialog.editTrophy', 'Edit Trophy'),
)

// Get default trophy data
const getDefaultTrophy = () => ({
  id: null,
  name: '',
  description: '',
  iconUrl: null,
  triggerType: 'completedLesson',
  triggerRepeatCount: 1,
  courseId: null,
  points: 0,
  rarity: 'common',
  isHidden: false,
  isActive: true,
})

// Watch for dialog visibility changes
watch(
  () => props.isDialogVisible,
  newValue => {
    if (newValue) {
      if (props.dialogMode === 'edit' && props.trophy && Object.keys(props.trophy).length > 0) {
        localTrophy.value = JSON.parse(JSON.stringify(props.trophy))
        iconPreview.value = props.trophy.iconUrl
      } else {
        localTrophy.value = getDefaultTrophy()
        iconPreview.value = null
      }
      iconFile.value = null
      isFormValid.value = true
    } else {
      localTrophy.value = {}
    }
  },
  { immediate: true },
)

// Close dialog
const closeDialog = () => {
  emit('update:isDialogVisible', false)
}

// Preview uploaded icon
const previewIcon = () => {
  if (!iconFile.value) {
    iconPreview.value = localTrophy.value.iconUrl || null
    
    return
  }
  
  const reader = new FileReader()

  reader.onload = e => {
    iconPreview.value = e.target.result
  }
  reader.readAsDataURL(iconFile.value)
}

// Fetch trigger types from API
const fetchTriggerTypes = async () => {
  try {
    const response = await axios.get('/api/admin/trophies/trigger-types')

    triggerTypes.value = Object.entries(response.data).map(([value, label]) => ({
      value,
      label,
    }))
  } catch (error) {
    console.error('Error fetching trigger types:', error)
    toast.error(t('trophies.errors.failedToLoadTriggerTypes', 'Failed to load trigger types'))
  }
}

// Fetch rarity levels from API
const fetchRarityLevels = async () => {
  try {
    const response = await axios.get('/api/admin/trophies/rarity-levels')

    rarityLevels.value = Object.entries(response.data).map(([value, label]) => ({
      value,
      label,
    }))
  } catch (error) {
    console.error('Error fetching rarity levels:', error)
    toast.error(t('trophies.errors.failedToLoadRarityLevels', 'Failed to load rarity levels'))
  }
}

// Fetch courses for dropdown
const fetchCourses = async () => {
  try {
    const response = await axios.get('/api/admin/courses')

    courses.value = response.data.data || []
  } catch (error) {
    console.error('Error fetching courses:', error)
    toast.error(t('trophies.errors.failedToLoadCourses', 'Failed to load courses'))
  }
}

// Submit form
const submitForm = async () => {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  
  isSubmitting.value = true
  
  // Create form data for file upload
  const formData = new FormData()
  
  // Add all trophy fields
  formData.append('name', localTrophy.value.name)
  formData.append('description', localTrophy.value.description || '')
  formData.append('triggerType', localTrophy.value.triggerType)
  formData.append('triggerRepeatCount', localTrophy.value.triggerRepeatCount)
  formData.append('points', localTrophy.value.points)
  formData.append('rarity', localTrophy.value.rarity)
  formData.append('isHidden', localTrophy.value.isHidden ? '1' : '0')
  formData.append('isActive', localTrophy.value.isActive ? '1' : '0')
  
  // Add courseId if selected
  if (localTrophy.value.courseId) {
    formData.append('courseId', localTrophy.value.courseId)
  }
  
  // Add icon file if selected
  if (iconFile.value) {
    formData.append('icon', iconFile.value)
  }
  
  try {
    if (props.dialogMode === 'edit') {
      // Update - use POST with _method for file upload compatibility
      formData.append('_method', 'PUT')
      await axios.post(
        `/api/admin/trophies/${localTrophy.value.id}`, 
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        },
      )
      toast.success(t('trophies.success.trophyUpdated', 'Trophy updated successfully'))
    } else {
      // Create
      await axios.post(
        '/api/admin/trophies', 
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        },
      )
      toast.success(t('trophies.success.trophyCreated', 'Trophy created successfully'))
    }
    
    emit('trophySaved')
    closeDialog()
  } catch (error) {
    console.error('Error saving trophy:', error)
    if (error.response?.status === 422) {
      toast.error(t('validation.correctErrors', 'Please correct the validation errors'))
    } else {
      toast.error(error.response?.data?.message || t('trophies.errors.failedToSaveTrophy', 'Failed to save trophy'))
    }
  } finally {
    isSubmitting.value = false
  }
}

// Load data on component creation
fetchTriggerTypes()
fetchRarityLevels()
fetchCourses()
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
  >
    <DialogCloseBtn @click="closeDialog" />

    <VCard class="pa-2">
      <!-- Enhanced Header -->
      <VCardTitle class="text-h5 font-weight-bold pa-6 pb-4">
        {{ dialogTitle }}
      </VCardTitle>
      
      <VDivider />

      <VCardText class="pa-6">
        <VForm
          ref="formRef"
          v-model="isFormValid"
          @submit.prevent="submitForm"
        >
          <VRow>
            <!-- Icon Upload Section -->
            <VCol
              cols="12"
              md="4"
            >
              <VCard
                variant="outlined"
                class="h-100"
              >
                <VCardSubtitle class="pa-4 pb-2">
                  <VIcon class="me-2">
                    tabler-photo
                  </VIcon>
                  {{ t('trophies.dialog.trophyIcon', 'Trophy Icon') }}
                </VCardSubtitle>
                <VCardText class="text-center">
                  <VAvatar
                    size="120"
                    rounded
                    class="mb-4"
                  >
                    <VImg
                      v-if="iconPreview"
                      :src="iconPreview"
                      :alt="t('trophies.dialog.trophyIconPreview', 'Trophy icon preview')"
                    />
                    <VIcon
                      v-else
                      size="64"
                      color="grey-lighten-1"
                    >
                      tabler-trophy
                    </VIcon>
                  </VAvatar>
                  <VFileInput
                    v-model="iconFile"
                    :label="t('trophies.dialog.chooseIcon', 'Choose Icon')"
                    accept="image/*"
                    prepend-icon="tabler-camera"
                    variant="outlined"
                    density="compact"
                    @change="previewIcon"
                  />
                </VCardText>
              </VCard>
            </VCol>
            
            <VCol
              cols="12"
              md="8"
            >
              <!-- Basic Information -->
              <div class="mb-6">
                <p class="text-overline text-primary mb-3">
                  {{ t('trophies.dialog.basicInformation', 'Basic Information') }}
                </p>
                <VTextField
                  v-model="localTrophy.name"
                  :label="t('trophies.dialog.trophyTitle', 'Trophy Title')"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                  class="mb-4"
                />
                <VTextarea
                  v-model="localTrophy.description"
                  :label="t('trophies.dialog.description', 'Description')"
                  variant="outlined"
                  density="comfortable"
                  rows="3"
                  auto-grow
                />
              </div>

              <VDivider class="my-6" />

              <!-- Trigger Configuration -->
              <div class="mb-6">
                <p class="text-overline text-primary mb-3">
                  {{ t('trophies.dialog.triggerConfiguration', 'Trigger Configuration') }}
                </p>
                <VSelect
                  v-model="localTrophy.triggerType"
                  :items="triggerTypes"
                  item-title="label"
                  item-value="value"
                  :label="t('trophies.dialog.triggerType', 'Trigger Type')"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                  prepend-inner-icon="tabler-bolt"
                  class="mb-4"
                />
                
                <VTextField
                  v-model="localTrophy.triggerRepeatCount"
                  :label="t('trophies.dialog.numberOfRepeats', 'Number of Repeats')"
                  type="number"
                  min="1"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required, rules.minValue(1)]"
                  :hint="t('trophies.dialog.numberOfRepeatsHint', 'How many times this trigger must occur')"
                  persistent-hint
                  prepend-inner-icon="tabler-repeat"
                  class="mb-4"
                />
                
                <VSelect
                  v-model="localTrophy.courseId"
                  :items="courses"
                  item-title="name"
                  item-value="id"
                  :label="t('trophies.dialog.courseFilter', 'Course Filter (Optional)')"
                  variant="outlined"
                  density="comfortable"
                  clearable
                  :hint="t('trophies.dialog.courseFilterHint', 'Limit this trophy to a specific course')"
                  persistent-hint
                  prepend-inner-icon="tabler-book"
                />
              </div>

              <VDivider class="my-6" />

              <!-- Trophy Properties -->
              <div class="mb-4">
                <p class="text-overline text-primary mb-3">
                  {{ t('trophies.dialog.trophyProperties', 'Trophy Properties') }}
                </p>
                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VSelect
                      v-model="localTrophy.rarity"
                      :items="rarityLevels"
                      item-title="label"
                      item-value="value"
                      :label="t('trophies.dialog.rarity', 'Rarity')"
                      variant="outlined"
                      density="comfortable"
                      :rules="[rules.required]"
                      prepend-inner-icon="tabler-star"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VTextField
                      v-model="localTrophy.points"
                      :label="t('trophies.dialog.pointsValue', 'Points Value')"
                      type="number"
                      min="0"
                      variant="outlined"
                      density="comfortable"
                      :rules="[rules.required, rules.minValue(0)]"
                      prepend-inner-icon="tabler-123"
                    />
                  </VCol>
                </VRow>
                
                <VRow class="mt-2">
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VSwitch
                      v-model="localTrophy.isHidden"
                      color="primary"
                      density="comfortable"
                      hide-details
                      inset
                    >
                      <template #label>
                        <span class="text-body-2">
                          {{ localTrophy.isHidden 
                            ? t('trophies.dialog.hiddenUntilEarned', 'Hidden until earned') 
                            : t('trophies.dialog.visibleToUsers', 'Visible to users') 
                          }}
                        </span>
                      </template>
                    </VSwitch>
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <VSwitch
                      v-model="localTrophy.isActive"
                      color="success"
                      density="comfortable"
                      hide-details
                      inset
                    >
                      <template #label>
                        <span class="text-body-2">
                          {{ localTrophy.isActive 
                            ? t('trophies.dialog.trophyActive', 'Trophy is Active') 
                            : t('trophies.dialog.trophyInactive', 'Trophy is Inactive') 
                          }}
                        </span>
                      </template>
                    </VSwitch>
                  </VCol>
                </VRow>
              </div>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <!-- Enhanced Footer Actions -->
      <VCardActions class="pa-6 pt-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          size="large"
          :disabled="isSubmitting"
          @click="closeDialog"
        >
          {{ t('common.cancel', 'Cancel') }}
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          size="large"
          :loading="isSubmitting"
          :disabled="!isFormValid"
          @click="submitForm"
        >
          <VIcon
            start
            icon="tabler-check"
          />
          {{ props.dialogMode === 'add' 
            ? t('trophies.dialog.createTrophy', 'Create Trophy') 
            : t('trophies.dialog.updateTrophy', 'Update Trophy') 
          }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
