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
  courseId: null,
  planId: null,
  amount: 0,
  paymentMethod: 'manual',
  paymentDate: '',
  receiptNumber: '',
  notes: '',
  currency: 'USD',
  autoGeneratePdf: true,
  notifyUser: true,
  createSubscription: true,
})

// UI state for autocompletes
const selectedUser = ref(null)
const selectedCourse = ref(null)
const selectedPlan = ref(null)

const plans = ref([])
const loadingPlans = ref(false)

// New state properties
const isSystemGenerated = ref(false)
const isLinkedToSubscription = ref(false)

const validationRules = {
  userId: [requiredValidator],
  courseId: [requiredValidator],
  planId: [requiredValidator],
  amount: [
    requiredValidator,
    v => !isNaN(parseFloat(v)) && isFinite(v) || 'Amount must be a valid number',
    v => v >= 0 || 'Amount must not be negative',
  ],
  paymentMethod: [requiredValidator],
  paymentDate: [requiredValidator],
}

const paymentMethods = [
  { title: 'Credit Card', value: 'credit_card' },
  { title: 'PayPal', value: 'paypal' },
  { title: 'Bank Transfer', value: 'bank_transfer' },
  { title: 'Manual', value: 'manual' },
]

const dialogTitle = computed(() => props.dialogMode === 'add' ? 'Add New Receipt' : 'Edit Receipt')

const resetFormData = () => {
  form.value = {
    userId: null,
    courseId: null,
    planId: null,
    amount: 0,
    paymentMethod: 'manual',
    paymentDate: new Date().toISOString().split('T')[0],
    receiptNumber: '',
    notes: '',
    currency: 'USD',
    autoGeneratePdf: true,
    notifyUser: true,
    createSubscription: true,
  }
  selectedUser.value = null
  selectedCourse.value = null
  selectedPlan.value = null
  isSystemGenerated.value = false
  isLinkedToSubscription.value = false
}

// Watchers to sync IDs
watch(selectedUser, val => {
  form.value.userId = val?.id || val
})

watch(selectedCourse, val => {
  form.value.courseId = val?.id || val
  if (val) {
    fetchCoursePlans()
  }
})

