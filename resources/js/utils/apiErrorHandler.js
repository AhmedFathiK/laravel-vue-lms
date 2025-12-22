/**
 * API Error Handler Helper
 * 
 * Parses errors from Axios responses, specifically designed for Laravel backend.
 * 
 * @param {Error} error - The error object from Axios
 * @returns {Object} - Parsed error object { message: string, errors: object|null }
 */
export const parseApiError = error => {
  // Default fallback
  const result = {
    message: 'An unexpected error occurred.',
    errors: null,
    status: null,
  }

  if (!error.response) {
    result.message = error.message || 'Network error'
    
    return result
  }

  result.status = error.response.status

  const data = error.response.data

  // Handle 422 Validation Errors (Laravel Standard)
  if (result.status === 422) {
    if (data.errors) {
      result.message = data.message || 'Please correct the errors below.'
      result.errors = data.errors
    } else if (data && typeof data === 'object') {
      // If data itself is the errors object (e.g. { field: [msg] })
      result.message = 'Please correct the errors below.'
      result.errors = data
    }
    
    if (result.errors) {
      return result
    }
  }

  // Handle other known error formats
  if (data && data.message) {
    result.message = data.message
  } else if (typeof data === 'string') {
    // Sometimes servers return raw HTML or text
    result.message = 'Server error occurred.'
  }

  return result
}

/**
 * Maps backend validation errors to VForm component
 * 
 * @param {Object} formRef - The ref to the VForm component
 * @param {Object} errors - The errors object from parseApiError (field: [messages])
 */
export const setFormErrors = (formRef, errors) => {
  if (!formRef || !errors) return

  // This depends on how VForm or your specific form library handles external errors.
  // Vuetify VForm doesn't have a direct 'setErrors' method for all fields at once easily 
  // without using something like vee-validate.
  // 
  // However, if we are using a library that supports it, or if we want to 
  // manually map them to an error state object that is passed to :error-messages props.
  
  // Strategy: We will assume the component utilizing this has a way to receive these.
  // For now, this helper might be limited if we stick strictly to vanilla Vuetify VForm 
  // without a wrapper.
  //
  // BUT, since we are building a composable, we can return the errors object 
  // and let the component bind it.
}
