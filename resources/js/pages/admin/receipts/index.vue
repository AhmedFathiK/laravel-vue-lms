<script setup>
import { formatCurrency } from '@/@core/utils/formatters'
import ReceiptDialog from '@/components/dialogs/ReceiptDialog.vue'
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
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
const selectedPaymentMethod = ref('')
const selectedItemType = ref('')
const isLoading = ref(false)
const fromDate = ref('')
const toDate = ref('')
const receiptId = ref('')
const userQuery = ref('')
const courseId = ref('')
const subscriptionType = ref('')
const isAddReceiptDialogVisible = ref(false)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('created_at')
const sortOrder = ref('desc')
const selectedRows = ref([])

// Fetch receipts
const receiptsData = ref({
  items: [],
  total: 0,
  current_page: 1,
  per_page: 10,
  last_page: 1,
})

// Headers for data table
const headers = [
  { title: "", key: "data-table-expand", sortable: false },
  { title: 'ID', key: 'id' },
  { title: 'User', key: 'user' },
  { title: 'Item', key: 'item_name' },
  { title: 'Amount', key: 'amount' },
  { title: 'Payment Method', key: 'payment_method' },
  { title: 'Payment Date', key: 'payment.payment_details.payment_date' },
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
      per_page: itemsPerPage.value,
      search: searchQuery.value || undefined,
      payment_method: selectedPaymentMethod.value || undefined,
      item_type: selectedItemType.value || undefined,
      from_date: fromDate.value || undefined,
      to_date: toDate.value || undefined,
      receipt_id: receiptId.value || undefined,
      user_query: userQuery.value || undefined,
      course_id: courseId.value || undefined,
      subscription_type: subscriptionType.value || undefined,
      sort_by: sortBy.value,
      sort_order: sortOrder.value,
    }
    
    const response = await api.get('/admin/receipts', { params })

    receiptsData.value = response
    
    // Fetch statistics separately
    fetchStatistics()
  } catch (error) {
    console.error('Error fetching receipts:', error)
    toast.error('Failed to load receipts')
  } finally {
    isLoading.value = false
  }
}

// Fetch statistics from API
const fetchStatistics = async () => {
  try {
    const response = await api.get('/admin/receipts/statistics')
    
    // Update widget data with statistics
    widgetData.value[0].value = response.total_receipts?.toString() || '0'
    widgetData.value[1].value = response.total_amount?.toFixed(2) || '0.00'
    widgetData.value[2].value = response.top_payment_method || 'None'
    widgetData.value[3].value = response.item_types_count?.toString() || '0'
  } catch (error) {
    console.error('Error fetching statistics:', error)
    toast.error('Failed to load statistics')
  }
}

// Watch for changes to trigger refetch
watch(
  [searchQuery, selectedPaymentMethod, selectedItemType, page, itemsPerPage, fromDate, toDate, receiptId, userQuery, courseId, subscriptionType, sortBy, sortOrder],
  () => {
    fetchReceipts()
  },
  { immediate: true },
)

// Fetch statistics on component mount
onMounted(() => {
  fetchStatistics()
})


// Computed properties
const receipts = computed(() => receiptsData.value.items || [])
const totalReceipts = computed(() => receiptsData.value.total || 0)

// Payment method options for dropdown
const paymentMethods = [
  {
    title: 'Credit Card',
    value: 'credit_card',
  },
  {
    title: 'PayPal',
    value: 'paypal',
  },
  {
    title: 'Bank Transfer',
    value: 'bank_transfer',
  },
  {
    title: 'Manual',
    value: 'Manual',
  },
]

// Item type options for dropdown
const itemTypes = [
  {
    title: 'Course',
    value: 'course',
  },
  {
    title: 'Subscription Plan',
    value: 'subscription_plan',
  },
]

// Subscription type options for dropdown
const subscriptionTypes = [
  {
    title: 'One-time',
    value: 'one-time',
  },
  {
    title: 'Recurring',
    value: 'recurring',
  },
]

// Helper functions for UI
const resolvePaymentMethodVariant = method => {
  method = method?.toLowerCase() || ''
  
  if (method.includes('credit_card'))
    return {
      color: 'success',
      icon: 'tabler-credit-card',
    }
  if (method.includes('paypal'))
    return {
      color: 'info',
      icon: 'tabler-brand-paypal',
    }
  if (method.includes('bank_transfer'))
    return {
      color: 'warning',
      icon: 'tabler-building-bank',
    }
  if (method.includes('manual'))
    return {
      color: 'primary',
      icon: 'tabler-cash',
    }
  
  return {
    color: 'secondary',
    icon: 'tabler-cash',
  }
}

const resolveItemTypeVariant = type => {
  type = type?.toLowerCase() || ''
  
  if (type.includes('course') || type.includes('subscription_plan'))
    return {
      color: 'primary',
      icon: 'tabler-book',
    }
  
  return {
    color: 'secondary',
    icon: 'tabler-file',
  }
}

