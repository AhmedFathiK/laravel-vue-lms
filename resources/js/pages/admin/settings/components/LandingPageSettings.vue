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

const pendingUploads = ref(new Map())

onMounted(fetchSettings)

onUnmounted(() => {
  pendingUploads.value.forEach(({ previewUrl }) => {
    URL.revokeObjectURL(previewUrl)
  })
})

const updateComplexProp = (section, key, jsonString) => {
  try {
    section.props[key] = JSON.parse(jsonString)
  } catch (e) {
    // ignore parsing errors while typing
  }
}

const handleImageUpload = (event, section, key) => {
  const file = event.target.files[0]
  if (!file) return

  // Create local preview
  const previewUrl = URL.createObjectURL(file)
  
  // Store pending upload
  const uploadKey = `${section.id}_${key}`
  
  // If there was a previous pending upload for this key, revoke its URL
  if (pendingUploads.value.has(uploadKey)) {
    URL.revokeObjectURL(pendingUploads.value.get(uploadKey).previewUrl)
  }
  
  pendingUploads.value.set(uploadKey, { 
    file, 
    previewUrl, 
    sectionId: section.id, 
    propKey: key, 
  })
  
  // Update UI immediately with preview
  section.props[key] = previewUrl
}

const removeImage = (section, key) => {
  section.props[key] = null
  
  // Remove from pending uploads if exists
  const uploadKey = `${section.id}_${key}`
  if (pendingUploads.value.has(uploadKey)) {
    URL.revokeObjectURL(pendingUploads.value.get(uploadKey).previewUrl)
    pendingUploads.value.delete(uploadKey)
  }
}

const saveSettings = async () => {
  try {
    isLoading.value = true

    // Process pending uploads first
    for (const [uploadKey, data] of pendingUploads.value.entries()) {
      const formData = new FormData()

      formData.append('file', data.file)
      
      const response = await api.post('/admin/landing-page-settings/upload-image', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      
      // Update the config with the real path from server
      const section = config.value.find(s => s.id === data.sectionId)
      if (section) {
        section.props[data.propKey] = response.path
      }
      
      // Remove from pending uploads on success
      URL.revokeObjectURL(data.previewUrl)
      pendingUploads.value.delete(uploadKey)
    }

    await api.post('/admin/landing-page-settings', { config: config.value })
    toast.success('Settings updated successfully')
  } catch (error) {
    console.error(error)
    toast.error('Failed to save settings')
  } finally {
    isLoading.value = false
  }
}

const sectionConfigs = {
  HeroSection: {
    groups: [
      {
        name: 'Main Content',
        fields: ['title', 'subtitle'],
      },
      {
        name: 'Primary Button',
        fields: ['buttonText', 'buttonLink'],
      },
      {
        name: 'Secondary Button',
        fields: ['secondaryButtonText', 'secondaryButtonLink', 'secondaryButtonTarget'],
      },
      {
        name: 'Hero Image',
        fields: ['heroImage', 'imageLink', 'imageTarget'],
      },
    ],
    labels: {
      heroImage: 'Hero Image',
      secondaryButtonTarget: 'Open in new tab',
      imageTarget: 'Open image link in new tab',
      title: 'Hero Title',
      subtitle: 'Hero Subtitle',
      buttonText: 'Primary Button Text',
      buttonLink: 'Primary Button Link',
      secondaryButtonText: 'Secondary Button Text',
      secondaryButtonLink: 'Secondary Button Link',
      imageLink: 'Image Link',
    },
  },
}

const getGroups = section => {
  const config = sectionConfigs[section.component]
  const allKeys = Object.keys(section.props)

  if (config && config.groups) {
    const configuredGroups = config.groups.map(group => ({
      ...group,
      fields: group.fields.filter(f => allKeys.includes(f)),
    })).filter(g => g.fields.length > 0)

    const groupedKeys = configuredGroups.flatMap(g => g.fields)
    const leftoverKeys = allKeys.filter(k => !groupedKeys.includes(k))

    if (leftoverKeys.length > 0) {
      configuredGroups.push({
        name: 'Other Properties',
        fields: leftoverKeys,
      })
    }

    return configuredGroups
  }

  return [{
    name: null,
    fields: allKeys,
  }]
}

const getLabel = (section, key) => {
  const config = sectionConfigs[section.component]
  if (config && config.labels && config.labels[key])
    return config.labels[key]

  // Convert camelCase to Title Case
  return key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase())
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
                      v-for="(group, gIndex) in getGroups(section)"
                      :key="gIndex"
                    >
                      <VCol
                        v-if="group.name"
                        cols="12"
                        class="pt-4"
                      >
                        <div class="text-subtitle-2 font-weight-bold text-primary mb-1">
                          {{ group.name }}
                        </div>
                        <VDivider />
                      </VCol>

                      <template
                        v-for="key in group.fields"
                        :key="key"
                      >
                        <!-- Image Upload Prop -->
                        <VCol
                          v-if="key === 'hero_image' || key === 'heroImage'"
                          cols="12"
                          md="6"
                        >
                          <VLabel class="mb-1 text-body-2 text-high-emphasis">
                            {{ getLabel(section, key) }}
                          </VLabel>
                          <VFileInput
                            prepend-icon="tabler-camera"
                            @change="e => handleImageUpload(e, section, key)"
                          />
                          <div
                            v-if="section.props[key]"
                            class="mt-2"
                          >
                            <VImg
                              :src="section.props[key]"
                              max-width="200"
                              class="mb-2 rounded border"
                            />
                            <div class="d-flex align-center gap-2">
                              <small class="text-medium-emphasis text-truncate">Current: {{ section.props[key] }}</small>
                              <VBtn
                                size="x-small"
                                color="error"
                                variant="text"
                                @click="removeImage(section, key)"
                              >
                                Remove
                              </VBtn>
                            </div>
                          </div>
                        </VCol>

                        <!-- String Props -->
                        <VCol
                          v-else-if="typeof section.props[key] === 'string' || section.props[key] === null"
                          cols="12"
                          md="6"
                        >
                          <AppTextField
                            v-model="section.props[key]"
                            :label="getLabel(section, key)"
                          />
                        </VCol>

                        <!-- Number Props -->
                        <VCol
                          v-else-if="typeof section.props[key] === 'number'"
                          cols="12"
                          md="6"
                        >
                          <AppTextField
                            v-model.number="section.props[key]"
                            :label="getLabel(section, key)"
                            type="number"
                          />
                        </VCol>

                        <!-- Boolean Props -->
                        <VCol
                          v-else-if="typeof section.props[key] === 'boolean'"
                          cols="12"
                          md="6"
                        >
                          <VSwitch
                            v-model="section.props[key]"
                            :label="getLabel(section, key)"
                          />
                        </VCol>

                        <!-- Complex Props (Array/Object) -->
                        <VCol
                          v-else
                          cols="12"
                        >
                          <VTextarea
                            :label="`${getLabel(section, key)} (JSON)`"
                            :model-value="JSON.stringify(section.props[key], null, 2)"
                            rows="5"
                            @update:model-value="v => updateComplexProp(section, key, v)"
                          />
                        </VCol>
                      </template>
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
