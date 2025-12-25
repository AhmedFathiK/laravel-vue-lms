<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import { confirmedValidator, emailValidator, maxLengthValidator, minLengthValidator, requiredValidator } from '@core/utils/validators'
import { computed, nextTick, ref, watch } from 'vue'

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
  dialogMode: {
    type: String,
    required: true,
    validator: value => ['add', 'edit'].includes(value),
  },
  roles: {
    type: Array,
    required: false,
    default: () => [],
  },
})

const emit = defineEmits([
  'submit',
  'refresh',
  'update:isDialogVisible',
])

// Default empty form factory
const createDefaultForm = () => ({
  id: 0,
  firstName: '',
  lastName: '',
  email: '',
  phoneNumber: '',
  password: '',
  passwordConfirmation: '',
  roles: [],
  verified: true,
})

// Form data
const form = ref(createDefaultForm())
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
  phoneNumber: [
    v => !v || maxLengthValidator(v, 20),
  ],
  password: [
    // Password is only required for new users
    v => (props.dialogMode === 'edit' ? true : requiredValidator(v)),

    // If password is provided, it must be at least 8 characters
    v => (!v || minLengthValidator(v, 8)),
  ],
  passwordConfirmation: [
    // Confirmation is required only if password is provided
    v => (!form.value.password ? true : requiredValidator(v)),

    // Must match password if provided
    v => (!form.value.password || confirmedValidator(v, form.value.password)),
  ],
  roles: [
    v => (v && v.length > 0) || 'At least one role must be selected',
  ],
}

// Reset form when dialog visibility changes
watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.userData) {
      // Split name into first and last name if firstName is not available
      let firstName = props.userData.firstName || ''
      let lastName = props.userData.lastName || ''
      
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
        phoneNumber: props.userData.phoneNumber || '',
        password: '',
        passwordConfirmation: '',
        roles: props.userData.roleNames || [],
        verified: props.userData.emailVerifiedAt ? true : false,
      }
    } else {
      form.value = createDefaultForm()
    }
    
    // Reset validation
    validationErrors.value = {}
    nextTick(() => {
      if (formRef.value) {
        formRef.value.resetValidation()
      }
    })
  }
})

const isEditMode = computed(() => props.dialogMode === 'edit')

// Custom emit for refresh to handle legacy submit listeners if any
const customEmit = (event, ...args) => {
  if (event === 'saved') {
    emit('refresh', ...args)

    // Also emit submit for backward compatibility if needed, but we should refactor parent
    // emit('submit', ...args) 
  } else {
    emit(event, ...args)
  }
}

const { isLoading, validationErrors, onSubmit } = useCrudSubmit({
  formRef,
  form,
  apiEndpoint: computed(() => props.userData?.id 
    ? `/admin/users/${props.userData.id}` 
    : '/admin/users'),
  isUpdate: computed(() => !!props.userData?.id),
  emit: customEmit,
  isFormData: false, // User data usually sent as JSON unless avatar is involved
  successMessage: computed(() => props.userData?.id ? 'User updated successfully' : 'User created successfully'),
})

const onFormReset = () => {
  form.value = createDefaultForm()
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    :persistent="isLoading"
    @update:model-value="val => !isLoading && emit('update:isDialogVisible', val)"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="!isLoading && emit('update:isDialogVisible', false)" />

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
          class="mt-6"
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
                :error-messages="validationErrors.lastName"
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
                :error-messages="validationErrors.email"
                :rules="rules.email"
                type="email"
                required
              />
            </VCol>

            <!-- 👉 Phone Number -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.phoneNumber"
                label="Phone Number"
                placeholder="+1 123 456 7890"
                :error-messages="validationErrors.phoneNumber"
                :rules="rules.phoneNumber"
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
                :error-messages="validationErrors.verified"
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
                :error-messages="validationErrors.password"
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
                :error-messages="validationErrors.passwordConfirmation"
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
                :error-messages="validationErrors.roles"
                :rules="rules.roles"
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
                :loading="isLoading"
              >
                {{ isEditMode ? 'Update' : 'Submit' }}
              </VBtn>

              <VBtn
                color="secondary"
                variant="tonal"
                :disabled="isLoading"
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
