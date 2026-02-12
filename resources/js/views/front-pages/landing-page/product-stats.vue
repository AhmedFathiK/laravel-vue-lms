<script setup>
const props = defineProps({
  stats: {
    type: Array,
    default: () => [
      {
        title: 'Support Tickets Resolved',
        value: '7.1k+',
        icon: 'tabler-device-laptop',
        color: 'primary',
      },
      {
        title: 'Join creatives community',
        value: '50k+',
        icon: 'tabler-user',
        color: 'success',
      },
      {
        title: 'Highly Rated Products',
        value: '4.8/5',
        icon: 'tabler-diamond',
        color: 'info',
      },
      {
        title: 'Money Back Guarantee',
        value: '100%',
        icon: 'tabler-check',
        color: 'warning',
      },
    ],
  },
})

const isTablerIcon = icon => {
  return icon && typeof icon === 'string' && icon.startsWith('tabler-')
}
</script>

<template>
  <div :style="{ 'background-color': 'rgb(var(--v-theme-surface))' }">
    <VContainer>
      <div class="py-12">
        <VRow>
          <VCol
            v-for="(product, index) in props.stats"
            :key="index"
            cols="12"
            sm="6"
            lg="3"
          >
            <VCard
              flat
              :style="{ border: `1px solid ${['primary', 'secondary', 'success', 'info', 'warning', 'error'].includes(product.color) ? `rgba(var(--v-theme-${product.color}))` : product.color}` }"
            >
              <VCardText class="text-center">
                <div class="mb-4 d-flex justify-center">
                  <VIcon
                    v-if="isTablerIcon(product.icon)"
                    :color="product.color"
                    :icon="product.icon"
                    size="64"
                  />
                  <VImg
                    v-else
                    :src="product.icon"
                    height="64"
                    width="64"
                    class="mx-auto"
                  />
                </div>
                <h3 class="text-h3">
                  {{ product.value }}
                </h3>
                <p class="text-body-1 font-weight-medium mb-0 text-wrap">
                  {{ product.title }}
                </p>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </div>
    </VContainer>
  </div>
</template>
