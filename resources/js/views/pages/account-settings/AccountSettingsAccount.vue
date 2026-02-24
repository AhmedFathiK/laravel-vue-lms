<script setup>
import { useAuthStore } from '@/stores/auth'
import api from '@/utils/api'
import avatar1 from '@images/avatars/avatar-1.png'
import { ref } from 'vue'
import { useToast } from 'vue-toastification'

const authStore = useAuthStore()
const toast = useToast()

const refInputEl = ref()

const accountDataLocal = ref({
  avatar: authStore.user?.avatar || avatar1,
  firstName: authStore.user?.firstName || '',
  lastName: authStore.user?.lastName || '',
  email: authStore.user?.email || '',
})

const isAccountDeactivated = ref(false)
const validateAccountDeactivation = [v => !!v || 'Please confirm account deactivation']
const isLoading = ref(false)
const avatarFile = ref(null)

const resetForm = () => {
  accountDataLocal.value = {
    avatar: authStore.user?.avatar || avatar1,
    firstName: authStore.user?.firstName || '',
    lastName: authStore.user?.lastName || '',
    email: authStore.user?.email || '',
  }
  avatarFile.value = null
}

const changeAvatar = file => {
  const { files } = file.target
  if (files && files.length) {
    const fileReader = new FileReader()

    fileReader.readAsDataURL(files[0])
    fileReader.onload = () => {
      if (typeof fileReader.result === 'string')
        accountDataLocal.value.avatar = fileReader.result
    }
    avatarFile.value = files[0]
  }
}

const resetAvatar = () => {
  accountDataLocal.value.avatar = authStore.user?.avatar || avatar1
  avatarFile.value = null
}

const saveChanges = async () => {
  try {
    isLoading.value = true
    
    const formData = new FormData()

    formData.append('firstName', accountDataLocal.value.firstName)
    formData.append('lastName', accountDataLocal.value.lastName)
    formData.append('email', accountDataLocal.value.email)
    
    if (avatarFile.value) {
      formData.append('avatar', avatarFile.value)
    }

    // Laravel requires POST with _method=PUT for file uploads on PUT routes
    formData.append('_method', 'PUT')

    const response = await api.post('/auth/profile', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    // Update store with new user data
    authStore.updateUserData(response)
    
    toast.success('Profile updated successfully')
  } catch (error) {
    console.error(error)
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors

      Object.values(errors).forEach(err => {
        toast.error(err[0])
      })
    } else {
      toast.error('Failed to update profile')
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard title="Profile Details">
        <VCardText class="d-flex">
          <!-- 👉 Avatar -->
          <VAvatar
            rounded
            size="100"
            class="me-6"
            :image="accountDataLocal.avatar"
          />

          <!-- 👉 Upload Photo -->
          <form class="d-flex flex-column justify-center gap-4">
            <div class="d-flex flex-wrap gap-2">
              <VBtn
                color="primary"
                @click="refInputEl?.click()"
              >
                <VIcon
                  icon="tabler-cloud-upload"
                  class="d-sm-none"
                />
                <span class="d-none d-sm-block">Upload new photo</span>
              </VBtn>

              <input
                ref="refInputEl"
                type="file"
                name="file"
                accept=".jpeg,.png,.jpg,gif"
                hidden
                @input="changeAvatar"
              >

              <VBtn
                type="reset"
                color="secondary"
                variant="tonal"
                @click="resetAvatar"
              >
                <span class="d-none d-sm-block">Reset</span>
                <VIcon
                  icon="tabler-refresh"
                  class="d-sm-none"
                />
              </VBtn>
            </div>

            <p class="text-body-1 mb-0">
              Allowed JPG, GIF or PNG. Max size of 2MB
            </p>
          </form>
        </VCardText>

        <VDivider />

        <VCardText>
          <!-- 👉 Form -->
          <VForm
            class="mt-6"
            @submit.prevent="saveChanges"
          >
            <VRow>
              <!-- 👉 First Name -->
              <VCol
                md="6"
                cols="12"
              >
                <AppTextField
                  v-model="accountDataLocal.firstName"
                  label="First Name"
                />
              </VCol>

              <!-- 👉 Last Name -->
              <VCol
                md="6"
                cols="12"
              >
                <AppTextField
                  v-model="accountDataLocal.lastName"
                  label="Last Name"
                />
              </VCol>

              <!-- 👉 Email -->
              <VCol
                cols="12"
                md="6"
              >
                <AppTextField
                  v-model="accountDataLocal.email"
                  label="E-mail"
                  type="email"
                />
              </VCol>

              <!-- 👉 Form Actions -->
              <VCol
                cols="12"
                class="d-flex flex-wrap gap-4"
              >
                <VBtn
                  type="submit"
                  :loading="isLoading"
                  :disabled="isLoading"
                >
                  Save changes
                </VBtn>

                <VBtn
                  color="secondary"
                  variant="tonal"
                  type="reset"
                  @click.prevent="resetForm"
                >
                  Reset
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