// Combine receipt count stats
const widgetData = ref([
  {
    title: 'Total Receipts',
    value: '0',
    icon: 'tabler-receipt',
    iconColor: 'primary',
  },
  {
    title: 'Total Amount',
    value: '0.00',
    icon: 'tabler-currency-dollar',
    iconColor: 'success',
    prefix: '$',
  },
  {
    title: 'Top Payment Method',
    value: 'None',
    icon: 'tabler-credit-card',
    iconColor: 'info',
  },
  {
    title: 'Item Types',
    value: '0',
    icon: 'tabler-category',
    iconColor: 'warning',
  },
])

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
    const response = await api.get(`/admin/receipts/${receipt.id}/download`)

    toast.success('Receipt download initiated')

    // In a real implementation, this would trigger a file download
  } catch (error) {
    console.error('Error downloading receipt:', error)
    toast.error('Failed to download receipt')
  }
}

// Resend receipt
const resendReceipt = async receipt => {
  try {
    const response = await api.post(`/admin/receipts/${receipt.id}/resend`)

    toast.success(`Receipt sent to ${receipt.user.email}`)
  } catch (error) {
    console.error('Error resending receipt:', error)
    toast.error('Failed to resend receipt')
  }
}

// Delete receipt
const deleteReceipt = async receipt => {
  if (!confirm('Are you sure you want to delete this receipt? This action cannot be undone.')) return

  try {
    const response = await api.delete(`/admin/receipts/${receipt.id}`)

    toast.success('Receipt deleted successfully')
    fetchReceipts()
  } catch (error) {
    console.error('Error deleting receipt:', error)
    toast.error(error.response?.data?.message || 'Failed to delete receipt')
  }
}



// Clear filters
const clearFilters = () => {
  searchQuery.value = ''
  selectedPaymentMethod.value = ''
  selectedItemType.value = ''
  fromDate.value = ''
  toDate.value = ''
  receiptId.value = ''
  userQuery.value = ''
  courseId.value = ''
  subscriptionType.value = ''
}

// Handle receipt dialog submission success
const onReceiptSubmitSuccess = () => {
  fetchReceipts()
  selectedReceipt.value = null
}

// Fetch data on component mount
onMounted(() => {
  fetchReceipts()
})
</script>

