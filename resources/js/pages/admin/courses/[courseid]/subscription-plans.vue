<script setup>
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import SubscriptionPlanDialog from '@/components/dialogs/SubscriptionPlanDialog.vue'
import api from '@/utils/api'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'subscriptions',
  },
})

const toast = useToast()
const route = useRoute()
const isLoading = ref(false)
const course = ref(null)
const plans = ref([])
const totalPlans = ref(0)
const options = ref({})
const availableLevels = ref([])

// Dialog states
const isPlanDialogVisible = ref(false)
const isDeleteDialogVisible = ref(false)
const dialogMode = ref('add')
const editingPlan = ref(null)
const planToDelete = ref(null)

// Get course ID from route parameter
const courseId = computed(() => parseInt(route.params.courseid))

// Headers for data table
const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Price', key: 'price' },
  { title: 'Type', key: 'planType' },
  { title: 'Billing Cycle', key: 'billingCycle' },
  { title: 'Status', key: 'isActive' },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Fetch course details
const fetchCourse = async () => {
  if (!courseId.value) return
  try {
    const response = await api.get(`/admin/courses/${courseId.value}`)

    course.value = response
  } catch (error) {
    console.error('Error fetching course:', error)
    toast.error('Failed to load course details')
  }
}

// Fetch subscription plans for the course
const fetchPlans = async () => {
  if (!courseId.value) return
  isLoading.value = true
  try {
    const { page, itemsPerPage, sortBy } = options.value

    const response = await api.get(`/admin/courses/${courseId.value}/subscription-plans`, {
      params: {
        page,
        itemsPerPage,
        sortBy,
      },
    })

    plans.value = response.items
    totalPlans.value = response.total
  } catch (error) {
    console.error('Error fetching subscription plans:', error)
    toast.error('Failed to load subscription plans')
  } finally {
    isLoading.value = false
  }
}

// Fetch levels for the course (for level-based access)
const fetchLevels = async () => {
  if (!courseId.value) return
  try {
    const response = await api.get(`/admin/courses/${courseId.value}/levels`)

    availableLevels.value = response
  } catch (error) {
    console.error('Error fetching levels:', error)
    toast.error('Failed to load levels')
  }
}

// Toggle plan status
const togglePlanStatus = async plan => {
  try {
    const updatedPlan = { ...plan, isActive: !plan.isActive }
    const response = await api.put(`/admin/courses/${courseId.value}/subscription-plans/${plan.id}`, updatedPlan)
    const index = plans.value.findIndex(p => p.id === plan.id)
    if (index !== -1) {
      plans.value[index] = response
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

// Open dialog for adding new subscription plan
const openAddDialog = () => {
  dialogMode.value = 'add'
  editingPlan.value = null
  isPlanDialogVisible.value = true
}

// Open dialog for editing subscription plan
const openEditDialog = plan => {
  dialogMode.value = 'edit'
  editingPlan.value = { ...plan }
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
  if (!result.confirmed || !questionToDelete.value){
    planToDelete.value = null
    
    return
  } 

  try {
    await api.delete(`/admin/courses/${courseId.value}/subscription-plans/${planToDelete.value.id}`)
    toast.success('Subscription plan deleted successfully')
    fetchPlans() // Refresh the list after deletion
  } catch (error) {
    console.error('Error deleting subscription plan:', error)
    toast.error('Failed to delete subscription plan')
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
  const types = { recurring: 'Recurring', 'one-time': 'One-time', free: 'Free' }
  
  return types[type] || type
}

const formatBillingCycle = cycle => {
  const cycles = { monthly: 'Monthly', yearly: 'Yearly', 'one-time': 'One-time' }
  
  return cycles[cycle] || cycle
}

// --- Lifecycle --- //
onMounted(() => {
  fetchCourse()
  fetchLevels()
})
</script>

<template>
  <section>
    <!-- Breadcrumb Navigation -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Courses', to: '/admin/courses' },
        { title: course ? course.title : 'Course', disabled: true },
        { title: 'Subscription Plans', disabled: true }
      ]"
      class="mb-4"
    />

    <VCard v-if="course">
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <h2 class="text-h5">
          Subscription Plans for {{ course.title }}
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
          <template #[`item.name`]="{ item }">
            <span class="font-weight-medium">{{ item.name }}</span>
          </template>

          <template #[`item.price`]="{ item }">
            <span>{{ formatPrice(item.price, item.currency) }}</span>
          </template>

          <template #[`item.planType`]="{ item }">
            <span>{{ formatPlanType(item.planType) }}</span>
          </template>

          <template #[`item.billingCycle`]="{ item }">
            <span>{{ formatBillingCycle(item.billingCycle) }}</span>
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

    <!-- Loading State -->
    <VCard
      v-else
      class="text-center py-8"
    >
      <VCardText>
        <VProgressCircular
          indeterminate
          color="primary"
        />
        <div class="mt-4">
          Loading course details...
        </div>
      </VCardText>
    </VCard>

    <!-- Add/Edit Plan Dialog -->
    <SubscriptionPlanDialog
      v-model:is-dialog-open="isPlanDialogVisible"
      :dialog-mode="dialogMode"
      :plan="editingPlan"
      :course-id="courseId"
      :available-levels="availableLevels"
      @submit-success="onFormSubmitSuccess"
    />

    <!-- Deletion Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isDeleteDialogVisible"
      confirmation-question="Are you sure you want to delete this subscription plan?"
      confirm-title="Subscription Plan Deleted"
      confirm-msg="The subscription plan has been deleted successfully."
      @confirm="handleDeleteConfirm"
    />
  </section>
</template>
