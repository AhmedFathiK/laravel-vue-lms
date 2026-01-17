<script setup>
import { computed } from 'vue'

const props = defineProps({
  current: {
    type: Number,
    required: true,
  },
  total: {
    type: Number,
    required: true,
  },
})

const progress = computed(() => {
  if (props.total === 0) return 0
  
  return (props.current / props.total) * 100
})
</script>

<template>
  <div class="lesson-progress-bar w-100">
    <VProgressLinear
      :model-value="progress"
      color="primary"
      height="8"
      rounded
    />
  </div>
</template>