<template>
  <section>
    <!-- 👉 Widgets -->
    <div class="d-flex mb-6">
      <VRow>
        <template
          v-for="(data, id) in widgetData"
          :key="id"
        >
          <VCol
            cols="12"
            md="3"
            sm="6"
          >
            <VCard>
              <VCardText>
                <div class="d-flex justify-space-between">
                  <div class="d-flex flex-column gap-y-1">
                    <div class="text-body-1 text-high-emphasis">
                      {{ data.title }}
                    </div>
                    <div class="d-flex gap-x-2 align-center">
                      <h4 class="text-h4">
                        <template v-if="data.prefix">
                          {{ data.prefix }}
                        </template>{{ data.value }}
                      </h4>
                    </div>
                  </div>
                  <VAvatar
                    :color="data.iconColor"
                    variant="tonal"
                    rounded
                    size="42"
                  >
                    <VIcon
                      :icon="data.icon"
                      size="26"
                    />
                  </VAvatar>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </template>
      </VRow>
    </div>

    <VCard class="mb-6">
      <VCardItem class="pb-4">
        <VCardTitle>Filters</VCardTitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <!-- 👉 Receipt ID -->
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
          
          <!-- 👉 User Query -->
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
          
          <!-- 👉 Select Payment Method -->
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
              clear-icon="tabler-x"
            />
          </VCol>
          
          <!-- 👉 Select Item Type -->
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
              clear-icon="tabler-x"
            />
          </VCol>
          
          <!-- 👉 From Date -->
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppDateTimePicker
              v-model="fromDate"
              label="From Date"
              placeholder="Select start date"
              :config="{ altInput: true, altFormat: 'F j, Y' }"
            />
          </VCol>
          
          <!-- 👉 To Date -->
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppDateTimePicker
              v-model="toDate"
              label="To Date"
              placeholder="Select end date"
              :config="{ altInput: true, altFormat: 'F j, Y' }"
            />
          </VCol>
        </VRow>
        
        <VRow class="mt-3">
          <VCol
            cols="12"
            class="d-flex justify-end"
          >
            <VBtn
              variant="outlined"
              color="secondary"
              class="me-3"
              @click="clearFilters"
            >
              Clear Filters
            </VBtn>
            <VBtn
              color="primary"
              @click="fetchReceipts"
            >
              Apply Filters
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardText class="d-flex flex-wrap gap-4">
        <div class="me-3 d-flex gap-3">
          <AppSelect
            :model-value="itemsPerPage"
            :items="[
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: 100, title: '100' },
            ]"
            style="inline-size: 6.25rem;"
            @update:model-value="itemsPerPage = parseInt($event, 10)"
          />
        </div>
        <VSpacer />

        <div class="app-user-search-filter d-flex align-center flex-wrap gap-4">
          <!-- 👉 Add Receipt Button -->
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="isAddReceiptDialogVisible = true"
          >
            Add Receipt
          </VBtn>
          
          <!-- 👉 Search  -->
          <div style="inline-size: 15.625rem;">
            <AppTextField
              v-model="searchQuery"
              placeholder="Search receipts"
            />
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- SECTION datatable -->
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
        <template #expanded-row="{item}">
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
                  <b>Issuance Date:</b> 
                </span>
                {{ formatDate(item.created_at) }}
              </p>
            </td>
          </tr>
        </template>
        <!-- Receipt ID -->
        <template #[`item.receipt_number`]="{ item }">
          <div class="d-flex align-center gap-x-2">
            <VIcon
              :size="22"
              icon="tabler-receipt"
              color="primary"
            />

            <div class="text-high-emphasis text-body-1">
              {{ item.receipt_number }}
            </div>
          </div>
        </template>

        <!-- User -->
        <template #[`item.user`]="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              size="34"
              color="primary"
              variant="tonal"
            >
              <span>{{ (item.user?.full_name || '').charAt(0).toUpperCase() }}</span>
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

        <!-- Item Name -->
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

        <!-- Amount -->
        <template #[`item.amount`]="{ item }">
          <div class="text-high-emphasis text-body-1">
            {{ formatCurrency(item.amount, item.currency) }}
          </div>
        </template>

        <!-- Payment Method -->
        <template #[`item.payment_method`]="{ item }">
          <div class="d-flex align-center gap-x-2">
            <VIcon
              :size="22"
              :icon="resolvePaymentMethodVariant(item.payment?.payment_method).icon"
              :color="resolvePaymentMethodVariant(item.payment?.payment_method).color"
            />

            <div class="text-capitalize text-high-emphasis text-body-1">
              {{ item.payment?.payment_method || 'Unknown' }}
            </div>
          </div>
        </template>

        <!-- Date -->
        <template #[`item.payment.payment_details.payment_date`]="{ item }">
          <div class="text-high-emphasis text-body-1">
            {{ formatDate(item.payment.payment_details.payment_date) }}
          </div>
        </template>

        <!-- Actions -->
        <template #[`item.actions`]="{ item }">
          <IconBtn @click="viewReceiptDetails(item)">
            <VIcon icon="tabler-eye" />
          </IconBtn>

          <VTooltip
            bottom
            :disabled="item.source_type != 'system'"
          >
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn
                  :disabled="item.source_type == 'system'"
                  @click="editReceipt(item)"
                >
                  <VIcon icon="tabler-edit" />
                </IconBtn>
              </span>
            </template>
            <span v-if="item.source_type == 'system'">System-generated receipts cannot be edited.</span>
          </VTooltip>

          

          <IconBtn @click="downloadReceipt(item)">
            <VIcon icon="tabler-download" />
          </IconBtn>

          <IconBtn @click="resendReceipt(item)">
            <VIcon icon="tabler-mail" />
          </IconBtn>

          <IconBtn 
            v-if="item.payment?.payment_provider === 'Manual'"
            @click="deleteReceipt(item)"
          >
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </template>

        <!-- pagination -->
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalReceipts"
          />
        </template>
      </VDataTableServer>
      <!-- SECTION -->
    </VCard>
    
    <!-- 👉 Receipt Details Dialog -->
    <VDialog
      v-model="isReceiptDetailsDialogVisible"
      max-width="600"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isReceiptDetailsDialogVisible = false" />
      <VCard
        v-if="selectedReceipt"
        title="Receipt Details"
      >
        <VCardText>
          <VRow>
            <VCol cols="12">
              <h6 class="text-h6 mb-2">
                Receipt Information
              </h6>
              <VList lines="two">
                <VListItem>
                  <template #prepend>
                    <VIcon
                      icon="tabler-receipt"
                      color="primary"
                    />
                  </template>
                  <VListItemTitle>Receipt Number</VListItemTitle>
                  <VListItemSubtitle>{{ selectedReceipt.receipt_number }}</VListItemSubtitle>
                </VListItem>
                
                <VListItem>
                  <template #prepend>
                    <VIcon
                      icon="tabler-calendar"
                      color="primary"
                    />
                  </template>
                  <VListItemTitle>Date</VListItemTitle>
                  <VListItemSubtitle>{{ formatDate(selectedReceipt.created_at) }}</VListItemSubtitle>
                </VListItem>
                
                <VListItem>
                  <template #prepend>
                    <VIcon
                      icon="tabler-currency-dollar"
                      color="success"
                    />
                  </template>
                  <VListItemTitle>Amount</VListItemTitle>
                  <VListItemSubtitle>{{ formatCurrency(selectedReceipt.amount, selectedReceipt.currency) }}</VListItemSubtitle>
                </VListItem>
              </VList>
            </VCol>
            
            <VCol cols="12">
              <h6 class="text-h6 mb-2">
                Item Details
              </h6>
              <VList lines="two">
                <VListItem>
                  <template #prepend>
                    <VIcon
                      :icon="resolveItemTypeVariant(selectedReceipt.item_type).icon"
                      :color="resolveItemTypeVariant(selectedReceipt.item_type).color"
                    />
                  </template>
                  <VListItemTitle>Item Type</VListItemTitle>
                  <VListItemSubtitle class="text-capitalize">
                    {{ selectedReceipt.item_type }}
                  </VListItemSubtitle>
                </VListItem>
                
                <VListItem>
                  <template #prepend>
                    <VIcon
                      icon="tabler-file-description"
                      color="primary"
                    />
                  </template>
                  <VListItemTitle>Item Name</VListItemTitle>
                  <VListItemSubtitle>{{ selectedReceipt.item_name }}</VListItemSubtitle>
                </VListItem>
              </VList>
            </VCol>
            
            <VCol cols="12">
              <h6 class="text-h6 mb-2">
                Payment Details
              </h6>
              <VList lines="two">
                <VListItem v-if="selectedReceipt.payment">
                  <template #prepend>
                    <VIcon
                      :icon="resolvePaymentMethodVariant(selectedReceipt.payment.payment_method).icon"
                      :color="resolvePaymentMethodVariant(selectedReceipt.payment.payment_method).color"
                    />
                  </template>
                  <VListItemTitle>Payment Method</VListItemTitle>
                  <VListItemSubtitle class="text-capitalize">
                    {{ selectedReceipt.payment.payment_method }}
                  </VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="selectedReceipt.payment">
                  <template #prepend>
                    <VIcon
                      icon="tabler-building-store"
                      color="primary"
                    />
                  </template>
                  <VListItemTitle>Payment Provider</VListItemTitle>
                  <VListItemSubtitle>{{ selectedReceipt.payment.payment_provider }}</VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="selectedReceipt.payment && selectedReceipt.payment.transaction_id">
                  <template #prepend>
                    <VIcon
                      icon="tabler-id"
                      color="primary"
                    />
                  </template>
                  <VListItemTitle>Transaction ID</VListItemTitle>
                  <VListItemSubtitle>{{ selectedReceipt.payment.transaction_id }}</VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="selectedReceipt.payment && selectedReceipt.payment.payment_details && selectedReceipt.payment.payment_details.notes">
                  <template #prepend>
                    <VIcon
                      icon="tabler-notes"
                      color="primary"
                    />
                  </template>
                  <VListItemTitle>Notes</VListItemTitle>
                  <VListItemSubtitle>{{ selectedReceipt.payment.payment_details.notes }}</VListItemSubtitle>
                </VListItem>
              </VList>
            </VCol>
            
            <VCol cols="12">
              <h6 class="text-h6 mb-2">
                User Information
              </h6>
              <VList lines="two">
                <VListItem v-if="selectedReceipt.user">
                  <template #prepend>
                    <VIcon
                      icon="tabler-user"
                      color="primary"
                    />
                  </template>
                  <VListItemTitle>Name</VListItemTitle>
                  <VListItemSubtitle>{{ selectedReceipt.user.full_name }}</VListItemSubtitle>
                </VListItem>
                
                <VListItem v-if="selectedReceipt.user">
                  <template #prepend>
                    <VIcon
                      icon="tabler-mail"
                      color="primary"
                    />
                  </template>
                  <VListItemTitle>Email</VListItemTitle>
                  <VListItemSubtitle>{{ selectedReceipt.user.email }}</VListItemSubtitle>
                </VListItem>
              </VList>
            </VCol>
          </VRow>
        </VCardText>
        
        <VDivider />
        <VCardText class="d-flex justify-end flex-wrap gap-3">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isReceiptDetailsDialogVisible = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            @click="downloadReceipt(selectedReceipt)"
          >
            Download Receipt
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
    
    <!-- 👉 Receipt Dialog Component -->
    <ReceiptDialog
      :is-dialog-visible="isAddReceiptDialogVisible"
      :dialog-mode="selectedReceipt ? 'edit' : 'add'"
      :receipt="selectedReceipt"
      @update:is-dialog-visible="isAddReceiptDialogVisible = $event; selectedReceipt = null"
      @submit-success="onReceiptSubmitSuccess"
    />
  </section>
</template>
