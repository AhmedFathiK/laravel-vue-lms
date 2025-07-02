<template>
  <div>
    <VDialog
      :model-value="isDialogVisible"
      max-width="800px"
      persistent
      @update:model-value="onDialogVisibleUpdate"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="onDialogVisibleUpdate(false)" />

      <!-- Dialog Content -->
      <VCard :title="dialogMode === 'add' ? 'Add New Trophy' : 'Edit Trophy'">
        <VCardText>
          <VForm
            ref="refVForm"
            v-model="valid"
            @submit.prevent="saveTrophy"
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
                    Trophy Icon
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
                        alt="Trophy icon preview"
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
                      label="Choose Icon"
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
                <VCard
                  variant="outlined"
                  class="mb-4"
                >
                  <VCardSubtitle class="pa-4 pb-2">
                    <VIcon class="me-2">
                      mdi-information
                    </VIcon>
                    Basic Information
                  </VCardSubtitle>
                  <VCardText class="pt-2">
                    <VTextField
                      v-model="editedTrophy.name"
                      label="Trophy Title"
                      variant="outlined"
                      density="compact"
                      :rules="[rules.required]"
                      class="mb-4"
                    />
                    <VTextarea
                      v-model="editedTrophy.description"
                      label="Description"
                      variant="outlined"
                      density="compact"
                      rows="3"
                      auto-grow
                    />
                  </VCardText>
                </VCard>

                <!-- Trigger Configuration -->
                <VCard
                  variant="outlined"
                  class="mb-4"
                >
                  <VCardSubtitle class="pa-4 pb-2">
                    <VIcon class="me-2">
                      mdi-cog
                    </VIcon>
                    Trigger Configuration
                  </VCardSubtitle>
                  <VCardText class="pt-2">
                    <VSelect
                      v-model="editedTrophy.trigger_type"
                      :items="triggerTypes"
                      item-title="label"
                      item-value="value"
                      label="Trigger Type"
                      variant="outlined"
                      density="compact"
                      :rules="[rules.required]"
                      class="mb-4"
                    />
                  
                    <VTextField
                      v-model="editedTrophy.trigger_repeat_count"
                      label="Number of Repeats"
                      type="number"
                      min="1"
                      variant="outlined"
                      density="compact"
                      :rules="[rules.required, rules.minValue(1)]"
                      hint="How many times this trigger must occur"
                      persistent-hint
                      class="mb-4"
                    />
                  
                    <VSelect
                      v-model="editedTrophy.course_id"
                      :items="courses"
                      item-title="name"
                      item-value="id"
                      label="Course Filter (Optional)"
                      variant="outlined"
                      density="compact"
                      clearable
                      hint="Limit this trophy to a specific course"
                      persistent-hint
                    />
                  </VCardText>
                </VCard>

                <!-- Trophy Properties -->
                <VCard variant="outlined">
                  <VCardSubtitle class="pa-4 pb-2">
                    <VIcon class="me-2">
                      mdi-star
                    </VIcon>
                    Trophy Properties
                  </VCardSubtitle>
                  <VCardText class="pt-2">
                    <VRow>
                      <VCol
                        cols="12"
                        sm="6"
                      >
                        <VSelect
                          v-model="editedTrophy.rarity"
                          :items="rarityLevels"
                          item-title="label"
                          item-value="value"
                          label="Rarity"
                          variant="outlined"
                          density="compact"
                          :rules="[rules.required]"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        sm="6"
                      >
                        <VTextField
                          v-model="editedTrophy.points"
                          label="Points Value"
                          type="number"
                          min="0"
                          variant="outlined"
                          density="compact"
                          :rules="[rules.required, rules.minValue(0)]"
                        />
                      </VCol>
                    </VRow>
                  
                    <VRow class="mt-2">
                      <VCol
                        cols="12"
                        sm="6"
                      >
                        <VSwitch
                          v-model="editedTrophy.is_hidden"
                          label="Hidden Trophy"
                          hint="Hidden until earned"
                          persistent-hint
                          color="primary"
                          inset
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        sm="6"
                      >
                        <VSwitch
                          v-model="editedTrophy.is_active"
                          label="Active"
                          hint="Trophy can be earned"
                          persistent-hint
                          color="success"
                          inset
                        />
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
            <!-- Dialog Actions -->
            <div class="d-flex justify-end mt-3">
              <VSpacer />
              <VBtn
                variant="outlined"
                color="secondary"
                class="me-3"
                :disabled="isSubmitting"
                @click="onDialogVisibleUpdate(false)"
              >
                Cancel
              </VBtn>
              <VBtn
                color="primary"
                :loading="isSubmitting"
                :disabled="!valid"
                @click="saveTrophy"
              >
                {{ dialogMode === 'add' ? 'Create Trophy' : 'Update Trophy' }}
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import api from '@/utils/api'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { ref, watch, computed } from 'vue'
import { useToast } from 'vue-toastification'
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

