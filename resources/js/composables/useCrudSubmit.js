import api from '@/utils/api'
import { parseApiError } from '@/utils/apiErrorHandler'
import { ref, unref, watch } from 'vue'
import { useToast } from 'vue-toastification'

/**
 * Composable for handling standard CRUD submit operations in dialogs
 * 
 * @param {Object} options
 * @param {Ref} options.formRef - The VForm reference
 * @param {Ref|Object} options.form - The form data object (reactive)
 * @param {Function|String} options.apiEndpoint - The API URL (or function returning it)
 * @param {Ref|Boolean} options.isUpdate - Whether this is an update operation (PUT)
 * @param {Function} options.emit - The component's emit function
 * @param {String} options.successMessage - Custom success message
 * @param {Object} options.extraData - Additional data to append to FormData
 * @param {Boolean} options.isFormData - Force FormData usage (default: true)
 * @param {Function} options.transformFormData - Function to transform form data before submission
 */
export const useCrudSubmit = options => {
  const {
    formRef,
    form,
    apiEndpoint,
    isUpdate = false,
    emit,
    successMessage,
    extraData = {},
    isFormData = true,
    transformFormData,
  } = options

  const isLoading = ref(false)
  const validationErrors = ref({})
  const toast = useToast()

  // Automatically clear validation errors when form data changes
  watch(form, () => {
    if (Object.keys(validationErrors.value).length > 0) {
      validationErrors.value = {}
    }
  }, { deep: true })

  // Helper to append data recursively
  const appendFormData = (formData, key, value) => {
    if (value === null || value === undefined) return

    if (Array.isArray(value)) {
      value.forEach((item, index) => {
        // If item is object, recursive call with index
        // Always use key[index] for both objects and primitives to keep the array index explicit
        appendFormData(formData, `${key}[${index}]`, item)
      })
      
      return
    }

    if (typeof value === "object" && !(value instanceof File)) {
      Object.entries(value).forEach(([childKey, childValue]) => {
        appendFormData(formData, `${key}[${childKey}]`, childValue)
      })
      
      return
    }

    if (typeof value === "boolean") {
      formData.append(key, value ? '1' : '0')

      return
    }

    formData.append(key, value)
  }

  const onSubmit = async () => {
    // 1. Validate Form
    const refForm = unref(formRef)
    if (refForm) {
      const { valid } = await refForm.validate()
      if (!valid) return
    }

    isLoading.value = true
    validationErrors.value = {}

    try {
      // 2. Prepare Payload
      let formData = unref(form)
      
      // Apply transformation if provided
      if (typeof transformFormData === 'function') {
        formData = transformFormData(JSON.parse(JSON.stringify(formData)))
      }

      const url = typeof apiEndpoint === 'function' ? apiEndpoint() : unref(apiEndpoint)
      const updateMode = unref(isUpdate)
      const extraDataValue = unref(extraData)
      const isFormDataValue = unref(isFormData)

      let payload

      if (isFormDataValue) {
        payload = new FormData()
        
        // Append all form fields recursively
        for (const key in formData) {
          appendFormData(payload, key, formData[key])
        }

        // Append extra data
        if (extraDataValue) {
          for (const key in extraDataValue) {
            appendFormData(payload, key, extraDataValue[key])
          }
        }

        // Method Override for PUT (Laravel Requirement for FormData with files)
        if (updateMode) {
          payload.append('_method', 'PUT')
        }
      } else {
        // JSON Payload
        payload = { ...formData, ...extraDataValue }
      }

      // 3. API Call
      // Always use POST when using FormData with _method override, otherwise standard
      const method = (isFormDataValue && updateMode) ? 'post' : (updateMode ? 'put' : 'post')
      
      const response = await api[method](url, payload)

      // 4. Success Handling
      const defaultMsg = updateMode ? 'Updated successfully' : 'Created successfully'

      toast.success(unref(successMessage) || defaultMsg)
      
      emit('saved', response)
      emit('update:isDialogVisible', false)
      
    } catch (error) {
      // 5. Error Handling
      const parsed = parseApiError(error)
      
      if (parsed.status === 422 && parsed.errors) {
        // Set validation errors for the UI to bind to
        validationErrors.value = parsed.errors
        
        // Log validation errors in development to help debugging
        if (import.meta.env.DEV) {
          console.error('[useCrudSubmit] Validation Errors:', parsed.errors)
        }
        
        // Always show the top-level message as a toast for better visibility
        // Laravel 422 responses usually come with a "message" field like "The title field is required."
        if (parsed.message) {
          toast.error(parsed.message)
        } else if (parsed.errors.general || parsed.errors.error) {
          // Fallback for custom general errors
          toast.error(parsed.errors.general?.[0] || parsed.errors.error?.[0])
        }
      } else {
        // Server errors or other non-validation errors get a toast
        toast.error(parsed.message)
      }
      
    } finally {
      isLoading.value = false
    }
  }

  return {
    isLoading,
    validationErrors,
    onSubmit,
  }
}
