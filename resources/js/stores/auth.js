// stores/auth.js
import { useAbility } from '@/plugins/casl/composables/useAbility'
import api from '@/utils/api' // Adjust the import path as necessary
import { useActiveCourse } from '@/stores/activeCourse'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const permissions = ref([])
  const abilities = ref([])
  const isLoading = ref(false)
  const errors = ref({})
  const role = ref(null)
  const roles = ref([])
  
  // Create a ref to hold the ability instance
  const abilityRef = ref(null)
  
  // Function to get the ability instance when needed
  const getAbility = () => {
    if (!abilityRef.value) {
      try {
        abilityRef.value = useAbility()
      } catch (error) {
        console.warn('Ability not yet available, will retry later:', error)
        
        return null
      }
    }
    
    return abilityRef.value
  }
  

  
  // Debug function to log state changes
  const logState = (action, data) => {
    console.log(`[AuthStore] ${action}:`, { 
      user: user.value,
      data: data,
    })
  }

  // Getters
  const isAuthenticated = computed(() => !!user.value)
  const userName = computed(() => user.value?.first_name || '')
  const userEmail = computed(() => user.value?.email || '')
  const userRole = computed(() => role.value || '')
  const userRoles = computed(() => roles.value || [])
  const userPermissions = computed(() => permissions.value || [])
  const userAbilities = computed(() => abilities.value || [])

  // Convert permissions array to CASL rules format
  const convertPermissionsToCaslRules = permissionsArray => {
    if (!Array.isArray(permissionsArray) || permissionsArray.length === 0) {
      return []
    }

    // Group permissions by subject
    const permissionsBySubject = {}
    
    permissionsArray.forEach(permission => {
      // Handle permissions with dot notation (e.g. 'view.user')
      if (permission.includes('.')) {
        const [action, subject] = permission.split('.')
        
        if (!permissionsBySubject[subject]) {
          permissionsBySubject[subject] = []
        }
        permissionsBySubject[subject].push(action)
      }
    })

    // Convert to CASL rules format
    const caslRules = []
    
    // Add grouped permissions
    Object.entries(permissionsBySubject).forEach(([subject, actions]) => {
      caslRules.push({
        action: actions,
        subject: subject,
      })
    })

    return caslRules
  }

  // Define ability rules based on permissions and roles
  const defineRulesFor = (userData, userPermissions, userRoles) => {
    logState('defineRulesFor', { userData, userPermissions, userRoles })
    
    let abilityRules = []

    // Convert permissions to CASL rules
    abilityRules = convertPermissionsToCaslRules(userPermissions)
    abilities.value = abilityRules
  }

  // Update user data from response
  const updateUserData = responseData => {
    if (!responseData) return false
    
    logState('updateUserData called with', responseData)
    
    // Set user data
    user.value = responseData.user
    
    // Extract roles from response (now at root level)
    if (responseData.roles) {
      roles.value = responseData.roles
      role.value = responseData.roles[0] || null
    }
    
    // Extract permissions from response (now at root level)
    if (responseData.permissions) {
      permissions.value = responseData.permissions
    }
    
    // Define ability rules
    defineRulesFor(responseData.user, responseData.permissions, responseData.roles)
    
    logState('updateUserData completed', { 
      user: user.value, 
      role: role.value, 
      roles: roles.value,
      permissions: permissions.value,
    })
    
    return true
  }

  // Actions
  const login = async credentials => {
    isLoading.value = true
    errors.value = {}
    
    logState('login started', credentials)

    try {
      // First, get CSRF cookie for Sanctum
      await api.get('/sanctum/csrf-cookie')
      
      // Attempt login
      const response = await api.post('/auth/login', credentials)
      
      logState('login response', response)
      
      if (response) {
        updateUserData(response)
        
        return { success: true, user: response.user }
      } else {
        console.error('Login response missing data:', response)
        
        return { success: false, error: 'Invalid response format' }
      }
    } catch (error) {
      logState('login error', error)
      
      if (error.response?.status === 422) {
        errors.value = error.response.data.errors || {}
      } else {
        errors.value = { general: 'Login failed. Please try again.' }
      }
      
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const register = async userData => {
    isLoading.value = true
    errors.value = {}
    
    logState('register started', userData)

    try {
      // First, get CSRF cookie for Sanctum
      await api.get('/sanctum/csrf-cookie')
      
      const response = await api.post('/auth/register', userData)
      
      logState('register response', response)
      
      if (response) {
        updateUserData(response)
        
        return { success: true, user: response.user }
      } else {
        console.error('Register response missing data:', response)
        
        return { success: false, error: 'Invalid response format' }
      }
    } catch (error) {
      logState('register error', error)
      
      if (error.response?.status === 422) {
        errors.value = error.response.data.errors || {}
      } else {
        errors.value = { general: 'Registration failed. Please try again.' }
      }
      
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const logout = async () => {
    isLoading.value = true
    logState('logout started')

    try {
      await api.post('/auth/logout')
      
      // Clear user data
      user.value = null
      permissions.value = []
      roles.value = []
      role.value = null
      errors.value = {}
      abilities.value = []
      
      // Clear active course data
      const activeCourseStore = useActiveCourse()

      activeCourseStore.clearActiveCourse()

      logState('logout completed')
      
      return { success: true }
    } catch (error) {
      console.error('Logout error:', error)
      logState('logout error', error)

      throw error
    } finally {
      isLoading.value = false
    }
  }

  const fetchUser = async () => {
    isLoading.value = true
    logState('fetchUser started')
    
    try {
      const response = await api.get('/auth/user')

      logState('fetchUser response', response)
      
      if (response) {
        updateUserData(response)
        
        return { success: true, user: response.user }
      } else {
        console.error('fetchUser response missing data:', response)
        
        return { success: false, error: 'Invalid response format' }
      }
    } catch (error) {
      logState('fetchUser error', error)
      
      // Clear user data on error
      clearUser()
      
      throw error
    } finally {
      isLoading.value = false
    }
  }

  // Check authentication status (for app init and page refreshes)
  const checkAuth = async () => {
    if (user.value) {
      logState('checkAuth - user already exists')
      
      return { authenticated: true }
    }
    
    logState('checkAuth - fetching user')
    
    try {
      const result = await fetchUser()
      
      if (result.success) {
        logState('checkAuth - user fetched successfully')
        
        return { authenticated: true, user: result.user }
      } else {
        logState('checkAuth - user fetch failed')
        
        return { authenticated: false }
      }
    } catch (error) {
      logState('checkAuth - error', error)
      
      return { authenticated: false }
    }
  }

  const updateProfile = async profileData => {
    isLoading.value = true
    errors.value = {}
    logState('updateProfile started', profileData)

    try {
      const response = await api.put('/auth/profile', profileData)

      logState('updateProfile response', response)
      
      if (response) {
        updateUserData(response)
        
        return { success: true, user: response.user }
      } else {
        console.error('updateProfile response missing data:', response)
        
        return { success: false, error: 'Invalid response format' }
      }
    } catch (error) {
      logState('updateProfile error', error)
      
      if (error.response?.status === 422) {
        errors.value = error.response.data.errors || {}
      } else {
        errors.value = { general: 'Profile update failed. Please try again.' }
      }
      
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const changePassword = async passwordData => {
    isLoading.value = true
    errors.value = {}
    logState('changePassword started')

    try {
      const response = await api.put('/auth/password', passwordData)

      logState('changePassword response', response)
      
      return { success: true, message: response.message }
    } catch (error) {
      logState('changePassword error', error)
      
      if (error.response?.status === 422) {
        errors.value = error.response.data.errors || {}
      } else {
        errors.value = { general: 'Password change failed. Please try again.' }
      }
      
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const clearErrors = () => {
    errors.value = {}
  }

  const clearUser = () => {
    user.value = null
    permissions.value = []
    roles.value = []
    role.value = null
    abilities.value = []
    

    logState('clearUser completed')
  }

  // Manually set permissions and update abilities
  const updatePermissions = newPermissions => {
    permissions.value = newPermissions
    defineRulesFor(user.value, newPermissions, roles.value)
  }

  return {
    // State
    user,
    isLoading,
    errors,
    abilities,
    role,
    roles,
    
    // Getters
    isAuthenticated,
    userName,
    userEmail,
    userRole,
    userRoles,
    userAbilities,
    
    // Actions
    login,
    register,
    logout,
    fetchUser,
    checkAuth,
    updateProfile,
    changePassword,
    clearErrors,
    clearUser,
    updateUserData,
    updatePermissions,
  }
})
