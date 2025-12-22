<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import home from '@images/svg/home.svg'
import office from '@images/svg/office.svg'

const props = defineProps({
  billingAddress: {
    type: Object,
    required: false,
    default: () => ({
      firstName: '',
      lastName: '',
      selectedCountry: null,
      addressLine1: '',
      addressLine2: '',
      landmark: '',
      contact: '',
      country: null,
      city: '',
      state: '',
      zipCode: null,
    }),
  },
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  apiEndpoint: {
    type: String,
    required: false,
    default: null,
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'submit',
  'refresh',
])

const addressTypes = [
  {
    icon: {
      icon: home,
      size: '28',
    },
    title: 'Home',
    desc: 'Delivery Time (9am - 9pm)',
    value: 'Home',
  },
  {
    icon: {
      icon: office,
      size: '28',
    },
    title: 'Office',
    desc: 'Delivery Time (9am - 5pm)',
    value: 'Office',
  },
]

const selectedAddress = ref('Home')

// Form factory
const createDefaultForm = (data = {}) => ({
  firstName: data.firstName || '',
  lastName: data.lastName || '',
  selectedCountry: data.selectedCountry || null,
  addressLine1: data.addressLine1 || '',
  addressLine2: data.addressLine2 || '',
  landmark: data.landmark || '',
  contact: data.contact || '',
  country: data.country || null,
  city: data.city || '',
  state: data.state || '',
  zipCode: data.zipCode || null,
})

const form = ref(createDefaultForm(props.billingAddress))
const refForm = ref(null)

// Watch for dialog visibility to reset form
watch(() => props.isDialogVisible, (isVisible) => {
  if (isVisible) {
    form.value = createDefaultForm(props.billingAddress)
  }
})

// Watch for prop changes
watch(() => props.billingAddress, (newVal) => {
  if (props.isDialogVisible) {
    form.value = createDefaultForm(newVal)
  }
})

// Custom emit to handle success
const customEmit = (event, ...args) => {
  if (event === 'saved') {
    emit('refresh', ...args)
    emit('submit', ...args) // Keep original emit for compatibility
  } else {
    emit(event, ...args)
  }
}

const { isLoading, onSubmit, validationErrors } = useCrudSubmit({
  formRef: refForm,
  form,
  apiEndpoint: computed(() => props.apiEndpoint || '/api/addresses'), // Placeholder default
  isUpdate: computed(() => !!props.billingAddress?.id), // Assuming id exists if editing
  emit: customEmit,
  isFormData: false,
  successMessage: computed(() => props.billingAddress?.id ? 'Address updated successfully' : 'Address added successfully'),
})
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900 "
    :model-value="props.isDialogVisible"
    @update:model-value="val => $emit('update:isDialogVisible', val)"
  >
    <!-- 👉 Dialog close btn -->
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard class="pa-sm-10 pa-2">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          {{ (props.billingAddress.addressLine1 || props.billingAddress.addressLine2) ? 'Edit' : 'Add New' }} Address
        </h4>
        <p class="text-body-1 text-center mb-6">
          Add new address for express delivery
        </p>

        <div class="d-flex mb-6">
          <CustomRadiosWithIcon
            v-model:selected-radio="selectedAddress"
            :radio-content="addressTypes"
            :grid-column="{ sm: '6', cols: '12' }"
          />
        </div>

        <!-- 👉 Form -->
        <VForm
          ref="refForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- 👉 First Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.firstName"
                label="First Name"
                placeholder="John"
                :error-messages="validationErrors.firstName"
              />
            </VCol>

            <!-- 👉 Last Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.lastName"
                label="Last Name"
                placeholder="Doe"
                :error-messages="validationErrors.lastName"
              />
            </VCol>

            <!-- 👉 Select Country -->
            <VCol cols="12">
              <AppSelect
                v-model="form.selectedCountry"
                label="Select Country"
                placeholder="Select Country"
                :items="['USA', 'Aus', 'Canada', 'NZ']"
                :error-messages="validationErrors.selectedCountry"
              />
            </VCol>

            <!-- 👉 Address Line 1 -->
            <VCol cols="12">
              <AppTextField
                v-model="form.addressLine1"
                label="Address Line 1"
                placeholder="12, Business Park"
                :error-messages="validationErrors.addressLine1"
              />
            </VCol>

            <!-- 👉 Address Line 2 -->
            <VCol cols="12">
              <AppTextField
                v-model="form.addressLine2"
                label="Address Line 2"
                placeholder="Mall Road"
                :error-messages="validationErrors.addressLine2"
              />
            </VCol>

            <!-- 👉 Landmark -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.landmark"
                label="Landmark"
                placeholder="Nr. Hard Rock Cafe"
                :error-messages="validationErrors.landmark"
              />
            </VCol>

            <!-- 👉 City -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.city"
                label="City"
                placeholder="Los Angeles"
                :error-messages="validationErrors.city"
              />
            </VCol>

            <!-- 👉 State -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.state"
                label="State"
                placeholder="California"
                :error-messages="validationErrors.state"
              />
            </VCol>

            <!-- 👉 Zip Code -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.zipCode"
                label="Zip Code"
                placeholder="99950"
                type="number"
                :error-messages="validationErrors.zipCode"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch label="Use as a billing address?" />
            </VCol>

            <!-- 👉 Submit and Cancel button -->
            <VCol
              cols="12"
              class="text-center"
            >
              <VBtn
                type="submit"
                class="me-3"
                :loading="isLoading"
              >
                submit
              </VBtn>

              <VBtn
                variant="tonal"
                color="secondary"
                @click="$emit('update:isDialogVisible', false)"
              >
                Cancel
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
