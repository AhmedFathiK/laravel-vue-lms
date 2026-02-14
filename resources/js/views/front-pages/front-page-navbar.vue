<script setup>
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import { useAuthStore } from '@/stores/auth'
import { useSettingsStore } from '@/stores/settings'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { can } from '@layouts/plugins/casl'
import { useWindowScroll } from '@vueuse/core'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({ 
  activeId: String,
  config: {
    type: Object,
    default: () => ({}),
  },
})

const display = useDisplay()
const { y } = useWindowScroll()
const router = useRouter()
const isMenuOpen = ref(false)
const authStore = useAuthStore()
const settingsStore = useSettingsStore()

const navItems = computed(() => {
  if (props.config?.menuItems && props.config.menuItems.length > 0) {
    return props.config.menuItems.map(item => ({
      name: item.name,
      to: item.isHash ? { name: 'index', hash: `#${item.to}` } : item.to,
      target: item.target || '_self',
      isActive: item.isHash && props.activeId === item.to,
    }))
  }
  
  // Legacy support for snake_case if middleware didn't convert for some reason
  if (props.config?.menu_items && props.config.menu_items.length > 0) {
    return props.config.menu_items.map(item => ({
      name: item.name,
      to: (item.is_hash || item.isHash) ? { name: 'index', hash: `#${item.to}` } : item.to,
      target: item.target || '_self',
      isActive: (item.is_hash || item.isHash) && props.activeId === item.to,
    }))
  }
  
  return []
})

// Get authentication status
const isAuthenticated = computed(() => authStore.isAuthenticated)
const userName = computed(() => authStore.userName)

// Handle logout
const handleLogout = async () => {
  try {
    await authStore.logout()

    // Redirect to home page after logout
    router.push('/')
  } catch (error) {
    console.error('Logout failed:', error)
  }
}

watch(() => display, () => {
  return display.mdAndUp ? isMenuOpen.value = false : isMenuOpen.value
}, { deep: true })
</script>

<template>
  <!-- 👉 Navigation drawer for mobile devices  -->
  <VNavigationDrawer
    v-model="isMenuOpen"
    width="275"
    data-allow-mismatch
    disable-resize-watcher
  >
    <PerfectScrollbar
      :options="{ wheelPropagation: false }"
      class="h-100"
    >
      <!-- Nav items -->
      <div>
        <div class="d-flex flex-column gap-y-4 pa-4">
          <RouterLink
            v-for="(item, index) in navItems"
            :key="index"
            :to="item.to"
            :target="item.target"
            class="nav-link font-weight-medium"
            :class="[item.isActive ? 'active-link' : '']"
          >
            {{ item.name }}
          </RouterLink>

          <RouterLink
            v-if="isAuthenticated"
            to="/dashboard"
            class="font-weight-medium nav-link"
          >
            Dashboard
          </RouterLink>
          
          <!-- Add logout button in mobile menu -->
          <div 
            v-if="isAuthenticated"
            class="font-weight-medium nav-link cursor-pointer"
            @click="handleLogout"
          >
            Logout
          </div>
        </div>
      </div>

      <!-- Navigation drawer close icon -->
      <VIcon
        id="navigation-drawer-close-btn"
        icon="tabler-x"
        size="20"
        @click="isMenuOpen = !isMenuOpen"
      />
    </PerfectScrollbar>
  </VNavigationDrawer>

  <!-- 👉 Navbar for desktop devices  -->
  <div class="front-page-navbar">
    <div class="front-page-navbar">
      <VAppBar
        :color="$vuetify.theme.current.dark ? 'rgba(var(--v-theme-surface),0.38)' : 'rgba(var(--v-theme-surface), 0.38)'"
        :class="y > 10 ? 'app-bar-scrolled' : [$vuetify.theme.current.dark ? 'app-bar-dark' : 'app-bar-light', 'elevation-0']"
        class="navbar-blur"
      >
        <!-- toggle icon for mobile device -->
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n3 me-2 d-inline-block d-md-none"
          @click="isMenuOpen = !isMenuOpen"
        >
          <VIcon
            size="26"
            icon="tabler-menu-2"
            color="rgba(var(--v-theme-on-surface))"
          />
        </IconBtn>
        <!-- Title and Landing page sections -->
        <div class="d-flex align-center">
          <VAppBarTitle class="me-6">
            <RouterLink
              to="/"
              class="d-flex gap-x-4"
              :class="$vuetify.display.mdAndUp ? 'd-none' : 'd-block'"
            >
              <div class="app-logo">
                <VNodeRenderer :nodes="settingsStore.appLogo" />
                <h1 class="app-logo-title">
                  {{ settingsStore.appName }}
                </h1>
              </div>
            </RouterLink>
          </VAppBarTitle>

          <!-- landing page sections -->
          <div class="text-base align-center d-none d-md-flex">
            <RouterLink
              v-for="(item, index) in navItems"
              :key="index"
              :to="item.to"
              :target="item.target"
              class="nav-link font-weight-medium py-2 px-2 px-lg-4"
              :class="[item.isActive ? 'active-link' : '']"
            >
              {{ item.name }}
            </RouterLink>

            <RouterLink
              v-if="isAuthenticated"
              to="/dashboard"
              class="nav-link font-weight-medium py-2 px-2 px-lg-4"
            >
              Dashboard
            </RouterLink>
            
            <RouterLink
              v-if="isAuthenticated && can('access', 'admin_panel')"
              to="/admin/dashboard"
              class="nav-link font-weight-medium py-2 px-2 px-lg-4"
            >
              Admin Dashboard
            </RouterLink>
          </div>
        </div>

        <VSpacer />

        <div class="d-flex gap-x-4 align-center">
          <NavbarThemeSwitcher />

          <VBtn
            v-if="!isAuthenticated"
            prepend-icon="tabler-lock-open"
            variant="elevated"
            color="primary"
            :to="{ name: 'login' }"
            target="_blank"
          >
            Login
          </VBtn>

          <VBtn
            v-else
            prepend-icon="tabler-logout"
            variant="elevated"
            color="error"
            @click="handleLogout"
          >
            Logout
          </VBtn>
        </div>
      </VAppBar>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.nav-menu {
  display: flex;
  gap: 2rem;
}

