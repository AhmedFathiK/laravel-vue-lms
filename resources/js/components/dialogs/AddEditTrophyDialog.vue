
<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import api from '@/utils/api'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { integerValidator, requiredValidator } from '@core/utils/validators'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
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
})

const emit = defineEmits(['update:isDialogVisible', 'saved'])

const { t } = useI18n()
const toast = useToast()
const refForm = ref(null)

// Data for dropdowns
const triggerTypes = ref([])
const rarityLevels = ref([])
const courses = ref([])

// File upload
const iconFile = ref(null)
const iconPreview = ref(null)

const defaultForm = () => ({
  name: '',
  description: '',
  triggerType: 'completedLesson',
  triggerRepeatCount: 1,
  courseId: null,
  points: 0,
  rarity: 'common',
  isHidden: false,
  isActive: true,
  iconUrl: null, // Keep existing URL if any
})

const form = ref(defaultForm())

// Fetch helper data
const fetchTriggerTypes = async () => {
  try {
    const response = await api.get('/admin/trophies/trigger-types')

    triggerTypes.value = Object.entries(response).map(([value, label]) => ({ value, label }))
  } catch (error) {
    console.error('Error fetching trigger types:', error)
    toast.error('Failed to load trigger types')
  }
}

const fetchRarityLevels = async () => {
  try {
    const response = await api.get('/admin/trophies/rarity-levels')

    rarityLevels.value = Object.entries(response).map(([value, label]) => ({ value, label }))
  } catch (error) {
    console.error('Error fetching rarity levels:', error)
    toast.error('Failed to load rarity levels')
  }
}

const fetchCourses = async () => {
  try {
    const response = await api.get('/admin/courses')

    courses.value = response.data || []
  } catch (error) {
    console.error('Error fetching courses:', error)
    toast.error('Failed to load courses')
  }
}

onMounted(() => {
  fetchTriggerTypes()
  fetchRarityLevels()
  fetchCourses()
})

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.data) {
      form.value = {
        ...defaultForm(),
        ...props.data,
      }
      iconPreview.value = props.data.iconUrl
    } else {
      form.value = defaultForm()
      iconPreview.value = null
    }
    
    iconFile.value = null

    nextTick(() => {
      refForm.value?.resetValidation()
    })
  }
})

// Preview uploaded icon
const previewIcon = () => {
  if (!iconFile.value) {
    iconPreview.value = form.value.iconUrl || null
    
    return
  }
  
  const reader = new FileReader()

  reader.onload = e => {
    iconPreview.value = e.target.result
  }
  reader.readAsDataURL(iconFile.value)
}

watch(iconFile, () => {
  previewIcon()
})

// Extra data for file upload
const extraData = computed(() => {
  const data = {}
  if (iconFile.value) data.icon = iconFile.value
  
  return data
})

const { isLoading, validationErrors, onSubmit } = useCrudSubmit({
  formRef: refForm,
  form: form,
  apiEndpoint: computed(() => props.dialogMode === 'edit'
    ? `/admin/trophies/${props.data.id}` 
    : '/admin/trophies'),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  extraData: extraData,
  isFormData: true, // Always use FormData for file upload compatibility
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

    <VCard :title="props.dialogMode === 'edit' ? 'Edit Trophy' : 'Add New Trophy'">
      <VCardText>
        <VForm
          ref="refForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.name"
                label="Name"
                :rules="[requiredValidator]"
                placeholder="Enter trophy name"
                :error-messages="validationErrors.name"
              />
            </VCol>

            <!-- Points -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.points"
                label="Points"
                type="number"
                :rules="[requiredValidator, integerValidator]"
                placeholder="Enter points"
                :error-messages="validationErrors.points"
              />
            </VCol>

            <!-- Description -->
            <VCol cols="12">
              <AppTextarea
                v-model="form.description"
                label="Description"
                rows="2"
                placeholder="Enter trophy description"
                :error-messages="validationErrors.description"
              />
            </VCol>

            <!-- Trigger Type -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.triggerType"
                :items="triggerTypes"
                item-title="label"
                item-value="value"
                label="Trigger Type"
                :rules="[requiredValidator]"
                placeholder="Select trigger type"
                :error-messages="validationErrors.triggerType"
              />
            </VCol>

            <!-- Trigger Repeat Count -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.triggerRepeatCount"
                label="Trigger Repeat Count"
                type="number"
                :rules="[requiredValidator, integerValidator]"
                placeholder="Enter repeat count"
                :error-messages="validationErrors.triggerRepeatCount"
              />
            </VCol>

            <!-- Course -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.courseId"
                :items="courses"
                item-title="title"
                item-value="id"
                label="Course (Optional)"
                placeholder="Select course"
                clearable
                :error-messages="validationErrors.courseId"
              />
            </VCol>

            <!-- Rarity -->
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.rarity"
                :items="rarityLevels"
                item-title="label"
                item-value="value"
                label="Rarity"
                :rules="[requiredValidator]"
                placeholder="Select rarity"
                :error-messages="validationErrors.rarity"
              />
            </VCol>

            <!-- Flags -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="form.isActive"
                label="Active"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="form.isHidden"
                label="Hidden"
              />
            </VCol>

            <!-- Icon Upload Placeholder (Simple File Input) -->
            <!-- In a full implementation, we'd use VFileInput here binding to iconFile -->
            <VCol cols="12">
              <VFileInput
                v-model="iconFile"
                label="Icon"
                accept="image/*"
                placeholder="Upload trophy icon"
                prepend-icon="tabler-camera"
                :error-messages="validationErrors.icon"
              />
              <div
                v-if="iconPreview"
                class="mt-4"
              >
                <VImg
                  :src="iconPreview"
                  max-width="100"
                  max-height="100"
                  class="rounded"
                />
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
