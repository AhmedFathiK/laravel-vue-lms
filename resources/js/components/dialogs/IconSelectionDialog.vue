<script setup>
import tablerIcons from '@/utils/tabler-icons.json'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'select',
])

const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = ref(60)

const icons = ref(tablerIcons)

const filteredIcons = computed(() => {
  const query = searchQuery.value.toLowerCase()
  if (!query) return icons.value
  
  return icons.value.filter(icon => icon.toLowerCase().includes(query))
})

const totalPages = computed(() => Math.ceil(filteredIcons.value.length / itemsPerPage.value))

const paginatedIcons = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  
  return filteredIcons.value.slice(start, end)
})

const selectIcon = icon => {
  emit('select', `tabler-${icon}`)
  emit('update:isDialogVisible', false)
}

const handleClose = () => {
  emit('update:isDialogVisible', false)
  searchQuery.value = ''
  currentPage.value = 1
}

watch(() => props.isDialogVisible, val => {
  if (val) {
    searchQuery.value = ''
    currentPage.value = 1
  }
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="900"
    @update:model-value="handleClose"
  >
    <VCard class="icon-selection-dialog">
      <VCardTitle class="d-flex justify-space-between align-center pa-4">
        <span>Select Icon</span>
        <VBtn
          icon="tabler-x"
          variant="text"
          color="default"
          @click="handleClose"
        />
      </VCardTitle>

      <VCardText class="pa-4">
        <AppTextField
          v-model="searchQuery"
          placeholder="Search icons..."
          prepend-inner-icon="tabler-search"
          clearable
          class="mb-4"
          @update:model-value="currentPage = 1"
        />

        <div
          v-if="paginatedIcons.length > 0"
          class="icon-grid"
        >
          <div
            v-for="icon in paginatedIcons"
            :key="icon"
            class="icon-item cursor-pointer d-flex flex-column align-center justify-center pa-2 rounded border"
            @click="selectIcon(icon)"
          >
            <VIcon
              :icon="`tabler-${icon}`"
              size="32"
              class="mb-2"
            />
            <span class="text-caption text-truncate w-100 text-center">{{ icon }}</span>
          </div>
        </div>

        <div
          v-else
          class="d-flex justify-center align-center py-8 text-medium-emphasis"
        >
          No icons found matching "{{ searchQuery }}"
        </div>

        <div
          v-if="totalPages > 1"
          class="d-flex justify-center mt-4"
        >
          <VPagination
            v-model="currentPage"
            :length="totalPages"
            :total-visible="5"
            size="small"
          />
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.icon-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  gap: 1rem;
}

.icon-item {
  transition: all 0.2s ease;
  
  &:hover {
    background-color: rgba(var(--v-theme-primary), 0.1);
    border-color: rgba(var(--v-theme-primary), 0.5) !important;
    transform: translateY(-2px);
  }
}
</style>
