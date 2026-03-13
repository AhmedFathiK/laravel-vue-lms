<script setup>
import safeBoxWithGoldenCoin from '@images/misc/3d-safe-box-with-golden-dollar-coins.png'
import spaceRocket from '@images/misc/3d-space-rocket-with-smoke.png'
import dollarCoinPiggyBank from '@images/avatars/avatar-1.png'
import visaMasterIcon from '@images/icons/payments/visa-master.png'
import walletIcon from '@images/icons/payments/mobile-wallet.png'
import PaymentMethodSelector from '@/components/PaymentMethodSelector.vue'
import api from '@/utils/api'
import { onMounted, ref, watch, computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    required: false,
  },
  courseId: {
    type: [Number, String],
    default: null,
  },
  activeEntitlement: {
    type: Object,
    default: null,
  },
  xs: {
    type: [Number, String],
    required: false,
  },
  sm: {
    type: [Number, String],
    required: false,
  },
  md: {
    type: [Number, String],
    required: false,
  },
  lg: {
    type: [Number, String],
    required: false,
  },
  xl: {
    type: [Number, String],
    required: false,
  },
})

const emit = defineEmits(['loaded'])

const annualMonthlyPlanPriceToggler = ref(false)
const pricingPlans = ref([])
const allFeatures = ref([])
const isLoading = ref(false)
const upgradeCalculations = ref({})

// Payment Methods
const isPaymentMethodDialogVisible = ref(false)
const paymentMethods = ref([])
const selectedPaymentMethod = ref(null)
const selectedPlanForPayment = ref(null)
const isProcessingPayment = ref(false)
const autoRenew = ref(true)

const fetchPlans = async () => {
  if (!props.courseId) return
  isLoading.value = true
  try {
    const response = await api.get(`/learner/courses/${props.courseId}/billing-plans`)

    pricingPlans.value = response.plans || []
    allFeatures.value = response.allFeatures || []
    emit('loaded', pricingPlans.value.length)
  } catch (error) {
    console.error('Failed to fetch plans', error)
  } finally {
    isLoading.value = false
  }
}

const calculateUpgrade = async plan => {
  if (!props.activeEntitlement || props.activeEntitlement.billingPlanId === plan.id) return
  
  try {
    const response = await api.get(`/learner/entitlements/${props.activeEntitlement.id}/upgrade/${plan.id}/calculate`)

    upgradeCalculations.value[plan.id] = response
  } catch (error) {
    console.error('Failed to calculate upgrade', error)
  }
}

const handlePlanAction = async plan => {
  if (props.activeEntitlement?.billingPlanId === plan.id && props.activeEntitlement.status === 'active') {
    return // Block active plan actions
  }

  // If free plan, handle directly
  if (parseFloat(plan.price) === 0) {
    try {
      const response = await api.post('/learner/acquire-entitlement', { planId: plan.id })
      if (response.status === 'enrolled') {
        window.location.reload()
      }
    } catch (error) {
      console.error('Failed to acquire free plan', error)
    }

    return
  }

  // For paid plans, show payment method selection first
  selectedPlanForPayment.value = plan
  
  // Get amount (either full price or upgrade price)
  const amount = upgradeCalculations.value[plan.id]?.upgrade_price ?? plan.price
  
  try {
    const response = await api.get('/payments/methods', {
      params: {
        amount,
        currency: plan.currency || 'EGP',
      },
    })

    if (response.success) {
      paymentMethods.value = response.data
      isPaymentMethodDialogVisible.value = true
      selectedPaymentMethod.value = null // Reset selection
      autoRenew.value = true // Reset auto-renew
    }
  } catch (error) {
    console.error('Failed to fetch payment methods', error)
  }
}

