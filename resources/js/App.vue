<script setup>
import { useTheme } from 'vuetify'
import ScrollToTop from '@core/components/ScrollToTop.vue'
import initCore from '@core/initCore'
import {
  initConfigStore,
  useConfigStore,
} from '@core/stores/config'
import { hexToRgb } from '@core/utils/colorConverter'
import { initializeAuth } from '@/plugins/3.auth'
import { onMounted, watch } from 'vue'
import { useSettingsStore } from '@/stores/settings'

const { global } = useTheme()

// ℹ️ Sync current theme with initial loader theme
initCore()
initConfigStore()

const configStore = useConfigStore()

// Initialize auth on app start
onMounted(async () => {
  try {
    await initializeAuth()
    
    // Fetch global settings
    const settingsStore = useSettingsStore()

    await settingsStore.fetchSettings()

    // Update document title when appName changes
    watch(() => settingsStore.appName, newName => {
      if (newName) {
        document.title = newName
      }
    }, { immediate: true })
  } catch (error) {
    console.error('Failed to initialize auth on app start:', error)
  }
})
</script>

<template>
  <VLocaleProvider :rtl="configStore.isAppRTL">
    <!-- ℹ️ This is required to set the background color of active nav link based on currently active global theme's primary -->
    <VApp :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`">
      <RouterView />

      <ScrollToTop />
    </VApp>
  </VLocaleProvider>
</template>
