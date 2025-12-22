<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'refresh'])

const selectedPlan = ref('standard')

const plansList = [
  {
    desc: 'Standard - $99/month',
    title: 'Standard',
    value: 'standard',
  },
  {
    desc: 'Basic - $0/month',
    title: 'Basic',
    value: 'basic',
  },
  {
    desc: 'Enterprise - $499/month',
    title: 'Enterprise',
    value: 'enterprice',
  },
  {
    desc: 'Company - $999/month',
    title: 'Company',
    value: 'company',
  },
]

const isConfirmDialogVisible = ref(false)

const dialogModelValueUpdate = val => {
  emit('update:isDialogVisible', val)
}

const form = ref({
  planId: selectedPlan.value,
})

watch(selectedPlan, val => {
  form.value.planId = val
})

const { isLoading, validationErrors, onSubmit } = useCrudSubmit({
  form,
  apiEndpoint: computed(() => '/api/learner/subscribe'),
  isUpdate: computed(() => false), // Always a new subscription action? Or update?
  emit,
  successMessage: 'Plan upgraded successfully',
})

const handleCancelSubscription = confirmed => {
  if (confirmed) {
    // TODO: Implement cancel subscription API call
    // axios.post('/api/learner/unsubscribe')
    isConfirmDialogVisible.value = false
    emit('refresh')
  } else {
    isConfirmDialogVisible.value = false
  }
}
</script>

<template>
  <!-- 👉 upgrade plan -->
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 650"
    :model-value="props.isDialogVisible"
    @update:model-value="dialogModelValueUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-2 pa-sm-10">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          Upgrade Plan
        </h4>
        <p class="text-body-1 text-center mb-6">
          Choose the best plan for user.
        </p>

        <div class="d-flex justify-space-between flex-column flex-sm-row gap-4">
          <AppSelect
            v-model="selectedPlan"
            :items="plansList"
            label="Choose a plan"
            placeholder="Basic"
            :error-messages="validationErrors.planId"
          />
          <VBtn
            class="align-self-end"
            :block="$vuetify.display.xs"
            :loading="isLoading"
            @click="onSubmit"
          >
            Upgrade
          </VBtn>
        </div>

        <VDivider class="my-6" />

        <p class="text-body-1 mb-1">
          User current plan is standard plan
        </p>
        <div class="d-flex justify-space-between align-center flex-wrap">
          <div class="d-flex align-center gap-1 me-3">
            <sup class="text-body-1 text-primary">$</sup>
            <h1 class="text-h1 text-primary">
              99
            </h1>
            <sub class="text-body-2 mt-5">
              / month
            </sub>
          </div>
          <VBtn
            color="error"
            variant="tonal"
            @click="isConfirmDialogVisible = true"
          >
            Cancel Subscription
          </VBtn>
        </div>
      </VCardText>

      <!-- 👉 Confirm Dialog -->
      <ConfirmDialog
        v-model:is-dialog-visible="isConfirmDialogVisible"
        cancel-title="Cancelled"
        confirm-title="Unsubscribed!"
        confirm-msg="Your subscription cancelled successfully."
        confirmation-question="Are you sure to cancel your subscription?"
        cancel-msg="Unsubscription Cancelled!!"
        @confirm="handleCancelSubscription"
      />
    </VCard>
  </VDialog>
</template>
