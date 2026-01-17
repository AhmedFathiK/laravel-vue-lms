<script setup>
import { formatCurrency, formatDate } from '@/@core/utils/formatters'
import { computed } from 'vue'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  receipt: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'download', 'resend'])

const resolvePaymentMethodVariant = method => {
  method = method?.toLowerCase() || ''
  if (method.includes('credit_card')) return { color: 'success', icon: 'tabler-credit-card' }
  if (method.includes('paypal')) return { color: 'info', icon: 'tabler-brand-paypal' }
  if (method.includes('bank_transfer')) return { color: 'warning', icon: 'tabler-building-bank' }
  if (method.includes('manual')) return { color: 'primary', icon: 'tabler-cash' }
  
  return { color: 'secondary', icon: 'tabler-cash' }
}

const resolveItemTypeVariant = type => {
  type = type?.toLowerCase() || ''
  if (type.includes('course') || type.includes('billing_plan')) return { color: 'primary', icon: 'tabler-book' }
  
  return { color: 'secondary', icon: 'tabler-file' }
}

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: val => emit('update:isDialogVisible', val),
})

const downloadReceipt = () => {
  emit('download', props.receipt)
}

const resendReceipt = () => {
  emit('resend', props.receipt)
}
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="600"
  >
    <DialogCloseBtn @click="dialogVisible = false" />
    <VCard
      v-if="receipt"
      class="receipt-dialog"
    >
      <VCardText class="pa-0">
        <div class="receipt-a5-container">
          <div class="receipt-header pa-6">
            <div class="d-flex justify-space-between align-center">
              <div class="d-flex align-center">
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="50"
                  class="me-4"
                >
                  <VIcon
                    icon="tabler-receipt"
                    size="32"
                  />
                </VAvatar>
                <div>
                  <h5 class="text-h5">
                    Receipt
                  </h5>
                  <p class="text-body-2 mb-0">
                    #{{ receipt.receiptNumber }}
                  </p>
                </div>
              </div>
              <div class="text-end">
                <p class="text-body-2 mb-0">
                  <strong>Date:</strong> {{ formatDate(receipt.createdAt) }}
                </p>
              </div>
            </div>
          </div>

          <VDivider />

          <div class="receipt-body pa-6">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <h6 class="text-h6 mb-4">
                  Billed To
                </h6>
                <VList
                  v-if="receipt.user"
                  density="compact"
                  class="card-list"
                >
                  <VListItem>
                    <template #prepend>
                      <VIcon
                        icon="tabler-user"
                        size="20"
                        class="me-2"
                      />
                    </template>
                    <VListItemTitle>{{ receipt.user.fullName }}</VListItemTitle>
                  </VListItem>
                  <VListItem>
                    <template #prepend>
                      <VIcon
                        icon="tabler-mail"
                        size="20"
                        class="me-2"
                      />
                    </template>
                    <VListItemTitle>{{ receipt.user.email }}</VListItemTitle>
                  </VListItem>
                  <VListItem v-if="receipt.user.phoneNumber">
                    <template #prepend>
                      <VIcon
                        icon="tabler-phone"
                        size="20"
                        class="me-2"
                      />
                    </template>
                    <VListItemTitle>{{ receipt.user.phoneNumber }}</VListItemTitle>
                  </VListItem>
                </VList>
              </VCol>
              <VCol
                cols="12"
                md="6"
                class="text-md-end"
              >
                <h6 class="text-h6 mb-4">
                  Payment Details
                </h6>
                <VList
                  v-if="receipt.payment"
                  density="compact"
                  class="card-list"
                >
                  <VListItem>
                    <VListItemTitle>
                      <VIcon
                        :icon="resolvePaymentMethodVariant(receipt.payment.paymentMethod).icon"
                        :color="resolvePaymentMethodVariant(receipt.payment.paymentMethod).color"
                        size="20"
                        class="me-2"
                      />
                      <span class="text-capitalize">{{ receipt.payment.paymentMethod }}</span>
                    </VListItemTitle>
                  </VListItem>
                  <VListItem>
                    <VListItemTitle>
                      <VIcon
                        icon="tabler-id"
                        size="20"
                        class="me-2"
                      />
                      {{ receipt.payment.transactionId || 'N/A' }}
                    </VListItemTitle>
                  </VListItem>
                </VList>
              </VCol>
            </VRow>

            <VDivider class="my-6" />

            <VTable class="receipt-table">
              <thead>
                <tr>
                  <th class="text-left">
                    Item
                  </th>
                  <th class="text-left">
                    Type
                  </th>
                  <th class="text-right">
                    Amount
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>{{ receipt.itemName }}</td>
                  <td class="text-capitalize">
                    <VChip
                      :color="resolveItemTypeVariant(receipt.itemType).color"
                      size="small"
                      label
                    >
                      {{ receipt.itemType.replace('_', ' ') }}
                    </VChip>
                  </td>
                  <td class="text-right">
                    {{ formatCurrency(receipt.amount, receipt.currency) }}
                  </td>
                </tr>
              </tbody>
            </VTable>

            <VDivider class="my-6" />

            <div class="d-flex justify-end">
              <div class="receipt-total">
                <div class="d-flex justify-space-between">
                  <p class="text-body-1 mb-2">
                    Subtotal:
                  </p>
                  <p class="text-body-1 mb-2">
                    {{ formatCurrency(receipt.amount, receipt.currency) }}
                  </p>
                </div>
                <div class="d-flex justify-space-between">
                  <p class="text-body-1 mb-2">
                    Tax (0%):
                  </p>
                  <p class="text-body-1 mb-2">
                    $0.00
                  </p>
                </div>
                <VDivider class="my-2" />
                <div class="d-flex justify-space-between">
                  <h6 class="text-h6">
                    Total:
                  </h6>
                  <h6 class="text-h6">
                    {{ formatCurrency(receipt.amount, receipt.currency) }}
                  </h6>
                </div>
              </div>
            </div>
          </div>

          <VDivider />

          <div class="receipt-footer pa-6 text-center">
            <p class="text-body-2 mb-0">
              Thank you for your business!
            </p>
            <p class="text-caption">
              If you have any questions, please contact us at support@example.com.
            </p>
          </div>
        </div>
      </VCardText>
      <VCardActions class="justify-end gap-3 pa-4">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="dialogVisible = false"
        >
          Close
        </VBtn>
        <VBtn
          prepend-icon="tabler-mail"
          @click="resendReceipt"
        >
          Resend
        </VBtn>
        <VBtn
          prepend-icon="tabler-download"
          @click="downloadReceipt"
        >
          Download
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.receipt-a5-container {
  inline-size: 100%;
  max-inline-size: 559px; /* A5 width in pixels at 96 DPI */
  margin-inline: auto;
  background-color: rgb(var(--v-theme-surface));
  border-radius: 6px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 10%);
}

.receipt-header {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.receipt-body {
  padding-block: 1.5rem;
  padding-inline: 1.5rem;
}

.receipt-table {
  &.v-table {
    --v-table-header-height: 40px;

    .v-table__wrapper {
      border-radius: 4px;
      border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    }

    th {
      background-color: rgba(var(--v-theme-on-surface), 0.04);
    }
  }
}

.receipt-total {
  inline-size: 100%;
  max-inline-size: 250px;
}

.receipt-footer {
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.card-list {
  --v-list-item-min-height: 24px;
  --v-list-item-padding-inline-end: 0;
  --v-list-item-padding-inline-start: 0;
}
</style>
