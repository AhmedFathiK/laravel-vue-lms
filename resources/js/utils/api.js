import { useAuthStore } from '@/stores/auth'
import { themeConfig } from '@themeConfig'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'

// Create Axios instance with defaults
const api = axios.create({
  baseURL: '/api', // Base URL will be prepended to all requests
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest', // Important for Laravel to detect AJAX requests
    'Cache-Control': 'no-cache',
    'Pragma': 'no-cache',
    'Expires': '0',
  },
  withCredentials: true, // Important for cookies/session handling with Sanctum
})

// Request interceptor
api.interceptors.request.use(
  config => {
    // Get locale from cookie directly instead of using useI18n
    // Use the app title from themeConfig for the cookie name
    const cookieName = `${themeConfig.app.title}-language`

    const cookieLocale = document.cookie
      .split('; ')
      .find(row => row.startsWith(`${cookieName}=`))
      ?.split('=')[1]
      
    if (cookieLocale) {
      config.headers['X-Locale'] = cookieLocale
    }
    
    // If the request data is FormData, remove the Content-Type header
    // to let the browser set it automatically with the correct boundary
    if (config.data instanceof FormData) {
      delete config.headers['Content-Type']
    }
    
    return config
  },
  error => {
    return Promise.reject(error)
  },
)

// Function to refresh CSRF token
const refreshCSRFToken = async () => {
  try {
    await axios.get('/api/sanctum/csrf-cookie', { withCredentials: true })
    
    return true
  } catch (error) {
    console.error('Failed to refresh CSRF token:', error)
    
    return false
  }
}

// Add response interceptor to extract data
api.interceptors.response.use(
  response => {
    // Extract data from response to simplify usage
    return response.data
  },
  async error => {
    const router = useRouter()
    const authStore = useAuthStore()
    const toast = useToast()
    
    // Check if error is due to CSRF token mismatch (419 status)
    if (error.response && error.response.status === 419) {
      console.log('CSRF token mismatch, attempting to refresh token...')
      
      // Store the original request to retry it
      const originalRequest = error.config
      
      // Prevent infinite retry loop
      if (!originalRequest._retry) {
        originalRequest._retry = true
        
        // Try to refresh the CSRF token
        const tokenRefreshed = await refreshCSRFToken()
        
        if (tokenRefreshed) {
          console.log('CSRF token refreshed successfully, retrying request...')
          
          // Retry the original request with new token
          return api(originalRequest)
        }
      }
      
      // If token refresh failed or we've already retried, attempt to logout
      console.error('Failed to refresh CSRF token or request has already been retried')
      toast.error('Your session has expired. Please log in again.')
      
      try {
        // Attempt to log the user out properly
        await authStore.logout()
      } catch (logoutError) {
        console.error('Logout failed:', logoutError)
        
        // Even if logout fails, we should clear user data and redirect
        authStore.clearUser()
      }
      
      // Redirect to login page
      router.push('/login')
      
      return Promise.reject(error)
    }

    // Handle 401 Unauthorized errors
    if (error.response && error.response.status === 401 && error.config.url !== '/auth/logout' && authStore.isAuthenticated) {
      console.error('Unauthorized access (401). Logging out.')
      toast.error('Your session has expired. Please log in again.')
      
      try {
        // Attempt to log the user out properly
        await authStore.logout()
      } catch (logoutError) {
        console.error('Logout failed after 401:', logoutError)
      } finally {

        // Redirect to login page, avoiding navigation errors if already there
        window.location.href = '/login'
      }
    }
    
    // Pass through other errors for handling in components
    return Promise.reject(error)
  },
)

// Log requests in development
if (process.env.NODE_ENV === 'development') {
  api.interceptors.request.use(request => {
    console.log('API Request:', request.method.toUpperCase(), request.url, request.data || {})
    
    return request
  })
  
  api.interceptors.response.use(
    response => {      
      return response
    },
    error => {
      console.error('API Error:', error.response || error)
      
      return Promise.reject(error)
    },
  )
}

// Export the axios instance
export default api
