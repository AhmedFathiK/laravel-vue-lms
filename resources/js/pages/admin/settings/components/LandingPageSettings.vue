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

      // 3. Check reviews array
      if (section.props.reviews && Array.isArray(section.props.reviews)) {
        for (const review of section.props.reviews) {
          if (review._pendingFile) {
            try {
              const path = await uploadFile(review._pendingFile)

              review[review._pendingKey] = path
            } catch (e) {
              console.error('Failed to upload review avatar', e)
            }
            delete review._pendingFile
            delete review._pendingKey
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
  CustomersReview: {
    groups: [
      {
        name: 'Header',
        fields: ['tag', 'title', 'subtitle'],
      },
      {
        name: 'Reviews List',
        fields: ['reviews'],
      },
    ],
    labels: {
      tag: 'Section Tag',
      title: 'Main Title',
      subtitle: 'Subtitle Description',
      reviews: 'Customer Reviews',
    },
  },
  FaqSection: {
    groups: [
      {
        name: 'Header',
        fields: ['tag', 'title', 'subtitle'],
      },
      {
        name: 'FAQ List',
        fields: ['faqs'],
      },
    ],
    labels: {
      tag: 'Section Tag',
      title: 'Main Title',
      subtitle: 'Subtitle Description',
      faqs: 'Questions & Answers',
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

const addReview = section => {
  if (!section.props.reviews) section.props.reviews = []
  section.props.reviews.push({
    name: 'New Reviewer',
    position: 'Position',
    desc: 'Review comment...',
    rating: 5,
    avatar: null,
  })
}

const removeReview = (section, index) => {
  section.props.reviews.splice(index, 1)
}

const addFaq = section => {
  if (!section.props.faqs) section.props.faqs = []
  section.props.faqs.push({
    question: 'New Question',
    answer: 'Answer goes here...',
  })
}

const removeFaq = (section, index) => {
  section.props.faqs.splice(index, 1)
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
                          v-if="key === 'hero_image' || key === 'heroImage' || key === 'faq_image' || key === 'faqImage'"
                          cols="12"
                          md="6"
                        >
                          <VLabel class="mb-1 text-body-2 text-high-emphasis">
                            {{ getLabel(section, key) }}
                          </VLabel>
                          <VFileInput
                            :label="getLabel(section, key)"
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
                                    <VLabel class="mb-1 text-body-2 text-high-emphasis">
                                      Icon
                                    </VLabel>
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

                        <!-- Reviews List Prop -->
                        <VCol
                          v-else-if="key === 'reviews' && Array.isArray(section.props[key])"
                          cols="12"
                        >
                          <div class="d-flex flex-column gap-4">
                            <VCard
                              v-for="(review, index) in section.props[key]"
                              :key="index"
                              variant="outlined"
                              class="mb-2"
                            >
                              <VCardText>
                                <div class="d-flex justify-space-between align-start mb-4">
                                  <span class="text-subtitle-2">Review {{ index + 1 }}</span>
                                  <VBtn
                                    color="error"
                                    variant="text"
                                    size="small"
                                    icon="tabler-trash"
                                    @click="removeReview(section, index)"
                                  />
                                </div>
                                <VRow>
                                  <VCol
                                    cols="12"
                                    md="6"
                                  >
                                    <AppTextField
                                      v-model="review.name"
                                      label="Reviewer Name"
                                    />
                                  </VCol>
                                  <VCol
                                    cols="12"
                                    md="6"
                                  >
                                    <AppTextField
                                      v-model="review.position"
                                      label="Position/Title"
                                    />
                                  </VCol>
                                  <VCol
                                    cols="12"
                                    md="6"
                                  >
                                    <AppTextField
                                      v-model.number="review.rating"
                                      label="Rating (1-5)"
                                      type="number"
                                      min="1"
                                      max="5"
                                    />
                                  </VCol>
                                  <VCol
                                    cols="12"
                                    md="6"
                                  >
                                    <VLabel class="mb-1 text-body-2 text-high-emphasis">
                                      Avatar
                                    </VLabel>
                                    <VFileInput
                                      label="Avatar"
                                      prepend-icon="tabler-camera"
                                      accept="image/*"
                                      @change="e => handleFileUpload(e, review, 'avatar')"
                                    />
                                    <div
                                      v-if="review.avatar"
                                      class="mt-2"
                                    >
                                      <div class="d-flex align-center gap-4">
                                        <VAvatar
                                          :image="review.avatar"
                                          size="40"
                                        />
                                        <VBtn
                                          size="x-small"
                                          color="error"
                                          variant="text"
                                          @click="removeImage(review, 'avatar')"
                                        >
                                          Remove Avatar
                                        </VBtn>
                                      </div>
                                    </div>
                                  </VCol>
                                  <VCol cols="12">
                                    <VTextarea
                                      v-model="review.desc"
                                      label="Comment"
                                      rows="3"
                                      auto-grow
                                    />
                                  </VCol>
                                </VRow>
                              </VCardText>
                            </VCard>
                            
                            <VBtn
                              variant="tonal"
                              prepend-icon="tabler-plus"
                              @click="addReview(section)"
                            >
                              Add Review
                            </VBtn>
                          </div>
                        </VCol>

                        <!-- FAQs List Prop -->
                        <VCol
                          v-else-if="key === 'faqs' && Array.isArray(section.props[key])"
                          cols="12"
                        >
                          <div class="d-flex flex-column gap-4">
                            <VCard
                              v-for="(faq, index) in section.props[key]"
                              :key="index"
                              variant="outlined"
                              class="mb-2"
                            >
                              <VCardText>
                                <div class="d-flex justify-space-between align-start mb-4">
                                  <span class="text-subtitle-2">FAQ {{ index + 1 }}</span>
                                  <VBtn
                                    color="error"
                                    variant="text"
                                    size="small"
                                    icon="tabler-trash"
                                    @click="removeFaq(section, index)"
                                  />
                                </div>
                                <VRow>
                                  <VCol cols="12">
                                    <AppTextField
                                      v-model="faq.question"
                                      label="Question"
                                    />
                                  </VCol>
                                  <VCol cols="12">
                                    <VTextarea
                                      v-model="faq.answer"
                                      label="Answer"
                                      rows="3"
                                      auto-grow
                                    />
                                  </VCol>
                                </VRow>
                              </VCardText>
                            </VCard>
                            
                            <VBtn
                              variant="tonal"
                              prepend-icon="tabler-plus"
                              @click="addFaq(section)"
                            >
                              Add FAQ
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
