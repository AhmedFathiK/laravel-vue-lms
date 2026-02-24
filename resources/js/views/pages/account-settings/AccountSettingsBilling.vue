<script setup>
import { ref } from 'vue'

const currentPlan = ref({
  plan: 'Basic',
  status: 'active',
  price: 0,
  benefits: [
    'Access to free courses',
    'Basic support',
  ],
})

const isUpgradePlanDialogVisible = ref(false)
</script>

<template>
  <VRow>
    <!-- 👉 Current Plan -->
    <VCol cols="12">
      <VCard title="Current Plan">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <div class="mb-6">
                <h3 class="text-h3 font-weight-medium mb-1">
                  {{ currentPlan.plan }}
                </h3>
                <p class="text-body-1">
                  A simple start for everyone
                </p>
              </div>

              <div class="mb-6">
                <h4 class="text-h4 font-weight-medium mb-1">
                  Active until Dec 09, 2026
                </h4>
                <p class="text-body-1">
                  We will send you a notification upon Subscription expiration
                </p>
              </div>

              <div>
                <div class="d-flex align-center gap-2 mb-2">
                  <span class="text-h4 font-weight-medium">${{ currentPlan.price }} Per Month</span>
                  <VChip
                    color="primary"
                    label
                    size="small"
                  >
                    Popular
                  </VChip>
                </div>
                <p class="text-body-1 mb-0">
                  Standard plan for small to medium businesses
                </p>
              </div>
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <VAlert
                variant="tonal"
                color="warning"
                class="mb-6"
              >
                <VAlertTitle class="mb-1">
                  We need your attention!
                </VAlertTitle>
                <span>Your plan requires update</span>
              </VAlert>

              <div class="d-flex justify-space-between align-center flex-wrap gap-4">
                <h6 class="text-h6 font-weight-semibold">
                  Days
                </h6>
                <h6 class="text-h6 font-weight-semibold">
                  24 of 30 Days
                </h6>
              </div>

              <VProgressLinear
                model-value="75"
                rounded
                color="primary"
                height="8"
                class="mt-3 mb-2"
              />
              <p class="text-body-2 mb-0">
                6 days remaining until your plan requires update
              </p>
            </VCol>

            <VCol cols="12">
              <div class="d-flex gap-4 flex-wrap">
                <VBtn
                  variant="tonal"
                  @click="isUpgradePlanDialogVisible = true"
                >
                  Upgrade Plan
                </VBtn>

                <VBtn
                  variant="tonal"
                  color="error"
                >
                  Cancel Subscription
                </VBtn>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Payment Methods -->
    <VCol cols="12">
      <VCard title="Payment Methods">
        <VCardText class="d-flex flex-column gap-4">
          <VAlert
            variant="tonal"
            color="primary"
          >
            <template #prepend>
              <VIcon
                icon="tabler-credit-card"
                size="24"
              />
            </template>
            <VAlertTitle class="mb-1">
              No payment methods saved
            </VAlertTitle>
            <span>You have not saved any payment methods yet.</span>
          </VAlert>

          <div>
            <VBtn
              variant="tonal"
              prepend-icon="tabler-plus"
            >
              Add Payment Method
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Billing History -->
    <VCol cols="12">
      <VCard title="Billing History">
        <VCardText>
          <VDataTable
            :headers="[
              { title: 'Invoice ID', key: 'id' },
              { title: 'Date', key: 'issuedDate' },
              { title: 'Amount', key: 'total' },
              { title: 'Status', key: 'status' },
            ]"
            :items="[]"
            no-data-text="No invoices found"
          />
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
