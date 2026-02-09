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
const previewUrls = ref([])

onMounted(fetchSettings)

onUnmounted(() => {
  previewUrls.value.forEach(url => URL.revokeObjectURL(url))
})

const isTablerIcon = icon => {
  return icon && typeof icon === 'string' && icon.startsWith('tabler-')
}

const updateComplexProp = (section, key, jsonString) => {
  try {
    section.props[key] = JSON.parse(jsonString)
  } catch (e) {
    // ignore parsing errors while typing
  }
}

const handleFileUpload = (event, targetObj, key) => {
  const file = event.target.files[0]
  if (!file) return

  // Create local preview
  const previewUrl = URL.createObjectURL(file)

  previewUrls.value.push(previewUrl)
  
  // Store pending upload on the object itself
  targetObj._pendingFile = file
  targetObj._pendingKey = key
  
  // Update UI immediately with preview
  targetObj[key] = previewUrl
}

const removeImage = (targetObj, key) => {
  targetObj[key] = null
  if (targetObj._pendingFile) {
    delete targetObj._pendingFile
    delete targetObj._pendingKey
  }
}

const uploadFile = async file => {
  const formData = new FormData()

  formData.append('file', file)
  
  const response = await api.post('/admin/landing-page-settings/upload-image', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })

  
  return response.path
}

const saveSettings = async () => {
  try {
    isLoading.value = true

    // Process pending uploads
    for (const section of config.value) {
      // 1. Check section level props (e.g. Hero Image)
      if (section.props._pendingFile) {
        try {
          const path = await uploadFile(section.props._pendingFile)

          section.props[section.props._pendingKey] = path
        } catch (e) {
          console.error('Failed to upload section image', e)
        }
        delete section.props._pendingFile
        delete section.props._pendingKey
      }

      // 2. Check features array
      if (section.props.features && Array.isArray(section.props.features)) {
        for (const feature of section.props.features) {
          if (feature._pendingFile) {
            try {
              const path = await uploadFile(feature._pendingFile)

              feature[feature._pendingKey] = path
            } catch (e) {
              console.error('Failed to upload feature icon', e)
            }
            delete feature._pendingFile
            delete feature._pendingKey
          }
        }
      }
    }

    // Clone config to remove any internal properties before sending
    // (Though we deleted _pendingFile, this is extra safety if we add more internal props later)
    const configToSend = JSON.parse(JSON.stringify(config.value))

    await api.post('/admin/landing-page-settings', { config: configToSend })
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
  Features: {
    groups: [
      {
        name: 'Header',
        fields: ['tag', 'title', 'subtitle'],
      },
      {
        name: 'Feature List',
        fields: ['features'],
      },
    ],
    labels: {
      tag: 'Section Tag',
      title: 'Main Title',
      subtitle: 'Subtitle Description',
      features: 'Features List',
    },
  },
}

const addFeature = section => {
  if (!section.props.features) section.props.features = []
  section.props.features.push({
    title: 'New Feature',
    desc: 'Description here',
    icon: 'tabler-star',
  })
}

const removeFeature = (section, index) => {
  section.props.features.splice(index, 1)
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
                            accept="image/*"
                            @change="e => handleFileUpload(e, section.props, key)"
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
                                @click="removeImage(section.props, key)"
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

                        <!-- Features List Prop -->
                        <VCol
                          v-else-if="key === 'features' && Array.isArray(section.props[key])"
                          cols="12"
                        >
                          <div class="d-flex flex-column gap-4">
                            <VCard
                              v-for="(feature, index) in section.props[key]"
                              :key="index"
                              variant="outlined"
                              class="mb-2"
                            >
                              <VCardText>
                                <div class="d-flex justify-space-between align-start mb-4">
                                  <span class="text-subtitle-2">Feature {{ index + 1 }}</span>
                                  <VBtn
                                    color="error"
                                    variant="text"
                                    size="small"
                                    icon="tabler-trash"
                                    @click="removeFeature(section, index)"
                                  />
                                </div>
                                <VRow>
                                  <VCol
                                    cols="12"
                                    md="6"
                                  >
                                    <AppTextField
                                      v-model="feature.title"
                                      label="Title"
                                    />
                                  </VCol>
                                  <VCol
                                    cols="12"
                                    md="6"
                                  >
                                    <VFileInput
                                      label="Icon"
                                      prepend-icon="tabler-camera"
                                      accept="image/*"
                                      @change="e => handleFileUpload(e, feature, 'icon')"
                                    />
                                    <div
                                      v-if="feature.icon"
                                      class="mt-2"
                                    >
                                      <div class="d-flex align-center gap-4">
                                        <VIcon
                                          v-if="isTablerIcon(feature.icon)"
                                          :icon="feature.icon"
                                          size="40"
                                        />
                                        <VImg
                                          v-else
                                          :src="feature.icon"
                                          max-width="80"
                                          max-height="80"
                                          class="rounded border"
                                        />
                                        <VBtn
                                          size="x-small"
                                          color="error"
                                          variant="text"
                                          @click="removeImage(feature, 'icon')"
                                        >
                                          Remove Icon
                                        </VBtn>
                                      </div>
                                    </div>
                                  </VCol>
                                  <VCol cols="12">
                                    <VTextarea
                                      v-model="feature.desc"
                                      label="Description"
                                      rows="2"
                                      auto-grow
                                    />
                                  </VCol>
                                </VRow>
                              </VCardText>
                            </VCard>
                            
                            <VBtn
                              variant="tonal"
                              prepend-icon="tabler-plus"
                              @click="addFeature(section)"
                            >
                              Add Feature
                            </VBtn>
                          </div>
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
