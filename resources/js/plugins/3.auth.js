// Import auth store
import { useAuthStore } from '@/stores/auth'
import { onMounted, reactive } from 'vue'

// Create a reactive auth state object that can be accessed globally
export const authState = reactive({
  initialized: false,
  initializing: false,
  error: null,
})

// Create global auth initializer
export const initializeAuth = async () => {
  // Prevent multiple initialization attempts
  if (authState.initializing) {
    console.log('Auth initialization already in progress, waiting...')

    // Wait for current initialization to complete
    while (authState.initializing) {
      await new Promise(resolve => setTimeout(resolve, 50))
    }
    console.log('Existing auth initialization completed')
    
    return { success: true, initialized: authState.initialized, authenticated: !!useAuthStore().user }
  }
  
  // If already initialized, just return the current state
  if (authState.initialized) {
    const authenticated = !!useAuthStore().user

    console.log('Auth already initialized, returning current state:', { authenticated })
    
    return { success: true, initialized: true, authenticated }
  }
  
  console.log('Starting auth initialization...')
  authState.initializing = true
  authState.error = null
  
  try {
    const authStore = useAuthStore()
    
    // Check authentication status
    console.log('Checking auth status...')

    const result = await authStore.checkAuth()
    
    authState.initialized = true
    console.log('Auth initialized successfully:', result.authenticated)
    
    return { ...result, success: true }
  } catch (error) {
    console.error('Auth initialization error:', error)
    authState.error = error
    authState.initialized = true // Mark as initialized even if it failed
    
    return { success: false, error, authenticated: false }
  } finally {
    authState.initializing = false
  }
}

export default function (app) {
  // Add auth state to global properties
  app.config.globalProperties.$authState = authState
  app.config.globalProperties.$initAuth = initializeAuth
  
  // Provide the auth initializer to the app
  app.provide('initAuth', initializeAuth)
  
  // Initialize auth immediately when the plugin is loaded
  // This helps with direct URL navigation
  console.log('Auth plugin loaded, initializing immediately...')
  initializeAuth().catch(error => {
    console.error('Initial auth initialization failed:', error)
  })
  
  // Register a global composition function that runs auth initialization
  app.mixin({
    setup() {
      onMounted(async () => {
        if (!authState.initialized && !authState.initializing) {
          console.log('Component mounted, initializing auth...')
          try {
            await initializeAuth()
          } catch (error) {
            console.error('Auth initialization failed in mixin:', error)
          }
        }
      })
      
      return {}
    },
  })
} 
