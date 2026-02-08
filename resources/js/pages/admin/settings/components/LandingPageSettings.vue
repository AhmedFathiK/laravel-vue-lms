<script setup>
import { useToast } from 'vue-toastification'
import api from '@/utils/api'

const toast = useToast()
const isLoading = ref(false)
const config = ref([])

const fetchSettings = async () => {
  try {
    isLoading.value = true

    const data = await api.get('/admin/landing-page-settings')

    config.value = data
  } catch (error) {
    console.error(error)
    toast.error('Failed to fetch settings')
  } finally {
    isLoading.value = false
  }
}

const saveSettings = async () => {
  try {
    isLoading.value = true
    await api.post('/admin/landing-page-settings', { config: config.value })
    toast.success('Settings updated successfully')
  } catch (error) {
    console.error(error)
    toast.error('Failed to save settings')
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchSettings)

const updateComplexProp = (section, key, jsonString) => {
  try {
    section.props[key] = JSON.parse(jsonString)
  } catch (e) {
    // ignore parsing errors while typing
  }
}
</script>

<template>
  <VCard title="Landing Page Settings">
    <VCardText>
      <div
        v-if="isLoading"
        class="d-flex justify-center my-4"
      >
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </div>
      <div v-else>
        <VExpansionPanels multiple>
          <VExpansionPanel
            v-for="section in config"
            :key="section.id"
          >
            <VExpansionPanelTitle>
              <div class="d-flex align-center gap-4 w-100">
                <VSwitch
                  v-model="section.visible"
                  hide-details
                  density="compact"
                  label="Visible"
                  class="me-4"
                  @click.stop
                />
                <span>{{ section.name }} ({{ section.component }})</span>
              </div>
            </VExpansionPanelTitle>
            <VExpansionPanelText>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="section.name"
                    label="Section Name"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="6"
                >
                  <AppTextField
                    v-model="section.id"
                    label="HTML ID"
                  />
                </VCol>
                            
                <VCol cols="12">
                  <VDivider class="my-4" />
                  <div class="text-subtitle-1 mb-4">
                    Component Properties
                  </div>
                  <VRow>
                    <template
                      v-for="(value, key) in section.props"
                      :key="key"
                    >
                      <!-- String Props -->
                      <VCol
                        v-if="typeof value === 'string'"
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          v-model="section.props[key]"
                          :label="key"
                        />
                      </VCol>

                      <!-- Number Props -->
                      <VCol
                        v-else-if="typeof value === 'number'"
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          v-model.number="section.props[key]"
                          :label="key"
                          type="number"
                        />
                      </VCol>

                      <!-- Boolean Props -->
                      <VCol
                        v-else-if="typeof value === 'boolean'"
                        cols="12"
                        md="6"
                      >
                        <VSwitch
                          v-model="section.props[key]"
                          :label="key"
                        />
                      </VCol>

                      <!-- Complex Props (Array/Object) -->
                      <VCol
                        v-else
                        cols="12"
                      >
                        <VTextarea
                          :label="`${key} (JSON)`"
                          :model-value="JSON.stringify(value, null, 2)"
                          rows="5"
                          @update:model-value="v => updateComplexProp(section, key, v)"
                        />
                      </VCol>
                    </template>
                  </VRow>
                </VCol>
              </VRow>
            </VExpansionPanelText>
          </VExpansionPanel>
        </VExpansionPanels>

        <div class="mt-4 d-flex justify-end">
          <VBtn
            :loading="isLoading"
            @click="saveSettings"
          >
            Save Changes
          </VBtn>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