watch(selectedPlan, val => {
  form.value.planId = val?.id || val
  updateAmountFromPlan()
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
  const courseId = form.value.courseId
  if (!courseId) return
  
  loadingPlans.value = true
  try {
    console.log(`Fetching subscription plans for course ID: ${courseId}`)

    const response = await api.get(`/admin/courses/${courseId}/subscription-plans`)

    plans.value = response.items || []
  } catch (error) {
    toast.error('Failed to load subscription plans.')
  } finally {
    loadingPlans.value = false
  }
}

watch(() => [props.isDialogVisible, props.data, props.dialogMode], async ([isVisible, receipt, mode]) => {
  if (isVisible) {
    if (mode === 'edit' && receipt && receipt.id) {
      isSystemGenerated.value = receipt.sourceType !== 'manual'
      isLinkedToSubscription.value = receipt.isLinkedToSubscription

      selectedUser.value = {
        id: receipt.user.id,
        fullName: receipt.user.fullName,
      }
      
      if (receipt.itemType == 'course') {
        selectedCourse.value = {
          id: receipt.course.id,
          title: receipt.course.title,
        }
        selectedPlan.value = null
      } else if (receipt.itemType == 'subscriptionPlan' && receipt.subscriptionPlan.course) {
        selectedCourse.value = {
          id: receipt.subscriptionPlan.course.id,
          title: receipt.subscriptionPlan.course.title,
        }
        selectedPlan.value = {
          id: receipt.subscriptionPlan.id,
          name: receipt.subscriptionPlan.name,
        }
      }

      form.value = {
        userId: receipt.user.id,
        courseId: selectedCourse.value?.id,
        planId: selectedPlan.value?.id,
        amount: receipt.amount,
        paymentMethod: receipt.payment?.paymentMethod || 'manual',
        paymentDate: receipt.payment?.paymentDetails?.paymentDate || new Date(receipt.createdAt).toISOString().split('T')[0],
        receiptNumber: receipt.receiptNumber,
        notes: receipt.payment?.paymentDetails?.notes || '',
        currency: receipt.currency || 'USD',
        autoGeneratePdf: true,
        notifyUser: true,
        createSubscription: isLinkedToSubscription.value,
      }

      if (form.value.courseId) {
        await fetchCoursePlans()
      }
    } else {
      resetFormData()
    }
    if (formRef.value) {
      formRef.value.resetValidation()
    }
  }
}, { immediate: true })

const updateAmountFromPlan = () => {
  const planId = form.value.planId
  const plan = plans.value.find(p => p.id === planId)
  
  if (plan) {
    form.value.amount = plan.price
  }
}

const { isLoading: isSubmitting, onSubmit, validationErrors } = useCrudSubmit({
  formRef,
  form,
  apiEndpoint: computed(() => props.dialogMode === 'add' ? '/admin/receipts' : `/admin/receipts/${props.data.id}`),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  emit: customEmit,
  isFormData: false,
  successMessage: computed(() => props.dialogMode === 'add' ? 'Receipt created successfully.' : 'Receipt updated successfully.'),
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
        <VAlert
          v-if="isSystemGenerated"
          type="warning"
          variant="tonal"
          class="mb-4"
        >
          System-generated receipts cannot be edited.
        </VAlert>
        <VAlert
          v-if="isLinkedToSubscription && !isSystemGenerated"
          type="info"
          variant="tonal"
          class="mb-4"
        >
          This receipt is linked to a subscription. Some fields are locked.
        </VAlert>
        <VForm
          ref="formRef"
          :disabled="isSystemGenerated"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-if="isLinkedToSubscription || isSystemGenerated"
                :model-value="selectedUser?.fullName"
                label="User"
                readonly
                disabled
              />
              <AppServerSideAutocomplete
                v-else
                v-model="selectedUser"
                label="User"
                placeholder="Select a user"
                api-link="/admin/users/select-fields"
                item-title="fullName"
                item-value="id"
                :rules="validationRules.userId"
                :error-messages="validationErrors.userId"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-if="isLinkedToSubscription || isSystemGenerated"
                :model-value="selectedCourse?.title"
                label="Course"
                readonly
                disabled
              />
              <AppServerSideAutocomplete
                v-else
                v-model="selectedCourse"
                label="Course"
                placeholder="Select a course"
                api-link="/admin/courses/select-fields"
                item-title="title"
                item-value="id"
                :rules="validationRules.courseId"
                :error-messages="validationErrors.courseId"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-if="isLinkedToSubscription || isSystemGenerated"
                :model-value="selectedPlan?.name"
                label="Subscription Plan"
                readonly
                disabled
              />
              <AppAutocomplete
                v-else
                v-model="selectedPlan"
                label="Subscription Plan"
                placeholder="Select a plan"
                :items="plans"
                item-title="name"
                item-value="id"
                :loading="loadingPlans"
                :disabled="!selectedCourse"
                :rules="validationRules.planId"
                :error-messages="validationErrors.planId"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-if="isLinkedToSubscription || isSystemGenerated"
                :model-value="form.amount"
                label="Amount"
                readonly
                disabled
              />
              <AppTextField
                v-else
                v-model.number="form.amount"
                label="Amount"
                type="number"
                min="0"
                step="0.01"
                :disabled="isLinkedToSubscription || isSystemGenerated"
                :rules="validationRules.amount"
                :error-messages="validationErrors.amount"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-if="isLinkedToSubscription || isSystemGenerated"
                :model-value="paymentMethods.find(method => method.value === 'manual')?.title"
                label="Payment Method"
                readonly
                disabled
              />
              <AppSelect
                v-else
                v-model="form.paymentMethod"
                label="Payment Method"
                :items="paymentMethods"
                :rules="validationRules.paymentMethod"
                :error-messages="validationErrors.paymentMethod"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppDateTimePicker
                v-model="form.paymentDate"
                label="Payment Date"
                :rules="validationRules.paymentDate"
                :disabled="isSystemGenerated"
                :error-messages="validationErrors.paymentDate"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                :model-value="form.receiptNumber"
                label="Receipt Number"
                readonly
                disabled
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.notes"
                label="Notes"
                rows="3"
                :disabled="isSystemGenerated"
                :error-messages="validationErrors.notes"
              />
            </VCol>
            <VCol cols="12">
              <VDivider class="mb-3" />
              <h6 class="text-h6 mb-2">
                Additional Options
              </h6>
              <VRow>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VCheckbox
                    v-model="form.autoGeneratePdf"
                    label="Auto-generate PDF"
                    :disabled="isSystemGenerated"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VCheckbox
                    v-model="form.notifyUser"
                    label="Notify User"
                    :disabled="isSystemGenerated"
                  />
                </VCol>
                <VCol
                  v-if="!isLinkedToSubscription"
                  cols="12"
                  md="4"
                >
                  <VCheckbox
                    v-model="form.createSubscription"
                    label="Create Subscription"
                    :disabled="isLinkedToSubscription || isSystemGenerated"
                  />
                </VCol>
              </VRow>
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
          :disabled="isSystemGenerated"
          @click="onSubmit"
        >
          {{ props.dialogMode === 'add' ? 'Create' : 'Update' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
