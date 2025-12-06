<script setup>
import { requiredValidator } from '@/@core/utils/validators'
import api from '@/utils/api'
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
  receipt: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:isDialogVisible', 'submitSuccess'])
const toast = useToast()
const formRef = ref(null)

// Form fields
const user = ref(null)
const course = ref(null)
const planId = ref(null)
const amount = ref(0)
const paymentMethod = ref({ title: 'Manual', value: 'Manual' })
const paymentDate = ref('')
const receiptNumber = ref('')
const notes = ref('')
const currency = ref('USD')
const autoGeneratePdf = ref(true)
const notifyUser = ref(true)
const createSubscription = ref(true)
const isSubmitting = ref(false)
const plans = ref([])
const loadingPlans = ref(false)

// New state properties
const isSystemGenerated = ref(false)
const isLinkedToSubscription = ref(false)

const validationRules = {
  user: [requiredValidator],
  course: [requiredValidator],
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
  user.value = null
  course.value = null
  planId.value = null
  amount.value = 0
  paymentMethod.value = 'manual'
  paymentDate.value = new Date().toISOString().split('T')[0]
  receiptNumber.value = ''
  notes.value = ''
  currency.value = 'USD'
  autoGeneratePdf.value = true
  notifyUser.value = true
  createSubscription.value = true
  isSystemGenerated.value = false
  isLinkedToSubscription.value = false
}

watch(() => [props.isDialogVisible, props.receipt, props.dialogMode], async ([isVisible, receipt, mode]) => {
  if (isVisible) {
    if (mode === 'edit' && receipt && receipt.id) {
      isSystemGenerated.value = receipt.sourceType !== 'manual'
      isLinkedToSubscription.value = receipt.isLinkedToSubscription

      user.value = {
        id: receipt.user.id,
        fullName: receipt.user.fullName,
      }

      if (receipt.itemType == 'course') {
        course.value = {
          id: receipt.course.id,
          title: receipt.course.title,
        }
      } else if (receipt.itemType == 'subscriptionPlan') {
        planId.value = {
          id: receipt.subscriptionPlan.id,
          name: receipt.subscriptionPlan.name,
        }
        if (receipt.subscriptionPlan.course) {
          course.value = {
            id: receipt.subscriptionPlan.course.id,
            title: receipt.subscriptionPlan.course.title,
          }
        }
      }
      
      amount.value = receipt.amount
      paymentMethod.value = receipt.payment?.paymentMethod || 'manual'
      paymentDate.value = receipt.payment?.paymentDetails?.paymentDate || new Date(receipt.createdAt).toISOString().split('T')[0]
      receiptNumber.value = receipt.receiptNumber
      notes.value = receipt.payment?.paymentDetails?.notes || ''
      currency.value = receipt.currency || 'USD'
      autoGeneratePdf.value = true
      notifyUser.value = true
      createSubscription.value = isLinkedToSubscription.value

      if (course.value) {
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

const fetchCoursePlans = async () => {
  if (!course.value) return
  loadingPlans.value = true
  try {
    console.log(`Fetching subscription plans for course ID: ${course.value}`)

    const courseId = course.value.id || course.value // Handle both object and primitive ID
    const response = await api.get(`/admin/courses/${courseId}/subscription-plans`)

    plans.value = response.items || []
  } catch (error) {
    toast.error('Failed to load subscription plans.')
  } finally {
    loadingPlans.value = false
  }
}

const updateAmountFromPlan = () => {
  const selectedPlan = plans.value.find(p => p.id === planId.value)
  if (selectedPlan) {
    amount.value = selectedPlan.price
  }
}

const submitForm = async () => {
  if (isSystemGenerated.value) return
  const { valid } = await formRef.value.validate()
  if (!valid) return

  isSubmitting.value = true
  
  const baseData = {
    "paymentMethod": paymentMethod.value,
    "paymentDate": paymentDate.value,
    notes: notes.value,
    "notifyUser": notifyUser.value,
    "autoGeneratePdf": autoGeneratePdf.value,
    "receiptNumber": receiptNumber.value,
  }

  const editableData = isLinkedToSubscription.value ? {} : {
    "userId": user.value?.id,
    "courseId": course.value?.id,
    "planId": planId.value?.id,
    amount: amount.value,
    "createSubscription": createSubscription.value,
  }

  const receiptData = { ...baseData, ...editableData }

  try {
    let response
    if (props.dialogMode === 'add') {
      response = await api.post('/admin/receipts', receiptData)
      toast.success('Receipt created successfully.')
    } else {
      response = await api.put(`/admin/receipts/${props.receipt.id}`, receiptData)
      toast.success('Receipt updated successfully.')
    }
    emit('submitSuccess')
    onDialogVisibleUpdate(false)
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to save receipt.')
  } finally {
    isSubmitting.value = false
  }
}

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
          @submit.prevent="submitForm"
        >
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-if="isLinkedToSubscription || isSystemGenerated"
                :model-value="user.fullName"
                label="User"
                readonly
                disabled
              />
              <AppServerSideAutocomplete
                v-else
                v-model="user"
                label="User"
                placeholder="Select a user"
                api-link="/admin/users/select-fields"
                item-title="fullName"
                item-value="id"
                :rules="validationRules.user"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-if="isLinkedToSubscription || isSystemGenerated"
                :model-value="course.title"
                label="Course"
                readonly
                disabled
              />
              <AppServerSideAutocomplete
                v-else
                v-model="course"
                label="Course"
                placeholder="Select a course"
                api-link="/admin/courses/select-fields"
                item-title="title"
                item-value="id"
                :rules="validationRules.course"
                @update:model-value="fetchCoursePlans"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-if="isLinkedToSubscription || isSystemGenerated"
                :model-value="planId.name"
                label="Subscription Plan"
                readonly
                disabled
              />
              <AppAutocomplete
                v-else
                v-model="planId"
                label="Subscription Plan"
                placeholder="Select a plan"
                :items="plans"
                item-title="name"
                item-value="id"
                :loading="loadingPlans"
                :disabled="!course"
                :rules="validationRules.planId"
                @update:model-value="updateAmountFromPlan"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-if="isLinkedToSubscription || isSystemGenerated"
                :model-value="amount"
                label="Amount"
                readonly
                disabled
              />
              <AppTextField
                v-else
                v-model.number="amount"
                label="Amount"
                type="number"
                min="0"
                step="0.01"
                :disabled="isLinkedToSubscription || isSystemGenerated"
                :rules="validationRules.amount"
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
                v-model="paymentMethod"
                label="Payment Method"
                :items="paymentMethods"
                :rules="validationRules.paymentMethod"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppDateTimePicker
                v-model="paymentDate"
                label="Payment Date"
                :rules="validationRules.paymentDate"
                :disabled="isSystemGenerated"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                :model-value="receiptNumber"
                label="Receipt Number"
                readonly
                disabled
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="notes"
                label="Notes"
                rows="3"
                :disabled="isSystemGenerated"
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
                    v-model="autoGeneratePdf"
                    label="Auto-generate PDF"
                    :disabled="isSystemGenerated"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <VCheckbox
                    v-model="notifyUser"
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
                    v-model="createSubscription"
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
          @click="submitForm"
        >
          {{ props.dialogMode === 'add' ? 'Create' : 'Update' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
