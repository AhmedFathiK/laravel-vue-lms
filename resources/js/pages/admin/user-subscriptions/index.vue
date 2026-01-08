<script setup>
import { formatDate } from '@/@core/utils/formatters'
import AddEditUserSubscriptionDialog from '@/components/dialogs/AddEditUserSubscriptionDialog.vue'
import api from '@/utils/api'
import { can } from '@layouts/plugins/casl'
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'subscriptions',
  },
})

const toast = useToast()

// 👉 Store
const searchQuery = ref('')
const selectedStatus = ref(null)
const isLoading = ref(false)
const fromStartDate = ref('')
const toStartDate = ref('')
const userId = ref(null)
const subscriptionPlanId = ref(null)
const autoRenew = ref(null)
const isAddEditDialogVisible = ref(false)
const showDeleted = ref(false)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('created_at')
const sortOrder = ref('desc')

// Fetch subscriptions
const subscriptionsData = ref({
  data: [],
  total: 0,
})

// Headers for data table
const headers = [
  { title: 'ID', key: 'id' },
  { title: 'User', key: 'user' },
  { title: 'Plan', key: 'plan' },
  { title: 'Starts At', key: 'startsAt' },
  { title: 'Ends At', key: 'endsAt' },
  { title: 'Status', key: 'status' },
  { title: 'Auto Renew', key: 'autoRenew', align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
]

const updateOptions = options => {
  if (options.sortBy?.length) {
    sortBy.value = options.sortBy[0]?.key
    sortOrder.value = options.sortBy[0]?.order
  }
  fetchSubscriptions()
}

// Fetch subscriptions from API
const fetchSubscriptions = async () => {
  isLoading.value = true
  try {
    const params = {
      page: page.value,
      perPage: itemsPerPage.value,
      status: selectedStatus.value || undefined,
      fromStartDate: fromStartDate.value || undefined,
      toStartDate: toStartDate.value || undefined,
      userId: userId.value || undefined,
      subscriptionPlanId: subscriptionPlanId.value || undefined,
      autoRenew: autoRenew.value !== null ? autoRenew.value : undefined,
      sortBy: sortBy.value,
      sortOrder: sortOrder.value,
      withTrashed: showDeleted.value,
    }
    
    const response = await api.get('/admin/user-subscriptions', { params })

    subscriptionsData.value = response
  } catch (error) {
    console.error('Error fetching subscriptions:', error)
    toast.error('Failed to load subscriptions')
  } finally {
    isLoading.value = false
  }
}

// Watch for changes to trigger refetch
watch(
  [page, itemsPerPage, selectedStatus, fromStartDate, toStartDate, userId, subscriptionPlanId, autoRenew, showDeleted],
  fetchSubscriptions,
  { immediate: true },
)

// Computed properties
const subscriptions = computed(() => subscriptionsData.value.data || [])
const totalSubscriptions = computed(() => subscriptionsData.value.total || 0)

// Status options
const statusOptions = [
  { title: 'Active', value: 'active' },
  { title: 'Canceled', value: 'canceled' },
  { title: 'Expired', value: 'expired' },
]

// Helper functions for UI
const resolveStatusVariant = status => {
  if (status === 'active') return 'success'
  if (status === 'canceled') return 'warning'
  if (status === 'expired') return 'error'
  
  return 'secondary'
}

// Actions
const selectedSubscription = ref(null)

const editSubscription = subscription => {
  selectedSubscription.value = subscription
  isAddEditDialogVisible.value = true
}

const cancelSubscription = async subscription => {
  if (!confirm('Are you sure you want to cancel this subscription?')) return
  
  try {
    await api.post(`/admin/user-subscriptions/${subscription.id}/cancel`)
    toast.success('Subscription canceled successfully')
    fetchSubscriptions()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to cancel subscription')
  }
}

const deleteSubscription = async subscription => {
  if (!confirm('Are you sure you want to delete this subscription?')) return
  
  try {
    await api.delete(`/admin/user-subscriptions/${subscription.id}`)
    toast.success('Subscription deleted successfully')
    fetchSubscriptions()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to delete subscription')
  }
}

const onSubscriptionSubmitSuccess = () => {
  fetchSubscriptions()
  isAddEditDialogVisible.value = false
}

const clearFilters = () => {
  selectedStatus.value = null
  fromStartDate.value = ''
  toStartDate.value = ''
  userId.value = null
  subscriptionPlanId.value = null
  autoRenew.value = null
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
            md="3"
          >
            <AppSelect
              v-model="selectedStatus"
              label="Status"
              placeholder="Select Status"
              :items="statusOptions"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppDateTimePicker
              v-model="fromStartDate"
              label="From Date"
              placeholder="Select start date"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppDateTimePicker
              v-model="toStartDate"
              label="To Date"
              placeholder="Select end date"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
            class="d-flex align-end"
          >
            <VBtn
              variant="tonal"
              color="secondary"
              @click="clearFilters"
            >
              Clear Filters
            </VBtn>
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
          <VSwitch
            v-model="showDeleted"
            label="Show Deleted"
          />
          <VBtn
            v-if="can('create', 'subscriptions')"
            color="primary"
            prepend-icon="tabler-plus"
            @click="selectedSubscription = null; isAddEditDialogVisible = true"
          >
            Add Subscription
          </VBtn>
        </div>
      </VCardText>
      <VDivider />
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="subscriptions"
        :headers="headers"
        :items-length="totalSubscriptions"
        :loading="isLoading"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
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
              <span v-if="item.user">{{ (item.user.fullName || '').charAt(0).toUpperCase() }}</span>
              <VIcon
                v-else
                icon="tabler-user-off"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <h6 class="text-base">
                {{ item.user?.fullName || 'Unknown User' }}
              </h6>
              <div class="text-sm">
                {{ item.user?.email || '' }}
              </div>
            </div>
          </div>
        </template>
        <template #[`item.plan`]="{ item }">
          <div class="d-flex flex-column">
            <h6 class="text-base">
              {{ item.plan?.name || 'N/A' }}
            </h6>
            <div class="text-sm">
              {{ item.plan?.course?.title || '' }}
            </div>
          </div>
        </template>
        <template #[`item.startsAt`]="{ item }">
          {{ formatDate(item.startsAt) }}
        </template>
        <template #[`item.endsAt`]="{ item }">
          {{ item.endsAt ? formatDate(item.endsAt) : 'Never' }}
        </template>
        <template #[`item.status`]="{ item }">
          <VChip
            :color="resolveStatusVariant(item.status)"
            label
            size="small"
            class="text-capitalize"
          >
            {{ item.status }}
          </VChip>
        </template>
        <template #[`item.autoRenew`]="{ item }">
          <VChip
            :color="item.autoRenew ? 'success' : 'error'"
            label
            size="small"
          >
            {{ item.autoRenew ? 'Yes' : 'No' }}
          </VChip>
        </template>
        <template #[`item.actions`]="{ item }">
          <IconBtn
            v-if="can('update', 'subscriptions')"
            @click="editSubscription(item)"
          >
            <VIcon icon="tabler-edit" />
          </IconBtn>

          <IconBtn
            v-if="can('update', 'subscriptions') && item.status === 'active'"
            @click="cancelSubscription(item)"
          >
            <VIcon icon="tabler-circle-x" />
          </IconBtn>

          <IconBtn
            v-if="can('delete', 'subscriptions')"
            @click="deleteSubscription(item)"
          >
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </template>
        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalSubscriptions"
          />
        </template>
      </VDataTableServer>
    </VCard>
    
    <AddEditUserSubscriptionDialog
      :is-dialog-visible="isAddEditDialogVisible"
      :dialog-mode="selectedSubscription ? 'edit' : 'add'"
      :data="selectedSubscription"
      @update:is-dialog-visible="isAddEditDialogVisible = $event"
      @submit-success="onSubscriptionSubmitSuccess"
    />
  </section>
</template>
