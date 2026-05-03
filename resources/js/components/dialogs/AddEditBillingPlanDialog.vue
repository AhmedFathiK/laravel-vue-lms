<script setup>
import { requiredValidator } from '@/@core/utils/validators'
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import api from '@/utils/api'
import { computed, nextTick, onMounted, ref, watch } from 'vue'

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
    default: null,
  },
  courseId: {
    type: [Number, String],
    required: false,
    default: null,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'submitSuccess'])
const defaultCurrency = import.meta.env.VITE_DEFAULT_CURRENCY || 'EGP'

const currencyOptions = computed(() => {
  const raw = import.meta.env.VITE_SUPPORTED_CURRENCIES || defaultCurrency
  
  return raw
    .split(',')
    .map(c => c.trim().toUpperCase())
    .filter(Boolean)
})

const formRef = ref(null)
const featuresList = ref([])
const coursesList = ref([])
const loadingFeatures = ref(false)
const loadingCourses = ref(false)

const defaultForm = () => ({
  name: '',
  description: '',
  price: 0,
  currency: defaultCurrency,
  billingType: 'recurring', // recurring, one_time, free
  billingInterval: 'month', // day, week, month, year
  accessType: 'while_active', // lifetime, limited, while_active
  accessDurationDays: null,
  isActive: true,
  features: [],
  courseIds: props.courseId ? [props.courseId] : [],
})

const localPlan = ref(defaultForm())

const dialogTitle = computed(() => (props.dialogMode === 'add' ? 'Add New Plan' : 'Edit Plan'))

const fetchFeatures = async () => {
  loadingFeatures.value = true
  try {
    const response = await api.get('/admin/features')

    featuresList.value = response
  } catch (error) {
    console.error('Failed to fetch features', error)
  } finally {
    loadingFeatures.value = false
  }
}

const fetchCourses = async () => {
  // Always fetch courses to ensure we have the list for the dialog
  // if (props.courseId) return 
  loadingCourses.value = true
  try {
    const response = await api.get('/admin/courses/select-fields')

    coursesList.value = response
  } catch (error) {
    console.error('Failed to fetch courses', error)
  } finally {
    loadingCourses.value = false
  }
}

watch(
  () => props.isDialogVisible,
  async newValue => {
    if (newValue) {
      // Fetch data first
      await Promise.all([fetchFeatures(), fetchCourses()])

      if (props.dialogMode === 'edit' && props.data) {
        // Deep copy and map if necessary (middleware handles camelCase)
        const data = JSON.parse(JSON.stringify(props.data))
        
        localPlan.value = {
          ...defaultForm(),
          ...data,

          // Ensure features are mapped to IDs if they come as objects
          features: data.features ? data.features.map(f => f.id || f) : [],

          // Ensure courses are mapped to IDs
          courseIds: data.courses ? data.courses.map(c => c.id || c) : (data.course ? [data.course.id || data.course] : []),
        }
      } else {
        localPlan.value = defaultForm()
      }

      nextTick(() => {
        formRef.value?.resetValidation()
      })
    } else {
      localPlan.value = defaultForm()
    }
  },
  { immediate: true },
)

// Watch for billing type changes to enforce business logic
watch(() => localPlan.value.billingType, newType => {
  if (newType === 'one_time') {
    localPlan.value.billingInterval = null
    if (localPlan.value.accessType === 'while_active') {
      localPlan.value.accessType = 'lifetime'
    }
  }
  if (newType === 'free') {
    localPlan.value.price = 0
    localPlan.value.billingInterval = null
    if (localPlan.value.accessType === 'while_active') {
      localPlan.value.accessType = 'lifetime'
    }
  }
  if (newType === 'recurring') {
    if (!localPlan.value.billingInterval) {
      localPlan.value.billingInterval = 'month'
    }
    localPlan.value.accessType = 'while_active'
  }
})

// Watch for access type changes
watch(() => localPlan.value.accessType, newType => {
  if (newType !== 'limited') {
    localPlan.value.accessDurationDays = null
  } else if (!localPlan.value.accessDurationDays) {
    localPlan.value.accessDurationDays = 30
  }
})

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}

