<script setup>
import { useActiveCourse } from '@/stores/activeCourse'
import $api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTheme } from 'vuetify'

definePage({
  meta: {
    layout: 'learner',
  },
})

const route = useRoute()
const router = useRouter()
const vuetifyTheme = useTheme()
const activeCourseStore = useActiveCourse()

const activeTab = ref('concepts') // 'concepts' or 'terms'
const loading = ref(true)

const stats = ref({
  concepts: {
    total: 0,
    dueCount: 0,
    buckets: { needsPractice: 0, improving: 0, strong: 0, mastered: 0 },
  },
  terms: {
    total: 0,
    dueCount: 0,
    buckets: { needsPractice: 0, improving: 0, strong: 0, mastered: 0 },
  },
})

// Categories data for accordion
const categories = ref([])
const categoriesLoading = ref(false)

const initializePage = async () => {
  loading.value = true
  
  if (!activeCourseStore.activeCourseId) {
    await activeCourseStore.fetchActiveCourse()
    
    if (!activeCourseStore.activeCourseId) {
      router.push('/courses/select')
      
      return
    }
  }

  // Fetch data
  await Promise.all([
    fetchStats(),
    fetchCategories(),
  ])
  
  loading.value = false
}

const fetchStats = async () => {
  if (!activeCourseStore.activeCourseId) return
  
  try {
    const res = await $api.get('/revision/statistics', {
      params: { courseId: activeCourseStore.activeCourseId },
    })

    stats.value = res
  } catch (e) {
    if (e.response?.status === 403) {
      router.push('/not-authorized')
    }
    console.error(e)
  }
}

const fetchCategories = async () => {
  if (activeTab.value !== 'concepts' || !activeCourseStore.activeCourseId) return
  
  categoriesLoading.value = true
  try {
    const res = await $api.get('/revision/grammar-topics', {
      params: { courseId: activeCourseStore.activeCourseId },
    })

    categories.value = res
  } catch (e) {
    if (e.response?.status === 403) {
      router.push('/not-authorized')
    }
    console.error(e)
  } finally {
    categoriesLoading.value = false
  }
}

watch(() => activeCourseStore.activeCourseId, newVal => {
  if (newVal) {
    initializePage()
  } else {
    router.push('/courses/select')
  }
})

watch(activeTab, () => {
  if (activeTab.value === 'concepts') {
    fetchCategories()
  }
})

onMounted(() => {
  initializePage()
})

const selectedCourse = computed(() => activeCourseStore.activeCourse)

const startReview = (early = false) => {
  router.push({ 
    name: 'revisions-session', 
    query: { 
      type: activeTab.value === 'concepts' ? 'concept' : 'term',
      earlyReview: early ? '1' : undefined,
      courseId: activeCourseStore.activeCourseId,
    }, 
  })
}

// Chart Options
const chartOptions = computed(() => {
  const currentStats = activeTab.value === 'concepts' ? stats.value.concepts : stats.value.terms
  const total = currentStats.total
  const themeColors = vuetifyTheme.current.value.colors
  const variableColors = vuetifyTheme.current.value.variables
  
  const labelColor = `rgba(${variableColors['theme-on-surface']}, 0.87)`
  
  return {
    chart: {
      type: 'donut',
      fontFamily: 'inherit',
    },
    labels: ['Needs practice', 'Improving', 'Strong', 'Mastered'],
    colors: [
      'rgba(var(--v-theme-error), 1)',
      'rgba(var(--v-theme-warning), 1)',
      'rgba(var(--v-theme-info), 1)',
      'rgba(var(--v-theme-success), 1)',
    ],
    plotOptions: {
      pie: {
        donut: {
          size: '75%',
          labels: {
            show: true,
            total: {
              show: true,
              label: activeTab.value === 'concepts' ? 'TOPICS' : 'WORDS',
              formatter: () => total,
              color: 'rgba(var(--v-theme-on-surface), 0.87)',
              fontSize: '13px',
              fontWeight: 600,
            },
            value: {
              fontSize: '26px',
              fontWeight: 700,
              color: 'rgba(var(--v-theme-on-surface), 0.87)',
            },
          },
        },
      },
    },
    dataLabels: { enabled: false },
    legend: {
      position: 'bottom',
      offsetY: 0,
      itemMargin: { horizontal: 10, vertical: 5 },
      markers: { width: 10, height: 10, radius: 10 },
      labels: {
        colors: labelColor,
      },
    },
    stroke: { show: false },
  }
})

const chartSeries = computed(() => {
  const buckets = activeTab.value === 'concepts' ? stats.value.concepts.buckets : stats.value.terms.buckets
  
  return [
    buckets.needsPractice,
    buckets.improving,
    buckets.strong,
    buckets.mastered,
  ]
})

