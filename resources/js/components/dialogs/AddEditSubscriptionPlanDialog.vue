<script setup>
import { requiredValidator } from '@/@core/utils/validators'
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import { computed, nextTick, ref, watch } from 'vue'

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
  plan: {
    type: Object,
    default: null,
  },
  courseId: {
    type: Number,
    required: true,
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

const defaultForm = () => ({
  name: '',
  description: '',
  price: 0,
  currency: defaultCurrency,
  billingCycle: 'one-time',
  planType: 'one-time',
  isFree: false,
  durationDays: null,
  isActive: true,
  courseId: props.courseId,
})

const localPlan = ref(defaultForm())

const dialogTitle = computed(() => (props.dialogMode === 'add' ? 'Add New Plan' : 'Edit Plan'))

watch(
  () => props.isDialogVisible,
  newValue => {
    if (newValue) {
      if (props.dialogMode === 'edit' && props.plan) {
        localPlan.value = JSON.parse(JSON.stringify(props.plan))
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

// Watch for plan type changes to enforce business logic
watch(() => localPlan.value.planType, newPlanType => {
  if (newPlanType === 'one-time') {
    localPlan.value.billingCycle = 'one-time'
  }
  if (newPlanType === 'free') {
    localPlan.value.price = 0
    localPlan.value.isFree = true
  }
})

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}

const { isLoading: isSubmitting, validationErrors, onSubmit: submitForm } = useCrudSubmit({
  formRef,
  form: localPlan,
  apiEndpoint: computed(() => props.dialogMode === 'add'
    ? `/admin/courses/${props.courseId}/subscription-plans`
    : `/admin/courses/${props.courseId}/subscription-plans/${localPlan.value.id}`),
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
    ? 'Subscription plan created successfully' 
    : 'Subscription plan updated successfully'),
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
      <!-- Enhanced Header with better visual hierarchy -->
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
              <VCol cols="12">
                <VSelect
                  v-model="localPlan.planType"
                  :items="[
                    { title: 'One-time Payment', value: 'one-time' },
                    { title: 'Recurring Subscription', value: 'recurring' },
                    { title: 'Free Access', value: 'free' },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Plan Type"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-tag-outline"
                  :error-messages="validationErrors.planType"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="localPlan.billingCycle"
                  :items="[
                    { title: 'One-time', value: 'one-time' },
                    { title: 'Monthly', value: 'monthly' },
                    { title: 'Yearly', value: 'yearly' },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Billing Cycle"
                  :disabled="localPlan.planType !== 'recurring'"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-sync"
                  :error-messages="validationErrors.billingCycle"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model.number="localPlan.durationDays"
                  label="Duration (days)"
                  type="number"
                  min="1"
                  placeholder="Leave empty for unlimited"
                  :disabled="localPlan.billingCycle !== 'one-time'"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-clock-outline"
                  :error-messages="validationErrors.durationDays"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VTextField
                  v-model.number="localPlan.price"
                  label="Price"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="0.00"
                  :disabled="localPlan.planType === 'free'"
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
                  :disabled="localPlan.planType === 'free'"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-cash"
                  :error-messages="validationErrors.currency"
                />
              </VCol>
            </VRow>
          </div>

          <VDivider class="my-6" />

          <!-- Section 3: Access & Permissions -->
          <div class="mb-4">
            <p class="text-overline text-primary mb-3">
              Access & Permissions
            </p>
          
            <VRow>
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

      <!-- Enhanced Footer Actions -->
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
          {{ props.plan ? 'Update' : 'Create' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
