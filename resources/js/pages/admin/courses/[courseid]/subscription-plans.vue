<script setup>
import api from '@/utils/api'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'

const toast = useToast()
const route = useRoute()
const isLoading = ref(false)
const course = ref(null)
const plans = ref([])
const isDialogOpen = ref(false)
const dialogMode = ref('add') // 'add' or 'edit'

const newPlan = ref({
  name: '',
  description: '',
  price: 0,
  currency: 'USD',
  billing_cycle: 'one-time',
  plan_type: 'one-time',
  is_free: false,
  duration_days: null,
  is_active: true,
  course_id: null,
  accessible_levels: [],
})

const editingPlan = ref(null)
const availableLevels = ref([])

// Headers for data table
const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Price', key: 'price' },
  { title: 'Type', key: 'plan_type' },
  { title: 'Billing Cycle', key: 'billing_cycle' },
  { title: 'Status', key: 'is_active' },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Get course ID from route parameter
const courseId = computed(() => route.params.courseid)

// Fetch course details
const fetchCourse = async () => {
  if (!courseId.value) return
  
  try {
    const response = await api.get(`/admin/courses/${courseId.value}`)

    course.value = response.data
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
    const response = await api.get(`/admin/courses/${courseId.value}/subscription-plans`)

    plans.value = response.data || []
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

    availableLevels.value = response.data || []
  } catch (error) {
    console.error('Error fetching levels:', error)
    toast.error('Failed to load levels')
  }
}

// Create new subscription plan
const createPlan = async () => {
  if (!courseId.value) {
    toast.error('Course ID is missing')
    
    return
  }
  
  newPlan.value.course_id = parseInt(courseId.value)
  
  // If it's a free plan, set price to 0
  if (newPlan.value.is_free || newPlan.value.plan_type === 'free') {
    newPlan.value.price = 0
    newPlan.value.is_free = true
    newPlan.value.plan_type = 'free'
  }
  
  try {
    const response = await api.post('/admin/subscription-plans', newPlan.value)

    plans.value.push(response.data)
    toast.success('Subscription plan created successfully')
    isDialogOpen.value = false
    resetForm()
  } catch (error) {
    console.error('Error creating subscription plan:', error)
    toast.error('Failed to create subscription plan')
  }
}

// Update subscription plan
const updatePlan = async () => {
  // If it's a free plan, set price to 0
  if (editingPlan.value.is_free || editingPlan.value.plan_type === 'free') {
    editingPlan.value.price = 0
    editingPlan.value.is_free = true
    editingPlan.value.plan_type = 'free'
  }
  
  try {
    const response = await api.put(`/admin/subscription-plans/${editingPlan.value.id}`, editingPlan.value)
    const index = plans.value.findIndex(p => p.id === editingPlan.value.id)
    if (index !== -1) {
      plans.value[index] = response.data
    }
    toast.success('Subscription plan updated successfully')
    isDialogOpen.value = false
    resetForm()
  } catch (error) {
    console.error('Error updating subscription plan:', error)
    toast.error('Failed to update subscription plan')
  }
}

// Delete subscription plan
const deletePlan = async id => {
  if (!confirm('Are you sure you want to delete this subscription plan?')) return
  
  try {
    await api.delete(`/admin/subscription-plans/${id}`)
    plans.value = plans.value.filter(p => p.id !== id)
    toast.success('Subscription plan deleted successfully')
  } catch (error) {
    console.error('Error deleting subscription plan:', error)
    toast.error('Failed to delete subscription plan')
  }
}

// Toggle plan active status
const togglePlanStatus = async plan => {
  try {
    const updatedPlan = { ...plan, is_active: !plan.is_active }
    const response = await api.put(`/admin/subscription-plans/${plan.id}`, updatedPlan)
    const index = plans.value.findIndex(p => p.id === plan.id)
    if (index !== -1) {
      plans.value[index] = response.data
    }
    toast.success(`Plan ${plan.is_active ? 'deactivated' : 'activated'} successfully`)
  } catch (error) {
    console.error('Error toggling plan status:', error)
    toast.error('Failed to update plan status')
  }
}

