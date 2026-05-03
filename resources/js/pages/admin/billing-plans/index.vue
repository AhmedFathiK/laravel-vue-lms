<script setup>
import AddEditBillingPlanDialog from '@/components/dialogs/AddEditBillingPlanDialog.vue'
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import api from '@/utils/api'
import { ref } from 'vue'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'user_entitlements',
  },
})

const toast = useToast()
const isLoading = ref(false)
const plans = ref([])
const totalPlans = ref(0)
const options = ref({})

// Filters
const filters = ref({
  billingType: null,
  billingInterval: null,
  isActive: null,
  courseIds: [],
  featureIds: [],
})

const coursesList = ref([])
const featuresList = ref([])

// Dialog states
const isPlanDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const dialogMode = ref('add')
const editingPlan = ref(null)
const planToDelete = ref(null)

// Headers for data table
const headers = [
  { title: 'Courses', key: 'courses' },
  { title: 'Name', key: 'name' },
  { title: 'Price', key: 'price' },
  { title: 'Type', key: 'billingType' },
  { title: 'Billing Cycle', key: 'billingInterval' },
  { title: 'Access', key: 'accessType' },
  { title: 'Status', key: 'isActive' },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Fetch courses for filter
const fetchCourses = async () => {
  try {
    const response = await api.get('/admin/courses/select-fields')

    coursesList.value = response
  } catch (error) {
    console.error('Failed to fetch courses:', error)
  }
}

// Fetch features for filter
const fetchFeatures = async () => {
  try {
    const response = await api.get('/admin/features')

    featuresList.value = response
  } catch (error) {
    console.error('Failed to fetch features:', error)
  }
}

// Fetch billing plans
const fetchPlans = async () => {
  isLoading.value = true
  try {
    const { page, itemsPerPage, sortBy } = options.value

    const params = {
      page,
      itemsPerPage,
      sortBy,
      ...filters.value,
    }

    const response = await api.get('/admin/billing-plans', { params })

    // Use response items directly as middleware handles camelCase conversion
    plans.value = response.data
    
    totalPlans.value = response.meta.total
  } catch (error) {
    console.error('Error fetching billing plans:', error)
    toast.error('Failed to load billing plans')
  } finally {
    isLoading.value = false
  }
}

// Initial load
onMounted(() => {
  fetchCourses()
  fetchFeatures()
})

// Toggle plan status
const togglePlanStatus = async plan => {
  try {
    const payload = { isActive: !plan.isActive }

    // Note: The backend route for toggle might need the course ID if it's nested, but we should use the global route if available.
    // However, the toggle route in api.php is: Route::post('billing-plans/{billingPlan}/toggle-status', ...);
    const response = await api.post(`/admin/billing-plans/${plan.id}/toggle-status`, payload)
    const index = plans.value.findIndex(p => p.id === plan.id)
    if (index !== -1) {
      plans.value[index] = response.data
    }
    toast.success(`Plan ${!plan.isActive ? 'activated' : 'deactivated'} successfully`)
  } catch (error) {
    console.error('Error toggling plan status:', error)
    toast.error('Failed to update plan status')

    // Revert the switch state on failure
    const index = plans.value.findIndex(p => p.id === plan.id)
    if (index !== -1) {
      plans.value[index].isActive = plan.isActive
    }
  }
}

// Open dialog for adding new billing plan
const openAddDialog = () => {
  dialogMode.value = 'add'
  editingPlan.value = null
  isPlanDialogVisible.value = true
}

// Open dialog for editing billing plan
const openEditDialog = plan => {
  dialogMode.value = 'edit'
  editingPlan.value = { ...plan }

  // Ensure we pass the courseId if available so the dialog knows which course is selected
  if (plan.course) {
    editingPlan.value.courseId = plan.course.id
  }
  isPlanDialogVisible.value = true
}

// Handle successful submission from dialog
const onFormSubmitSuccess = () => {
  fetchPlans()
}

// --- Deletion Logic ---
const openDeleteDialog = plan => {
  planToDelete.value = plan
  isDeleteDialogVisible.value = true
}

const handleDeleteConfirm = async result => {
  if (!result.confirmed || !planToDelete.value){
    planToDelete.value = null
    
    return
  } 

  try {
    await api.delete(`/admin/billing-plans/${planToDelete.value.id}`)
    toast.success('Billing plan deleted successfully')
    fetchPlans() // Refresh the list after deletion
  } catch (error) {
    console.error('Error deleting billing plan:', error)
    toast.error(error.response?.data?.message || 'Failed to delete billing plan')
  } finally {
    // Reset the plan to delete regardless of outcome
    planToDelete.value = null
  }
}

// --- Formatting --- //
const formatPrice = (price, currency) => {
  if (price === 0) return 'Free'
  
  return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(price)
}

const formatPlanType = type => {
  const types = { recurring: 'Recurring', "one_time": 'One-time', free: 'Free' }
  
  return types[type] || type
}

const formatBillingCycle = cycle => {
  if (!cycle) return 'One-time'
  const cycles = { day: 'Daily', week: 'Weekly', month: 'Monthly', year: 'Yearly' }
  
  return cycles[cycle] || cycle
}

const formatAccessType = (type, days) => {
  const types = { lifetime: 'Lifetime', "while_active": 'While Active', limited: 'Limited' }
  const label = types[type] || type
  if (type === 'limited' && days) {
    return `${label} (${days} days)`
  }
  
  return label
}
</script>

<template>
  <section>
    <!-- Breadcrumb Navigation -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Billing Plans', disabled: true }
      ]"
      class="mb-4"
    />

    <VCard>
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <h2 class="text-h5">
          All Billing Plans
        </h2>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openAddDialog"
        >
          Add Plan
        </VBtn>
      </VCardText>

      <VCardText>
        <!-- Filters -->
        <VRow class="mb-4">
          <VCol
            cols="12"
            md="4"
          >
            <AppAutocomplete
              v-model="filters.courseIds"
              :items="coursesList"
              item-title="title"
              item-value="id"
              label="Filter by Courses"
              placeholder="Select courses"
              multiple
              chips
              closable-chips
              clearable
              @update:model-value="fetchPlans"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppAutocomplete
              v-model="filters.featureIds"
              :items="featuresList"
              item-title="name"
              item-value="id"
              label="Filter by Features"
              placeholder="Select features"
              multiple
              chips
              closable-chips
              clearable
              @update:model-value="fetchPlans"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="filters.billingType"
              :items="[
                { title: 'All Types', value: null },
                { title: 'Recurring', value: 'recurring' },
                { title: 'One-time', value: 'one-time' },
                { title: 'Free', value: 'free' },
              ]"
              item-title="title"
              item-value="value"
              label="Filter by Type"
              clearable
              @update:model-value="fetchPlans"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="filters.billingInterval"
              :items="[
                { title: 'All Cycles', value: null },
                { title: 'Monthly', value: 'month' },
                { title: 'Quarterly', value: 'quarter' },
                { title: 'Yearly', value: 'year' },
              ]"
              item-title="title"
              item-value="value"
              label="Filter by Billing Cycle"
              clearable
              @update:model-value="fetchPlans"
            />
          </VCol>
          <VCol
            cols="12"
            md="4"
          >
            <AppSelect
              v-model="filters.isActive"
              :items="[
                { title: 'All Status', value: null },
                { title: 'Active', value: true },
                { title: 'Inactive', value: false },
              ]"
              item-title="title"
              item-value="value"
              label="Filter by Status"
              clearable
              @update:model-value="fetchPlans"
            />
          </VCol>
        </VRow>

        <VDataTableServer
          v-model:options="options"
          :headers="headers"
          :items="plans"
          :items-length="totalPlans"
          :loading="isLoading"
          class="elevation-1"
          @update:options="fetchPlans"
        >
          <!-- Column Templates -->
          <template #[`item.courses`]="{ item }">
            <div v-if="item.courses && item.courses.length">
              <VTooltip
                v-if="item.courses.length > 1"
                location="top"
              >
                <template #activator="{ props }">
                  <VChip
                    v-bind="props"
                    size="small"
                    color="primary"
                  >
                    {{ item.courses.length }} Courses
                  </VChip>
                </template>
                <ul class="list-none pa-0">
                  <li
                    v-for="c in item.courses"
                    :key="c.id"
                  >
                    {{ c.title }}
                  </li>
                </ul>
              </VTooltip>
              <span
                v-else
                class="font-weight-medium text-primary"
              >
                {{ item.courses[0].title }}
              </span>
            </div>
            <span
              v-else
              class="text-disabled"
            >Global / Unassigned</span>
          </template>

          <template #[`item.name`]="{ item }">
            <span class="font-weight-medium">{{ item.name }}</span>
          </template>

          <template #[`item.price`]="{ item }">
            <span>{{ formatPrice(item.price, item.currency) }}</span>
          </template>

          <template #[`item.billingType`]="{ item }">
            <span>{{ formatPlanType(item.billingType) }}</span>
          </template>

          <template #[`item.billingInterval`]="{ item }">
            <span>{{ formatBillingCycle(item.billingInterval) }}</span>
          </template>

          <template #[`item.accessType`]="{ item }">
            <VChip
              size="small"
              variant="tonal"
              :color="item.accessType === 'lifetime' ? 'success' : 'info'"
            >
              {{ formatAccessType(item.accessType, item.accessDurationDays) }}
            </VChip>
          </template>

          <template #[`item.isActive`]="{ item }">
            <VChip
              :color="item.isActive ? 'success' : 'error'"
              size="small"
              label
            >
              {{ item.isActive ? 'Active' : 'Inactive' }}
            </VChip>
          </template>

          <!-- Actions column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex gap-1 align-center">
              <VBtn
                icon
                variant="text"
                color="primary"
                size="small"
                @click="openEditDialog(item)"
              >
                <VIcon icon="tabler-edit" />
              </VBtn>
              <VSwitch
                :model-value="item.isActive"
                color="success"
                hide-details
                density="compact"
                class="flex-shrink-0"
                @update:model-value="togglePlanStatus(item)"
              />
              <VBtn
                icon
                variant="text"
                color="error"
                size="small"
                @click="openDeleteDialog(item)"
              >
                <VIcon icon="tabler-trash" />
              </VBtn>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <!-- Add/Edit Plan Dialog -->
    <AddEditBillingPlanDialog
      v-model:is-dialog-visible="isPlanDialogVisible"
      :dialog-mode="dialogMode"
      :data="editingPlan"
      @submit-success="onFormSubmitSuccess"
    />

    <!-- Deletion Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isDeleteDialogVisible"
      confirmation-question="Are you sure you want to delete this billing plan?"
      confirm-title="Billing Plan Deleted"
      confirm-msg="The billing plan has been deleted successfully."
      @confirm="handleDeleteConfirm"
    />
  </section>
</template>