const proceedToCheckout = async () => {
  if (!selectedPlanForPayment.value || !selectedPaymentMethod.value) return

  isProcessingPayment.value = true

  const plan = selectedPlanForPayment.value

  try {
    let response
    if (props.activeEntitlement?.billingPlanId === plan.id) {
      // Renew
      response = await api.post(`/learner/entitlements/${props.activeEntitlement.id}/renew`, {
        paymentMethodId: String(selectedPaymentMethod.value),
        autoRenew: autoRenew.value,
      })
    } else if (props.activeEntitlement) {
      // Upgrade or Downgrade (Both handled by upgrade endpoint logic for pro-rating)
      response = await api.post(`/learner/entitlements/${props.activeEntitlement.id}/upgrade/${plan.id}`, {
        paymentMethodId: String(selectedPaymentMethod.value),
        autoRenew: autoRenew.value,
      })
    } else {
      // New acquisition
      response = await api.post('/payments/checkout', {
        amount: plan.price,
        currency: plan.currency || 'EGP',
        planId: plan.id,
        courseId: props.courseId,
        paymentMethodId: String(selectedPaymentMethod.value),
        autoRenew: autoRenew.value,
      })
    }

    if (response.paymentUrl || (response.success && response.paymentUrl)) {
      window.location.href = response.paymentUrl || response.data?.paymentUrl
    }
  } catch (error) {
    console.error('Checkout failed', error)
  } finally {
    isProcessingPayment.value = false
  }
}

onMounted(fetchPlans)

watch(() => props.courseId, fetchPlans)
watch(pricingPlans, plans => {
  if (props.activeEntitlement) {
    plans.forEach(plan => {
      if (plan.id !== props.activeEntitlement.billingPlanId && parseFloat(plan.price) > 0) {
        calculateUpgrade(plan)
      }
    })
  }
})

const getActionText = plan => {
  if (props.activeEntitlement?.billingPlanId === plan.id) {
    if (props.activeEntitlement.status === 'active') {
      return 'Active'
    }
    
    return 'Renew Plan'
  }
  
  if (!props.activeEntitlement) {
    return parseFloat(plan.price) === 0 ? 'Enroll for Free' : 'Get Started'
  }

  // Check if upgrade or downgrade
  const currentPrice = parseFloat(props.activeEntitlement.billingPlan?.price || 0)
  const newPrice = parseFloat(plan.price)

  if (newPrice > currentPrice) {
    return 'Upgrade Plan'
  }
  
  if (newPrice < currentPrice) {
    return 'Downgrade Plan'
  }
  
  return 'Switch Plan'
}

const getPaymentMethodIcon = method => {
  if (method.type === 'CARD') return visaMasterIcon
  if (method.type === 'WALLET') return walletIcon
  
  return method.image
}

const hasFeature = (plan, featureId) => {
  return plan.features && plan.features.some(f => f.id === featureId)
}
</script>

