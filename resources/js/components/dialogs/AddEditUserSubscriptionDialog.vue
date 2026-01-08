<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import api from '@/utils/api'
import { requiredValidator } from '@core/utils/validators'
import { computed, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  dialogMode: {
    type: String,
    default: 'add',
    validator: value => ['add', 'edit'].includes(value),
  },
  data: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:isDialogVisible', 'submitSuccess'])
const toast = useToast()
const formRef = ref(null)

// Form fields
const form = ref({
  userId: null,
  subscriptionPlanId: null,
  startsAt: '',
  endsAt: '',
  status: 'active',
  autoRenew: false,
})

// UI state for autocompletes
const selectedUser = ref(null)
const selectedCourse = ref(null)
const selectedPlan = ref(null)

const plans = ref([])
const loadingPlans = ref(false)

const validationRules = {
  userId: [requiredValidator],
  subscriptionPlanId: [requiredValidator],
  startsAt: [requiredValidator],
  status: [requiredValidator],
}

const statusOptions = [
  { title: 'Active', value: 'active' },
  { title: 'Canceled', value: 'canceled' },
  { title: 'Expired', value: 'expired' },
]

const dialogTitle = computed(() => props.dialogMode === 'add' ? 'Add User Subscription' : 'Edit User Subscription')

const resetFormData = () => {
  form.value = {
    userId: null,
    subscriptionPlanId: null,
    startsAt: new Date().toISOString().split('T')[0],
    endsAt: '',
    status: 'active',
    autoRenew: false,
  }
  selectedUser.value = null
  selectedCourse.value = null
  selectedPlan.value = null
}

// Watchers to sync IDs
watch(selectedUser, val => {
  form.value.userId = val?.id || val
})

watch(selectedCourse, val => {
  if (val) {
    fetchCoursePlans()
  } else {
    plans.value = []
    selectedPlan.value = null
  }
})

watch(selectedPlan, val => {
  form.value.subscriptionPlanId = val?.id || val
  
  // Auto-calculate endsAt based on plan duration if adding new
  if (props.dialogMode === 'add' && val && form.value.startsAt) {
    const startDate = new Date(form.value.startsAt)
    const endDate = new Date(startDate)
    
    if (val.durationUnit === 'month') {
      endDate.setMonth(startDate.getMonth() + val.duration)
    } else if (val.durationUnit === 'day') {
      endDate.setDate(startDate.getDate() + val.duration)
    } else if (val.durationUnit === 'year') {
      endDate.setFullYear(startDate.getFullYear() + val.duration)
    }
    
    form.value.endsAt = endDate.toISOString().split('T')[0]
  }
})

// Custom emit to map 'saved' to 'submitSuccess'
const customEmit = (event, ...args) => {
  if (event === 'saved') {
    emit('submitSuccess', ...args)
  } else {
    emit(event, ...args)
  }
}

const fetchCoursePlans = async () => {
  const courseId = selectedCourse.value?.id || selectedCourse.value
  if (!courseId) return
  
  loadingPlans.value = true
  try {
    const response = await api.get(`/admin/courses/${courseId}/subscription-plans`)

    plans.value = response.items || []
  } catch (error) {
    toast.error('Failed to load subscription plans.')
  } finally {
    loadingPlans.value = false
  }
}

watch(() => [props.isDialogVisible, props.data, props.dialogMode], async ([isVisible, subscription, mode]) => {
  if (isVisible) {
    if (mode === 'edit' && subscription && subscription.id) {
      selectedUser.value = {
        id: subscription.user.id,
        fullName: subscription.user.fullName,
      }
      
      if (subscription.plan?.course) {
        selectedCourse.value = {
          id: subscription.plan.course.id,
          title: subscription.plan.course.title,
        }
        
        await fetchCoursePlans()
        
        selectedPlan.value = {
          id: subscription.plan.id,
          name: subscription.plan.name,
        }
      }

      form.value = {
        userId: subscription.userId,
        subscriptionPlanId: subscription.subscriptionPlanId,
        startsAt: subscription.startsAt ? new Date(subscription.startsAt).toISOString().split('T')[0] : '',
        endsAt: subscription.endsAt ? new Date(subscription.endsAt).toISOString().split('T')[0] : '',
        status: subscription.status,
        autoRenew: !!subscription.autoRenew,
      }
    } else {
      resetFormData()
    }
    if (formRef.value) {
      formRef.value.resetValidation()
    }
  }
}, { immediate: true })

const { isLoading: isSubmitting, onSubmit, validationErrors } = useCrudSubmit({
  formRef,
  form,
  apiEndpoint: computed(() => props.dialogMode === 'add' ? '/admin/user-subscriptions' : `/admin/user-subscriptions/${props.data.id}`),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  emit: customEmit,
  isFormData: false,
  successMessage: computed(() => props.dialogMode === 'add' ? 'Subscription created successfully.' : 'Subscription updated successfully.'),
})

const onDialogVisibleUpdate = val => {
  emit('update:isDialogVisible', val)
  if (!val) {
    resetFormData()
  }
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="800"
    persistent
    @update:model-value="onDialogVisibleUpdate(false)"
  >
    <DialogCloseBtn @click="onDialogVisibleUpdate(false)" />
    <VCard :title="dialogTitle">
      <VCardText>
        <VForm
          ref="formRef"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppServerSideAutocomplete
                v-model="selectedUser"
                label="User"
                placeholder="Select a user"
                api-link="/admin/users/select-fields"
                item-title="fullName"
                item-value="id"
                :pre-selected-items="selectedUser ? [selectedUser] : []"
                :rules="validationRules.userId"
                :error-messages="validationErrors.userId"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppServerSideAutocomplete
                v-model="selectedCourse"
                label="Course"
                placeholder="Select a course"
                api-link="/admin/courses/select-fields"
                item-title="title"
                item-value="id"
                :pre-selected-items="selectedCourse ? [selectedCourse] : []"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppAutocomplete
                v-model="selectedPlan"
                label="Subscription Plan"
                placeholder="Select a plan"
                :items="plans"
                item-title="name"
                item-value="id"
                :loading="loadingPlans"
                :disabled="!selectedCourse"
                :rules="validationRules.subscriptionPlanId"
                :error-messages="validationErrors.subscriptionPlanId"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.status"
                label="Status"
                :items="statusOptions"
                :rules="validationRules.status"
                :error-messages="validationErrors.status"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppDateTimePicker
                v-model="form.startsAt"
                label="Starts At"
                :rules="validationRules.startsAt"
                :error-messages="validationErrors.startsAt"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppDateTimePicker
                v-model="form.endsAt"
                label="Ends At"
                :error-messages="validationErrors.endsAt"
                clearable
              />
            </VCol>
            <VCol cols="12">
              <VCheckbox
                v-model="form.autoRenew"
                label="Auto Renew"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          @click="onDialogVisibleUpdate(false)"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="onSubmit"
        >
          {{ props.dialogMode === 'add' ? 'Create' : 'Update' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
