<!-- Login Page with Enhanced Auth Store Integration -->
<script setup>
import { VForm } from 'vuetify/components/VForm'
import AuthProvider from '@/views/pages/authentication/AuthProvider.vue'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import authV2LoginIllustrationBorderedDark from '@images/pages/auth-v2-login-illustration-bordered-dark.png'
import authV2LoginIllustrationBorderedLight from '@images/pages/auth-v2-login-illustration-bordered-light.png'
import authV2LoginIllustrationDark from '@images/pages/auth-v2-login-illustration-dark.png'
import authV2LoginIllustrationLight from '@images/pages/auth-v2-login-illustration-light.png'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { useAuthStore } from '@/stores/auth'
import { authState } from '@/plugins/3.auth'
import api from '@/utils/api'

const authThemeImg = useGenerateImageVariant(authV2LoginIllustrationLight, authV2LoginIllustrationDark, authV2LoginIllustrationBorderedLight, authV2LoginIllustrationBorderedDark, true)
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

// Composables
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

// Reactive state
const isPasswordVisible = ref(false)
const refVForm = ref()
const loginInProgress = ref(false)
const debug = ref(true)

const credentials = ref({
  email: 'admin@demo.com',
  password: 'admin',
})

const rememberMe = ref(false)

// Get errors and loading state from auth store
const { errors, isLoading, user } = storeToRefs(authStore)

// Debug function
const logDebug = (message, data) => {
  if (debug.value) {
    console.log(`[Login] ${message}:`, data || '')
  }
}

// Clear any existing errors when component mounts
onMounted(() => {
  authStore.clearErrors()
  logDebug('Component mounted', { authState })
})

// Enhanced login function using auth store
const login = async () => {
  if (loginInProgress.value) return
  
  loginInProgress.value = true
  
  try {
    logDebug('Login attempt', credentials.value)
    
    // First, get CSRF cookie for Sanctum
    await api.get('/sanctum/csrf-cookie')
    
    // Attempt login using auth store
    const result = await authStore.login({
      email: credentials.value.email,
      password: credentials.value.password,
    })

    logDebug('Login result', result)

    if (result.success) {
      logDebug('Login successful', result.user)
      
      // Double check user data is in the store
      if (!authStore.user) {
        logDebug('User not in store after login, manually updating', result.user)
        authStore.updateUserData(result.user)
      }
      
      // Ensure auth state is updated
      authState.initialized = true
      
      // Debug output current state
      logDebug('Auth state after login', { 
        user: authStore.user,
        authenticated: authStore.isAuthenticated, 
        authState, 
      })
      
      // Redirect to intended page or dashboard
      const redirectTo = route.query.to ? String(route.query.to) : '/'
      
      await nextTick(() => {
        router.replace(redirectTo)
      })
    }
  } catch (error) {
    console.error('Login error:', error)
    logDebug('Login error', error)
    
    // Show error message if no field-specific errors
    if (!authStore.errors.email && !authStore.errors.password && authStore.errors.general) {
      console.log('General error:', authStore.errors.general)
    }
  } finally {
    loginInProgress.value = false
  }
}

// Form submission handler
const onSubmit = async () => {
  const { valid: isValid } = await refVForm.value?.validate()
  
  if (isValid) {
    await login()
  }
}

// Watchers to clear errors when user types
watch(() => credentials.value.email, () => {
  if (authStore.errors.email) {
    authStore.clearErrors()
  }
})

watch(() => credentials.value.password, () => {
  if (authStore.errors.password) {
    authStore.clearErrors()
  }
})

// Watch user state for debugging
watch(() => user.value, newValue => {
  logDebug('User state changed', newValue)
})
</script>

