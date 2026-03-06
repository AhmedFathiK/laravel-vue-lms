<script setup>
import AppPricing from '@/components/AppPricing.vue'
import { ref, computed } from 'vue'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  courseId: {
    type: [Number, String],
    default: null,
  },
  activeEntitlement: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:isDialogVisible'])

const dialogVisibleUpdate = val => {
  emit('update:isDialogVisible', val)
}

const plansCount = ref(1) // Start small (1 column) to avoid flash of big dialog

const dialogWidth = computed(() => {
  if (plansCount.value <= 1) return 500
  if (plansCount.value === 2) return 900
  
  return 1200
})

const plansCols = computed(() => {
  if (plansCount.value <= 1) return 12
  if (plansCount.value === 2) return 6
  
  return 4
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    :width="$vuetify.display.smAndDown ? 'auto' : dialogWidth"
    @update:model-value="dialogVisibleUpdate"
  >
    <!-- 👉 Dialog close btn -->
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard class="pricing-dialog pa-2 pa-sm-10">
      <VCardText>
        <AppPricing
          :md="plansCols"
          :course-id="props.courseId"
          :active-entitlement="props.activeEntitlement"
          @loaded="count => plansCount = count"
        />
      </VCardText>
    </VCard>
  </VDialog>
</template>
