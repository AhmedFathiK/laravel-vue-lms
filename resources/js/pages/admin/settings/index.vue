<script setup>
import GeneralSettings from './components/GeneralSettings.vue'
import LandingPageSettings from './components/LandingPageSettings.vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const activeTab = ref(route.query.tab || 'general')

// Tabs definition
const tabs = [
  { title: 'General', icon: 'tabler-settings', value: 'general' },
  { title: 'Landing Page', icon: 'tabler-layout-navbar', value: 'landing_page' },
]

const changeTab = tab => {
  activeTab.value = tab
  router.push({ query: { ...route.query, tab: tab } })
}

watch(() => route.query.tab, val => {
  if (val) activeTab.value = val
})
</script>

<template>
  <VRow>
    <VCol
      cols="12"
      md="4"
    >
      <h5 class="text-h5 mb-4">
        Settings
      </h5>

      <VTabs
        v-model="activeTab"
        direction="vertical"
        class="v-tabs-pill disable-tab-transition"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.value"
          :value="tab.value"
          :prepend-icon="tab.icon"
          @click="changeTab(tab.value)"
        >
          {{ tab.title }}
        </VTab>
      </VTabs>
    </VCol>

    <VCol
      cols="12"
      md="8"
    >
      <VWindow
        v-model="activeTab"
        class="disable-tab-transition"
        :touch="false"
      >
        <VWindowItem value="general">
          <GeneralSettings />
        </VWindowItem>
        <VWindowItem value="landing_page">
          <LandingPageSettings />
        </VWindowItem>
      </VWindow>
    </VCol>
  </VRow>
</template>
