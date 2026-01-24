<script setup>
import api from '@/utils/api'
import { ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  courseId: {
    type: Number,
    required: true,
  },
  excludeIds: {
    type: Array,
    default: () => [],
  },
  noContext: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'select'])

const searchQuery = ref('')
const questions = ref([])
const selectedQuestions = ref([])
const isLoading = ref(false)
const options = ref({ page: 1, itemsPerPage: 10 })
const totalQuestions = ref(0)

const headers = [
  { title: 'Question', key: 'questionText' },
  { title: 'Type', key: 'type' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const fetchQuestions = async () => {
  if (!props.courseId) return
  isLoading.value = true
  try {
    const { page, itemsPerPage } = options.value

    const response = await api.get(`/admin/courses/${props.courseId}/questions`, {
      params: {
        page,
        perPage: itemsPerPage,
        search: searchQuery.value,
        noContext: props.noContext ? 1 : undefined,
        excludeIds: props.excludeIds,
      },
    })

    questions.value = response.data
    totalQuestions.value = response.total
  } catch (error) {
    console.error('Error fetching questions:', error)
  } finally {
    isLoading.value = false
  }
}

watch(() => props.isDialogVisible, val => {
  if (val) {
    fetchQuestions()
    selectedQuestions.value = []
  }
})

watch(options, fetchQuestions, { deep: true })
watch(searchQuery, () => {
  options.value.page = 1
  fetchQuestions()
})

const handleSelect = () => {
  emit('select', selectedQuestions.value)
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="900"
    @update:model-value="$emit('update:isDialogVisible', $event)"
  >
    <VCard title="Add Existing Questions">
      <VCardText>
        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="searchQuery"
              placeholder="Search questions..."
              prepend-inner-icon="tabler-search"
            />
          </VCol>
        </VRow>
        
        <VDataTableServer
          v-model="selectedQuestions"
          v-model:options="options"
          :headers="headers"
          :items="questions"
          :loading="isLoading"
          :items-length="totalQuestions"
          show-select
          return-object
          class="mt-4"
        >
          <template #item.questionText="{ item }">
            <div
              class="text-truncate"
              style="max-width: 400px;"
            >
              {{ item.questionText.replace(/<[^>]*>?/gm, '') }}
            </div>
          </template>
        </VDataTableServer>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          variant="tonal"
          @click="$emit('update:isDialogVisible', false)"
        >
          Cancel
        </VBtn>
        <VBtn
          color="primary"
          :disabled="!selectedQuestions.length"
          @click="handleSelect"
        >
          Add Selected
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
