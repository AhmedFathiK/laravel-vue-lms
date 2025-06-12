<script setup>
import { confirmedValidator, emailValidator, maxLengthValidator, minLengthValidator, requiredValidator } from '@core/utils/validators'
import { computed, nextTick, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({
  userData: {
    type: Object,
    required: false,
    default: () => null,
  },
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  roles: {
    type: Array,
    required: false,
    default: () => [],
  },
})

const emit = defineEmits([
  'submit',
  'update:isDialogVisible',
])

const toast = useToast()

// Default empty form
const defaultForm = {
  id: 0,
  firstName: '',
  lastName: '',
  email: '',
  password: '',
  passwordConfirmation: '',
  roles: [],
  verified: true,
}

// Form data and validation
const form = ref(JSON.parse(JSON.stringify(defaultForm)))
const errors = ref({})
const isFormValid = ref(false) // Start as false, will be updated by validation
const isSubmitting = ref(false)
const formRef = ref(null)

// Form validation rules
const rules = {
  firstName: [
    requiredValidator,
    v => maxLengthValidator(v, 100),
  ],
  lastName: [
    requiredValidator,
    v => maxLengthValidator(v, 100),
  ],
  email: [
    requiredValidator, 
    emailValidator,
    v => maxLengthValidator(v, 255),
  ],
  password: [
    // Password is only required for new users
    v => (props.userData ? true : requiredValidator(v)),

    // If password is provided, it must be at least 8 characters
    v => (!v || minLengthValidator(v, 8)),
  ],
  passwordConfirmation: [
    // Confirmation is required only if password is provided
    v => (!form.value.password ? true : requiredValidator(v)),

    // Must match password if provided
    v => (!form.value.password || confirmedValidator(v, form.value.password)),
  ],
}

// Check form validity manually (solves issue with submit button)
const validateForm = async () => {
  if (!formRef.value) return false
  
  const { valid } = await formRef.value.validate()

  isFormValid.value = valid
  
  return valid
}

// Watch for userData changes (removed form reset logic as it's now handled by isDialogVisible watcher)
watch(() => props.userData, () => {
  // This watcher is kept for reactivity, but form reset is now handled by isDialogVisible watcher
}, { immediate: true })

// No need to track touched fields or validate on change
// Validation will happen only on submit

// Reset form when dialog visibility changes
watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    // Reset form when dialog opens
    if (props.userData) {
      // Split name into first and last name if firstName is not available
      let firstName = props.userData.first_name || ''
      let lastName = props.userData.last_name || ''
      
      if (!firstName && !lastName && props.userData.name) {
        const nameParts = props.userData.name.split(' ')

        firstName = nameParts[0] || ''
        lastName = nameParts.slice(1).join(' ') || ''
      }
      
      form.value = {
        id: props.userData.id || 0,
        firstName: firstName,
        lastName: lastName,
        email: props.userData.email || '',
        password: '',
        passwordConfirmation: '',
        roles: props.userData.role_names || [],
        verified: props.userData.email_verified_at ? true : false,
      }
    } else {
      // Ensure form is completely reset for new user
      form.value = JSON.parse(JSON.stringify(defaultForm))
    }
    
    errors.value = {}
    
    // Reset form validation without validating initially
    nextTick(() => {
      if (formRef.value) {
        formRef.value.resetValidation()
        isFormValid.value = true // Allow form submission until validation on submit
      }
    })
  }
}, { immediate: true })

const isEditMode = computed(() => !!props.userData)

// Convert form data from camelCase to snake_case for API
const prepareFormData = () => {
  // Convert camelCase to snake_case for API
  const { firstName, lastName, password, passwordConfirmation, ...rest } = form.value
  
  const formData = {
    ...rest,
    ['first_name']: firstName, // Using bracket notation to avoid linter errors
    ['last_name']: lastName,
  }
  
  // Only include password fields if password is provided
  if (password) {
    formData.password = password
    formData['password_confirmation'] = passwordConfirmation
  }
  
  return formData
}

