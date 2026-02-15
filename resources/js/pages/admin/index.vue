<script setup>
import CardStatisticsVerticalSimple from '@core/components/CardStatisticsVerticalSimple.vue'
import AnalyticsEarningReportsWeeklyOverview from '@/views/admin/AnalyticsEarningReportsWeeklyOverview.vue'
import FinancialTrendChart from '@/components/charts/FinancialTrendChart.vue'
import api from '@/utils/api'
import { onMounted, ref, watch } from 'vue'

const usersCount = ref('0')
const coursesCount = ref('0')
const totalRevenue = ref('0')
const recentUsers = ref([])
const isLoading = ref(true)

// Financial Dashboard State
const financialStats = ref({
  totalIncome: 0,
  totalExpenses: 0,
  netProfit: 0,
  currency: '',
})

const financialChartData = ref({
  labels: [],
  datasets: [],
})

const isFinancialLoading = ref(false)

const dateRange = ref({
  fromDate: null,
  toDate: null,
})

const fetchStats = async () => {
  isLoading.value = true
  try {
    // Users (Total and Recent)
    const usersRes = await api.get('/admin/users', { 
      params: { 
        'per_page': 5, 
        'sort_by': 'created_at', 
        'order_by': 'desc',
      },
    })

    usersCount.value = usersRes.data.total.toString()
    recentUsers.value = usersRes.data.data

    // Courses
    const coursesRes = await api.get('/admin/courses', { params: { 'per_page': 1 } })

    coursesCount.value = coursesRes.data.stats.total.toString()

    // Revenue
    const revenueRes = await api.get('/admin/receipts/statistics')

    // Format revenue with currency if needed, assuming it returns a number
    totalRevenue.value = `$${Number(revenueRes.data.total_revenue).toFixed(2)}`
    
  } catch (e) {
    console.error('Error fetching admin stats:', e)
  } finally {
    isLoading.value = false
  }
}

const fetchFinancialStats = async () => {
  try {
    const params = {
      fromDate: dateRange.value.fromDate,
      toDate: dateRange.value.toDate,
    }

    const response = await api.get('/admin/financial-analytics/stats', { params })

    financialStats.value = response
  } catch (error) {
    console.error('Error fetching financial stats:', error)
  }
}

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

const refreshFinancialData = () => {
  fetchFinancialStats()
  fetchFinancialChartData()
}

watch(dateRange, () => {
  refreshFinancialData()
}, { deep: true })

onMounted(() => {
  fetchStats()
  refreshFinancialData()
})

const headers = [
  { title: 'User', key: 'fullName' },
  { title: 'Email', key: 'email' },
  { title: 'Role', key: 'role' },
  { title: 'Joined At', key: 'created_at' },
]
</script>

