<script setup>
import { formatCurrency } from '@/@core/utils/formatters'
import api from '@/utils/api'
import { computed, onMounted, ref } from 'vue'

const entitlements = ref([])
const receipts = ref([])
const isLoading = ref(false)
const isPricingPlanDialogVisible = ref(false)
const selectedEntitlementForAction = ref(null)

const fetchBillingData = async () => {
  try {
    isLoading.value = true
    
    const [entitlementsRes, receiptsRes] = await Promise.all([
      api.get('/learner/entitlements'),
      api.get('/learner/receipts'),
    ])

    entitlements.value = entitlementsRes
    receipts.value = receiptsRes.data || receiptsRes // Handle paginated response
  } catch (error) {
    console.error('Failed to fetch billing data:', error)
  } finally {
    isLoading.value = false
  }
}

const openPricingDialog = entitlement => {
  selectedEntitlementForAction.value = entitlement
  isPricingPlanDialogVisible.value = true
}

const isGracePeriod = entitlement => {
  return entitlement.isGracePeriod
}

const getStatusColor = status => {
  if (status === 'active') return 'success'
  if (status === 'expired') return 'secondary'
  if (status === 'canceled') return 'error'
  if (status === 'past_due') return 'warning'
  
  return 'primary'
}

onMounted(fetchBillingData)
</script>

<template>
  <VRow>
    <!-- 👉 Current Plans / Entitlements -->
    <VCol cols="12">
      <VCard title="My Plans & Subscriptions">
        <VCardText>
          <div
            v-if="isLoading"
            class="d-flex justify-center py-4"
          >
            <VProgressCircular indeterminate />
          </div>

          <div
            v-else-if="entitlements.length === 0"
            class="text-center py-6"
          >
            <VIcon
              icon="tabler-subscription"
              size="48"
              class="mb-2 text-disabled"
            />
            <p class="text-body-1 text-disabled">
              You don't have any active plans.
            </p>
            <VBtn
              to="/pricing"
              variant="tonal"
            >
              Browse Plans
            </VBtn>
          </div>

          <VRow v-else>
            <VCol
              v-for="entitlement in entitlements"
              :key="entitlement.id"
              cols="12"
              md="6"
            >
              <VCard
                variant="outlined"
                class="h-100"
              >
                <VCardText>
                  <div class="d-flex justify-space-between align-center mb-4">
                    <h4 class="text-h4 font-weight-medium">
                      {{ entitlement.billingPlan?.name }}
                    </h4>
                    <VChip
                      :color="getStatusColor(entitlement.status)"
                      label
                      size="small"
                      class="text-capitalize"
                    >
                      {{ entitlement.status }}
                    </VChip>
                  </div>

                  <VAlert
                    v-if="isGracePeriod(entitlement)"
                    variant="tonal"
                    color="warning"
                    class="mb-4"
                    density="compact"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-alert-triangle" />
                    </template>
                    Your plan has expired but you are currently in a grace period. Please renew to keep access.
                  </VAlert>

                  <div class="mb-4">
                    <div class="d-flex align-center gap-2 mb-1">
                      <VIcon
                        icon="tabler-calendar-event"
                        size="18"
                        class="text-disabled"
                      />
                      <span class="text-body-1">
                        Started: {{ new Date(entitlement.startsAt).toLocaleDateString() }}
                      </span>
                    </div>
                    <div
                      v-if="entitlement.endsAt"
                      class="d-flex align-center gap-2"
                    >
                      <VIcon
                        icon="tabler-calendar-off"
                        size="18"
                        class="text-disabled"
                      />
                      <span class="text-body-1">
                        Expires: {{ new Date(entitlement.endsAt).toLocaleDateString() }}
                      </span>
                    </div>
                    <div
                      v-else
                      class="d-flex align-center gap-2"
                    >
                      <VIcon
                        icon="tabler-infinity"
                        size="18"
                        class="text-disabled"
                      />
                      <span class="text-body-1">Lifetime Access</span>
                    </div>
                  </div>

                  <div
                    v-if="entitlement.billingPlan?.courses?.length"
                    class="mb-4"
                  >
                    <p class="text-subtitle-2 mb-1">
                      Included Courses:
                    </p>
                    <div class="d-flex flex-wrap gap-1">
                      <VChip
                        v-for="course in entitlement.billingPlan.courses"
                        :key="course.id"
                        size="x-small"
                        variant="tonal"
                      >
                        {{ course.title }}
                      </VChip>
                    </div>
                  </div>

                  <div class="d-flex gap-2">
                    <VBtn
                      v-if="entitlement.status === 'active' || entitlement.status === 'past_due' || entitlement.status === 'expired'"
                      size="small"
                      color="primary"
                      @click="openPricingDialog(entitlement)"
                    >
                      Renew / Upgrade
                    </VBtn>
                    <VBtn
                      v-if="entitlement.autoRenew"
                      size="small"
                      variant="tonal"
                      color="error"
                    >
                      Cancel Auto-renew
                    </VBtn>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Billing History -->
    <VCol cols="12">
      <VCard title="Billing History">
        <VCardText>
          <VDataTable
            :headers="[
              { title: 'Receipt #', key: 'receiptNumber' },
              { title: 'Date', key: 'createdAt' },
              { title: 'Item', key: 'itemName' },
              { title: 'Amount', key: 'amount' },
              { title: 'Status', key: 'payment.status' },
              { title: 'Actions', key: 'actions', sortable: false },
            ]"
            :items="receipts"
            :loading="isLoading"
            no-data-text="No invoices found"
          >
            <template #[`item.createdAt`]="{ item }">
              {{ new Date(item.createdAt).toLocaleDateString() }}
            </template>

            <template #[`item.amount`]="{ item }">
              {{ formatCurrency(item.amount, item.currency) }}
            </template>

            <template #[`item.payment.status`]="{ item }">
              <VChip
                :color="item.payment?.status === 'paid' ? 'success' : 'warning'"
                label
                size="x-small"
                class="text-capitalize"
              >
                {{ item.payment?.status || 'pending' }}
              </VChip>
            </template>

            <template #[`item.actions`]="{ item }">
              <IconBtn
                :href="`/api/learner/receipts/${item.id}/download`"
                target="_blank"
                rel="noopener noreferrer"
              >
                <VIcon icon="tabler-download" />
              </IconBtn>
            </template>
          </VDataTable>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <PricingPlanDialog
    v-model:is-dialog-visible="isPricingPlanDialogVisible"
    :course-id="selectedEntitlementForAction?.course_id"
    :active-entitlement="selectedEntitlementForAction"
  />
</template>
