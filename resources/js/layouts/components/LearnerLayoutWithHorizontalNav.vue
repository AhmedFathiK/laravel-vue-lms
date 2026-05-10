<script setup>
import originalNavItems from '@/navigation/learner'
import { themeConfig } from '@themeConfig'
import { useAbility } from '@/plugins/casl/composables/useAbility'
import { useActiveCourse } from '@/stores/activeCourse'
import { computed } from 'vue'

// Components
import Footer from '@/layouts/components/Footer.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import NavbarCourseLink from '@/layouts/components/NavbarCourseLink.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavBarI18n from '@core/components/I18n.vue'
import { HorizontalNavLayout } from '@layouts'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { useSettingsStore } from '@/stores/settings'

const settingsStore = useSettingsStore()
const ability = useAbility()
const activeCourseStore = useActiveCourse()

const navItems = computed(() => {
  return originalNavItems.map(item => {
    if (item.to?.name === 'revisions') {
      const hasAccess = ability.can('revision.access', { 
        subject: 'Course', 
        id: activeCourseStore.activeCourseId, 
      })
      
      if (!hasAccess) {
        return {
          ...item,
          badgeIcon: 'tabler-crown',
          badgeClass: 'text-warning bg-transparent',
        }
      }
    }
    
    return item
  })
})
</script>

<template>
  <HorizontalNavLayout :nav-items="navItems">
    <!-- 👉 navbar -->
    <template #navbar>
      <RouterLink
        to="/"
        class="app-logo d-flex align-center gap-x-3"
      >
        <VNodeRenderer :nodes="settingsStore.appLogo" />

        <h1 class="app-title font-weight-bold leading-normal text-xl text-capitalize">
          {{ settingsStore.appName }}
        </h1>
      </RouterLink>
      <VSpacer />

      <NavbarCourseLink />

      <NavBarI18n
        v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
        :languages="themeConfig.app.i18n.langConfig"
      />

      <NavbarThemeSwitcher class="me-2" />
      <UserProfile />
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <!-- <TheCustomizer /> -->
  </HorizontalNavLayout>
</template>