<template>
  <VRow>
    <!-- Welcome Header -->
    <VCol cols="12">
      <div class="d-flex align-center justify-space-between mb-4">
        <div>
          <h1 class="text-h4 mb-1">
            Welcome back, Admin! 👋
          </h1>
          <p class="text-body-1 text-medium-emphasis">
            Here's what's happening with your platform today.
          </p>
        </div>
      </div>
    </VCol>

    <!-- Stats Cards -->
    <VCol
      cols="12"
      sm="6"
      md="4"
    >
      <CardStatisticsVerticalSimple
        title="Total Users"
        :stats="usersCount"
        icon="tabler-users"
        color="primary"
      />
    </VCol>
    <VCol
      cols="12"
      sm="6"
      md="4"
    >
      <CardStatisticsVerticalSimple
        title="Total Courses"
        :stats="coursesCount"
        icon="tabler-book"
        color="success"
      />
    </VCol>
    <VCol
      cols="12"
      sm="6"
      md="4"
    >
      <CardStatisticsVerticalSimple
        title="Total Revenue"
        :stats="totalRevenue"
        icon="tabler-currency-dollar"
        color="warning"
      />
    </VCol>

    <!-- Financial Management Section -->
    <VCol cols="12">
      <VDivider class="my-4" />
      <h2 class="text-h5 mb-4">
        Financial Overview
      </h2>
    </VCol>

    <!-- Financial Filters -->
    <VCol cols="12">
      <VCard title="Filters">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppDateTimePicker
                v-model="dateRange.fromDate"
                label="From Date"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppDateTimePicker
                v-model="dateRange.toDate"
                label="To Date"
                clearable
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Financial Summary Cards -->
    <VCol
      cols="12"
      md="4"
    >
      <VCard>
        <VCardText class="d-flex align-center justify-space-between">
          <div>
            <h6 class="text-h6 mb-2">
              Total Income
            </h6>
            <h4 class="text-h4 text-success">
              {{ financialStats.currency }} {{ financialStats.totalIncome }}
            </h4>
          </div>
          <VAvatar
            color="success"
            variant="tonal"
            size="42"
          >
            <VIcon
              icon="tabler-arrow-up"
              size="26"
            />
          </VAvatar>
        </VCardText>
      </VCard>
    </VCol>

    <VCol
      cols="12"
      md="4"
    >
      <VCard>
        <VCardText class="d-flex align-center justify-space-between">
          <div>
            <h6 class="text-h6 mb-2">
              Total Expenses
            </h6>
            <h4 class="text-h4 text-error">
              {{ financialStats.currency }} {{ financialStats.totalExpenses }}
            </h4>
          </div>
          <VAvatar
            color="error"
            variant="tonal"
            size="42"
          >
            <VIcon
              icon="tabler-arrow-down"
              size="26"
            />
          </VAvatar>
        </VCardText>
      </VCard>
    </VCol>

    <VCol
      cols="12"
      md="4"
    >
      <VCard>
        <VCardText class="d-flex align-center justify-space-between">
          <div>
            <h6 class="text-h6 mb-2">
              Net Profit
            </h6>
            <h4 class="text-h4 text-primary">
              {{ financialStats.currency }} {{ financialStats.netProfit }}
            </h4>
          </div>
          <VAvatar
            color="primary"
            variant="tonal"
            size="42"
          >
            <VIcon
              icon="tabler-chart-pie"
              size="26"
            />
          </VAvatar>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Trend Chart -->
    <VCol cols="12">
      <VCard title="Financial Trends">
        <VCardText style="height: 400px">
          <FinancialTrendChart
            v-if="!isFinancialLoading && financialChartData.datasets.length"
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

    <!-- Earning Reports -->
    <VCol
      cols="12"
      md="6"
    >
      <AnalyticsEarningReportsWeeklyOverview />
    </VCol>

    <!-- Recent Users Table -->
    <VCol
      cols="12"
      md="6"
    >
      <VCard title="Recent Users">
        <VDataTable
          :headers="headers"
          :items="recentUsers"
          :loading="isLoading"
          hide-default-footer
          class="text-no-wrap"
        >
          <template #[`item.fullName`]="{ item }">
            <div class="d-flex align-center">
              <VAvatar
                size="32"
                color="primary"
                variant="tonal"
                class="me-2"
              >
                <span>{{ item.first_name ? item.first_name.charAt(0) : '' }}{{ item.last_name ? item.last_name.charAt(0) : '' }}</span>
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="font-weight-medium">{{ item.first_name }} {{ item.last_name }}</span>
                <span class="text-xs text-medium-emphasis">ID: {{ item.id }}</span>
              </div>
            </div>
          </template>
          <template #[`item.role`]="{ item }">
            <div v-if="item.roles && item.roles.length">
              <VChip
                v-for="role in item.roles"
                :key="role.id"
                size="small"
                class="me-1 text-capitalize"
                color="primary"
                variant="tonal"
              >
                {{ role.name }}
              </VChip>
            </div>
            <span
              v-else
              class="text-medium-emphasis"
            >-</span>
          </template>
          <template #[`item.created_at`]="{ item }">
            {{ new Date(item.created_at).toLocaleDateString() }}
          </template>
        </VDataTable>
      </VCard>
    </VCol>
  </VRow>
</template>