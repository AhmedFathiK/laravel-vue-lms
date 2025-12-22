import { parseApiError } from './apiErrorHandler'

/**
 * Handles API errors by parsing them and displaying appropriate toast notifications.
 *
 * @param {Error} error - The error object from the API call.
 * @param {Object} toast - The toast instance (e.g., from useToast()).
 * @param {String} [defaultMessage] - Optional fallback error message.
 */
export const handleApiError = (error, toast, defaultMessage = 'An unexpected error occurred.') => {
  const parsed = parseApiError(error)

  console.error('API Error:', parsed)

  if (parsed.status === 422 && parsed.errors) {
    // If we have a descriptive message from Laravel (e.g. "The title field is required. (and 2 more errors)"), 
    // show it as the primary notification.
    if (parsed.message && parsed.message !== 'The given data was invalid.') {
      toast.error(parsed.message)
      
      return
    }

    // Fallback: Display field-specific errors if available
    for (const field in parsed.errors) {
      if (parsed.errors[field]) {
        // Handle both array of strings (Laravel default) and single string
        const errorMessage = Array.isArray(parsed.errors[field]) 
          ? parsed.errors[field][0] 
          : parsed.errors[field]
          
        toast.error(errorMessage)
      }
    }
  } else {
    // Fallback for other errors or general messages
    toast.error(parsed.message || defaultMessage)
  }
}
