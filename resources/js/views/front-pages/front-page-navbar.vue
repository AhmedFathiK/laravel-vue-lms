<script setup>
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import { useAuthStore } from '@/stores/auth'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { can } from '@layouts/plugins/casl'
import { themeConfig } from '@themeConfig'
import { useWindowScroll } from '@vueuse/core'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useDisplay } from 'vuetify'

const props = defineProps({ activeId: String })

const display = useDisplay()
const { y } = useWindowScroll()
const route = useRoute()
const router = useRouter()
const sidebar = ref(false)
const authStore = useAuthStore()

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
  return display.mdAndUp ? sidebar.value = false : sidebar.value
}, { deep: true })

const isMenuOpen = ref(false)
const isMegaMenuOpen = ref(false)

const menuItems = [
  {
    listTitle: 'Pages',
    listIcon: 'tabler-layout-grid',
    navItems: [
      {
        name: 'Login',
        to: { name: 'login' },
        showWhen: 'unauthenticated',
      },
      {
        name: 'Register',
        to: { name: 'register' },
        showWhen: 'unauthenticated',
      },
      {
        name: 'Forgot Password',
        to: { name: 'forgot-password' },
        showWhen: 'unauthenticated',
      },
      {
        name: 'Pricing',
        to: { name: 'pricing' },
      },
      {
        name: 'Help Center',
        to: { name: 'help-center' },
      },
      {
        name: 'Payment',
        to: { name: 'payment' },
      },
      {
        name: 'Checkout',
        to: { name: 'checkout' },
      },
    ],
  },
  {
    listTitle: 'System',
    listIcon: 'tabler-lock-open',
    navItems: [
      {
        name: 'Access Control',
        to: { name: 'access-control' },
        showWhen: 'authenticated',
      },
      {
        name: 'Not Authorized',
        to: { name: 'not-authorized' },
      },
    ],
  },
]

// Filter menu items based on authentication status
const filteredMenuItems = computed(() => {
  return menuItems.map(category => {
    return {
      ...category,
      navItems: category.navItems.filter(item => {
        if (item.showWhen === 'authenticated') return isAuthenticated.value
        if (item.showWhen === 'unauthenticated') return !isAuthenticated.value

        return true
      }),
    }
  }).filter(category => category.navItems.length > 0)
})

const isCurrentRoute = to => {
  return route.matched.some(_route => _route.path.startsWith(router.resolve(to).path))

  // ℹ️ Below is much accurate approach if you don't have any nested routes

// return route.matched.some(_route => _route.path === router.resolve(to).path)
}

const isPageActive = computed(() => menuItems.some(item => item.navItems.some(listItem => isCurrentRoute(listItem.to))))
</script>

<template>
  <!-- 👉 Navigation drawer for mobile devices  -->
  <VNavigationDrawer
    v-model="sidebar"
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
            v-for="(item, index) in ['Home', 'Features', 'Team', 'FAQ', 'Contact us']"
            :key="index"
            :to="{ name: 'index', hash: `#${item.toLowerCase().replace(' ', '-')}` }"
            class="nav-link font-weight-medium"
            :class="[props.activeId?.toLocaleLowerCase().replace('-', ' ') === item.toLocaleLowerCase() ? 'active-link' : '']"
          >
            {{ item }}
          </RouterLink>

          <div class="font-weight-medium cursor-pointer">
            <div
              :class="[isMenuOpen ? 'mb-6 active-link' : '', isPageActive ? 'active-link' : '']"
              style="color: rgba(var(--v-theme-on-surface));"
              class="page-link"
              @click="isMenuOpen = !isMenuOpen"
            >
              Pages <VIcon :icon="isMenuOpen ? 'tabler-chevron-up' : 'tabler-chevron-down'" />
            </div>

            <div
              class="px-4"
              :class="isMenuOpen ? 'd-block' : 'd-none'"
            >
              <div
                v-for="(item, index) in filteredMenuItems"
                :key="index"
              >
                <div class="d-flex align-center gap-x-3 mb-4">
                  <VAvatar
                    variant="tonal"
                    color="primary"
                    rounded
                    :icon="item.listIcon"
                  />
                  <div class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ item.listTitle }}
                  </div>
                </div>
                <ul class="mb-6">
                  <li
                    v-for="listItem in item.navItems"
                    :key="listItem.name"
                    style="list-style: none;"
                    class="text-body-1 mb-4 text-no-wrap"
                  >
                    <RouterLink
                      :to="listItem.to"
                      :target="item.listTitle === 'External' ? '_blank' : '_self'"
                      class="mega-menu-item"
                      :class="isCurrentRoute(listItem.to) ? 'active-link' : 'text-high-emphasis'"
                    >
                      <VIcon
                        icon="tabler-circle"
                        :size="10"
                        class="me-2"
                      />
                      <span>{{ listItem.name }}</span>
                    </RouterLink>
                  </li>
                </ul>
              </div>
            </div>
          </div>

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
        @click="sidebar = !sidebar"
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
          @click="sidebar = !sidebar"
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
                <VNodeRenderer :nodes="themeConfig.app.logo" />
                <h1 class="app-logo-title">
                  {{ themeConfig.app.title }}
                </h1>
              </div>
            </RouterLink>
          </VAppBarTitle>

          <!-- landing page sections -->
          <div class="text-base align-center d-none d-md-flex">
            <RouterLink
              v-for="(item, index) in ['Home', 'Features', 'Team', 'FAQ', 'Contact us']"
              :key="index"
              :to="{ name: 'index', hash: `#${item.toLowerCase().replace(' ', '-')}` }"
              class="nav-link font-weight-medium py-2 px-2 px-lg-4"
              :class="[props.activeId?.toLocaleLowerCase().replace('-', ' ') === item.toLocaleLowerCase() ? 'active-link' : '']"
            >
              {{ item }}
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

          <!-- User avatar and menu when authenticated -->
          <template v-if="isAuthenticated">
            <div
              v-if="$vuetify.display.lgAndUp"
              class="me-2"
            >
              Welcome, {{ userName }}
            </div>
            
            <VBtn
              v-if="$vuetify.display.lgAndUp"
              prepend-icon="tabler-logout"
              variant="elevated"
              color="error"
              @click="handleLogout"
            >
              Logout
            </VBtn>

            <VBtn
              v-else
              rounded
              icon
              variant="elevated"
              color="error"
              @click="handleLogout"
            >
              <VIcon icon="tabler-logout" />
            </VBtn>
          </template>

          <!-- Login button when not authenticated -->
          <template v-else>
            <VBtn
              v-if="$vuetify.display.lgAndUp"
              prepend-icon="tabler-lock-open"
              variant="elevated"
              color="primary"
              to="/login"
            >
              Login
            </VBtn>

            <VBtn
              v-else
              rounded
              icon
              variant="elevated"
              color="primary"
              to="/login"
            >
              <VIcon icon="tabler-lock-open" />
            </VBtn>
          </template>
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

.page-link {
  &:hover {
    color: rgb(var(--v-theme-primary)) !important;
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
