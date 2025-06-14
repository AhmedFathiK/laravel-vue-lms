<script setup>
import api from '@/utils/api'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'

const props = defineProps({
  isDialogOpen: {
    type: Boolean,
    required: true,
  },
  dialogMode: {
    type: String,
    default: 'add',
    validator: value => ['add', 'edit'].includes(value),
  },
  category: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['update:is-dialog-open', 'categorySaved'])

const toast = useToast()
const { locale } = useI18n()

// Form data - initialize with empty values
const categoryName = ref('')
const categoryDescription = ref('')
const formErrors = ref({})
const isSubmitting = ref(false)

// Form validation rules
const nameRules = [
  value => !!value || 'Category name is required',
]

// Watch for changes in the category prop
watch(
  () => props.category,
  newCategory => {
    if (newCategory) {
      // For editing, extract values from the multilingual object if needed
      const name = newCategory.name
      
      categoryName.value = name && typeof name === 'object' 
        ? (name[locale.value] || name.en || '') 
        : (name || '')
      
      const description = newCategory.description
      
      categoryDescription.value = description && typeof description === 'object' 
        ? (description[locale.value] || description.en || '') 
        : (description || '')
    } else {
      // Reset form when category is null
      categoryName.value = ''
      categoryDescription.value = ''
    }
    
    // Reset form errors when dialog opens
    formErrors.value = {}
  },
  { deep: true, immediate: true },
)

// Validate form
const validateForm = () => {
  formErrors.value = {}
  
  // Validate name
  if (!categoryName.value) {
    formErrors.value.name = 'Category name is required'
    
    return false
  }
  
  return true
}

// Create new category
const createCategory = async () => {
  if (!validateForm()) return
  
  isSubmitting.value = true
  
  try {
    // Prepare data for the current locale
    const categoryData = {
      name: categoryName.value,
      description: categoryDescription.value,
    }

    const response = await api.post('/admin/course-categories', categoryData)
    
    toast.success('Category created successfully')
    emit('categorySaved')
    closeDialog()
  } catch (error) {
    console.error('Error creating category:', error)
    toast.error('Failed to create category')
  } finally {
    isSubmitting.value = false
  }
}

// Update category
const updateCategory = async () => {
  if (!validateForm()) return
  
  // Check if category exists and has an id
  if (!props.category || !props.category.id) {
    toast.error('Category ID is missing')
    
    return
  }
  
  isSubmitting.value = true
  
  try {
    // Prepare data for the current locale
    const categoryData = {
      name: categoryName.value,
      description: categoryDescription.value,
    }

    const response = await api.put(`/admin/course-categories/${props.category.id}`, categoryData)
    
    toast.success('Category updated successfully')
    emit('categorySaved')
    closeDialog()
  } catch (error) {
    console.error('Error updating category:', error)
    toast.error('Failed to update category')
  } finally {
    isSubmitting.value = false
  }
}

// Save category (create or update)
const saveCategory = () => {
  if (props.dialogMode === 'add') {
    createCategory()
  } else {
    updateCategory()
  }
}

// Close dialog and reset form
const closeDialog = () => {
  emit('update:is-dialog-open', false)
  categoryName.value = ''
  categoryDescription.value = ''
  formErrors.value = {}
}
</script>

<template>
  <VDialog
    :model-value="isDialogOpen"
    max-width="500"
    @update:model-value="emit('update:is-dialog-open', $event)"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="closeDialog" />

    <!-- Dialog Content -->
    <VCard :title="dialogMode === 'add' ? 'Add New Category' : 'Edit Category'">
      <VCardText>
        <VForm @submit.prevent="saveCategory">
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="categoryName"
                label="Category Name"
                placeholder="Enter category name"
                :rules="nameRules"
                :error-messages="formErrors.name"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="categoryDescription"
                label="Description"
                placeholder="Enter category description (optional)"
                rows="3"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardText class="d-flex justify-end flex-wrap gap-3">
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="isSubmitting"
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="saveCategory"
        >
          {{ dialogMode === 'add' ? 'Create' : 'Update' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template> 