// Open dialog for adding new subscription plan
const openAddDialog = () => {
  dialogMode.value = 'add'
  resetForm()
  isDialogOpen.value = true
}

// Open dialog for editing subscription plan
const openEditDialog = plan => {
  dialogMode.value = 'edit'
  editingPlan.value = { ...plan }
  isDialogOpen.value = true
}

// Reset form
const resetForm = () => {
  newPlan.value = {
    name: '',
    description: '',
    price: 0,
    currency: 'USD',
    billing_cycle: 'one-time',
    plan_type: 'one-time',
    is_free: false,
    duration_days: null,
    is_active: true,
    course_id: courseId.value ? parseInt(courseId.value) : null,
    accessible_levels: [],
  }
  editingPlan.value = null
}

// Save subscription plan (create or update)
const savePlan = () => {
  if (dialogMode.value === 'add') {
    createPlan()
  } else {
    updatePlan()
  }
}

// Format price with currency
const formatPrice = (price, currency) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  }).format(price)
}

// Format plan type for display
const formatPlanType = type => {
  switch (type) {
  case 'recurring':
    return 'Recurring'
  case 'one-time':
    return 'One-time'
  case 'free':
    return 'Free'
  default:
    return type
  }
}

// Format billing cycle for display
const formatBillingCycle = cycle => {
  switch (cycle) {
  case 'monthly':
    return 'Monthly'
  case 'yearly':
    return 'Yearly'
  case 'one-time':
    return 'One-time'
  default:
    return cycle
  }
}

onMounted(() => {
  fetchCourse()
  fetchPlans()
  fetchLevels()
})
</script>