<template>
  <RouterLink to="/">
    <div class="auth-logo d-flex align-center gap-x-3">
      <VNodeRenderer :nodes="themeConfig.app.logo" />
      <h1 class="auth-title">
        {{ themeConfig.app.title }}
      </h1>
    </div>
  </RouterLink>

  <VRow
    no-gutters
    class="auth-wrapper bg-surface"
  >
    <VCol
      md="8"
      class="d-none d-md-flex"
    >
      <div class="position-relative bg-background w-100 me-0">
        <div
          class="d-flex align-center justify-center w-100 h-100"
          style="padding-inline: 6.25rem;"
        >
          <VImg
            max-width="613"
            :src="authThemeImg"
            class="auth-illustration mt-16 mb-2"
          />
        </div>

        <img
          class="auth-footer-mask"
          :src="authThemeMask"
          alt="auth-footer-mask"
          height="280"
          width="100"
        >
      </div>
    </VCol>

    <VCol
      cols="12"
      md="4"
      class="auth-card-v2 d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-4"
      >
        <VCardText>
          <h4 class="text-h4 mb-1">
            Welcome to <span class="text-capitalize"> {{ themeConfig.app.title }} </span>! 👋🏻
          </h4>
          <p class="mb-0">
            Please sign-in to your account and start the adventure
          </p>
        </VCardText>

        <VCardText>
          <VAlert
            color="primary"
            variant="tonal"
          >
            <p class="text-sm mb-2">
              Admin Email: <strong>admin@demo.com</strong> / Pass: <strong>admin</strong>
            </p>
            <p class="text-sm mb-0">
              Client Email: <strong>client@demo.com</strong> / Pass: <strong>client</strong>
            </p>
          </VAlert>
        </VCardText>

        <!-- Debug info -->
        <VCardText v-if="debug">
          <details>
            <summary>Debug Info</summary>
            <pre>{{ JSON.stringify({ 
              user: user, 
              authenticated: authStore.isAuthenticated,
              authState: authState
            }, null, 2) }}</pre>
          </details>
        </VCardText>

        <!-- Show general error if exists -->
        <VCardText v-if="errors.general">
          <VAlert
            color="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errors.general }}
          </VAlert>
        </VCardText>

        <VCardText>
          <VForm
            ref="refVForm"
            @submit.prevent="onSubmit"
          >
            <VRow>
              <!-- Email -->
              <VCol cols="12">
                <AppTextField
                  v-model="credentials.email"
                  label="Email"
                  placeholder="johndoe@email.com"
                  type="email"
                  autofocus
                  :rules="[requiredValidator, emailValidator]"
                  :error-messages="errors.email"
                  :disabled="isLoading || loginInProgress"
                />
              </VCol>

              <!-- Password -->
              <VCol cols="12">
                <AppTextField
                  v-model="credentials.password"
                  label="Password"
                  placeholder="············"
                  :rules="[requiredValidator]"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="current-password"
                  :error-messages="errors.password"
                  :disabled="isLoading || loginInProgress"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <div class="d-flex align-center flex-wrap justify-space-between my-6">
                  <VCheckbox
                    v-model="rememberMe"
                    label="Remember me"
                    :disabled="isLoading || loginInProgress"
                  />
                  <RouterLink
                    class="text-primary ms-2 mb-1"
                    :to="{ name: 'forgot-password' }"
                  >
                    Forgot Password?
                  </RouterLink>
                </div>

                <VBtn
                  block
                  type="submit"
                  :loading="isLoading || loginInProgress"
                  :disabled="isLoading || loginInProgress"
                >
                  {{ isLoading || loginInProgress ? 'Signing In...' : 'Login' }}
                </VBtn>
              </VCol>

              <!-- Create account -->
              <VCol
                cols="12"
                class="text-center"
              >
                <span>New on our platform?</span>
                <RouterLink
                  class="text-primary ms-1"
                  :to="{ name: 'register' }"
                >
                  Create an account
                </RouterLink>
              </VCol>

              <VCol
                cols="12"
                class="d-flex align-center"
              >
                <VDivider />
                <span class="mx-4">or</span>
                <VDivider />
              </VCol>

              <!-- Auth providers -->
              <VCol
                cols="12"
                class="text-center"
              >
                <AuthProvider :disabled="isLoading || loginInProgress" />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss">
@use "@core-scss/template/pages/page-auth";
</style>
