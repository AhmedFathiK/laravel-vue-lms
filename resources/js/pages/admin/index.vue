<script setup>
import { ref, onMounted, computed } from 'vue'
import { useTheme } from 'vuetify'
import api from '@/utils/api'
import CardStatisticsHorizontal from '@core/components/cards/CardStatisticsHorizontal.vue'
import FinancialTrendChart from '@/components/charts/FinancialTrendChart.vue'

// State
const stats = ref({
  financials: { totalRevenue: 0, totalExpenses: 0, netProfit: 0, currency: 'EGP' },
  users: { totalStudents: 0, activeStudents: 0 },
  courses: { total: 0, active: 0, completionRate: 0, topPerforming: [], recentEnrollments: [] },
  charts: { enrollments: [], revenueByCourse: [] },
})

const isLoading = ref(true)
const isFinancialLoading = ref(false)
const financialChartData = ref({ labels: [], datasets: [] })
const dateRange = ref({ fromDate: null, toDate: null })

// Fetch Consolidated Stats
const fetchDashboardStats = async () => {
  isLoading.value = true
  try {
    const response = await api.get('/admin/dashboard/stats')

    stats.value = response
  } catch (error) {
    console.error('Error fetching dashboard stats:', error)
  } finally {
    isLoading.value = false
  }
}

// Fetch Financial Chart Data
const fetchFinancialChartData = async () => {
  isFinancialLoading.value = true
  try {
    const params = {
      fromDate: dateRange.value.fromDate,
      toDate: dateRange.value.toDate,
    }

    const response = await api.get('/admin/financial-analytics/chart-data', { params })

    financialChartData.value = response
  } catch (error) {
    console.error('Error fetching chart data:', error)
  } finally {
    isFinancialLoading.value = false
  }
}

onMounted(() => {
  fetchDashboardStats()
  fetchFinancialChartData()
})

// Enrollment Chart Options
const vuetifyTheme = useTheme()

const enrollmentChartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  
  return {
    chart: { type: 'area', toolbar: { show: false }, sparkline: { enabled: true } },
    colors: [currentTheme.primary],
    stroke: { curve: 'smooth', width: 2 },
    fill: { opacity: 0.3 },
    xaxis: { 
      categories: stats.value.charts?.enrollments?.map(e => e.date) || [],
      labels: { show: false },
      axisBorder: { show: false },
      axisTicks: { show: false },
    },
    yaxis: { show: false },
    grid: { show: false, padding: { top: 0, right: 0, bottom: 0, left: 0 } },
    tooltip: { x: { show: true }, y: { formatter: val => `${val} Enrollments` } },
  }
})

const enrollmentChartSeries = computed(() => [{
  name: 'Enrollments',
  data: stats.value.charts?.enrollments?.map(e => e.count) || [],
}])

// Tables Headers
const courseHeaders = [
  { title: 'Course', key: 'title' },
  { title: 'Enrollments', key: 'enrollmentsCount' },
  { title: 'Category', key: 'category' },
]

const enrollmentHeaders = [
  { title: 'Student', key: 'studentName' },
  { title: 'Course', key: 'courseTitle' },
  { title: 'Status', key: 'status' },
  { title: 'Date', key: 'enrolledAt' },
]
</script>

<template>
  <VRow>
    <!-- Welcome Header -->
    <VCol cols="12">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <h1 class="text-h4 mb-1">
            Admin Dashboard
          </h1>
          <p class="text-body-1 text-medium-emphasis">
            Overview of platform performance and key metrics.
          </p>
        </div>
        <div class="d-flex gap-4">
          <!-- Global Date Filter for Financials could go here if applies to all -->
        </div>
      </div>
    </VCol>

    <!-- Key Metrics Row -->
    <VCol
      cols="12"
      sm="6"
      md="3"
    >
      <CardStatisticsHorizontal
        title="Total Revenue"
        :stats="`${stats.financials?.currency || '$'}${stats.financials?.totalRevenue || 0}`"
        icon="tabler-currency-dollar"
        color="success"
      />
    </VCol>
    <VCol
      cols="12"
      sm="6"
      md="3"
    >
      <CardStatisticsHorizontal
        title="Net Profit"
        :stats="`${stats.financials?.currency || '$'}${stats.financials?.netProfit || 0}`"
        icon="tabler-chart-pie"
        color="primary"
      />
    </VCol>
    <VCol
      cols="12"
      sm="6"
      md="3"
    >
      <CardStatisticsHorizontal
        title="Active Students"
        :stats="stats.users?.activeStudents?.toString() || '0'"
        icon="tabler-users"
        color="info"
      />
    </VCol>
    <VCol
      cols="12"
      sm="6"
      md="3"
    >
      <CardStatisticsHorizontal
        title="Course Completion"
        :stats="`${stats.courses?.completionRate || 0}%`"
        icon="tabler-certificate"
        color="warning"
      />
    </VCol>

    <!-- Financial Trends -->
    <VCol
      cols="12"
      md="8"
    >
      <VCard title="Financial Trends">
        <template #append>
          <div class="d-flex gap-2">
            <AppDateTimePicker
              v-model="dateRange.fromDate"
              label="From"
              density="compact"
              style="width: 120px;"
              @update:model-value="fetchFinancialChartData"
            />
            <AppDateTimePicker
              v-model="dateRange.toDate"
              label="To"
              density="compact"
              style="width: 120px;"
              @update:model-value="fetchFinancialChartData"
            />
          </div>
        </template>
        <VCardText style="height: 350px">
          <FinancialTrendChart
            v-if="!isFinancialLoading && financialChartData.datasets && financialChartData.datasets.length"
            :chart-data="financialChartData"
          />
          <div
            v-else
            class="d-flex align-center justify-center h-100"
          >
            <VProgressCircular
              v-if="isFinancialLoading"
              indeterminate
              color="primary"
            />
            <span v-else>No data available</span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Enrollment Trends -->
    <VCol
      cols="12"
      md="4"
    >
      <VCard title="Enrollment Trend (30 Days)">
        <VCardText>
          <VueApexCharts
            type="area"
            height="300"
            :options="enrollmentChartOptions"
            :series="enrollmentChartSeries"
          />
          <div class="d-flex align-center justify-space-between mt-4">
            <h4 class="text-h4">
              {{ stats.users?.totalStudents || 0 }}
            </h4>
            <span class="text-body-2">Total Students</span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Top Courses & Recent Enrollments -->
    <VCol
      cols="12"
      md="6"
    >
      <VCard title="Top Performing Courses">
        <VDataTable
          :headers="courseHeaders"
          :items="stats.courses?.topPerforming || []"
          hide-default-footer
          class="text-no-wrap"
        />
      </VCard>
    </VCol>
    <VCol
      cols="12"
      md="6"
    >
      <VCard title="Recent Enrollments">
        <VDataTable
          :headers="enrollmentHeaders"
          :items="stats.courses?.recentEnrollments || []"
          hide-default-footer
          class="text-no-wrap"
        >
          <template #[`item.enrolledAt`]="{ item }">
            {{ new Date(item.enrolledAt).toLocaleDateString() }}
          </template>
          <template #[`item.status`]="{ item }">
            <VChip
              size="small"
              :color="item.status === 'Completed' ? 'success' : 'info'"
            >
              {{ item.status }}
            </VChip>
          </template>
        </VDataTable>
      </VCard>
    </VCol>
  </VRow>
</template>