<template>
  <section>
    <VCard v-if="course">
      <VCardText class="d-flex justify-space-between align-center">
        <h2>Subscription Plans for {{ course.title }}</h2>
        <VBtn 
          color="primary" 
          prepend-icon="tabler-plus"
          @click="openAddDialog"
        >
          Add Plan
        </VBtn>
      </VCardText>

      <VCardText>
        <VDataTable
          :headers="headers"
          :items="plans"
          :loading="isLoading"
          class="elevation-1"
        >
          <!-- Name column -->
          <template #[`item.name`]="{ item }">
            <span class="font-weight-medium">{{ item.name }}</span>
          </template>
          
          <!-- Price column -->
          <template #[`item.price`]="{ item }">
            <span>{{ formatPrice(item.price, item.currency) }}</span>
          </template>
          
          <!-- Plan type column -->
          <template #[`item.plan_type`]="{ item }">
            <span>{{ formatPlanType(item.plan_type) }}</span>
          </template>
          
          <!-- Billing cycle column -->
          <template #[`item.billing_cycle`]="{ item }">
            <span>{{ formatBillingCycle(item.billing_cycle) }}</span>
          </template>
          
          <!-- Status column -->
          <template #[`item.is_active`]="{ item }">
            <VChip
              :color="item.is_active ? 'success' : 'error'"
              size="small"
            >
              {{ item.is_active ? 'Active' : 'Inactive' }}
            </VChip>
          </template>

          <!-- Actions column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex gap-2">
              <VBtn
                icon
                variant="text"
                color="primary"
                size="small"
                @click="openEditDialog(item)"
              >
                <VIcon icon="tabler-edit" />
              </VBtn>
              <VBtn
                icon
                variant="text"
                :color="item.is_active ? 'warning' : 'success'"
                size="small"
                @click="togglePlanStatus(item)"
              >
                <VIcon :icon="item.is_active ? 'tabler-eye-off' : 'tabler-eye'" />
              </VBtn>
              <VBtn
                icon
                variant="text"
                color="error"
                size="small"
                @click="deletePlan(item.id)"
              >
                <VIcon icon="tabler-trash" />
              </VBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

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
    <VDialog
      v-model="isDialogOpen"
      max-width="700px"
    >
      <VCard>
        <VCardTitle>
          <span>{{ dialogMode === 'add' ? 'Add New Subscription Plan' : 'Edit Subscription Plan' }}</span>
        </VCardTitle>

        <VCardText>
          <VForm @submit.prevent="savePlan">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="dialogMode === 'add' ? newPlan.name : editingPlan.name"
                  label="Plan Name"
                  required
                />
              </VCol>
              
              <VCol cols="12">
                <VTextarea
                  v-model="dialogMode === 'add' ? newPlan.description : editingPlan.description"
                  label="Description"
                  rows="3"
                />
              </VCol>
              
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="dialogMode === 'add' ? newPlan.plan_type : editingPlan.plan_type"
                  :items="[
                    { title: 'One-time Payment', value: 'one-time' },
                    { title: 'Recurring Subscription', value: 'recurring' },
                    { title: 'Free Access', value: 'free' }
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Plan Type"
                  required
                />
              </VCol>
              
              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="dialogMode === 'add' ? newPlan.billing_cycle : editingPlan.billing_cycle"
                  :items="[
                    { title: 'One-time', value: 'one-time' },
                    { title: 'Monthly', value: 'monthly' },
                    { title: 'Yearly', value: 'yearly' }
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Billing Cycle"
                  :disabled="(dialogMode === 'add' ? newPlan.plan_type : editingPlan.plan_type) !== 'recurring'"
                  required
                />
              </VCol>
              
              <VCol
                cols="6"
                md="4"
              >
                <VTextField
                  v-model.number="dialogMode === 'add' ? newPlan.price : editingPlan.price"
                  label="Price"
                  type="number"
                  min="0"
                  step="0.01"
                  :disabled="(dialogMode === 'add' ? newPlan.plan_type : editingPlan.plan_type) === 'free'"
                  required
                />
              </VCol>
              
              <VCol
                cols="6"
                md="4"
              >
                <VSelect
                  v-model="dialogMode === 'add' ? newPlan.currency : editingPlan.currency"
                  :items="[
                    { title: 'USD ($)', value: 'USD' },
                    { title: 'EUR (€)', value: 'EUR' },
                    { title: 'GBP (£)', value: 'GBP' }
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Currency"
                  :disabled="(dialogMode === 'add' ? newPlan.plan_type : editingPlan.plan_type) === 'free'"
                  required
                />
              </VCol>
              
              <VCol
                cols="12"
                md="4"
              >
                <VTextField
                  v-model.number="dialogMode === 'add' ? newPlan.duration_days : editingPlan.duration_days"
                  label="Duration (days)"
                  type="number"
                  min="1"
                  :disabled="(dialogMode === 'add' ? newPlan.billing_cycle : editingPlan.billing_cycle) !== 'one-time'"
                  hint="Leave empty for unlimited access"
                  persistent-hint
                />
              </VCol>
              
              <VCol cols="12">
                <VSwitch
                  v-model="dialogMode === 'add' ? newPlan.is_active : editingPlan.is_active"
                  label="Active"
                  color="success"
                />
              </VCol>
              
              <VCol cols="12">
                <p class="text-subtitle-1 mb-2">
                  Accessible Levels
                </p>
                <VCheckboxGroup
                  v-model="dialogMode === 'add' ? newPlan.accessible_levels : editingPlan.accessible_levels"
                  :items="availableLevels.map(level => ({ title: level.title, value: level.id }))"
                  item-title="title"
                  item-value="value"
                  multiple
                  column
                >
                  <template #label="{ item }">
                    {{ item.title }}
                  </template>
                </VCheckboxGroup>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VCardActions>
          <VSpacer />
          <VBtn
            color="error"
            variant="text"
            @click="isDialogOpen = false"
          >
            Cancel
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            @click="savePlan"
          >
            Save
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </section>
</template>
