<script setup>
import { Bar } from 'vue-chartjs'
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js'
import { computed } from 'vue'
import { useTheme } from 'vuetify'

const props = defineProps({
  chartData: {
    type: Object,
    required: true,
  },
})

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale)

const theme = useTheme()

const chartOptions = computed(() => {
  const currentTheme = theme.current.value.colors

  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
        labels: {
          color: currentTheme['on-surface'],
        },
      },
      tooltip: {
        backgroundColor: currentTheme['surface'],
        titleColor: currentTheme['on-surface'],
        bodyColor: currentTheme['on-surface'],
        borderColor: currentTheme['border-color'],
        borderWidth: 1,
      },
    },
    scales: {
      x: {
        grid: {
          color: currentTheme['border-color'],
          borderColor: currentTheme['border-color'],
        },
        ticks: {
          color: currentTheme['on-surface'],
        },
      },
      y: {
        grid: {
          color: currentTheme['border-color'],
          borderColor: currentTheme['border-color'],
        },
        ticks: {
          color: currentTheme['on-surface'],
        },
      },
    },
  }
})
</script>

<template>
  <Bar
    :data="props.chartData"
    :options="chartOptions"
  />
</template>
