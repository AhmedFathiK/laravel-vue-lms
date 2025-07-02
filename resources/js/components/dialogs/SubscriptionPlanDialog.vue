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
const isLoading = ref(false)

const localPlan = ref({})

const dialogTitle = computed(() => (props.dialogMode === 'add' ? 'Add New Plan' : 'Edit Plan'))

watch(
  () => props.isDialogOpen,
  newValue => {
    if (newValue) {
      if (props.dialogMode === 'edit' && props.plan) {
        // Convert snake_case from parent to camelCase for local state
        localPlan.value = {
          id: props.plan.id,
          name: props.plan.name,
          description: props.plan.description,
          price: props.plan.price,
          currency: props.plan.currency,
          billingCycle: props.plan['billing_cycle'],
          planType: props.plan['plan_type'],
          isFree: props.plan['is_free'],
          durationDays: props.plan['duration_days'],
          isActive: props.plan['is_active'],
          courseId: props.plan['course_id'],
          accessibleLevels: props.plan['accessible_levels'] || [],
        }
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

const savePlan = async () => {
  const { valid } = await form.value.validate()
  if (!valid) {
    return
  }

  isLoading.value = true

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

  // Convert camelCase back to snake_case for the API request
  const payload = {
    name: localPlan.value.name,
    description: localPlan.value.description,
    price: localPlan.value.price,
    currency: localPlan.value.currency,
    'billing_cycle': localPlan.value.billingCycle,
    'plan_type': localPlan.value.planType,
    'is_free': localPlan.value.isFree,
    'duration_days': localPlan.value.durationDays,
    'is_active': localPlan.value.isActive,
    'course_id': localPlan.value.courseId,
    'accessible_levels': localPlan.value.accessibleLevels,
  }

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
    isLoading.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="isDialogOpen"
    max-width="700px"
    @update:model-value="closeDialog"
  >
    <DialogCloseBtn @click="closeDialog" />
    <VCard :title="dialogTitle">
      <VCardText>
        <VForm
          ref="form"
          @submit.prevent="savePlan"
        >
          <VRow>
            <VCol cols="12">
              <VTextField
                v-model="localPlan.name"
                label="Plan Name"
                :rules="[requiredValidator]"
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="localPlan.description"
                label="Description"
                rows="3"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
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
                required
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
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
                required
              />
            </VCol>

            <VCol
              cols="6"
              md="4"
            >
              <VTextField
                v-model.number="localPlan.price"
                label="Price"
                type="number"
                min="0"
                step="0.01"
                :disabled="localPlan.planType === 'free'"
                required
              />
            </VCol>

            <VCol
              cols="6"
              md="4"
            >
              <VSelect
                v-model="localPlan.currency"
                :items="['USD', 'EUR', 'GBP']"
                label="Currency"
                :disabled="localPlan.planType === 'free'"
                required
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model.number="localPlan.durationDays"
                label="Duration (days)"
                type="number"
                min="1"
                :disabled="localPlan.billingCycle !== 'one-time'"
                hint="Leave empty for unlimited access"
                persistent-hint
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="localPlan.isActive"
                label="Active"
                color="success"
              />
            </VCol>

            <VCol cols="12">
              <p class="text-subtitle-1 mb-2">
                Accessible Levels
              </p>
              <div class="d-flex flex-column gap-2">
                <VCheckbox
                  v-for="level in availableLevels"
                  :key="level.id"
                  v-model="localPlan.accessibleLevels"
                  :label="level.title"
                  :value="level.id"
                  hide-details
                  density="compact"
                />
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
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          :loading="isLoading"
          @click="savePlan"
        >
          Save
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
