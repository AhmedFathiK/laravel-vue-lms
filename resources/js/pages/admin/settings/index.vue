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
  <div>
    <VTabs
      v-model="activeTab"
      show-arrows
      class="v-tabs-pill mb-6"
    >
      <VTab
        v-for="tab in tabs"
        :key="tab.value"
        :value="tab.value"
        @click="changeTab(tab.value)"
      >
        <VIcon
          start
          :icon="tab.icon"
        />
        {{ tab.title }}
      </VTab>
    </VTabs>

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
  </div>
</template>
