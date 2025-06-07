// composables/useApi.js
import axios from 'axios'

// Create Axios instance with defaults
const api = axios.create({
  baseURL: '/api', // Base URL will be prepended to all requests
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest', // Important for Laravel to detect AJAX requests
  },
  withCredentials: true, // Important for cookies/session handling with Sanctum
})

// Request interceptor
api.interceptors.request.use(
  config => {
    // Add any request modifications here if needed
    return config
  },
  error => {
    return Promise.reject(error)
  },
)

// Add response interceptor to extract data
api.interceptors.response.use(
  response => {
    // Extract data from response to simplify usage
    return response.data
  },
  error => {
    // Pass through error for handling in components
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
