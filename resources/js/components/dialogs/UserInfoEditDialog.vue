<script setup>
import { computed, ref, watch } from 'vue'

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

// Default empty form
const defaultForm = {
  id: 0,
  name: '',
  email: '',
  password: '',
  passwordConfirmation: '',
  roles: [],
  verified: true,
  errors: {},
}

const form = ref({ ...defaultForm })

// Set form data if user is provided
watch(() => props.userData, newUser => {
  if (newUser) {
    form.value = {
      id: newUser.id || 0,
      name: newUser.name || '',
      email: newUser.email || '',
      password: '',
      passwordConfirmation: '',
      roles: newUser.role_names || [],
      verified: newUser.email_verified_at ? true : false,
      errors: {},
    }
  } else {
    form.value = { ...defaultForm }
  }
}, { immediate: true })

const isEditMode = computed(() => !!props.userData)

const onFormSubmit = () => {
  emit('update:isDialogVisible', false)
  emit('submit', form.value)
}

const onFormReset = () => {
  form.value = props.userData ? {
    id: props.userData.id || 0,
    name: props.userData.name || '',
    email: props.userData.email || '',
    password: '',
    passwordConfirmation: '',
    roles: props.userData.role_names || [],
    verified: props.userData.email_verified_at ? true : false,
    errors: {},
  } : { ...defaultForm }
  
  emit('update:isDialogVisible', false)
}

const dialogModelValueUpdate = val => {
  emit('update:isDialogVisible', val)
}
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
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
          class="mt-6"
          @submit.prevent="onFormSubmit"
        >
          <VRow>
            <!-- 👉 Full Name -->
            <VCol cols="12">
              <AppTextField
                v-model="form.name"
                label="Full Name"
                placeholder="John Doe"
                :error-messages="form.errors.name"
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
                :error-messages="form.errors.email"
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
                :error-messages="form.errors.verified"
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
                :error-messages="form.errors.password"
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
                :error-messages="form.errors.passwordConfirmation"
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
                :error-messages="form.errors.roles"
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
              <VBtn type="submit">
                {{ isEditMode ? 'Update' : 'Submit' }}
              </VBtn>

              <VBtn
                color="secondary"
                variant="tonal"
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
