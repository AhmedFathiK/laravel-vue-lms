<script setup>
import AppPricing from '@/components/AppPricing.vue'

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
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    :width="$vuetify.display.smAndDown ? 'auto' : 1200"
    @update:model-value="dialogVisibleUpdate"
  >
    <!-- 👉 Dialog close btn -->
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard class="pricing-dialog pa-2 pa-sm-10">
      <VCardText>
        <AppPricing
          md="4"
          :course-id="props.courseId"
          :active-entitlement="props.activeEntitlement"
        />
      </VCardText>
    </VCard>
  </VDialog>
</template>
