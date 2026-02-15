<script setup>
import AddEditExpenseDialog from '@/components/dialogs/AddEditExpenseDialog.vue'
import api from '@/utils/api'
import { onMounted, ref } from 'vue'

const isLoading = ref(false)
const expenses = ref([])
const categories = ref([])
const isDialogVisible = ref(false)
const dialogMode = ref('add')
const selectedExpense = ref(null)

const filters = ref({
  categoryId: null,
  status: null,
  fromDate: null,
  toDate: null,
  search: '',
})

const headers = [
  { title: 'Date', key: 'date' },
  { title: 'Amount', key: 'amount' },
  { title: 'Currency', key: 'currency' },
  { title: 'Category', key: 'category.name' },
  { title: 'Status', key: 'status' },
  { title: 'Description', key: 'description' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const fetchExpenses = async () => {
  isLoading.value = true
  try {
    const params = { ...filters.value }
    const response = await api.get('/admin/expenses', { params })

    expenses.value = response.data
  } catch (error) {
    console.error('Error fetching expenses:', error)
  } finally {
    isLoading.value = false
  }
}

const fetchCategories = async () => {
  try {
    const response = await api.get('/admin/expense-categories')

    categories.value = response
  } catch (error) {
    console.error('Error fetching categories:', error)
  }
}

const openAddDialog = () => {
  dialogMode.value = 'add'
  selectedExpense.value = null
  isDialogVisible.value = true
}

const openEditDialog = expense => {
  dialogMode.value = 'edit'
  selectedExpense.value = expense
  isDialogVisible.value = true
}

const deleteExpense = async id => {
  if (!confirm('Are you sure you want to delete this expense?')) return

  try {
    await api.delete(`/admin/expenses/${id}`)
    fetchExpenses()
  } catch (error) {
    console.error('Error deleting expense:', error)
  }
}

const onSaved = () => {
  isDialogVisible.value = false
  fetchExpenses()
}

onMounted(() => {
  fetchCategories()
  fetchExpenses()
})
</script>

<template>
  <div>
    <VCard title="Expenses">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.categoryId"
              label="Category"
              :items="categories"
              item-title="name"
              item-value="id"
              clearable
              @update:model-value="fetchExpenses"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppSelect
              v-model="filters.status"
              label="Status"
              :items="['pending', 'completed']"
              clearable
              @update:model-value="fetchExpenses"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppDateTimePicker
              v-model="filters.fromDate"
              label="From Date"
              clearable
              @update:model-value="fetchExpenses"
            />
          </VCol>
          <VCol
            cols="12"
            md="3"
          >
            <AppDateTimePicker
              v-model="filters.toDate"
              label="To Date"
              clearable
              @update:model-value="fetchExpenses"
            />
          </VCol>
        </VRow>
      </VCardText>

      <template #append>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openAddDialog"
        >
          Add Expense
        </VBtn>
      </template>

      <VDataTable
        :headers="headers"
        :items="expenses"
        :loading="isLoading"
        class="text-no-wrap"
      >
        <template #[`item.status`]="{ item }">
          <VChip
            :color="item.status === 'completed' ? 'success' : 'warning'"
            class="text-capitalize"
          >
            {{ item.status }}
          </VChip>
        </template>
        <template #[`item.actions`]="{ item }">
          <div class="d-flex gap-1">
            <IconBtn @click="openEditDialog(item)">
              <VIcon icon="tabler-edit" />
            </IconBtn>
            <IconBtn @click="deleteExpense(item.id)">
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <AddEditExpenseDialog
      v-model:is-dialog-visible="isDialogVisible"
      :dialog-mode="dialogMode"
      :data="selectedExpense"
      :categories="categories"
      @saved="onSaved"
    />
  </div>
</template>
