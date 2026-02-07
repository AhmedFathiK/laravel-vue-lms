<script setup>
import { useToast } from 'vue-toastification'
import api from '@/utils/api'
import { useSettingsStore } from '@/stores/settings'

const toast = useToast()
const settingsStore = useSettingsStore()
const isLoading = ref(false)
const appName = ref('')
const appLogo = ref(null)
const appLogoPreview = ref(null)

const fetchSettings = async () => {
  try {
    isLoading.value = true

    const data = await api.get('/admin/settings', { params: { group: 'general' } })

    appName.value = data.appName || settingsStore.appName || ''
    appLogoPreview.value = data.appLogo || ''
  } catch (error) {
    console.error(error)
    toast.error('Failed to fetch settings')
  } finally {
    isLoading.value = false
  }
}

const onFileChange = e => {
  const file = e.target.files[0]
  if (!file) return
  
  appLogo.value = file
  appLogoPreview.value = URL.createObjectURL(file)
}

const saveSettings = async () => {
  try {
    isLoading.value = true
    
    const formData = new FormData()

    formData.append('group', 'general')
    formData.append('settings[app_name]', appName.value)
    if (appLogo.value) {
      formData.append('settings[app_logo]', appLogo.value)
    }

    await api.post('/admin/settings', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    toast.success('Settings updated successfully')
    
    // Update the store to reflect changes immediately
    await settingsStore.fetchSettings()
  } catch (error) {
    console.error(error)
    toast.error('Failed to save settings')
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <VCard title="General Settings">
    <VCardText>
      <VForm @submit.prevent="saveSettings">
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="appName"
              label="App Name"
              placeholder="Enter App Name"
            />
          </VCol>
          
          <VCol
            cols="12"
            md="6"
          >
            <div class="d-flex align-center gap-4">
              <VAvatar
                size="80"
                rounded
                class="border"
              >
                <VImg
                  v-if="appLogoPreview"
                  :src="appLogoPreview"
                />
                <VIcon
                  v-else
                  icon="tabler-photo"
                  size="40"
                />
              </VAvatar>
              
              <div class="d-flex flex-column gap-2">
                <VBtn
                  color="primary"
                  tag="label"
                  variant="tonal"
                >
                  Upload Logo
                  <input
                    type="file"
                    hidden
                    accept="image/*"
                    @change="onFileChange"
                  >
                </VBtn>
                <span class="text-caption">Allowed JPG, GIF or PNG. Max size of 800K</span>
              </div>
            </div>
          </VCol>
          
          <VCol cols="12">
            <VBtn
              type="submit"
              :loading="isLoading"
            >
              Save Changes
            </VBtn>
          </VCol>
        </VRow>
      </VForm>
    </VCardText>
  </VCard>
</template>