const toast = useToast()
const refVForm = ref(null)
const valid = ref(true)
const isSubmitting = ref(false)

// Form data
const editedTrophy = ref({
  id: null,
  name: '',
  description: '',
  icon_url: null,
  trigger_type: 'completed_lesson',
  trigger_repeat_count: 1,
  course_id: null,
  points: 0,
  rarity: 'common',
  is_hidden: false,
  is_active: true,
})

// File upload
const iconFile = ref(null)
const iconPreview = ref(null)

// Data for dropdowns
const triggerTypes = ref([])
const rarityLevels = ref([])
const courses = ref([])

// Form validation rules
const rules = {
  required: value => !!value || 'Required.',
  number: value => !isNaN(Number(value)) || 'Must be a number.',
  minValue: min => value => Number(value) >= min || `Must be at least ${min}.`,
}

// Watch for changes in the trophy prop
watch(
  () => props.trophy,
  newTrophy => {
    if (newTrophy && Object.keys(newTrophy).length > 0) {
      editedTrophy.value = { ...newTrophy }
      iconPreview.value = newTrophy.icon_url
    } else {
      resetForm()
    }
  },
  { deep: true, immediate: true },
)

// Reset form
const resetForm = () => {
  editedTrophy.value = {
    id: null,
    name: '',
    description: '',
    icon_url: null,
    trigger_type: 'completed_lesson',
    trigger_repeat_count: 1,
    course_id: null,
    points: 0,
    rarity: 'common',
    is_hidden: false,
    is_active: true,
  }
  iconFile.value = null
  iconPreview.value = null
  valid.value = true
}

// Handle dialog visibility
const onDialogVisibleUpdate = val => {
  emit('update:isDialogVisible', val)
  if (!val) {
    resetForm()
  }
}

// Preview uploaded icon
const previewIcon = () => {
  if (!iconFile.value) {
    iconPreview.value = null
    
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
    toast.error('Failed to load trigger types')
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
    toast.error('Failed to load rarity levels')
  }
}

// Fetch courses for dropdown
const fetchCourses = async () => {
  try {
    const response = await axios.get('/api/admin/courses')

    courses.value = response.data.data || []
  } catch (error) {
    console.error('Error fetching courses:', error)
    toast.error('Failed to load courses')
  }
}

// Get trigger type label from value
const getTriggerTypeLabel = triggerType => {
  const type = triggerTypes.value.find(t => t.value === triggerType)
  
  return type ? type.label : triggerType
}

// Get color for trigger type chip
const getTriggerTypeColor = triggerType => {
  const colorMap = {
    'completed_lesson': 'green',
    'quiz_score': 'blue',
    'level_completed': 'purple',
    'course_completed': 'indigo',
    'term_mastered': 'cyan',
    'streak': 'amber',
    'custom': 'grey',
  }
  
  return colorMap[triggerType] || 'grey'
}

// Save trophy
const saveTrophy = async () => {
  const { valid: formIsValid } = await refVForm.value.validate()
  if (!formIsValid) return
  
  // Create form data for file upload
  const formData = new FormData()
  
  // Add all trophy fields
  formData.append('name', editedTrophy.value.name)
  formData.append('description', editedTrophy.value.description || '')
  formData.append('trigger_type', editedTrophy.value.trigger_type)
  formData.append('trigger_repeat_count', editedTrophy.value.trigger_repeat_count)
  formData.append('points', editedTrophy.value.points)
  formData.append('rarity', editedTrophy.value.rarity)
  formData.append('is_hidden', editedTrophy.value.is_hidden ? '1' : '0')
  formData.append('is_active', editedTrophy.value.is_active ? '1' : '0')
  
  // Add course_id if selected
  if (editedTrophy.value.course_id) {
    formData.append('course_id', editedTrophy.value.course_id)
  }
  
  // Add icon file if selected
  if (iconFile.value) {
    formData.append('icon', iconFile.value)
  }
  
  try {
    isSubmitting.value = true
    
    if (props.dialogMode === 'edit') {
      // Update
      const response = await axios.post(
        `/api/admin/trophies/${editedTrophy.value.id}?_method=PUT`, 
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        },
      )

      toast.success('Trophy updated successfully')
    } else {
      // Create
      const response = await axios.post(
        '/api/admin/trophies', 
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        },
      )

      toast.success('Trophy created successfully')
    }
    
    emit('trophySaved')
    onDialogVisibleUpdate(false)
  } catch (error) {
    console.error('Error saving trophy:', error)
    toast.error(error.response?.data?.message || 'Failed to save trophy')
  } finally {
    isSubmitting.value = false
  }
}

// Load data on component creation
fetchTriggerTypes()
fetchRarityLevels()
fetchCourses()
</script>
