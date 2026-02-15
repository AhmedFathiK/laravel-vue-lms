<script setup>
import FinancialTrendChart from '@/components/charts/FinancialTrendChart.vue'
import api from '@/utils/api'
import { onMounted, ref, watch } from 'vue'

const stats = ref({
  totalIncome: 0,
  totalExpenses: 0,
  netProfit: 0,
})

const chartData = ref({
  labels: [],
  datasets: [],
})

const isLoading = ref(false)

const dateRange = ref({
  fromDate: null,
  toDate: null,
})

const fetchStats = async () => {
  try {
    const params = {
      fromDate: dateRange.value.fromDate,
      toDate: dateRange.value.toDate,
    }

    const response = await api.get('/admin/financial-analytics/stats', { params })

    stats.value = response
  } catch (error) {
    console.error('Error fetching financial stats:', error)
  }
}

const fetchChartData = async () => {
  isLoading.value = true
  try {
    const params = {
      fromDate: dateRange.value.fromDate,
      toDate: dateRange.value.toDate,
    }

    const response = await api.get('/admin/financial-analytics/chart-data', { params })

    chartData.value = response
  } catch (error) {
    console.error('Error fetching chart data:', error)
  } finally {
    isLoading.value = false
  }
}

const refreshData = () => {
  fetchStats()
  fetchChartData()
}

watch(dateRange, () => {
  refreshData()
}, { deep: true })

onMounted(() => {
  refreshData()
})
</script>

<template>
  <VRow>
    <!-- Filters -->
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

    <!-- Summary Cards -->
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
              ${{ stats.totalIncome }}
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
              ${{ stats.totalExpenses }}
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
              ${{ stats.netProfit }}
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
            v-if="!isLoading && chartData.datasets.length"
            :chart-data="chartData"
          />
          <div
            v-else
            class="d-flex align-center justify-center h-100"
          >
            <VProgressCircular
              v-if="isLoading"
              indeterminate
              color="primary"
            />
            <span v-else>No data available</span>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
