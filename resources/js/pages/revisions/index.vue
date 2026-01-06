<script setup>
import $api from '@/utils/api'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

definePage({
  meta: {
    layout: 'learner',
  },
})

const router = useRouter()

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

const fetchStats = async () => {
  loading.value = true
  try {
    const res = await $api.get('/revision/statistics')

    stats.value = res
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const fetchCategories = async () => {
  if (activeTab.value !== 'concepts') return
  
  categoriesLoading.value = true
  try {
    const res = await $api.get('/revision/grammar-topics')

    categories.value = res
  } catch (e) {
    console.error(e)
  } finally {
    categoriesLoading.value = false
  }
}

onMounted(() => {
  fetchStats()
  fetchCategories()
})

const startReview = (early = false) => {
  router.push({ 
    name: 'revisions-session', 
    query: { 
      type: activeTab.value === 'concepts' ? 'concept' : 'term',
      earlyReview: early ? '1' : undefined,
    }, 
  })
}

// Chart Options
const chartOptions = computed(() => {
  const currentStats = stats.value.concepts
  const total = currentStats.total
  
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
          size: '70%',
          labels: {
            show: true,
            total: {
              show: true,
              label: 'TOPICS',
              formatter: () => total,
              color: 'rgba(var(--v-theme-on-surface), 0.87)',
              fontSize: '12px',
              fontWeight: 600,
            },
            value: {
              fontSize: '24px',
              fontWeight: 700,
              color: 'rgba(var(--v-theme-on-surface), 0.87)',
            },
          },
        },
      },
    },
    dataLabels: { enabled: false },
    legend: {
      position: 'right',
      offsetY: 0,
      height: 230,
      itemMargin: { vertical: 5 },
      markers: { width: 10, height: 10, radius: 10 },
    },
    stroke: { show: false },
  }
})

const chartSeries = computed(() => {
  const buckets = stats.value.concepts.buckets
  
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
    <VRow>
      <VCol cols="12">
        <div class="d-flex align-center gap-4 mb-4">
          <VTabs
            v-model="activeTab"
            @update:model-value="fetchCategories"
          >
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
                <div class="text-success font-weight-bold mb-2">
                  {{ stats.concepts.dueCount }} FREE REVIEWS LEFT
                </div>
                <h2 class="text-h3 font-weight-bold mb-6">
                  Your grammar
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
                <VChip
                  size="small"
                  color="primary"
                  variant="tonal"
                  variant-color="primary"
                >
                  <VIcon
                    start
                    icon="tabler-lock-open"
                    size="small"
                  />
                  UNLOCKED
                </VChip>
              </div>
              
              <div
                class="d-flex justify-center align-center"
                style="min-height: 250px;"
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
                  height="250"
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
        <VRow>
          <VCol cols="12">
            <VCard class="pa-6 text-center">
              <h3 class="text-h5">
                Vocabulary Review
              </h3>
              <p class="mb-4">
                You have {{ stats.terms.dueCount }} words to review.
              </p>
              <VBtn 
                v-if="stats.terms.dueCount > 0"
                color="primary" 
                @click="startReview(false)"
              >
                Review Vocabulary
              </VBtn>
              <VBtn 
                v-else
                :disabled="stats.terms.total === 0"
                color="primary" 
                variant="outlined"
                @click="startReview(true)"
              >
                Review Vocabulary (Early)
              </VBtn>
            </VCard>
          </VCol>
        </VRow>
      </VWindowItem>
    </VWindow>
  </VContainer>
</template>

<style scoped>
.gap-4 { gap: 16px; }
.match-height {
  align-items: stretch;
}
</style>
