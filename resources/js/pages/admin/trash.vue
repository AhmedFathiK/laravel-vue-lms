<script setup>
import ConfirmDialog from '@/components/dialogs/ConfirmDialog.vue'
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import api from '@/utils/api'
import { onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'trash',
  },
})

const toast = useToast()
const isLoading = ref(false)
const trashItems = ref([])
const totalItems = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const searchQuery = ref('')
const selectedType = ref(null)
const modelTypes = ref([])
const sortBy = ref('deleted_at')
const sortDesc = ref(true)

// Dialog visibility
const isDeletionDialogVisible = ref(false)
const isRestoreDialogVisible = ref(false)
const isEmptyTrashDialogVisible = ref(false)
const currentAction = ref(null)
const currentItemId = ref(null)

// Headers for data table
const headers = [
  { title: 'ID', key: 'id', sortable: true },
  { title: 'Type', key: 'model_type', sortable: true },
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Deleted At', key: 'deleted_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
]

// Fetch trash items
const fetchTrashItems = async () => {
  isLoading.value = true
  try {
    const params = {
      page: currentPage.value,
      perPage: itemsPerPage.value,
      search: searchQuery.value || undefined,
      type: selectedType.value?.value || undefined,
      sortBy: sortBy.value,
      orderBy: sortDesc.value ? 'desc' : 'asc',
    }

    const response = await api.get('/admin/trash', { params })

    trashItems.value = response.trashItems || []
    totalItems.value = response.totalItems || 0
  } catch (error) {
    console.error('Error fetching trash items:', error)
    toast.error('Failed to load trash items')
  } finally {
    isLoading.value = false
  }
}

// Fetch model types for filtering
const fetchModelTypes = async () => {
  try {
    const response = await api.get('/admin/trash/model-types')

    modelTypes.value = response || []
  } catch (error) {
    console.error('Error fetching model types:', error)
  }
}

// Format model type for display
const formatModelType = type => {
  if (!type) return ''

  // Extract the class name from the full namespace
  const parts = type.split('\\')
  
  return parts[parts.length - 1]
}

