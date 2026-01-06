<script setup>
import { useAuthStore } from '@/stores/auth'
import api from '@/utils/api'
import { themeConfig } from '@themeConfig'

const props = defineProps({
  languages: {
    type: Array,
    required: true,
  },
  location: {
    type: null,
    required: false,
    default: 'bottom end',
  },
})

const { locale } = useI18n({ useScope: 'global' })
const authStore = useAuthStore()

const changeLocale = async lang => {
  locale.value = lang
  
  // Update Cookie manually
  const cookieName = `${themeConfig.app.title}-language`

  document.cookie = `${cookieName}=${lang}; path=/; max-age=31536000`

  if (authStore.isAuthenticated) {
    try {
      await api.post('/user/locale', { locale: lang })
    } catch (e) {
      console.error('Failed to update locale', e)
    }
  }
}
</script>

<template>
  <IconBtn>
    <VIcon icon="tabler-language" />

    <!-- Menu -->
    <VMenu
      activator="parent"
      :location="props.location"
      offset="12px"
      width="175"
    >
      <!-- List -->
      <VList
        :selected="[locale]"
        color="primary"
      >
        <!-- List item -->
        <VListItem
          v-for="lang in props.languages"
          :key="lang.i18nLang"
          :value="lang.i18nLang"
          @click="changeLocale(lang.i18nLang)"
        >
          <!-- Language label -->
          <VListItemTitle>
            {{ lang.label }}
          </VListItemTitle>
        </VListItem>
      </VList>
    </VMenu>
  </IconBtn>
</template>
