<script setup>
import api from '@/utils/api'
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

import { requiredValidator } from '@/@core/utils/validators'

const props = defineProps({
  isDialogOpen: {
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
  availableLevels: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:isDialogOpen', 'submitSuccess'])

const toast = useToast()
const form = ref(null)
const isSubmitting = ref(false)

const localPlan = ref({})

const dialogTitle = computed(() => (props.dialogMode === 'add' ? 'Add New Plan' : 'Edit Plan'))

watch(
  () => props.isDialogOpen,
  newValue => {
    if (newValue) {
      if (props.dialogMode === 'edit' && props.plan) {
        localPlan.value = JSON.parse(JSON.stringify(props.plan))
      } else {
        // Initialize with camelCase keys for a new plan
        localPlan.value = {
          name: '',
          description: '',
          price: 0,
          currency: 'USD',
          billingCycle: 'one-time',
          planType: 'one-time',
          isFree: false,
          durationDays: null,
          isActive: true,
          courseId: props.courseId,
          accessibleLevels: [],
        }
      }
    } else {
      // Reset when dialog closes
      localPlan.value = {}
    }
  },
  { immediate: true },
)

// Watch for plan type changes to enforce business logic
watch(() => localPlan.value.planType, newPlanType => {
  if (newPlanType === 'one-time') {
    localPlan.value.billingCycle = 'one-time'
  }
})

const closeDialog = () => {
  emit('update:isDialogOpen', false)
}

const submitForm = async () => {
  const { valid } = await form.value.validate()
  if (!valid) {
    return
  }

  isSubmitting.value = true

  // If it's a free plan, set price to 0
  if (localPlan.value.isFree || localPlan.value.planType === 'free') {
    localPlan.value.price = 0
    localPlan.value.isFree = true
    localPlan.value.planType = 'free'
  }

  // If plan type is one-time, set billing cycle to one-time
  if (localPlan.value.planType === 'one-time') {
    localPlan.value.billingCycle = 'one-time'
  }

  const payload = localPlan.value

  try {
    if (props.dialogMode === 'add') {
      await api.post(`/admin/courses/${props.courseId}/subscription-plans`, payload)
      toast.success('Subscription plan created successfully')
    } else {
      await api.put(`/admin/courses/${props.courseId}/subscription-plans/${localPlan.value.id}`, payload)
      toast.success('Subscription plan updated successfully')
    }
    emit('submitSuccess')
    closeDialog()
  } catch (error) {
    console.error('Error saving subscription plan:', error)

    const errorMessages = error.response?.data?.errors
    if (errorMessages) {
      Object.values(errorMessages).forEach(msg => toast.error(msg[0]))
    } else {
      toast.error('Failed to save subscription plan')
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="isDialogOpen"
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
          ref="form"
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
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSelect
                  v-model="localPlan.currency"
                  :items="['USD', 'EUR', 'GBP']"
                  label="Currency"
                  :disabled="localPlan.planType === 'free'"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-cash"
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

              <VCol cols="12">
                <div class="d-flex align-center justify-space-between mb-3">
                  <p class="text-body-1 font-weight-medium mb-0">
                    Accessible Levels
                  </p>
                  <VChip
                    size="small"
                    color="primary"
                    variant="tonal"
                  >
                    {{ localPlan.accessibleLevels?.length || 0 }} selected
                  </VChip>
                </div>
              
                <VCard
                  variant="outlined"
                  class="pa-4"
                >
                  <VRow v-if="availableLevels?.length">
                    <VCol
                      v-for="level in availableLevels"
                      :key="level.id"
                      cols="12"
                      sm="6"
                    >
                      <VCheckbox
                        v-model="localPlan.accessibleLevels"
                        :label="level.title"
                        :value="level.id"
                        hide-details
                        density="comfortable"
                        color="primary"
                      />
                    </VCol>
                  </VRow>
                  <div
                    v-else
                    class="text-center text-medium-emphasis py-4"
                  >
                    No levels available
                  </div>
                </VCard>
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
      <VCardText class="d-flex justify-end flex-wrap gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        
        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="submitForm"
        >
          {{ formData.id ? 'Update' : 'Create' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