// Form submission handler
const submitForm = async () => {
  const valid = await validateForm()
  
  if (!valid) return
  
  isSubmitting.value = true
  
  try {
    // Convert form data for API
    const formData = prepareFormData()
    
    await emit('submit', formData)

    // Success - close dialog only on success
    emit('update:isDialogVisible', false)
    isSubmitting.value = false
  } catch (error) {
    // Error handling - keep dialog open
    isSubmitting.value = false
    
    // Handle validation errors from the API
    if (error.response?.data?.errors) {
      const apiErrors = error.response.data.errors
      
      // Map API snake_case errors to camelCase form fields
      const errorMapping = {
        ['first_name']: 'firstName',
        ['last_name']: 'lastName',
      }
      
      // Process all errors
      Object.entries(apiErrors).forEach(([key, messages]) => {
        // If there's a mapping for this field, use it
        if (errorMapping[key]) {
          errors.value[errorMapping[key]] = messages
        } else {
          // Otherwise use the original key
          errors.value[key] = messages
        }
      })
      
      // Show all error messages in toasts
      Object.values(apiErrors).forEach(fieldErrors => {
        fieldErrors.forEach(errorMessage => {
          toast.error(errorMessage)
        })
      })
    }
  }
}

const onFormReset = () => {
  if (formRef.value) {
    formRef.value.reset()
  }
  form.value = props.userData ? {
    id: props.userData.id || 0,
    firstName: props.userData.first_name || '',
    lastName: props.userData.last_name || '',
    email: props.userData.email || '',
    password: '',
    passwordConfirmation: '',
    roles: props.userData.role_names || [],
    verified: props.userData.email_verified_at ? true : false,
  } : JSON.parse(JSON.stringify(defaultForm))
  errors.value = {}
  emit('update:isDialogVisible', false)
}

const dialogModelValueUpdate = val => {
  if (!val && !isSubmitting.value) {
    emit('update:isDialogVisible', val)
  }
}
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    :persistent="isSubmitting"
    @update:model-value="dialogModelValueUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-sm-10 pa-2">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          {{ isEditMode ? 'Edit User' : 'Add New User' }}
        </h4>
        <p class="text-body-1 text-center mb-6">
          {{ isEditMode ? 'Update user details' : 'Create a new user account' }}
        </p>

        <!-- 👉 Form -->
        <VForm
          ref="formRef"
          v-model="isFormValid"
          class="mt-6"
          validate-on="submit"
          @submit.prevent="submitForm"
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
                :error-messages="errors.firstName"
                :rules="rules.firstName"
                required
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
                :error-messages="errors.lastName"
                :rules="rules.lastName"
                required
              />
            </VCol>

            <!-- 👉 Email -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.email"
                label="Email"
                placeholder="johndoe@example.com"
                :error-messages="errors.email"
                :rules="rules.email"
                type="email"
                required
              />
            </VCol>

            <!-- 👉 Status -->
            <VCol
              cols="12"
              md="6"
            >
              <VSwitch
                v-model="form.verified"
                color="success"
                label="Email Verified"
                :error-messages="errors.verified"
                hide-details
              />
            </VCol>

            <!-- 👉 Password -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.password"
                label="Password"
                placeholder="************"
                :error-messages="errors.password"
                :rules="rules.password"
                type="password"
                :required="!isEditMode"
              />
            </VCol>

            <!-- 👉 Password Confirmation -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.passwordConfirmation"
                label="Confirm Password"
                placeholder="************"
                :error-messages="errors.passwordConfirmation"
                :rules="rules.passwordConfirmation"
                type="password"
                :required="!isEditMode"
              />
            </VCol>

            <!-- 👉 Roles -->
            <VCol cols="12">
              <AppSelect
                v-model="form.roles"
                label="Roles"
                placeholder="Select roles"
                :items="roles"
                item-title="name"
                item-value="name"
                :error-messages="errors.roles"
                multiple
                chips
                closable-chips
              />
            </VCol>

            <!-- 👉 Submit and Cancel -->
            <VCol
              cols="12"
              class="d-flex flex-wrap justify-center gap-4"
            >
              <VBtn 
                type="submit"
                :loading="isSubmitting"
                :disabled="isSubmitting"
              >
                {{ isEditMode ? 'Update' : 'Submit' }}
              </VBtn>

              <VBtn
                color="secondary"
                variant="tonal"
                :disabled="isSubmitting"
                @click="onFormReset"
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
