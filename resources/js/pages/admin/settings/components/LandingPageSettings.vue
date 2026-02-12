<script setup>
import { useToast } from 'vue-toastification'
import IconSelectionDialog from '@/components/dialogs/IconSelectionDialog.vue'
import api from '@/utils/api'

const toast = useToast()
const isLoading = ref(false)
const isSaving = ref(false)
const config = ref([])
const activeTab = ref(null)

const isIconDialogVisible = ref(false)
const currentIconTarget = ref(null)

const openIconDialog = targetObj => {
  currentIconTarget.value = targetObj
  isIconDialogVisible.value = true
}

const handleIconSelect = icon => {
  if (currentIconTarget.value) {
    currentIconTarget.value.icon = icon

    // Clear any pending file upload if exists
    if (currentIconTarget.value._pendingFile) {
      delete currentIconTarget.value._pendingFile
      delete currentIconTarget.value._pendingKey
    }
  }
}

const fetchSettings = async () => {
  try {
    isLoading.value = true

    const data = await api.get('/admin/landing-page-settings')

    config.value = data
    if (data.length > 0 && !activeTab.value) {
      activeTab.value = data[0].id
    }
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
    isSaving.value = true

    // Find current section
    const currentSection = config.value.find(section => section.id === activeTab.value)
    
    if (currentSection) {
      // 1. Check section level props (e.g. Hero Image)
      if (currentSection.props._pendingFile) {
        try {
          const path = await uploadFile(currentSection.props._pendingFile)

          currentSection.props[currentSection.props._pendingKey] = path
        } catch (e) {
          console.error('Failed to upload section image', e)
        }
        delete currentSection.props._pendingFile
        delete currentSection.props._pendingKey
      }

      // 2. Check features array
      if (currentSection.props.features && Array.isArray(currentSection.props.features)) {
        for (const feature of currentSection.props.features) {
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
      if (currentSection.props.reviews && Array.isArray(currentSection.props.reviews)) {
        for (const review of currentSection.props.reviews) {
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

      // 4. Check team array
      if (currentSection.props.team && Array.isArray(currentSection.props.team)) {
        for (const member of currentSection.props.team) {
          if (member._pendingFile) {
            try {
              const path = await uploadFile(member._pendingFile)

              member[member._pendingKey] = path
            } catch (e) {
              console.error('Failed to upload team member image', e)
            }
            delete member._pendingFile
            delete member._pendingKey
          }
        }
      }

      // 5. Check pricing plans
      if (currentSection.props.plans && Array.isArray(currentSection.props.plans)) {
        for (const plan of currentSection.props.plans) {
          if (plan._pendingFile) {
            try {
              const path = await uploadFile(plan._pendingFile)

              plan[plan._pendingKey] = path
            } catch (e) {
              console.error('Failed to upload plan image', e)
            }
            delete plan._pendingFile
            delete plan._pendingKey
          }
        }
      }

      // 6. Check product stats
      if (currentSection.props.stats && Array.isArray(currentSection.props.stats)) {
        for (const stat of currentSection.props.stats) {
          if (stat._pendingFile) {
            try {
              const path = await uploadFile(stat._pendingFile)

              stat[stat._pendingKey] = path
            } catch (e) {
              console.error('Failed to upload stat icon', e)
            }
            delete stat._pendingFile
            delete stat._pendingKey
          }
        }
      }
    }

    // Clone current section to remove any internal properties before sending
    const currentSectionToSend = currentSection ? JSON.parse(JSON.stringify(currentSection)) : null
    
    // Prepare payload based on whether we found a specific section
    const payload = currentSectionToSend 
      ? { sectionId: currentSectionToSend.id, sectionData: currentSectionToSend }
      : { config: JSON.parse(JSON.stringify(config.value)) }

    await api.post('/admin/landing-page-settings', payload)
    toast.success('Settings updated successfully')
  } catch (error) {
    console.error(error)
    toast.error('Failed to save settings')
  } finally {
    isSaving.value = false
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
  OurTeam: {
    groups: [
      {
        name: 'Header',
        fields: ['tag', 'title', 'subtitle'],
      },
      {
        name: 'Team Members',
        fields: ['team'],
      },
    ],
    labels: {
      tag: 'Section Tag',
      title: 'Main Title',
      subtitle: 'Subtitle Description',
      team: 'Team Members',
    },
  },
  PricingPlans: {
    groups: [
      {
        name: 'Header',
        fields: ['tag', 'title', 'subtitle', 'saveText'],
      },
      {
        name: 'Plans',
        fields: ['plans'],
      },
    ],
    labels: {
      tag: 'Section Tag',
      title: 'Main Title',
      subtitle: 'Subtitle Description',
      saveText: 'Annual Savings Text',
      plans: 'Pricing Plans',
    },
  },
  ProductStats: {
    groups: [
      {
        name: 'Statistics',
        fields: ['stats'],
      },
    ],
    labels: {
      stats: 'Product Statistics',
    },
  },
}

const addStat = section => {
  if (!section.props.stats) section.props.stats = []
  section.props.stats.push({
    title: 'New Stat',
    value: '0',
    icon: 'tabler-chart-bar',
    color: 'primary',
  })
}

const removeStat = (section, index) => {
  section.props.stats.splice(index, 1)
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

const addTeamMember = section => {
  if (!section.props.team) section.props.team = []
  section.props.team.push({
    name: 'New Member',
    position: 'Position',
    image: null,
    backgroundColor: 'rgba(144, 85, 253, 0.16)',
    borderColor: 'rgba(144, 85, 253,0.16)',
  })
}

const removeTeamMember = (section, index) => {
  section.props.team.splice(index, 1)
}

const addPricingPlan = section => {
  if (!section.props.plans) section.props.plans = []
  section.props.plans.push({
    title: 'New Plan',
    image: null,
    monthlyPrice: 0,
    yearlyPrice: 0,
    features: ['Feature 1', 'Feature 2'],
    supportType: 'Standard',
    supportMedium: 'Email',
    respondTime: '24h',
    current: false,
  })
}

const removePricingPlan = (section, index) => {
  section.props.plans.splice(index, 1)
}

const addPlanFeature = plan => {
  if (!plan.features) plan.features = []
  plan.features.push('New Feature')
}

const removePlanFeature = (plan, index) => {
  plan.features.splice(index, 1)
}

const getGroups = section => {
  const config = sectionConfigs[section.component]
  const allKeys = Object.keys(section.props)

  if (config && config.groups) {
    const configuredGroups = config.groups.map(group => ({
      ...group,
      fields: group.fields,
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
        <div class="d-flex flex-column gap-4">
          <VTabs
            v-model="activeTab"
            show-arrows
            class="mb-4"
          >
            <VTab
              v-for="section in config"
              :key="section.id"
              :value="section.id"
            >
              {{ section.name }}
            </VTab>
          </VTabs>

          <VWindow v-model="activeTab">
            <VWindowItem
              v-for="section in config"
              :key="section.id"
              :value="section.id"
            >
              <div class="d-flex align-center gap-4 w-100 mb-6">
                <VSwitch
                  v-model="section.visible"
                  hide-details
                  density="compact"
                  label="Visible"
                  class="me-4"
                />
                <span class="text-caption text-medium-emphasis">{{ section.name }} ({{ section.component }})</span>
              </div>
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
                          v-else-if="key === 'saveText' || typeof section.props[key] === 'string' || section.props[key] === null"
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

                        <!-- Product Stats List Prop -->
                        <VCol
                          v-else-if="key === 'stats' && Array.isArray(section.props[key])"
                          cols="12"
                        >
                          <div class="d-flex flex-column gap-4">
                            <VExpansionPanels
                              variant="accordion"
                              class="expansion-panels-width-border"
                            >
                              <VExpansionPanel
                                v-for="(stat, index) in section.props[key]"
                                :key="index"
                              >
                                <VExpansionPanelTitle>
                                  <div class="d-flex justify-space-between align-center w-100">
                                    <span class="text-subtitle-2">{{ stat.title || `Stat ${index + 1}` }}</span>
                                    <VBtn
                                      color="error"
                                      variant="text"
                                      size="small"
                                      icon="tabler-trash"
                                      class="me-2"
                                      @click.stop="removeStat(section, index)"
                                    />
                                  </div>
                                </VExpansionPanelTitle>
                                <VExpansionPanelText>
                                  <VRow class="mt-2">
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <AppTextField
                                        v-model="stat.title"
                                        label="Title"
                                      />
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <AppTextField
                                        v-model="stat.value"
                                        label="Value"
                                      />
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <div class="d-flex justify-space-between align-center mb-1">
                                        <VLabel class="text-body-2 text-high-emphasis">
                                          Icon Image
                                        </VLabel>
                                        <VBtn
                                          size="small"
                                          variant="text"
                                          color="primary"
                                          prepend-icon="tabler-search"
                                          @click="openIconDialog(stat)"
                                        >
                                          Select Icon
                                        </VBtn>
                                      </div>
                                      <VFileInput
                                        label="Icon Image"
                                        prepend-icon="tabler-camera"
                                        accept="image/*"
                                        @change="e => handleFileUpload(e, stat, 'icon')"
                                      />
                                      <div
                                        v-if="stat.icon"
                                        class="mt-2"
                                      >
                                        <div class="d-flex align-center gap-4">
                                          <VIcon
                                            v-if="isTablerIcon(stat.icon)"
                                            :icon="stat.icon"
                                            size="40"
                                          />
                                          <VImg
                                            v-else
                                            :src="stat.icon"
                                            max-width="80"
                                            max-height="80"
                                            class="rounded border"
                                          />
                                          <VBtn
                                            size="x-small"
                                            color="error"
                                            variant="text"
                                            @click="removeImage(stat, 'icon')"
                                          >
                                            Remove Icon
                                          </VBtn>
                                        </div>
                                      </div>
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <AppTextField
                                        v-model="stat.color"
                                        label="Color"
                                        placeholder="primary"
                                      >
                                        <template #append-inner>
                                          <div
                                            class="cursor-pointer border rounded"
                                            :style="{
                                              backgroundColor: stat.color,
                                              width: '24px',
                                              height: '24px',
                                              borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity)) !important'
                                            }"
                                          >
                                            <VMenu
                                              activator="parent"
                                              :close-on-content-click="false"
                                              location="bottom end"
                                            >
                                              <VColorPicker
                                                v-model="stat.color"
                                                mode="hex"
                                                :modes="['hex', 'rgba', 'hsla']"
                                              />
                                            </VMenu>
                                          </div>
                                        </template>
                                      </AppTextField>
                                    </VCol>
                                  </VRow>
                                </VExpansionPanelText>
                              </VExpansionPanel>
                            </VExpansionPanels>
                            
                            <VBtn
                              variant="tonal"
                              prepend-icon="tabler-plus"
                              @click="addStat(section)"
                            >
                              Add Stat
                            </VBtn>
                          </div>
                        </VCol>

                        <!-- Features List Prop -->
                        <VCol
                          v-else-if="key === 'features' && Array.isArray(section.props[key])"
                          cols="12"
                        >
                          <div class="d-flex flex-column gap-4">
                            <VExpansionPanels
                              variant="accordion"
                              class="expansion-panels-width-border"
                            >
                              <VExpansionPanel
                                v-for="(feature, index) in section.props[key]"
                                :key="index"
                              >
                                <VExpansionPanelTitle>
                                  <div class="d-flex justify-space-between align-center w-100">
                                    <span class="text-subtitle-2">{{ feature.title || `Feature ${index + 1}` }}</span>
                                    <VBtn
                                      color="error"
                                      variant="text"
                                      size="small"
                                      icon="tabler-trash"
                                      class="me-2"
                                      @click.stop="removeFeature(section, index)"
                                    />
                                  </div>
                                </VExpansionPanelTitle>
                                <VExpansionPanelText>
                                  <VRow class="mt-2">
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
                                      <div class="d-flex justify-space-between align-center mb-1">
                                        <VLabel class="text-body-2 text-high-emphasis">
                                          Icon
                                        </VLabel>
                                        <VBtn
                                          size="small"
                                          variant="text"
                                          color="primary"
                                          prepend-icon="tabler-search"
                                          @click="openIconDialog(feature)"
                                        >
                                          Select Icon
                                        </VBtn>
                                      </div>
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
                                </VExpansionPanelText>
                              </VExpansionPanel>
                            </VExpansionPanels>
                            
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
                            <VExpansionPanels
                              variant="accordion"
                              class="expansion-panels-width-border"
                            >
                              <VExpansionPanel
                                v-for="(review, index) in section.props[key]"
                                :key="index"
                              >
                                <VExpansionPanelTitle>
                                  <div class="d-flex justify-space-between align-center w-100">
                                    <span class="text-subtitle-2">{{ review.name || `Review ${index + 1}` }}</span>
                                    <VBtn
                                      color="error"
                                      variant="text"
                                      size="small"
                                      icon="tabler-trash"
                                      class="me-2"
                                      @click.stop="removeReview(section, index)"
                                    />
                                  </div>
                                </VExpansionPanelTitle>
                                <VExpansionPanelText>
                                  <VRow class="mt-2">
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
                                </VExpansionPanelText>
                              </VExpansionPanel>
                            </VExpansionPanels>
                            
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
                            <VExpansionPanels
                              variant="accordion"
                              class="expansion-panels-width-border"
                            >
                              <VExpansionPanel
                                v-for="(faq, index) in section.props[key]"
                                :key="index"
                              >
                                <VExpansionPanelTitle>
                                  <div class="d-flex justify-space-between align-center w-100">
                                    <span class="text-subtitle-2">{{ faq.question || `FAQ ${index + 1}` }}</span>
                                    <VBtn
                                      color="error"
                                      variant="text"
                                      size="small"
                                      icon="tabler-trash"
                                      class="me-2"
                                      @click.stop="removeFaq(section, index)"
                                    />
                                  </div>
                                </VExpansionPanelTitle>
                                <VExpansionPanelText>
                                  <VRow class="mt-2">
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
                                </VExpansionPanelText>
                              </VExpansionPanel>
                            </VExpansionPanels>
                            
                            <VBtn
                              variant="tonal"
                              prepend-icon="tabler-plus"
                              @click="addFaq(section)"
                            >
                              Add FAQ
                            </VBtn>
                          </div>
                        </VCol>

                        <!-- Team Members List Prop -->
                        <VCol
                          v-else-if="key === 'team' && Array.isArray(section.props[key])"
                          cols="12"
                        >
                          <div class="d-flex flex-column gap-4">
                            <VExpansionPanels
                              variant="accordion"
                              class="expansion-panels-width-border"
                            >
                              <VExpansionPanel
                                v-for="(member, index) in section.props[key]"
                                :key="index"
                              >
                                <VExpansionPanelTitle>
                                  <div class="d-flex justify-space-between align-center w-100">
                                    <span class="text-subtitle-2">{{ member.name || `Member ${index + 1}` }}</span>
                                    <VBtn
                                      color="error"
                                      variant="text"
                                      size="small"
                                      icon="tabler-trash"
                                      class="me-2"
                                      @click.stop="removeTeamMember(section, index)"
                                    />
                                  </div>
                                </VExpansionPanelTitle>
                                <VExpansionPanelText>
                                  <VRow>
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <AppTextField
                                        v-model="member.name"
                                        label="Name"
                                      />
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <AppTextField
                                        v-model="member.position"
                                        label="Position"
                                      />
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <AppTextField
                                        v-model="member.backgroundColor"
                                        label="Background Color"
                                        placeholder="rgba(0,0,0,0.5)"
                                      >
                                        <template #append-inner>
                                          <div
                                            class="cursor-pointer border rounded"
                                            :style="{
                                              backgroundColor: member.backgroundColor,
                                              width: '24px',
                                              height: '24px',
                                              borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity)) !important'
                                            }"
                                          >
                                            <VMenu
                                              activator="parent"
                                              :close-on-content-click="false"
                                              location="bottom end"
                                            >
                                              <VColorPicker
                                                v-model="member.backgroundColor"
                                                mode="rgba"
                                                :modes="['rgba', 'hex', 'hsla']"
                                              />
                                            </VMenu>
                                          </div>
                                        </template>
                                      </AppTextField>
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <AppTextField
                                        v-model="member.borderColor"
                                        label="Border Color"
                                        placeholder="rgba(0,0,0,0.5)"
                                      >
                                        <template #append-inner>
                                          <div
                                            class="cursor-pointer border rounded"
                                            :style="{
                                              backgroundColor: member.borderColor,
                                              width: '24px',
                                              height: '24px',
                                              borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity)) !important'
                                            }"
                                          >
                                            <VMenu
                                              activator="parent"
                                              :close-on-content-click="false"
                                              location="bottom end"
                                            >
                                              <VColorPicker
                                                v-model="member.borderColor"
                                                mode="rgba"
                                                :modes="['rgba', 'hex', 'hsla']"
                                              />
                                            </VMenu>
                                          </div>
                                        </template>
                                      </AppTextField>
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <VLabel class="mb-1 text-body-2 text-high-emphasis">
                                        Photo
                                      </VLabel>
                                      <VFileInput
                                        label="Photo"
                                        prepend-icon="tabler-camera"
                                        accept="image/*"
                                        @change="e => handleFileUpload(e, member, 'image')"
                                      />
                                      <div
                                        v-if="member.image"
                                        class="mt-2"
                                      >
                                        <div class="d-flex align-center gap-4">
                                          <VImg
                                            :src="member.image"
                                            max-width="80"
                                            max-height="80"
                                            class="rounded border"
                                          />
                                          <VBtn
                                            size="x-small"
                                            color="error"
                                            variant="text"
                                            @click="removeImage(member, 'image')"
                                          >
                                            Remove Photo
                                          </VBtn>
                                        </div>
                                      </div>
                                    </VCol>
                                  </VRow>
                                </VExpansionPanelText>
                              </VExpansionPanel>
                            </VExpansionPanels>
                            
                            <VBtn
                              variant="tonal"
                              prepend-icon="tabler-plus"
                              @click="addTeamMember(section)"
                            >
                              Add Member
                            </VBtn>
                          </div>
                        </VCol>

                        <!-- Pricing Plans List Prop -->
                        <VCol
                          v-else-if="key === 'plans' && Array.isArray(section.props[key])"
                          cols="12"
                        >
                          <div class="d-flex flex-column gap-4">
                            <VExpansionPanels
                              variant="accordion"
                              class="expansion-panels-width-border"
                            >
                              <VExpansionPanel
                                v-for="(plan, index) in section.props[key]"
                                :key="index"
                              >
                                <VExpansionPanelTitle>
                                  <div class="d-flex justify-space-between align-center w-100">
                                    <div class="d-flex align-center gap-2">
                                      <span class="text-subtitle-2">{{ plan.title || `Plan ${index + 1}` }}</span>
                                      <VChip
                                        v-if="plan.current"
                                        color="primary"
                                        size="x-small"
                                      >
                                        Popular
                                      </VChip>
                                    </div>
                                    <VBtn
                                      color="error"
                                      variant="text"
                                      size="small"
                                      icon="tabler-trash"
                                      class="me-2"
                                      @click.stop="removePricingPlan(section, index)"
                                    />
                                  </div>
                                </VExpansionPanelTitle>
                                <VExpansionPanelText>
                                  <VRow class="mt-2">
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <AppTextField
                                        v-model="plan.title"
                                        label="Plan Title"
                                      />
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <VSwitch
                                        v-model="plan.current"
                                        label="Mark as Popular"
                                      />
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <AppTextField
                                        v-model.number="plan.monthlyPrice"
                                        label="Monthly Price"
                                        type="number"
                                      />
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <AppTextField
                                        v-model.number="plan.yearlyPrice"
                                        label="Yearly Price"
                                        type="number"
                                      />
                                    </VCol>
                                    
                                    <!-- Support Details -->
                                    <VCol
                                      cols="12"
                                      md="4"
                                    >
                                      <AppTextField
                                        v-model="plan.supportType"
                                        label="Support Type"
                                      />
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="4"
                                    >
                                      <AppTextField
                                        v-model="plan.supportMedium"
                                        label="Support Medium"
                                      />
                                    </VCol>
                                    <VCol
                                      cols="12"
                                      md="4"
                                    >
                                      <AppTextField
                                        v-model="plan.respondTime"
                                        label="Response Time"
                                      />
                                    </VCol>

                                    <VCol
                                      cols="12"
                                      md="6"
                                    >
                                      <VLabel class="mb-1 text-body-2 text-high-emphasis">
                                        Plan Image
                                      </VLabel>
                                      <VFileInput
                                        label="Plan Image"
                                        prepend-icon="tabler-camera"
                                        accept="image/*"
                                        @change="e => handleFileUpload(e, plan, 'image')"
                                      />
                                      <div
                                        v-if="plan.image"
                                        class="mt-2"
                                      >
                                        <div class="d-flex align-center gap-4">
                                          <VImg
                                            :src="plan.image"
                                            max-width="80"
                                            max-height="80"
                                            class="rounded border"
                                          />
                                          <VBtn
                                            size="x-small"
                                            color="error"
                                            variant="text"
                                            @click="removeImage(plan, 'image')"
                                          >
                                            Remove Image
                                          </VBtn>
                                        </div>
                                      </div>
                                    </VCol>

                                    <!-- Features List -->
                                    <VCol cols="12">
                                      <VLabel class="mb-2 text-subtitle-2">
                                        Features
                                      </VLabel>
                                      <div class="d-flex flex-column gap-2 ps-4 border-s-lg">
                                        <div 
                                          v-for="(feature, fIndex) in plan.features" 
                                          :key="fIndex"
                                          class="d-flex align-center gap-2"
                                        >
                                          <AppTextField
                                            v-model="plan.features[fIndex]"
                                            placeholder="Feature description"
                                            density="compact"
                                            hide-details
                                          />
                                          <VBtn
                                            color="error"
                                            variant="text"
                                            icon="tabler-x"
                                            size="small"
                                            @click="removePlanFeature(plan, fIndex)"
                                          />
                                        </div>
                                        <VBtn
                                          variant="text"
                                          size="small"
                                          prepend-icon="tabler-plus"
                                          class="justify-start px-0"
                                          @click="addPlanFeature(plan)"
                                        >
                                          Add Feature
                                        </VBtn>
                                      </div>
                                    </VCol>
                                  </VRow>
                                </VExpansionPanelText>
                              </VExpansionPanel>
                            </VExpansionPanels>
                            
                            <VBtn
                              variant="tonal"
                              prepend-icon="tabler-plus"
                              @click="addPricingPlan(section)"
                            >
                              Add Pricing Plan
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

              <div class="mt-4 d-flex justify-end">
                <VBtn
                  :loading="isSaving"
                  :disabled="isSaving"
                  @click="saveSettings"
                >
                  Save Changes
                </VBtn>
              </div>
            </VWindowItem>
          </VWindow>
        </div>
      </div>
    </VCardText>
  </VCard>

  <IconSelectionDialog
    v-model:is-dialog-visible="isIconDialogVisible"
    @select="handleIconSelect"
  />
</template>
