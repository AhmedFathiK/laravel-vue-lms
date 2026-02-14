import { defineStore } from 'pinia'
import api from '@/utils/api'
import { layoutConfig } from '@layouts'
import { h } from 'vue'

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    appName: layoutConfig.app.title,
    appLogo: layoutConfig.app.logo,
  }),
  actions: {
    async fetchSettings() {
      try {
        const data = await api.get('/admin/settings/general')
        
        if (data.appName) this.appName = data.appName
        
        if (data.appLogo) {
          // If it's a URL, wrap it in an img tag
          this.appLogo = h('img', { 
            src: data.appLogo,
            style: { maxHeight: '24px' }, // Add some styling if needed
          })
        }
      } catch (error) {
        console.error('Failed to fetch settings', error)
      }
    },
  },
})
