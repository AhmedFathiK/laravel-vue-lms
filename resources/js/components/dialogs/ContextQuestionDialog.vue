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
})

const emit = defineEmits(['update:isDialogVisible', 'select'])

const searchQuery = ref('')
const contexts = ref([])
const selectedContext = ref(null)
const contextQuestions = ref([])
const selectedQuestions = ref([])
const isLoading = ref(false)
const options = ref({ page: 1, itemsPerPage: 10 })
const totalContexts = ref(0)

const headers = [
  { title: 'Title', key: 'title' },
  { title: 'Media Type', key: 'mediaType' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const questionHeaders = [
  { title: 'Question', key: 'questionText' },
  { title: 'Type', key: 'type' },
]

const fetchContexts = async () => {
  if (!props.courseId) return
  isLoading.value = true
  try {
    const { page, itemsPerPage } = options.value

    const response = await api.get(`/admin/courses/${props.courseId}/question-contexts`, {
      params: {
        page,
        perPage: itemsPerPage,
        search: searchQuery.value,
      },
    })

    contexts.value = response.data
    totalContexts.value = response.meta.total
  } catch (error) {
    console.error('Error fetching contexts:', error)
  } finally {
    isLoading.value = false
  }
}

const fetchContextDetails = async context => {
  isLoading.value = true
  try {
    const { data } = await api.get(`/admin/courses/${props.courseId}/question-contexts/${context.id}`)

    // We need the questions from the context
    contextQuestions.value = (data.questions || []).filter(q => !props.excludeIds.includes(q.id))
    selectedContext.value = context

    // Select all by default for convenience
    selectedQuestions.value = [...contextQuestions.value]
  } catch (error) {
    console.error('Error fetching context details:', error)
  } finally {
    isLoading.value = false
  }
}

watch(() => props.isDialogVisible, val => {
  if (val) {
    fetchContexts()
    selectedContext.value = null
    selectedQuestions.value = []
    contextQuestions.value = []
  }
})

watch(options, fetchContexts, { deep: true })
watch(searchQuery, () => {
  options.value.page = 1
  fetchContexts()
})

const handleSelect = () => {
  emit('select', selectedQuestions.value)
  emit('update:isDialogVisible', false)
}

const backToContexts = () => {
  selectedContext.value = null
  selectedQuestions.value = []
}
</script>

<template>
  <VDialog
    :model-value="isDialogVisible"
    max-width="900"
    @update:model-value="$emit('update:isDialogVisible', $event)"
  >
    <VCard :title="selectedContext ? `Select Questions from: ${selectedContext.title}` : 'Select Context Group'">
      <VCardText>
        <template v-if="!selectedContext">
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="searchQuery"
                placeholder="Search contexts..."
                prepend-inner-icon="tabler-search"
              />
            </VCol>
          </VRow>
            
          <VDataTableServer
            v-model:options="options"
            :headers="headers"
            :items="contexts"
            :loading="isLoading"
            :items-length="totalContexts"
            class="mt-4"
          >
            <template #item.actions="{ item }">
              <VBtn
                size="small"
                @click="fetchContextDetails(item)"
              >
                Select
              </VBtn>
            </template>
          </VDataTableServer>
        </template>

        <template v-else>
          <VDataTable
            v-model="selectedQuestions"
            :headers="questionHeaders"
            :items="contextQuestions"
            show-select
            return-object
          >
            <template #item.questionText="{ item }">
              <div
                class="text-truncate"
                style="max-width: 400px;"
              >
                {{ item.questionText.replace(/<[^>]*>?/gm, '') }}
              </div>
            </template>
          </VDataTable>
        </template>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn
          color="secondary"
          variant="tonal"
          @click="selectedContext ? backToContexts() : $emit('update:isDialogVisible', false)"
        >
          {{ selectedContext ? 'Back' : 'Cancel' }}
        </VBtn>
        <VBtn
          v-if="selectedContext"
          color="primary"
          :disabled="!selectedQuestions.length"
          @click="handleSelect"
        >
          Add Selected Questions
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
