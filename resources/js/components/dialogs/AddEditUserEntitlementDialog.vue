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
  billingPlanId: null,
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
  billingPlanId: [requiredValidator],
  startsAt: [requiredValidator],
  status: [requiredValidator],
}

const statusOptions = [
  { title: 'Active', value: 'active' },
  { title: 'Canceled', value: 'canceled' },
  { title: 'Expired', value: 'expired' },
]

const dialogTitle = computed(() => props.dialogMode === 'add' ? 'Add User Entitlement' : 'Edit User Entitlement')

const resetFormData = () => {
  form.value = {
    userId: null,
    billingPlanId: null,
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
  form.value.billingPlanId = val?.id || val
  
  // Auto-calculate endsAt based on plan duration if adding new
  if (props.dialogMode === 'add' && val && form.value.startsAt) {
    const startDate = new Date(form.value.startsAt)
    const endDate = new Date(startDate)
    
    if (val.billingInterval === 'month') {
      endDate.setMonth(startDate.getMonth() + 1)
    } else if (val.billingInterval === 'year') {
      endDate.setFullYear(startDate.getFullYear() + 1)
    } else if (val.accessDurationDays) {
      endDate.setDate(startDate.getDate() + val.accessDurationDays)
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
    const response = await api.get(`/admin/courses/${courseId}/billing-plans`)

    // API returns { items: [], total: 0 }
    plans.value = response.items || []
  } catch (error) {
    toast.error('Failed to load billing plans.')
  } finally {
    loadingPlans.value = false
  }
}

watch(() => [props.isDialogVisible, props.data, props.dialogMode], async ([isVisible, entitlement, mode]) => {
  if (isVisible) {
    if (mode === 'edit' && entitlement && entitlement.id) {
      selectedUser.value = {
        id: entitlement.user.id,
        fullName: entitlement.user.fullName,
      }
      
      if (entitlement.billingPlan?.courses?.length) {
        selectedCourse.value = {
          id: entitlement.billingPlan.courses[0].id,
          title: entitlement.billingPlan.courses[0].title,
        }
        
        await fetchCoursePlans()
        
        selectedPlan.value = {
          id: entitlement.billingPlan.id,
          name: entitlement.billingPlan.name,
        }
      }

      form.value = {
        userId: entitlement.userId,
        billingPlanId: entitlement.billingPlanId,
        startsAt: entitlement.startsAt ? new Date(entitlement.startsAt).toISOString().split('T')[0] : '',
        endsAt: entitlement.endsAt ? new Date(entitlement.endsAt).toISOString().split('T')[0] : '',
        status: entitlement.status,
        autoRenew: !!entitlement.autoRenew,
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
  apiEndpoint: computed(() => props.dialogMode === 'add' ? '/admin/user-entitlements' : `/admin/user-entitlements/${props.data.id}`),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  emit: customEmit,
  isFormData: false,
  successMessage: computed(() => props.dialogMode === 'add' ? 'Entitlement created successfully.' : 'Entitlement updated successfully.'),
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
                label="Billing Plan"
                placeholder="Select a plan"
                :items="plans"
                item-title="name"
                item-value="id"
                :loading="loadingPlans"
                :disabled="!selectedCourse"
                :rules="validationRules.billingPlanId"
                :error-messages="validationErrors.billingPlanId"
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
