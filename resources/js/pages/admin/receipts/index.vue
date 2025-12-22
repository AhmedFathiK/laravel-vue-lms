<script setup>
import { formatCurrency, formatDate } from '@/@core/utils/formatters'
import ReceiptViewDialog from '@/components/dialogs/ReceiptViewDialog.vue'
import api from '@/utils/api'
import { can } from '@layouts/plugins/casl'
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'receipts',
  },
})

const toast = useToast()

// 👉 Store
const searchQuery = ref('')
const selectedPaymentMethod = ref(null)
const selectedItemType = ref(null)
const isLoading = ref(false)
const fromDate = ref('')
const toDate = ref('')
const receiptId = ref('')
const userQuery = ref('')
const courseId = ref('')
const subscriptionType = ref('')
const isAddReceiptDialogVisible = ref(false)
const showDeleted = ref(false)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('createdAt')
const sortOrder = ref('desc')

// Fetch receipts
const receiptsData = ref({
  items: [],
  total: 0,
})

// Headers for data table
const headers = [
  { title: '', key: 'data-table-expand', sortable: false },
  { title: 'ID', key: 'id' },
  { title: 'User', key: 'user' },
  { title: 'Item', key: 'item_name' },
  { title: 'Amount', key: 'amount' },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Date', key: 'createdAt' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const updateOptions = options => {
  if (options.sortBy?.length) {
    sortBy.value = options.sortBy[0]?.key
    sortOrder.value = options.sortBy[0]?.order
  }
  fetchReceipts()
}

// Fetch receipts from API
const fetchReceipts = async () => {
  isLoading.value = true
  try {
    const params = {
      page: page.value,
      perPage: itemsPerPage.value,
      search: searchQuery.value || undefined,
      paymentMethod: selectedPaymentMethod.value || undefined,
      itemType: selectedItemType.value || undefined,
      fromDate: fromDate.value || undefined,
      toDate: toDate.value || undefined,
      receiptId: receiptId.value || undefined,
      userQuery: userQuery.value || undefined,
      courseId: courseId.value || undefined,
      subscriptionType: subscriptionType.value || undefined,
      sortBy: sortBy.value,
      sortOrder: sortOrder.value,
      withTrashed: showDeleted.value,
    }
    
    const response = await api.get('/admin/receipts', { params })

    receiptsData.value = response
  } catch (error) {
    console.error('Error fetching receipts:', error)
    toast.error('Failed to load receipts')
  } finally {
    isLoading.value = false
  }
}

// Watch for changes to trigger refetch
watch(
  [searchQuery, selectedPaymentMethod, selectedItemType, page, itemsPerPage, fromDate, toDate, receiptId, userQuery, courseId, subscriptionType, sortBy, sortOrder, showDeleted],
  fetchReceipts,
  { immediate: true },
)

// Computed properties
const receipts = computed(() => receiptsData.value.items || [])
const totalReceipts = computed(() => receiptsData.value.total || 0)

// Payment method options for dropdown
const paymentMethods = [
  { title: 'Credit Card', value: 'credit_card' },
  { title: 'PayPal', value: 'paypal' },
  { title: 'Bank Transfer', value: 'bank_transfer' },
  { title: 'Manual', value: 'Manual' },
]

// Item type options for dropdown
const itemTypes = [
  { title: 'Course', value: 'course' },
  { title: 'Subscription Plan', value: 'subscription' },
]

// Helper functions for UI
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
  if (type.includes('course') || type.includes('subscription_plan')) return { color: 'primary', icon: 'tabler-book' }
  
  return { color: 'secondary', icon: 'tabler-file' }
}

// View receipt details
const selectedReceipt = ref(null)
const isReceiptDetailsDialogVisible = ref(false)

const viewReceiptDetails = receipt => {
  selectedReceipt.value = receipt
  isReceiptDetailsDialogVisible.value = true
}

// Edit receipt
const editReceipt = receipt => {
  selectedReceipt.value = receipt
  isAddReceiptDialogVisible.value = true
}

