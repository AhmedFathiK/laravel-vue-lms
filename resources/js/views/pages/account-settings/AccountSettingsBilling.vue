<script setup>
import { formatCurrency } from '@/@core/utils/formatters'
import api from '@/utils/api'
import { computed, onMounted, ref } from 'vue'
import ReceiptViewDialog from '@/components/dialogs/ReceiptViewDialog.vue'

const entitlements = ref([])
const receipts = ref([])
const isLoading = ref(false)
const isPricingPlanDialogVisible = ref(false)
const selectedEntitlementForAction = ref(null)

const isReceiptDialogVisible = ref(false)
const selectedReceipt = ref({})

const openReceiptDialog = receipt => {
  selectedReceipt.value = receipt
  isReceiptDialogVisible.value = true
}

const downloadReceipt = async receipt => {
  try {
    const response = await api.get(`/learner/receipts/${receipt.id}/download`, {
      responseType: 'blob',
    })

    // Create a temporary URL for the blob
    const url = window.URL.createObjectURL(new Blob([response]))
    const link = document.createElement('a')

    link.href = url
    link.setAttribute('download', `receipt-${receipt.receiptNumber}.pdf`)
    document.body.appendChild(link)
    link.click()

    // Clean up
    link.parentNode.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Failed to download receipt:', error)
  }
}

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

          <VDataTable
            v-else
            :headers="[
              { title: 'Plan Name', key: 'billingPlan.name' },
              { title: 'Status', key: 'status' },
              { title: 'Included Courses', key: 'courses' },
              { title: 'Start Date', key: 'startsAt' },
              { title: 'Expiry Date', key: 'endsAt' },
              { title: 'Actions', key: 'actions', sortable: false },
            ]"
            :items="entitlements"
            :loading="isLoading"
            no-data-text="No active plans found"
          >
            <template #[`item.billingPlan.name`]="{ item }">
              <div class="d-flex flex-column">
                <span class="text-h6 font-weight-medium">{{ item.billingPlan?.name }}</span>
                <VAlert
                  v-if="isGracePeriod(item)"
                  variant="tonal"
                  color="warning"
                  density="compact"
                  class="mt-1 py-1"
                  style="max-width: 300px;"
                >
                  <template #prepend>
                    <VIcon
                      icon="tabler-alert-triangle"
                      size="16"
                    />
                  </template>
                  <span class="text-caption">Grace Period</span>
                </VAlert>
              </div>
            </template>

            <template #[`item.status`]="{ item }">
              <VChip
                :color="getStatusColor(item.status)"
                label
                size="small"
                class="text-capitalize"
              >
                {{ item.status }}
              </VChip>
            </template>

            <template #[`item.courses`]="{ item }">
              <div class="d-flex flex-wrap gap-1 py-2">
                <VChip
                  v-for="course in item.billingPlan?.courses"
                  :key="course.id"
                  size="x-small"
                  variant="tonal"
                >
                  {{ course.title }}
                </VChip>
              </div>
            </template>

            <template #[`item.startsAt`]="{ item }">
              {{ new Date(item.startsAt).toLocaleDateString() }}
            </template>

            <template #[`item.endsAt`]="{ item }">
              <span v-if="item.endsAt">{{ new Date(item.endsAt).toLocaleDateString() }}</span>
              <span
                v-else
                class="text-disabled"
              >Lifetime Access</span>
            </template>

            <template #[`item.actions`]="{ item }">
              <div class="d-flex gap-2">
                <VBtn
                  v-if="item.status === 'active' || item.status === 'past_due' || item.status === 'expired'"
                  size="small"
                  color="primary"
                  variant="tonal"
                  @click="openPricingDialog(item)"
                >
                  Renew / Upgrade
                </VBtn>
                <VBtn
                  v-if="item.autoRenew"
                  size="small"
                  variant="tonal"
                  color="error"
                  icon
                >
                  <VIcon icon="tabler-calendar-off" />
                  <VTooltip activator="parent">
                    Cancel Auto-renew
                  </VTooltip>
                </VBtn>
              </div>
            </template>
          </VDataTable>
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
              <div class="d-flex gap-2">
                <IconBtn @click="openReceiptDialog(item)">
                  <VIcon icon="tabler-eye" />
                  <VTooltip activator="parent">
                    View Receipt
                  </VTooltip>
                </IconBtn>
                <IconBtn @click="downloadReceipt(item)">
                  <VIcon icon="tabler-download" />
                  <VTooltip activator="parent">
                    Download PDF
                  </VTooltip>
                </IconBtn>
              </div>
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

  <ReceiptViewDialog
    v-model:is-dialog-visible="isReceiptDialogVisible"
    :receipt="selectedReceipt"
    @download="downloadReceipt"
  />
</template>