// Format date for display
const formatDate = dateString => {
  if (!dateString) return ''
  const date = new Date(dateString)
  
  return new Intl.DateTimeFormat('default', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

// Handle data table options change (pagination, sorting)
const handleOptionsChange = options => {
  if (options.page) {
    currentPage.value = options.page
  }
  
  if (options.itemsPerPage) {
    itemsPerPage.value = options.itemsPerPage
  }
  
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key
    sortDesc.value = options.sortBy[0].order === 'desc'
  }
  
  fetchTrashItems()
}

// Handle search
const handleSearch = () => {
  currentPage.value = 1
  fetchTrashItems()
}

// Handle filter by type
const handleTypeFilter = () => {
  currentPage.value = 1
  fetchTrashItems()
}

// Open confirmation dialog for restore or delete
const confirmAction = (action, id) => {
  currentAction.value = action
  currentItemId.value = id
  
  if (action === 'restore') {
    isRestoreDialogVisible.value = true
  } else if (action === 'delete') {
    isDeletionDialogVisible.value = true
  } else if (action === 'empty') {
    isEmptyTrashDialogVisible.value = true
  }
}

// Handle deletion confirmation dialog result
const handleDeletionConfirm = async result => {
  if (!result.confirmed) return
  
  if (currentAction.value === 'delete') {
    await deleteItem(currentItemId.value)
  } else if (currentAction.value === 'empty') {
    await emptyTrash()
  }
  
  // Reset values
  currentAction.value = null
  currentItemId.value = null
}

// Handle restore confirmation dialog result
const handleRestoreConfirm = async confirmed => {
  if (!confirmed) return
  
  await restoreItem(currentItemId.value)
  
  // Reset values
  currentAction.value = null
  currentItemId.value = null
}

// Restore an item from trash
const restoreItem = async id => {
  try {
    await api.post(`/admin/trash/${id}/restore`)
    toast.success('Item restored successfully')
    fetchTrashItems()
  } catch (error) {
    console.error('Error restoring item:', error)
    toast.error('Failed to restore item')
  }
}

// Permanently delete an item
const deleteItem = async id => {
  try {
    await api.delete(`/admin/trash/${id}`)
    toast.success('Item permanently deleted')
    fetchTrashItems()
  } catch (error) {
    console.error('Error deleting item:', error)
    toast.error('Failed to delete item')
  }
}

// Empty the trash
const emptyTrash = async () => {
  try {
    await api.post('/admin/trash/empty')
    toast.success('Trash emptied successfully')
    fetchTrashItems()
  } catch (error) {
    console.error('Error emptying trash:', error)
    toast.error('Failed to empty trash')
  }
}

// Initialize
onMounted(() => {
  fetchTrashItems()
  fetchModelTypes()
})
</script>

<template>
  <section>
    <VCard>
      <VCardText class="d-flex justify-space-between align-center">
        <h2>Trash</h2>
        <VBtn 
          color="error" 
          prepend-icon="tabler-trash"
          :disabled="totalItems === 0"
          @click="confirmAction('empty')"
        >
          Empty Trash
        </VBtn>
      </VCardText>

      <VCardText>
        <div class="d-flex flex-wrap gap-4 mb-4">
          <!-- Search input -->
          <VTextField
            v-model="searchQuery"
            label="Search"
            density="compact"
            prepend-inner-icon="tabler-search"
            single-line
            hide-details
            @keyup.enter="handleSearch"
          />
          
          <!-- Type filter -->
          <VSelect
            v-model="selectedType"
            :items="modelTypes"
            label="Filter by Type"
            density="compact"
            hide-details
            clearable
            @update:model-value="handleTypeFilter"
          />
        </div>

        <VDataTable
          :headers="headers"
          :items="trashItems"
          :loading="isLoading"
          :items-per-page="itemsPerPage"
          :page="currentPage"
          :items-length="totalItems"
          class="elevation-1"
          @update:options="handleOptionsChange"
        >
          <!-- Type column -->
          <template #[`item.model_type`]="{ item }">
            <span>{{ formatModelType(item.model_type) }}</span>
          </template>
          
          <!-- Deleted at column -->
          <template #[`item.deleted_at`]="{ item }">
            <span>{{ formatDate(item.deleted_at) }}</span>
          </template>

          <!-- Actions column -->
          <template #[`item.actions`]="{ item }">
            <div class="d-flex gap-2">
              <VBtn
                icon
                variant="text"
                color="success"
                size="small"
                @click="confirmAction('restore', item.id)"
              >
                <VIcon icon="tabler-arrow-back-up" />
              </VBtn>
              <VBtn
                icon
                variant="text"
                color="error"
                size="small"
                @click="confirmAction('delete', item.id)"
              >
                <VIcon icon="tabler-trash" />
              </VBtn>
            </div>
          </template>
          
          <!-- No data display -->
          <template #no-data>
            <div class="text-center pa-4">
              <p class="text-subtitle-1">
                No items in trash
              </p>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Restore Confirmation Dialog -->
    <ConfirmDialog
      v-model:is-dialog-visible="isRestoreDialogVisible"
      confirmation-question="Are you sure you want to restore this item?"
      confirm-title="Item Restored"
      confirm-msg="The item has been restored successfully."
      cancel-title="Action Cancelled"
      cancel-msg="No changes were made."
      @confirm="handleRestoreConfirm"
    />

    <!-- Deletion Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isDeletionDialogVisible"
      :confirmation-question="
        currentAction === 'delete' 
          ? 'Are you sure you want to permanently delete this item?' 
          : 'Are you sure you want to empty the trash?'
      "
      :confirm-title="
        currentAction === 'delete' 
          ? 'Item Deleted' 
          : 'Trash Emptied'
      "
      :confirm-msg="
        currentAction === 'delete' 
          ? 'The item has been permanently deleted.' 
          : 'The trash has been emptied successfully.'
      "
      cancel-title="Action Cancelled"
      cancel-msg="No changes were made."
      @confirm="handleDeletionConfirm"
    />

    <!-- Empty Trash Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isEmptyTrashDialogVisible"
      confirmation-question="Are you sure you want to empty the trash? This will permanently delete all items."
      confirm-title="Trash Emptied"
      confirm-msg="The trash has been emptied successfully."
      cancel-title="Action Cancelled"
      cancel-msg="No changes were made."
      @confirm="handleDeletionConfirm"
    />
  </section>
</template> 
 