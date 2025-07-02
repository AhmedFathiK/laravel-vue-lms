<script setup>
import { ref, onMounted, computed, nextTick } from 'vue'
import api from '@/utils/api'
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import TrophyEditDialog from '@/components/dialogs/TrophyEditDialog.vue'
  
const trophies = ref([])
const loading = ref(true)
const dialog = ref(false)
const deleteDialog = ref(false) 
const deleting = ref(false)
const editedIndex = ref(-1)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const orderBy = ref('created_at')
const orderDir = ref('desc')

// These are now handled in the TrophyEditDialog component
const triggerTypes = ref([])
  
const editedTrophy = ref({
  id: null,
  name: '',
  description: '',
  icon_url: null,
  trigger_type: 'completed_lesson',
  trigger_repeat_count: 1,
  course_id: null,
  points: 0,
  rarity: 'common',
  is_hidden: false,
  is_active: true,
})
  
const defaultTrophy = {
  id: null,
  name: '',
  description: '',
  icon_url: null,
  trigger_type: 'completed_lesson',
  trigger_repeat_count: 1,
  course_id: null,
  points: 0,
  rarity: 'common',
  is_hidden: false,
  is_active: true,
}
  
// Form validation is now handled in the TrophyEditDialog component
  
const rules = {
  required: value => !!value || 'Required.',
  number: value => !isNaN(Number(value)) || 'Must be a number.',
  minValue: min => value => Number(value) >= min || `Must be at least ${min}.`,
}
  
const headers = [
  { title: 'Icon', key: 'icon_url', sortable: false, width: '80px' },
  { title: 'Name', key: 'name' },
  { title: 'Trigger Type', key: 'trigger_type' },
  { title: 'Recipients', key: 'recipients_count', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false },
]
  
const updateOptions = options => {
  if (options.sortBy?.length) {
    orderBy.value = options.sortBy[0]?.key
    orderDir.value = options.sortBy[0]?.order
  }
  fetchTrophies()
}

// Dialog title is now handled in the TrophyEditDialog component

// Get trigger type label from value
const getTriggerTypeLabel = triggerType => {
  const type = triggerTypes.value.find(t => t.value === triggerType)
  
  return type ? type.label : triggerType
}

// Get color for trigger type chip
const getTriggerTypeColor = triggerType => {
  const colorMap = {
    'completed_lesson': 'green',
    'quiz_score': 'blue',
    'level_completed': 'purple',
    'course_completed': 'indigo',
    'term_mastered': 'cyan',
    'streak': 'amber',
    'custom': 'grey',
  }
  
  return colorMap[triggerType] || 'grey'
}

// Fetch trigger types from API for the data table display
const fetchTriggerTypes = async () => {
  try {
    const response = await api.get('/admin/trophies/trigger-types')

    triggerTypes.value = Object.entries(response).map(([value, label]) => ({
      value,
      label,
    }))
  } catch (error) {
    console.error('Error fetching trigger types:', error)
  }
}
  
const fetchTrophies = async () => {
  loading.value = true
  try {
    const params = {
      page: page.value,
      "per_page": itemsPerPage.value,
      "order_by": orderBy.value,
      "order_dir": orderDir.value,
    }

    const response = await api.get('/admin/trophies', { params })

    trophies.value = response.items || []
  } catch (error) {
    console.error('Error fetching trophies:', error)
  } finally {
    loading.value = false
  }
}

// Watch for changes to trigger refetch
watch([ page, itemsPerPage], () => {
  fetchTrophies()
})
  
onMounted(() => {
  fetchTrophies()
  fetchTriggerTypes()
})
  
const openCreateDialog = () => {
  editedIndex.value = -1
  editedTrophy.value = Object.assign({}, defaultTrophy)
  dialog.value = true
}

const openEditDialog = item => {
  editedIndex.value = trophies.value.indexOf(item)
  editedTrophy.value = Object.assign({}, item)
  dialog.value = true
}
  
const openDeleteDialog = item => {
  editedTrophy.value = { ...item }
  deleteDialog.value = true
}

const closeDialog = () => {
  dialog.value = false
  nextTick(() => {
    editedTrophy.value = Object.assign({}, defaultTrophy)
    editedIndex.value = -1
  })
}
  
const deleteTrophyConfirm = async () => {
  deleting.value = true
  try {
    await api.delete(`/admin/trophies/${editedTrophy.value.id}`)
    fetchTrophies()
    deleteDialog.value = false
    setTimeout(() => {
      editedTrophy.value = Object.assign({}, defaultTrophy)
    }, 300)
  } catch (error) {
    console.error('Error deleting trophy:', error)
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <section>
    <!-- Breadcrumb Navigation -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Trophies', disabled: true }
      ]"
      class="mb-4"
    />
    
    <VContainer>
      <VRow>
        <VCol cols="12">
          <VCard>
            <VCardItem class="pb-4">
              <VCardTitle>Trophies Management</VCardTitle>
              <template #append>
                <VBtn
                  prepend-icon="tabler-plus"
                  @click="openCreateDialog"
                >
                  Add Trophy
                </VBtn>
              </template>
            </VCardItem>
            <VCardText>
              <VDataTableServer
                v-model:items-per-page="itemsPerPage"
                v-model:page="page"
                :headers="headers"
                :items="trophies"
                :loading="loading"
                @update:options="updateOptions"
              >
                <!-- Icon column -->
                <template #item.icon_url="{ item }">
                  <VAvatar
                    size="40"
                    rounded
                  >
                    <VImg
                      v-if="item.icon_url"
                      :src="item.icon_url"
                      alt="Trophy icon"
                    />
                    <VIcon v-else>
                      tabler-trophy
                    </VIcon>
                  </VAvatar>
                </template>
              
                <!-- Trigger type column -->
                <template #item.trigger_type="{ item }">
                  <VChip
                    :color="getTriggerTypeColor(item.trigger_type)"
                    size="small"
                  >
                    {{ getTriggerTypeLabel(item.trigger_type) }}
                  </VChip>
                </template>
              
                <!-- Recipients column -->
                <template #item.recipients_count="{ item }">
                  {{ item.recipients_count || 0 }} users awarded
                </template>
              
                <!-- Actions column -->
                <template #item.actions="{ item }">
                  <IconBtn @click="openDeleteDialog(item)">
                    <VIcon icon="tabler-trash" />
                  </IconBtn>

                  <IconBtn @click="openEditDialog(item)">
                    <VIcon icon="tabler-pencil" />
                  </IconBtn>
                </template>
              </VDataTableServer>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
  
      <!-- Trophy Edit Dialog -->
      <TrophyEditDialog
        v-model:is-dialog-visible="dialog"
        :dialog-mode="editedIndex === -1 ? 'add' : 'edit'"
        :trophy="editedTrophy"
        @trophy-saved="fetchTrophies"
      />
  
      <!-- Delete Confirmation Dialog -->
      <DeletionConfirmDialog
        :is-dialog-visible="deleteDialog"
        confirmation-question="Are you sure you want to delete this trophy?"
        @update:is-dialog-visible="deleteDialog = $event"
        @confirm="deleteTrophyConfirm"
      />
    </VContainer>
  </section>
</template>