.nav-link {
  &:not(:hover) {
    color: rgb(var(--v-theme-on-surface));
  }
}

@media (max-width: 1280px) {
  .nav-menu {
    gap: 2.25rem;
  }
}

@media (min-width: 1920px) {
  .front-page-navbar {
    .v-toolbar {
      max-inline-size: calc(1440px - 32px);
    }
  }
}

@media (min-width: 1280px) and (max-width: 1919px) {
  .front-page-navbar {
    .v-toolbar {
      max-inline-size: calc(1200px - 32px);
    }
  }
}

@media (min-width: 960px) and (max-width: 1279px) {
  .front-page-navbar {
    .v-toolbar {
      max-inline-size: calc(900px - 32px);
    }
  }
}

@media (min-width: 600px) and (max-width: 959px) {
  .front-page-navbar {
    .v-toolbar {
      max-inline-size: calc(100% - 64px);
    }
  }
}

@media (max-width: 600px) {
  .front-page-navbar {
    .v-toolbar {
      max-inline-size: calc(100% - 32px);
    }
  }
}

.nav-item-img {
  border: 10px solid rgb(var(--v-theme-background));
  border-radius: 10px;
}

.active-link {
  color: rgb(var(--v-theme-primary)) !important;
}

.app-bar-light {
  border: 2px solid rgba(var(--v-theme-surface), 68%);
  border-radius: 0.5rem;
  background-color: rgba(var(--v-theme-surface), 38%);
  transition: all 0.1s ease-in-out;
}

.app-bar-dark {
  border: 2px solid rgba(var(--v-theme-surface), 68%);
  border-radius: 0.5rem;
  background-color: rgba(255, 255, 255, 4%);
  transition: all 0.1s ease-in-out;
}

.app-bar-scrolled {
  border: 2px solid rgb(var(--v-theme-surface));
  border-radius: 0.5rem;
  background-color: rgb(var(--v-theme-surface)) !important;
  transition: all 0.1s ease-in-out;
}

.front-page-navbar::after {
  position: fixed;
  z-index: 2;
  backdrop-filter: saturate(100%) blur(6px);
  block-size: 5rem;
  content: "";
  inline-size: 100%;
}
</style>

<style lang="scss">
@use "@layouts/styles/mixins" as layoutMixins;

.mega-menu {
  position: fixed !important;
  inset-block-start: 5.4rem;
  inset-inline-start: 50%;
  transform: translateX(-50%);

  @include layoutMixins.rtl {
    transform: translateX(50%);
  }
}

.front-page-navbar {
  .v-toolbar__content {
    padding-inline: 30px !important;
  }

  .v-toolbar {
    inset-inline: 0 !important;
    margin-block-start: 1rem !important;
    margin-inline: auto !important;
  }
}

.mega-menu-item {
  &:hover {
    color: rgb(var(--v-theme-primary)) !important;
  }
}

#navigation-drawer-close-btn {
  position: absolute;
  cursor: pointer;
  inset-block-start: 0.5rem;
  inset-inline-end: 1rem;
}
</style>
