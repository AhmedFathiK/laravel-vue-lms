<script setup>
import AddEditTrophyDialog from '@/components/dialogs/AddEditTrophyDialog.vue'
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import api from '@/utils/api'
import { can } from '@layouts/plugins/casl'
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
  
const trophies = ref([])
const loading = ref(true)
const isDialogOpen = ref(false)
const deleteDialog = ref(false) 
const deleting = ref(false)
const dialogMode = ref('add')

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('id')
const orderBy = ref('asc')

// Trigger types for display
const triggerTypes = ref([])
  
const editedTrophy = ref({})

const totalTrophies = computed(() => trophies.value.length)

const defaultTrophy = {
  id: null,
  name: '',
  description: '',
  iconUrl: null,
  triggerType: 'completed_lesson',
  triggerRepeatCount: 1,
  courseId: null,
  points: 0,
  rarity: 'common',
  isHidden: false,
  isActive: true,
}

const headers = ref([
  { 
    title: t('trophies.table.icon', 'Icon'), 
    key: 'icon', 
    sortable: false, 
    width: '80px', 
  },
  { 
    title: t('trophies.table.name', 'Name'), 
    key: 'name', 
  },
  { 
    title: t('trophies.table.triggerType', 'Trigger Type'), 
    key: 'trigger', 
    sortable: false, 
  },
  { 
    title: t('trophies.table.recipients', 'Recipients'), 
    key: 'recipients', 
    sortable: false, 
  },
  { 
    title: t('trophies.table.actions', 'Actions'), 
    key: 'actions', 
    sortable: false, 
  },
])

const updateOptions = options => {
  if (options.sortBy?.length) {
    orderBy.value = options.sortBy[0]?.key
    orderBy.value = options.sortBy[0]?.order
  }
  fetchTrophies()
}

// Get trigger type label from value
const getTriggerTypeLabel = triggerType => {
  const type = triggerTypes.value.find(t => t.value === triggerType)
  
  return type ? type.label : triggerType
}

// Get color for trigger type chip
const getTriggerTypeColor = triggerType => {
  const colorMap = {
    'completed_lesson': 'success',
    'quiz_score': 'info',
    'level_completed': 'secondary',
    'course_completed': 'primary',
    'term_mastered': 'warning',
    'streak': 'error',
    'custom': 'default',
  }

  
  return colorMap[triggerType] || 'default'
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
      perPage: itemsPerPage.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
    }

    const response = await api.get('/admin/trophies', { params })

    trophies.value = response.data || []
  } catch (error) {
    console.error('Error fetching trophies:', error)
  } finally {
    loading.value = false
  }
}

// Watch for changes to trigger refetch
watch([page, itemsPerPage], () => {
  fetchTrophies()
})

onMounted(() => {
  fetchTrophies()
  fetchTriggerTypes()
})

const openCreateDialog = () => {
  dialogMode.value = 'add'
  editedTrophy.value = { ...defaultTrophy }
  isDialogOpen.value = true
}

const openEditDialog = item => {
  dialogMode.value = 'edit'
  editedTrophy.value = { ...item }
  isDialogOpen.value = true
}

const openDeleteDialog = item => {
  editedTrophy.value = { ...item }
  deleteDialog.value = true
}

const deleteTrophyConfirm = async () => {
  deleting.value = true
  try {
    await api.delete(`/admin/trophies/${editedTrophy.value.id}`)
    fetchTrophies()
    deleteDialog.value = false
    setTimeout(() => {
      editedTrophy.value = { ...defaultTrophy }
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
        { title: t('trophies.breadcrumb.admin', 'Admin'), disabled: true },
        { title: t('trophies.breadcrumb.trophies', 'Trophies'), disabled: true }
      ]"
      class="mb-4"
    />
    
    <VContainer>
      <VRow>
        <VCol cols="12">
          <VCard>
            <VCardItem class="pb-4">
              <VCardTitle>
                {{ t('trophies.page.title', 'Trophies Management') }}
              </VCardTitle>
              <template #append>
                <VBtn
                  v-if="can('create', 'trophies')"
                  color="primary"
                  prepend-icon="tabler-plus"
                  @click="openCreateDialog"
                >
                  {{ t('trophies.page.addTrophy', 'Add Trophy') }}
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
                :items-length="totalTrophies"
                @update:options="updateOptions"
              >
                <!-- Icon column -->
                <template #[`item.icon`]="{ item }">
                  <VAvatar
                    size="40"
                    rounded
                  >
                    <VImg
                      v-if="item.iconUrl"
                      :src="item.iconUrl"
                      :alt="t('trophies.table.trophyIconAlt', 'Trophy icon')"
                    />
                    <VIcon v-else>
                      tabler-trophy
                    </VIcon>
                  </VAvatar>
                </template>
              
                <!-- Trigger type column -->
                <template #[`item.trigger`]="{ item }">
                  <VChip
                    :color="getTriggerTypeColor(item.triggerType)"
                    size="small"
                  >
                    {{ getTriggerTypeLabel(item.triggerType) }}
                  </VChip>
                </template>
              
                <!-- Recipients column -->
                <template #[`item.recipients`]="{ item }">
                  {{ t('trophies.table.usersAwarded', { count: item.recipientsCount || 0 }) }}
                </template>
              
                <!-- Actions column -->
                <template #[`item.actions`]="{ item }">
                  <div class="d-flex gap-1">
                    <IconBtn
                      v-if="can('edit', 'trophies')"
                      @click="openEditDialog(item)"
                    >
                      <VIcon icon="tabler-edit" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        {{ t('common.edit', 'Edit') }}
                      </VTooltip>
                    </IconBtn>
                    
                    <IconBtn
                      v-if="can('delete', 'trophies')"
                      @click="openDeleteDialog(item)"
                    >
                      <VIcon icon="tabler-trash" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        {{ t('common.delete', 'Delete') }}
                      </VTooltip>
                    </IconBtn>
                  </div>
                </template>
              </VDataTableServer>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
  
      <!-- Trophy Edit Dialog -->
      <AddEditTrophyDialog
        v-model:is-dialog-visible="isDialogOpen"
        :dialog-mode="dialogMode"
        :trophy-data="editedTrophy"
        @saved="fetchTrophies"
      />
  
      <!-- Delete Confirmation Dialog -->
      <DeletionConfirmDialog
        :is-dialog-visible="deleteDialog"
        :confirmation-question="t('trophies.delete.confirmQuestion', 'Are you sure you want to delete this trophy?')"
        @update:is-dialog-visible="deleteDialog = $event"
        @confirm="deleteTrophyConfirm"
      />
    </VContainer>
  </section>
</template>
