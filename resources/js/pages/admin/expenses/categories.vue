<script setup>
import AddEditExpenseCategoryDialog from '@/components/dialogs/AddEditExpenseCategoryDialog.vue'
import api from '@/utils/api'
import { onMounted, ref } from 'vue'

const isLoading = ref(false)
const categories = ref([])
const isDialogVisible = ref(false)
const dialogMode = ref('add')
const selectedCategory = ref(null)

const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Description', key: 'description' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const fetchCategories = async () => {
  isLoading.value = true
  try {
    const response = await api.get('/admin/expense-categories')

    categories.value = response
  } catch (error) {
    console.error('Error fetching categories:', error)
  } finally {
    isLoading.value = false
  }
}

const openAddDialog = () => {
  dialogMode.value = 'add'
  selectedCategory.value = null
  isDialogVisible.value = true
}

const openEditDialog = category => {
  dialogMode.value = 'edit'
  selectedCategory.value = category
  isDialogVisible.value = true
}

const deleteCategory = async id => {
  if (!confirm('Are you sure you want to delete this category?')) return

  try {
    await api.delete(`/admin/expense-categories/${id}`)
    fetchCategories()
  } catch (error) {
    console.error('Error deleting category:', error)
  }
}

const onSaved = () => {
  isDialogVisible.value = false
  fetchCategories()
}

onMounted(() => {
  fetchCategories()
})
</script>

<template>
  <div>
    <VCard title="Expense Categories">
      <template #append>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openAddDialog"
        >
          Add Category
        </VBtn>
      </template>

      <VDataTable
        :headers="headers"
        :items="categories"
        :loading="isLoading"
        class="text-no-wrap"
      >
        <template #[`item.actions`]="{ item }">
          <div class="d-flex gap-1">
            <IconBtn @click="openEditDialog(item)">
              <VIcon icon="tabler-edit" />
            </IconBtn>
            <IconBtn @click="deleteCategory(item.id)">
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <AddEditExpenseCategoryDialog
      v-model:is-dialog-visible="isDialogVisible"
      :dialog-mode="dialogMode"
      :data="selectedCategory"
      @saved="onSaved"
    />
  </div>
</template>