// Download receipt
const downloadReceipt = async receipt => {
  try {
    const response = await api.get(`/admin/receipts/${receipt.id}/download`, {
      responseType: 'blob',
    })

    const url = window.URL.createObjectURL(new Blob([response]))
    const link = document.createElement('a')

    link.href = url
    link.setAttribute('download', `receipt-${receipt.receiptNumber}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
    toast.success('Receipt downloaded successfully')
  } catch (error) {
    console.error('Error downloading receipt:', error)
    toast.error('Failed to download receipt')
  }
}

// Resend receipt
const resendReceipt = async receipt => {
  try {
    await api.post(`/admin/receipts/${receipt.id}/resend`)
    toast.success(`Receipt sent to ${receipt.user.email}`)
  } catch (error) {
    console.error('Error resending receipt:', error)
    toast.error('Failed to resend receipt')
  }
}

// --- Deletion Logic ---
const isDeleteDialogVisible = ref(false)
const deletionReason = ref('')
const receiptToDelete = ref(null)

const openDeleteDialog = receipt => {
  receiptToDelete.value = receipt
  isDeleteDialogVisible.value = true
}

const closeDeleteDialog = () => {
  isDeleteDialogVisible.value = false
  deletionReason.value = ''
  receiptToDelete.value = null
}

const deleteReceipt = async () => {
  if (!receiptToDelete.value || !deletionReason.value) {
    toast.error('Deletion reason is required.')
    
    return
  }

  try {
    await api.delete(`/admin/receipts/${receiptToDelete.value.id}`, {
      data: { reason: deletionReason.value },
    })
    toast.success('Receipt deleted successfully')
    fetchReceipts()
    closeDeleteDialog()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to delete receipt')
  }
}

// --- Void Logic ---
const isVoidDialogVisible = ref(false)
const voidReason = ref('')
const receiptToVoid = ref(null)

const openVoidDialog = receipt => {
  receiptToVoid.value = receipt
  isVoidDialogVisible.value = true
}

const closeVoidDialog = () => {
  isVoidDialogVisible.value = false
  voidReason.value = ''
  receiptToVoid.value = null
}

const voidReceipt = async () => {
  if (!receiptToVoid.value || !voidReason.value) {
    toast.error('Void reason is required.')
    
    return
  }

  try {
    await api.post(`/admin/receipts/${receiptToVoid.value.id}/void`, {
      reason: voidReason.value,
    })
    toast.success('Receipt voided successfully')
    fetchReceipts()
    closeVoidDialog()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to void receipt')
  }
}


// Clear filters
const clearFilters = () => {
  searchQuery.value = ''
  selectedPaymentMethod.value = null
  selectedItemType.value = null
  fromDate.value = ''
  toDate.value = ''
  receiptId.value = ''
  userQuery.value = ''
  courseId.value = ''
  subscriptionType.value = ''
}

const onReceiptSubmitSuccess = () => {
  fetchReceipts()
  selectedReceipt.value = null
}
</script>

<template>
  <section>
    <VCard class="mb-6">
      <VCardItem class="pb-4">
        <VCardTitle>Filters</VCardTitle>
      </VCardItem>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              v-model="receiptId"
              label="Receipt Number"
              placeholder="Search by receipt number"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              v-model="userQuery"
              label="User"
              placeholder="Search by user name or email"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="selectedPaymentMethod"
              label="Payment Method"
              placeholder="Select Payment Method"
              :items="paymentMethods"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="selectedItemType"
              label="Item Type"
              placeholder="Select Item Type"
              :items="itemTypes"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppDateTimePicker
              v-model="fromDate"
              label="From Date"
              placeholder="Select start date"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppDateTimePicker
              v-model="toDate"
              label="To Date"
              placeholder="Select end date"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VDivider />
      <VCardText class="d-flex flex-wrap gap-4">
        <div class="me-3 d-flex gap-3">
          <AppSelect
            :model-value="itemsPerPage"
            :items="[{ value: 10, title: '10' }, { value: 25, title: '25' }, { value: 50, title: '50' }, { value: 100, title: '100' }]"
            style="inline-size: 6.25rem;"
            @update:model-value="itemsPerPage = parseInt($event, 10)"
          />
        </div>
        <VSpacer />
        <div class="d-flex align-center flex-wrap gap-4">
          <AppTextField
            v-model="searchQuery"
            placeholder="Search receipts"
            style="inline-size: 15.625rem;"
          />
          <VSwitch
            v-model="showDeleted"
            label="Show Deleted"
          />
          <VBtn
            v-if="can('create', 'receipts')"
            color="primary"
            prepend-icon="tabler-plus"
            @click="isAddReceiptDialogVisible = true"
          >
            Add Receipt
          </VBtn>
        </div>
      </VCardText>
      <VDivider />
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="receipts"
        :headers="headers"
        :items-length="totalReceipts"
        :loading="isLoading"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- Expanded Row Data -->
        <template #expanded-row="{ item }">
          <tr>
            <td :colspan="headers.length">
              <p class="my-1">
                <span class="text-high-emphasis text-body-1">
                  <b>Receipt Number:</b>
                </span>
                {{ item.receipt_number }}
              </p>
              <p class="my-1">
                <span class="text-high-emphasis text-body-1">
                  <b>Payment Method:</b>
                </span>
                <span class="text-capitalize">{{ item.payment?.payment_method || 'N/A' }}</span>
              </p>
              <p
                v-if="item.deleted_at"
                class="my-1"
              >
                <span class="text-high-emphasis text-body-1">
                  <b>Deletion Reason:</b>
                </span>
                <span>{{ item.deletion_reason || 'No reason provided' }}</span>
              </p>
              <p
                v-if="item.voided_at"
                class="my-1"
              >
                <span class="text-high-emphasis text-body-1">
                  <b>Voided At:</b>
                </span>
                <span>{{ formatDate(item.voided_at) }}</span>
              </p>
              <p
                v-if="item.voided_at"
                class="my-1"
              >
                <span class="text-high-emphasis text-body-1">
                  <b>Void Reason:</b>
                </span>
                <span>{{ item.void_reason }}</span>
              </p>
              <p
                v-if="item.voided_at"
                class="my-1"
              >
                <span class="text-high-emphasis text-body-1">
                  <b>Voided By:</b>
                </span>
                <span>{{ item.voided_by?.full_name || 'Unknown' }}</span>
              </p>
            </td>
          </tr>
        </template>

        <template #[`item.id`]="{ item }">
          #{{ item.id }}
        </template>
        <template #[`item.user`]="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              size="34"
              :color="item.user ? 'primary' : 'grey'"
              variant="tonal"
            >
              <span v-if="item.user">{{ (item.user.full_name || '').charAt(0).toUpperCase() }}</span>
              <VIcon
                v-else
                icon="tabler-user-off"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <h6 class="text-base">
                {{ item.user?.full_name || 'Unknown User' }}
              </h6>
              <div class="text-sm">
                {{ item.user?.email || '' }}
              </div>
            </div>
          </div>
        </template>
        <template #[`item.item_name`]="{ item }">
          <div class="d-flex align-center gap-x-2">
            <VIcon
              :size="22"
              :icon="resolveItemTypeVariant(item.item_type).icon"
              :color="resolveItemTypeVariant(item.item_type).color"
            />
            <div class="text-high-emphasis text-body-1">
              {{ item.item_name }}
            </div>
          </div>
        </template>
        <template #[`item.amount`]="{ item }">
          <div class="text-high-emphasis text-body-1">
            {{ formatCurrency(item.amount, item.currency) }}
          </div>
        </template>
        <template #[`item.status`]="{ item }">
          <VChip
            v-if="item.voidedAt"
            color="warning"
            label
            size="small"
          >
            Voided
          </VChip>
          <VChip
            v-else-if="item.deletedAt"
            color="error"
            label
            size="small"
          >
            Deleted
          </VChip>
          <VChip
            v-else
            color="success"
            label
            size="small"
          >
            Completed
          </VChip>
        </template>
        <template #[`item.createdAt`]="{ item }">
          <div class="text-high-emphasis text-body-1">
            {{ formatDate(item.createdAt) }}
          </div>
        </template>
        <template #[`item.actions`]="{ item }">
          <IconBtn @click="viewReceiptDetails(item)">
            <VIcon icon="tabler-eye" />
          </IconBtn>

          <VTooltip
            v-if="!item.deletedAt && !item.voidedAt"
            bottom
            :disabled="item.sourceType !== 'system'"
          >
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn
                  :disabled="item.sourceType === 'system'"
                  @click="editReceipt(item)"
                >
                  <VIcon icon="tabler-edit" />
                </IconBtn>
              </span>
            </template>
            <span v-if="item.sourceType === 'system'">System-generated receipts cannot be edited.</span>
          </VTooltip>

          <IconBtn
            v-if="!item.deletedAt && !item.voidedAt"
            @click="downloadReceipt(item)"
          >
            <VIcon icon="tabler-download" />
          </IconBtn>

          <IconBtn
            v-if="!item.deletedAt && !item.voidedAt"
            @click="resendReceipt(item)"
          >
            <VIcon icon="tabler-mail" />
          </IconBtn>

          <IconBtn
            v-if="can('void', 'receipts') && !item.deletedAt && !item.voidedAt"
            @click="openVoidDialog(item)"
          >
            <VIcon icon="tabler-circle-x" />
          </IconBtn>

          <IconBtn
            v-if="can('delete', 'receipts') && !item.deletedAt && !item.voidedAt"
            @click="openDeleteDialog(item)"
          >
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </template>
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalReceipts"
          />
        </template>
      </VDataTableServer>
    </VCard>
    
    <!-- 👉 Add/Edit Receipt Dialog -->
    <AddEditReceiptDialog
      :is-dialog-visible="isAddReceiptDialogVisible"
      :dialog-mode="selectedReceipt ? 'edit' : 'add'"
      :receipt="selectedReceipt"
      @update:is-dialog-visible="isAddReceiptDialogVisible = $event; selectedReceipt = null"
      @submit-success="onReceiptSubmitSuccess"
    />
    
    <!-- 👉 View Receipt Dialog -->
    <ReceiptViewDialog
      :is-dialog-visible="isReceiptDetailsDialogVisible"
      :receipt="selectedReceipt"
      @update:is-dialog-visible="isReceiptDetailsDialogVisible = false"
      @download="downloadReceipt"
      @resend="resendReceipt"
    />

    <!-- 👉 Delete Confirmation Dialog -->
    <VDialog
      v-model="isDeleteDialogVisible"
      max-width="500"
    >
      <VCard>
        <VCardTitle class="headline">
          Confirm Deletion
        </VCardTitle>
        <VCardText>
          <p>Are you sure you want to delete this receipt? This action cannot be undone.</p>
          <VAlert
            v-if="receiptToDelete?.is_linked_to_subscription"
            color="warning"
            variant="tonal"
            class="mb-4"
          >
            This receipt is linked to a subscription. Deleting it will also revoke access to that subscription.
          </VAlert>
          <AppTextField
            v-model="deletionReason"
            label="Deletion Reason"
            placeholder="Enter reason for deletion"
            :rules="[v => !!v || 'Reason is required']"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="secondary"
            text
            @click="closeDeleteDialog"
          >
            Cancel
          </VBtn>
          <VBtn
            color="error"
            :disabled="!deletionReason"
            @click="deleteReceipt"
          >
            Delete
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- 👉 Void Confirmation Dialog -->
    <VDialog
      v-model="isVoidDialogVisible"
      max-width="500"
    >
      <VCard>
        <VCardTitle class="headline">
          Confirm Void
        </VCardTitle>
        <VCardText>
          <p>Are you sure you want to void this receipt? This will cancel the associated payment and deactivate the subscription.</p>
          <AppTextField
            v-model="voidReason"
            label="Void Reason"
            placeholder="Enter reason for voiding"
            :rules="[v => !!v || 'Reason is required']"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="secondary"
            text
            @click="closeVoidDialog"
          >
            Cancel
          </VBtn>
          <VBtn
            color="warning"
            :disabled="!voidReason"
            @click="voidReceipt"
          >
            Void
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </section>
</template>
