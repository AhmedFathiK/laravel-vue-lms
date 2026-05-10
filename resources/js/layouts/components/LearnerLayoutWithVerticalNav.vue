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

// @layouts plugin
import { VerticalNavLayout } from '@layouts'

const ability = useAbility()
const activeCourseStore = useActiveCourse()

const navItems = computed(() => {
  return originalNavItems.map(item => {
    if (item.to?.name === 'revisions') {
      const hasAccess = ability.can('revision.access', { 
        subject: 'Course', 
        id: activeCourseStore.activeCourseId 
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
  <VerticalNavLayout :nav-items="navItems">
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="tabler-menu-2"
          />
        </IconBtn>

        <NavbarThemeSwitcher />

        <VSpacer />

        <NavbarCourseLink />

        <NavBarI18n
          v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
          :languages="themeConfig.app.i18n.langConfig"
        />
        <UserProfile />
      </div>
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <!-- <TheCustomizer /> -->
  </VerticalNavLayout>
</template>