const { isLoading: isSubmitting, validationErrors, onSubmit: submitForm } = useCrudSubmit({
  formRef,
  form: localPlan,
  apiEndpoint: computed(() => {
    if (props.dialogMode === 'add') {
      return `/admin/billing-plans`
    }
    
    return `/admin/billing-plans/${localPlan.value.id}`
  }),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  isFormData: false, // JSON payload
  emit: (event, ...args) => {
    if (event === 'saved') {
      emit('submitSuccess', ...args)
    } else {
      emit(event, ...args)
    }
  },
  successMessage: computed(() => props.dialogMode === 'add' 
    ? 'Billing plan created successfully' 
    : 'Billing plan updated successfully'),
})
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
      <VCardTitle class="text-h5 font-weight-bold pa-6 pb-4">
        {{ dialogTitle }}
      </VCardTitle>
    
      <VDivider />

      <VCardText class="pa-6">
        <VForm
          ref="formRef"
          @submit.prevent="submitForm"
        >
          <!-- Section 1: Basic Information -->
          <div class="mb-6">
            <p class="text-overline text-primary mb-3">
              Basic Information
            </p>
            <VRow>
              <VCol
                v-if="!props.courseId"
                cols="12"
              >
                <AppAutocomplete
                  v-model="localPlan.courseIds"
                  :items="coursesList"
                  item-title="title"
                  item-value="id"
                  label="Select Courses"
                  placeholder="Select courses"
                  multiple
                  chips
                  closable-chips
                  :loading="loadingCourses"
                  :rules="[requiredValidator]"
                  :error-messages="validationErrors.courseIds"
                />
              </VCol>

              <VCol cols="12">
                <VTextField
                  v-model="localPlan.name"
                  label="Plan Name"
                  placeholder="e.g., Premium Monthly"
                  :rules="[requiredValidator]"
                  variant="outlined"
                  density="comfortable"
                  :error-messages="validationErrors.name"
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  v-model="localPlan.description"
                  label="Description"
                  placeholder="Describe what this plan includes..."
                  rows="3"
                  variant="outlined"
                  density="comfortable"
                  :error-messages="validationErrors.description"
                />
              </VCol>
            </VRow>
          </div>

          <VDivider class="my-6" />

          <!-- Section 2: Pricing & Billing -->
          <div class="mb-6">
            <p class="text-overline text-primary mb-3">
              Pricing & Billing
            </p>
            <VRow>
              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="localPlan.billingType"
                  :items="[
                    { title: 'One-time Payment', value: 'one_time' },
                    { title: 'Recurring Plan', value: 'recurring' },
                    { title: 'Free Access', value: 'free' },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Plan Type"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-tag-outline"
                  :error-messages="validationErrors.billingType"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="localPlan.billingInterval"
                  :items="[
                    { title: 'Daily', value: 'day' },
                    { title: 'Weekly', value: 'week' },
                    { title: 'Monthly', value: 'month' },
                    { title: 'Yearly', value: 'year' },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Billing Cycle"
                  :disabled="localPlan.billingType !== 'recurring'"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-sync"
                  :error-messages="validationErrors.billingInterval"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="localPlan.accessType"
                  :items="[
                    { title: 'Lifetime Access', value: 'lifetime' },
                    { title: 'Limited Time', value: 'limited' },
                    { title: 'While Active', value: 'while_active' },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Access Type"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-account-lock-open-outline"
                  :error-messages="validationErrors.accessType"
                  :disabled="localPlan.billingType === 'recurring'"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model.number="localPlan.accessDurationDays"
                  label="Duration (days)"
                  type="number"
                  min="1"
                  placeholder="Days of access"
                  :disabled="localPlan.accessType !== 'limited'"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-clock-outline"
                  :error-messages="validationErrors.accessDurationDays"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model.number="localPlan.price"
                  label="Price"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="0.00"
                  :disabled="localPlan.billingType === 'free'"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-currency-usd"
                  :error-messages="validationErrors.price"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="localPlan.currency"
                  :items="currencyOptions"
                  label="Currency"
                  :disabled="localPlan.billingType === 'free'"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-cash"
                  :error-messages="validationErrors.currency"
                />
              </VCol>
            </VRow>
          </div>

          <VDivider class="my-6" />

          <!-- Section 3: Features & Access -->
          <div class="mb-4">
            <p class="text-overline text-primary mb-3">
              Features & Access
            </p>
          
            <VRow>
              <VCol cols="12">
                {{ localPlan.features }}
                <AppAutocomplete
                  v-model="localPlan.features"
                  :items="featuresList"
                  item-title="name"
                  item-value="id"
                  label="Included Features"
                  multiple
                  chips
                  closable-chips
                  placeholder="Select features"
                  :loading="loadingFeatures"
                  :error-messages="validationErrors.features"
                />
              </VCol>

              <VCol cols="12">
                <VSwitch
                  v-model="localPlan.isActive"
                  label="Active Status"
                  color="success"
                  density="comfortable"
                  hide-details
                  inset
                >
                  <template #label>
                    <span class="text-body-1">
                      {{ localPlan.isActive ? 'Plan is Active' : 'Plan is Inactive' }}
                    </span>
                  </template>
                </VSwitch>
              </VCol>
            </VRow>
          </div>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6 pt-4">
        <VSpacer />
        <VBtn
          variant="elevated"
          color="secondary"
          :disabled="isSubmitting"
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          :loading="isSubmitting"
          @click="submitForm"
        >
          {{ props.dialogMode === 'edit' ? 'Update' : 'Create' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
