<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  paymentMethods: {
    type: Array,
    required: true,
  },
  modelValue: {
    type: [String, Number],
    default: null,
  },
  autoRenew: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:modelValue', 'update:autoRenew'])

const selectedMethod = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const autoRenewValue = computed({
  get: () => props.autoRenew,
  set: (val) => emit('update:autoRenew', val),
})

const showAutoRenew = computed(() => {
  if (!selectedMethod.value) return false
  const method = props.paymentMethods.find(m => m.id === selectedMethod.value)
  return method && method.type === 'CARD'
})
</script>

<template>
  <div>
    <VRow>
      <VCol
        v-for="method in props.paymentMethods"
        :key="method.id"
        cols="12"
        sm="6"
        md="4"
      >
        <VCard
          border
          :color="selectedMethod === method.id ? 'primary' : ''"
          :variant="selectedMethod === method.id ? 'tonal' : 'outlined'"
          class="d-flex flex-column align-center justify-center pa-4 cursor-pointer h-100 transition-all"
          @click="selectedMethod = method.id"
        >
          <VImg
            :src="method.image"
            height="40"
            width="60"
            class="mb-2"
            contain
          />
          <span class="text-subtitle-2 text-center">{{ method.name }}</span>
        </VCard>
      </VCol>
    </VRow>

    <div v-if="showAutoRenew" class="mt-4 d-flex justify-center">
      <VCheckbox
        v-model="autoRenewValue"
        label="Auto-renew subscription"
        hint="You can cancel anytime from settings."
        persistent-hint
        color="primary"
      />
    </div>
  </div>
</template>