const getStatusColor = status => {
  switch (status) {
  case 'Needs practice': return 'error'
  case 'Improving': return 'warning'
  case 'Strong': return 'info'
  case 'Mastered': return 'success'
  default: return 'grey'
  }
}

// Expand/Collapse all
const openPanels = ref([])

const toggleAll = () => {
  if (openPanels.value.length === categories.value.length) {
    openPanels.value = []
  } else {
    openPanels.value = categories.value.map((_, i) => i)
  }
}
</script>

<template>
  <VContainer>
    <div
      v-if="loading"
      class="d-flex justify-center align-center"
      style="min-height: 400px"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
    </div>

    <template v-else>
      <VRow>
        <VCol cols="12">
          <div class="d-flex align-center gap-4 mb-4">
            <VTabs v-model="activeTab">
              <VTab value="concepts">
                Grammar
              </VTab>
              <VTab value="terms">
                Vocabulary
              </VTab>
            </VTabs>
          </div>
        </VCol>
      </VRow>

      <VWindow v-model="activeTab">
        <VWindowItem value="concepts">
          <!-- Top Section: 2 Cards -->
          <VRow class="match-height">
            <!-- Left Card: Your Grammar -->
            <VCol
              cols="12"
              md="6"
            >
              <VCard class="h-100 pa-6 d-flex flex-column justify-space-between">
                <div>
                  <h2 class="text-h3 font-weight-bold mb-6">
                    Your grammar in {{ selectedCourse?.title || 'Course' }}
                  </h2>
                
                  <div class="d-flex align-center mb-2">
                    <span class="text-h2 font-weight-bold me-2">{{ stats.concepts.total }}</span>
                  </div>
                  <div class="text-body-1 text-medium-emphasis mb-6">
                    Total topics learned
                    <VIcon
                      icon="tabler-info-circle"
                      size="small"
                      class="ms-1"
                    />
                  </div>
                
                  <p class="text-body-1 mb-6">
                    Make progress faster with daily grammar practice.
                  </p>
                </div>
              
                <div>
                  <VBtn 
                    v-if="stats.concepts.dueCount > 0"
                    color="primary" 
                    size="large" 
                    rounded="pill"
                    class="px-8"
                    @click="startReview(false)"
                  >
                    Practice now
                  </VBtn>
                  <VBtn 
                    v-else
                    :disabled="stats.concepts.total === 0"
                    color="primary" 
                    size="large" 
                    rounded="pill"
                    variant="outlined"
                    class="px-8"
                    @click="startReview(true)"
                  >
                    Practice anyway (Early Review)
                  </VBtn>
                </div>
              </VCard>
            </VCol>

            <!-- Right Card: Grammar Topic Mastery Chart -->
            <VCol
              cols="12"
              md="6"
            >
              <VCard class="h-100 pa-6">
                <div class="d-flex justify-space-between align-center mb-4">
                  <h3 class="text-h5 font-weight-bold">
                    Grammar topic mastery
                  </h3>
                </div>
              
                <div
                  class="d-flex justify-center align-center"
                  style="min-height: 300px;"
                >
                  <div
                    v-if="!loading && stats.concepts.total === 0"
                    class="text-medium-emphasis"
                  >
                    No data available
                  </div>
                  <VueApexCharts
                    v-else-if="!loading"
                    type="donut"
                    height="300"
                    width="100%"
                    :options="chartOptions"
                    :series="chartSeries"
                  />
                  <VProgressCircular
                    v-else
                    indeterminate
                    color="primary"
                  />
                </div>
              </VCard>
            </VCol>
          </VRow>

          <!-- Bottom Section: Accordion List -->
          <VRow class="mt-6">
            <VCol cols="12">
              <div class="d-flex justify-space-between align-end mb-4">
                <div>
                  <h3 class="text-h4 font-weight-bold mb-1">
                    All categories
                  </h3>
                  <div class="text-body-1 text-medium-emphasis">
                    Categories: {{ categories.length }}
                  </div>
                </div>
              
                <VBtn 
                  variant="text" 
                  color="primary" 
                  prepend-icon="tabler-arrows-vertical"
                  @click="toggleAll"
                >
                  {{ openPanels.length === categories.length ? 'Collapse all' : 'Expand all' }}
                </VBtn>
              </div>

              <VExpansionPanels
                v-model="openPanels"
                multiple
              >
                <VExpansionPanel
                  v-for="category in categories"
                  :key="category.id"
                >
                  <VExpansionPanelTitle>
                    <div class="d-flex align-center gap-2">
                      <VIcon
                        icon="tabler-category"
                        color="primary"
                        class="me-2"
                      />
                      <span class="font-weight-bold text-h6">{{ category.title }}</span>
                    </div>
                  </VExpansionPanelTitle>
                
                  <VExpansionPanelText>
                    <div
                      v-if="category.description"
                      class="mb-4 text-body-2"
                    >
                      {{ category.description }}
                    </div>
                  
                    <VList
                      lines="two"
                      class="bg-transparent"
                    >
                      <template
                        v-for="(topic, index) in category.topics"
                        :key="topic.id"
                      >
                        <VDivider v-if="index > 0" />
                        <VListItem class="px-0 py-2">
                          <template #prepend>
                            <div class="me-4 mt-1">
                              <VBadge
                                dot
                                :color="getStatusColor(topic.status)"
                                location="bottom right"
                                offset-x="2"
                                offset-y="2"
                              >
                                <VIcon icon="tabler-book" />
                              </VBadge>
                            </div>
                          </template>
                        
                          <VListItemTitle class="font-weight-bold mb-1">
                            {{ topic.title }}
                          </VListItemTitle>
                          <VListItemSubtitle>
                            {{ topic.explanation || 'Practice ' + topic.title }}
                          </VListItemSubtitle>
                        
                          <template #append>
                            <VChip
                              size="small"
                              :color="getStatusColor(topic.status)"
                              class="me-2"
                            >
                              {{ topic.status }}
                            </VChip>
                          <!-- Could link to specific lesson or practice just this topic later -->
                          </template>
                        </VListItem>
                      </template>
                      <div
                        v-if="category.topics.length === 0"
                        class="text-center text-medium-emphasis py-4"
                      >
                        No topics in this category yet.
                      </div>
                    </VList>
                  </VExpansionPanelText>
                </VExpansionPanel>
              </VExpansionPanels>
            </VCol>
          </VRow>
        </VWindowItem>

        <!-- Vocabulary Tab (Keeping simpler for now or reuse structure) -->
        <VWindowItem value="terms">
          <!-- Top Section: 2 Cards -->
          <VRow class="match-height">
            <!-- Left Card: Your Vocabulary -->
            <VCol
              cols="12"
              md="6"
            >
              <VCard class="h-100 pa-6 d-flex flex-column justify-space-between">
                <div>
                  <div class="text-success font-weight-bold mb-2">
                    {{ stats.terms.dueCount }} WORDS DUE FOR REVIEW
                  </div>
                  <h2 class="text-h3 font-weight-bold mb-6">
                    Your vocabulary in {{ selectedCourse?.title || 'Course' }}
                  </h2>
                
                  <div class="d-flex align-center mb-2">
                    <span class="text-h2 font-weight-bold me-2">{{ stats.terms.total }}</span>
                  </div>
                  <div class="text-body-1 text-medium-emphasis mb-6">
                    Total words learned
                    <VIcon
                      icon="tabler-info-circle"
                      size="small"
                      class="ms-1"
                    />
                  </div>
                
                  <p class="text-body-1 mb-6">
                    Build your vocabulary with daily practice.
                  </p>
                </div>
              
                <div>
                  <VBtn 
                    v-if="stats.terms.dueCount > 0"
                    color="primary" 
                    size="large" 
                    rounded="pill"
                    class="px-8"
                    @click="startReview(false)"
                  >
                    Practice now
                  </VBtn>
                  <VBtn 
                    v-else
                    :disabled="stats.terms.total === 0"
                    color="primary" 
                    size="large" 
                    rounded="pill"
                    variant="outlined"
                    class="px-8"
                    @click="startReview(true)"
                  >
                    Practice anyway (Early Review)
                  </VBtn>
                </div>
              </VCard>
            </VCol>

            <!-- Right Card: Vocabulary Mastery Chart -->
            <VCol
              cols="12"
              md="6"
            >
              <VCard class="h-100 pa-6">
                <div class="d-flex justify-space-between align-center mb-4">
                  <h3 class="text-h5 font-weight-bold">
                    Vocabulary mastery
                  </h3>
                </div>
              
                <div
                  class="d-flex justify-center align-center"
                  style="min-height: 300px;"
                >
                  <div
                    v-if="!loading && stats.terms.total === 0"
                    class="text-medium-emphasis"
                  >
                    No data available
                  </div>
                  <VueApexCharts
                    v-else-if="!loading"
                    type="donut"
                    height="300"
                    width="100%"
                    :options="chartOptions"
                    :series="chartSeries"
                  />
                  <VProgressCircular
                    v-else
                    indeterminate
                    color="primary"
                  />
                </div>
              </VCard>
            </VCol>
          </VRow>
        </VWindowItem>
      </VWindow>
    </template>
  </VContainer>
</template>

<style scoped>
.gap-4 { gap: 16px; }
.match-height {
  align-items: stretch;
}
</style>
