<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

definePage({
  meta: {
    layout: 'blank',
  },
})

const router = useRouter()
const summaryItems = ref([])

onMounted(() => {
  const state = history.state
  if (state && state.summary) {
    try {
      summaryItems.value = JSON.parse(state.summary)
    } catch (e) {
      console.error('Failed to parse summary data', e)
    }
  }
})

const goBack = () => {
  router.push({ name: 'revisions' })
}
</script>

<template>
  <VContainer class="fill-height">
    <VRow
      justify="center"
      align="center"
    >
      <VCol
        cols="12"
        md="8"
        lg="6"
      >
        <VCard class="text-center pa-6">
          <div class="mb-6">
            <VAvatar
              color="success"
              size="80"
              variant="tonal"
              class="mb-4"
            >
              <VIcon
                icon="tabler-brain"
                size="48"
              />
            </VAvatar>
            <h2 class="text-h3 font-weight-bold">
              Session Complete!
            </h2>
            <p class="text-h6 text-medium-emphasis">
              You strengthened {{ summaryItems.length }} items
            </p>
          </div>

          <VDivider class="mb-4" />

          <VList class="text-start">
            <VListItem
              v-for="(data, index) in summaryItems"
              :key="index"
              class="mb-2 bg-var-theme-background rounded"
            >
              <template #prepend>
                <VAvatar
                  color="primary"
                  variant="tonal"
                  size="40"
                >
                  <VIcon icon="tabler-vocabulary" />
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-bold">
                <!-- Access term/concept title safely -->
                {{ data.item.revisionable.term || data.item.revisionable.title }}
              </VListItemTitle>
              
              <VListItemSubtitle>
                <!-- Show improvement or status -->
                Stability: {{ parseFloat(data.item.stability).toFixed(1) }} 
                <VChip
                  size="x-small"
                  color="success"
                  class="ms-2"
                >
                  +{{ data.grade }} Grade
                </VChip>
              </VListItemSubtitle>

              <template #append>
                <VIcon 
                  v-if="data.grade >= 3" 
                  icon="tabler-arrow-up-right" 
                  color="success" 
                />
                <VIcon 
                  v-else
                  icon="tabler-refresh" 
                  color="warning" 
                />
              </template>
            </VListItem>
          </VList>

          <div class="mt-6">
            <VBtn
              color="primary"
              size="large"
              block
              @click="goBack"
            >
              Continue Learning
            </VBtn>
          </div>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>
