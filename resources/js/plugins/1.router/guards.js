import { authState, initializeAuth } from '@/plugins/3.auth'
import { useAuthStore } from '@/stores/auth'
import { canNavigate } from '@layouts/plugins/casl'

export const setupGuards = router => {
  // 👉 router.beforeEach
  // Docs: https://router.vuejs.org/guide/advanced/navigation-guards.html#global-before-guards
  router.beforeEach(async to => {
    console.log('Router guard running for:', to.path, 'Public:', to.meta.public, 'Auth initialized:', authState.initialized)
    
    /*
     * If it's a public route, continue navigation. This kind of pages are allowed to visited by login & non-login users. Basically, without any restrictions.
     * Examples of public routes are, 404, under maintenance, etc.
     */
    if (to.meta.public) {
      console.log('Public route detected:', to.path)
      
      return
    }

    /**
     * Ensure auth is initialized before proceeding with ANY navigation guard checks
     * This helps with page refreshes and direct URL navigation
     */
    if (!authState.initialized) {
      console.log('Auth not initialized, initializing now...')
      try {
        await initializeAuth()
        console.log('Auth initialized in router guard, continuing checks')
      } catch (error) {
        console.error('Auth initialization failed in router guard:', error)

        // Continue with guard logic even if initialization fails
      }
    }

    /**
     * Check if user is logged in by checking current user state
     */
    const authStore = useAuthStore()
    const authenticated = authStore.isAuthenticated
    
    console.log('Auth check result:', { authenticated, user: authStore.user })

    /*
     * If user is trying to access login-like page (unauthenticatedOnly)
     * Redirect them to home page if they're already logged in
     */
    if (to.meta.unauthenticatedOnly && authenticated) {
      console.log('Authenticated user trying to access unauthenticatedOnly page, redirecting to home')
      
      return { path: '/' }
    }
    
    /*
     * If user is trying to access login like page, let them through
     * We've already handled redirecting them if they're already logged in above
     */
    if (to.meta.unauthenticatedOnly)
      return undefined

    // If route requires authentication and user is not logged in, redirect to login
    if (!authenticated) {
      return {
        name: 'login',
        query: {
          ...to.query,
          to: to.fullPath !== '/' ? to.path : undefined,
        },
      }
    }

    // Handle role-based redirection for the root path
    // Skip this redirect if we're already handling a public route or a named route
    if (to.path === '/' && authenticated && to.name !== 'index') {
      const userRole = authStore.userRole
      
      // Prevent infinite redirect - if we're already on access-control, don't redirect again
      if (to.name === 'access-control')
        return
        
      // Admin routes
      if (userRole === 'admin' || userRole === 'super_admin') {
        return { name: 'access-control' }
      }
      
      // User-specific routes
      if (userRole === 'student') {
        return { name: 'dashboard' }
      }

    }

    // Check for permissions/abilities to access the route
    // Only check routes that explicitly define action and subject
    if (to.meta.action && to.meta.subject && !canNavigate(to) && to.matched.length) {
      return { name: 'not-authorized' }
    }
  })
}
