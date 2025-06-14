<script setup>
import api from '@/utils/api'
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'

const toast = useToast()
const { locale } = useI18n()
const isLoading = ref(false)
const categories = ref([])

const editingCategory = ref(null)
const isDialogOpen = ref(false)
const dialogMode = ref('add') // 'add' or 'edit'

// Headers for data table
const headers = [
  { title: 'ID', key: 'id' },
  { title: 'Name', key: 'name' },
  { title: 'Description', key: 'description' },
  { title: 'Courses Count', key: 'courses_count' },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Fetch categories from API
const fetchCategories = async () => {
  isLoading.value = true
  try {
    const response = await api.get('/admin/course-categories')
    
    // Update to access the 'categories' property in the response
    categories.value = response.categories || []
  } catch (error) {
    console.error('Error fetching categories:', error)
    toast.error('Failed to load categories')
  } finally {
    isLoading.value = false
  }
}

// Delete category
const deleteCategory = async id => {
  if (!confirm('Are you sure you want to delete this category?')) return
  
  try {
    await api.delete(`/admin/course-categories/${id}`)
    fetchCategories()
    toast.success('Category deleted successfully')
  } catch (error) {
    console.error('Error deleting category:', error)
    toast.error('Failed to delete category')
  }
}

// Open dialog for adding new category
const openAddDialog = () => {
  dialogMode.value = 'add'
  editingCategory.value = null
  isDialogOpen.value = true
}

// Open dialog for editing category
const openEditDialog = category => {
  dialogMode.value = 'edit'
  editingCategory.value = { ...category }
  isDialogOpen.value = true
}

// Watch for locale changes and refresh data
watch(() => locale.value, () => {
  fetchCategories()
})

onMounted(() => {
  fetchCategories()
})
</script>

<template>
  <section>
    <VCard>
      <VCardText class="d-flex justify-space-between align-center">
        <h2>Course Categories</h2>
        <VBtn 
          color="primary" 
          prepend-icon="tabler-plus"
          @click="openAddDialog"
        >
          Add Category
        </VBtn>
      </VCardText>

      <VCardText>
        <VDataTable
          :headers="headers"
          :items="categories"
          :loading="isLoading"
          class="elevation-1"
        >
          <!-- Name column -->
          <template #[`item.name`]="{ item }">
            <span class="font-weight-medium">{{ item.name }}</span>
          </template>
          
          <!-- Description column -->
          <template #[`item.description`]="{ item }">
            <span>{{ item.description }}</span>
          </template>

          <!-- Actions column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex gap-2">
              <VBtn
                icon
                variant="text"
                color="primary"
                size="small"
                @click="openEditDialog(item)"
              >
                <VIcon icon="tabler-edit" />
              </VBtn>
              <VBtn
                icon
                variant="text"
                color="error"
                size="small"
                @click="deleteCategory(item.id)"
              >
                <VIcon icon="tabler-trash" />
              </VBtn>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Category Dialog -->
    <AddEditCategoryDialog
      v-model:is-dialog-open="isDialogOpen"
      :dialog-mode="dialogMode"
      :category="editingCategory"
      @category-saved="fetchCategories"
    />
  </section>
</template> 
