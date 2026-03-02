<script setup>
import { useToast } from 'vue-toastification'
import { requiredValidator } from '@core/utils/validators'
import api from '@/utils/api'

const toast = useToast()
const isLoading = ref(false)
const refForm = ref()

const settings = ref({
  paymentGateway: 'myfatoorah',

  // Paymob
  paymobSecretKey: '',
  paymobPublicKey: '',
  paymobIntegrations: [],

  // MyFatoorah
  paymentMyfatoorahApiKey: '',
  paymentMyfatoorahBaseUrl: 'https://apitest.myfatoorah.com',
  paymentMyfatoorahTestMode: true,
  myfatoorahAllowedMethods: [],
})

const allMyFatoorahMethods = ref([])
const isFetchingMyFatoorahMethods = ref(false)

const gateways = [
  { title: 'MyFatoorah', value: 'myfatoorah' },
  { title: 'Paymob', value: 'paymob' },
]

const fetchMyFatoorahMethods = async () => {
  if (!settings.value.paymentMyfatoorahApiKey) return
  
  try {
    isFetchingMyFatoorahMethods.value = true


    // We can use the existing learner endpoint but maybe pass a flag to get all
    // Or just fetch them and the backend handles filtering if it's a learner
    // For admin, we might need a specific endpoint to bypass filters
    const response = await api.get('/admin/payments/all-methods', {
      params: {
        gateway: 'myfatoorah',
        amount: 100,
        currency: 'EGP',
      },
    })

    allMyFatoorahMethods.value = response.data
  } catch (error) {
    console.error(error)
    toast.error('Failed to fetch MyFatoorah methods')
  } finally {
    isFetchingMyFatoorahMethods.value = false
  }
}

const paymobIntegrationTypes = [
  { title: 'Card (Iframe)', value: 'CARD' },
  { title: 'Mobile Wallet', value: 'WALLET' },
]

const addPaymobIntegration = () => {
  settings.value.paymobIntegrations.push({ id: '', name: '', type: 'CARD', image: null })
}

const removePaymobIntegration = index => {
  settings.value.paymobIntegrations.splice(index, 1)
}

const fetchSettings = async () => {
  try {
    isLoading.value = true

    const data = await api.get('/admin/settings/payment')
    
    Object.keys(settings.value).forEach(key => {
      if (data[key] !== undefined) {
        let value = data[key]

        // Handle JSON strings from database (if they didn't get parsed by middleware/Laravel)
        if (key === 'paymobIntegrations' || key === 'myfatoorahAllowedMethods') {
          try {
            value = typeof value === 'string' ? JSON.parse(value) : (value || [])
            
            // Ensure paymob integrations have a type
            if (key === 'paymobIntegrations' && Array.isArray(value)) {
              value = value.map(int => ({
                id: int.id || '',
                name: int.name || '',
                type: int.type || 'CARD',
                image: int.image || null,
              }))
            }
          } catch (e) {
            value = []
          }
        }

        // Handle boolean
        if (key === 'paymentMyfatoorahTestMode') {
          value = value === '1' || value === 1 || value === true
        }
        settings.value[key] = value
      }
    })

    if (settings.value.paymentGateway === 'myfatoorah') {
      fetchMyFatoorahMethods()
    }
  } catch (error) {
    console.error(error)
    toast.error('Failed to fetch payment settings')
  } finally {
    isLoading.value = false
  }
}