<template>
  <!-- 👉 Title and subtitle -->
  <div class="text-center">
    <h3 class="text-h3 pricing-title mb-2">
      {{ props.title ? props.title : 'Pricing Plans' }}
    </h3>
    <p class="mb-0">
      All plans include 40+ advanced tools and features to boost your product.
    </p>
    <p class="mb-2">
      Choose the best plan to fit your needs.
    </p>
  </div>

  <!-- 👉 Annual and monthly price toggler -->
  <div
    v-if="pricingPlans.some(p => p.billing_type === 'recurring')"
    class="d-flex font-weight-medium text-body-1 align-center justify-center mx-auto mt-12 mb-6"
  >
    <VLabel
      for="pricing-plan-toggle"
      class="me-3"
    >
      Monthly
    </VLabel>

    <div class="position-relative">
      <VSwitch
        id="pricing-plan-toggle"
        v-model="annualMonthlyPlanPriceToggler"
      >
        <template #label>
          <div class="text-body-1 font-weight-medium">
            Annually
          </div>
        </template>
      </VSwitch>
    </div>
  </div>

  <div
    v-if="isLoading"
    class="d-flex justify-center py-10"
  >
    <VProgressCircular indeterminate />
  </div>

  <!-- SECTION pricing plans -->
  <VRow v-else>
    <VCol
      v-for="plan in pricingPlans"
      :key="plan.id"
      :cols="props.xs || 12"
      :sm="props.sm"
      :md="props.md || 4"
      :lg="props.lg"
      :xl="props.xl"
    >
      <!-- 👉  Card -->
      <VCard
        flat
        border
        class="h-100 d-flex flex-column"
        :class="plan.isPopular ? 'border-primary border-opacity-100' : ''"
      >
        <VCardText
          style="block-size: 3.75rem;"
          class="text-end"
        >
          <!-- 👉 Popular -->
          <VChip
            v-if="plan.isPopular"
            label
            color="primary"
            size="small"
          >
            Popular
          </VChip>
          <VChip
            v-if="activeEntitlement?.billingPlanId === plan.id"
            label
            color="success"
            size="small"
          >
            Current
          </VChip>
        </VCardText>

        <!-- 👉 Plan logo -->
        <VCardText class="flex-grow-1">
          <!-- 👉 Plan name -->
          <h4 class="text-h4 mb-1 text-center">
            {{ plan.name }}
          </h4>
          <p class="mb-0 text-body-1 text-center">
            {{ plan.description }}
          </p>

          <!-- 👉 Plan price  -->
          <div class="position-relative">
            <div class="d-flex justify-center pt-5 pb-10">
              <div class="text-body-1 align-self-start font-weight-medium">
                {{ plan.currency }}
              </div>
              <h1 class="text-h1 font-weight-medium text-primary">
                {{ plan.price }}
              </h1>
              <div
                v-if="plan.billing_type === 'recurring'"
                class="text-body-1 font-weight-medium align-self-end"
              >
                /{{ plan.billing_interval }}
              </div>
            </div>

            <div
              v-if="upgradeCalculations[plan.id]"
              class="text-center mt-n8 mb-4"
            >
              <VChip
                color="info"
                size="small"
                variant="tonal"
              >
                Upgrade for {{ upgradeCalculations[plan.id].upgrade_price }} {{ plan.currency }}
              </VChip>
              <p class="text-caption text-disabled mt-1">
                {{ upgradeCalculations[plan.id].remaining_days }} days remaining credited
              </p>
            </div>
          </div>

          <!-- 👉 Plan features -->
          <VList class="card-list mb-4">
            <VListItem
              v-for="feature in allFeatures"
              :key="feature.id"
            >
              <template #prepend>
                <VIcon
                  :icon="hasFeature(plan, feature.id) ? 'tabler-check' : 'tabler-x'"
                  :color="hasFeature(plan, feature.id) ? 'primary' : 'disabled'"
                  size="20"
                  class="me-2"
                />
              </template>

              <VListItemTitle 
                class="text-body-1"
                :class="!hasFeature(plan, feature.id) && 'text-disabled'"
              >
                {{ feature.name }}
              </VListItemTitle>
            </VListItem>
          </VList>
        </VCardText>

        <VCardText class="pt-0">
          <!-- 👉 Plan actions -->
          <VBtn
            block
            :color="activeEntitlement?.billingPlanId === plan.id ? 'success' : 'primary'"
            :variant="plan.isPopular ? 'elevated' : 'tonal'"
            :disabled="activeEntitlement?.billingPlanId === plan.id && activeEntitlement.status === 'active'"
            @click="handlePlanAction(plan)"
          >
            {{ getActionText(plan) }}
          </VBtn>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
  <!-- !SECTION  -->

  <!-- Payment Method Selection Dialog -->
  <VDialog
    v-model="isPaymentMethodDialogVisible"
    max-width="600"
  >
    <VCard>
      <VCardTitle class="pa-4">
        <div class="d-flex align-center justify-space-between">
          <h3 class="text-h5 font-weight-bold">
            Select Payment Method
          </h3>
          <VBtn
            icon
            variant="text"
            @click="isPaymentMethodDialogVisible = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4">
        <p class="text-body-1 mb-4">
          Choose how you'd like to pay for <strong>{{ selectedPlanForPayment?.name }}</strong>.
        </p>

        <VCard
          variant="outlined"
          class="pa-4 mb-6"
        >
          <PaymentMethodSelector
            v-model="selectedPaymentMethod"
            v-model:auto-renew="autoRenew"
            :payment-methods="paymentMethods"
          />
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          variant="outlined"
          color="secondary"
          @click="isPaymentMethodDialogVisible = false"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          variant="elevated"
          :disabled="!selectedPaymentMethod"
          :loading="isProcessingPayment"
          @click="proceedToCheckout"
        >
          Proceed to Pay
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 1rem;
}

.save-upto-chip {
  inset-block-start: -2.4rem;
  inset-inline-end: -6rem;
}

.annual-price-text {
  inset-block-end: 3%;
  inset-inline-start: 50%;
  transform: translateX(-50%);
}
</style>