const saveSettings = async () => {
  const { valid } = await refForm.value?.validate()
  if (!valid) return

  try {
    isLoading.value = true
    
    // Prepare payload
    const payload = {
      group: 'payment',
      settings: { ...settings.value },
    }

    await api.post('/admin/settings/payment', payload)
    toast.success('Payment settings updated successfully')
    
    // Refresh methods if we are using MyFatoorah
    if (settings.value.paymentGateway === 'myfatoorah') {
      fetchMyFatoorahMethods()
    }
  } catch (error) {
    console.error(error)
    toast.error('Failed to save payment settings')
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchSettings)
</script>

<template>
  <VCard title="Payment Settings">
    <VCardText>
      <VForm
        ref="refForm"
        @submit.prevent="saveSettings"
      >
        <VRow>
          <VCol cols="12">
            <AppSelect
              v-model="settings.paymentGateway"
              :items="gateways"
              label="Active Payment Gateway"
              hint="Select which gateway will be used for checkouts"
              persistent-hint
              :rules="[requiredValidator]"
            />
          </VCol>

          <VDivider class="my-4" />

          <!-- Paymob Settings -->
          <template v-if="settings.paymentGateway === 'paymob'">
            <VCol cols="12">
              <h6 class="text-h6 mb-2">
                Paymob Configuration
              </h6>
            </VCol>
            
            <VCol cols="12">
              <AppTextField
                v-model="settings.paymobSecretKey"
                label="Secret Key"
                placeholder="Enter Paymob Secret Key"
                type="password"
                :rules="[requiredValidator]"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="settings.paymobPublicKey"
                label="Public Key"
                placeholder="Enter Paymob Public Key"
                :rules="[requiredValidator]"
              />
            </VCol>

            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-4">
                <h6 class="text-h6">
                  Payment Methods (Integrations)
                </h6>
                <VBtn
                  size="small"
                  prepend-icon="tabler-plus"
                  @click="addPaymobIntegration"
                >
                  Add Method
                </VBtn>
              </div>

              <VRow
                v-for="(integration, index) in settings.paymobIntegrations"
                :key="index"
                class="mb-4 align-center border rounded pa-2"
              >
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="integration.id"
                    label="Integration ID"
                    placeholder="e.g. 12345"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="integration.name"
                    label="Method Name"
                    placeholder="e.g. Credit Card"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="3"
                >
                  <AppSelect
                    v-model="integration.type"
                    :items="paymobIntegrationTypes"
                    label="Type"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="1"
                  class="d-flex justify-center"
                >
                  <VBtn
                    icon="tabler-trash"
                    color="error"
                    variant="tonal"
                    size="small"
                    @click="removePaymobIntegration(index)"
                  />
                </VCol>
              </VRow>
            </VCol>
          </template>

          <!-- MyFatoorah Settings -->
          <template v-if="settings.paymentGateway === 'myfatoorah'">
            <VCol cols="12">
              <h6 class="text-h6 mb-2">
                MyFatoorah Configuration
              </h6>
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="settings.paymentMyfatoorahApiKey"
                label="API Key"
                placeholder="Enter MyFatoorah API Key"
                type="password"
                :rules="[requiredValidator]"
              />
            </VCol>

            <VCol
              cols="12"
              md="8"
            >
              <AppTextField
                v-model="settings.paymentMyfatoorahBaseUrl"
                label="Base URL"
                placeholder="https://api.myfatoorah.com"
                :rules="[requiredValidator]"
              />
            </VCol>

            <VCol
              cols="12"
              md="4"
            >
              <VSwitch
                v-model="settings.paymentMyfatoorahTestMode"
                label="Test Mode"
              />
            </VCol>

            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-2">
                <h6 class="text-h6">
                  Available Payment Methods
                </h6>
                <VBtn
                  size="small"
                  variant="tonal"
                  :loading="isFetchingMyFatoorahMethods"
                  @click="fetchMyFatoorahMethods"
                >
                  Refresh Methods
                </VBtn>
              </div>
              <p class="text-caption mb-4">
                Select the methods you want to offer to users
              </p>
              
              <VRow v-if="allMyFatoorahMethods.length">
                <VCol 
                  v-for="method in allMyFatoorahMethods" 
                  :key="method.id"
                  cols="12"
                  sm="6"
                  md="4"
                >
                  <VCheckbox
                    v-model="settings.myfatoorahAllowedMethods"
                    :value="method.id"
                    :label="method.name"
                    hide-details
                  >
                    <template #label>
                      <div class="d-flex align-center gap-2">
                        <VImg
                          v-if="method.image"
                          :src="method.image"
                          width="30"
                          height="20"
                          contain
                        />
                        <span>{{ method.name }}</span>
                      </div>
                    </template>
                  </VCheckbox>
                </VCol>
              </VRow>
              <VAlert
                v-else-if="!isFetchingMyFatoorahMethods"
                type="info"
                variant="tonal"
              >
                Enter API Key and click Refresh to see available methods
              </VAlert>
            </VCol>
          </template>

          <VCol
            cols="12"
            class="mt-4"
          >
            <VBtn
              type="submit"
              :loading="isLoading"
            >
              Save Payment Settings
            </VBtn>
          </VCol>
        </VRow>
      </VForm>
    </VCardText>
  </VCard>
</template>
