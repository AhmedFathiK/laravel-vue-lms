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
            <div class="py-2">
              <div class="font-weight-medium mb-1">
                {{ item.questionText }}
              </div>

              <!-- MCQ -->
              <ol
                v-if="item.type === 'mcq'"
                type="a"
                class="ms-5 text-caption"
              >
                <li
                  v-for="(option, index) in (item.content?.options || item.options)"
                  :key="index"
                >
                  {{ option }}
                </li>
              </ol>

              <!-- Matching -->
              <ul
                v-else-if="item.type === 'matching'"
                class="ms-5 text-caption"
              >
                <li
                  v-for="(pair, index) in (item.content?.pairs || item.options)"
                  :key="index"
                >
                  {{ pair.left }} → {{ pair.right }}
                </li>
              </ul>

              <!-- Fill blank with choices -->
              <ul
                v-else-if="item.type === 'fill_blank_choices'"
                class="ms-5 text-caption"
              >
                <li
                  v-for="(option, index) in (item.content?.blanks || item.options)"
                  :key="index"
                >
                  Blank {{ index + 1 }}: {{ option.options.join(', ') }}
                </li>
              </ul>

              <!-- Fill blank -->
              <ul
                v-else-if="item.type === 'fill_blank'"
                class="ms-5 text-caption"
              >
                <li
                  v-for="(answers, index) in (item.content?.correct_answer || item.content?.correctAnswer)"
                  :key="index"
                >
                  Blank {{ index + 1 }}: {{ Array.isArray(answers) ? answers.join(', ') : answers }}
                </li>
              </ul>

              <!-- Reordering -->
              <ol
                v-else-if="item.type === 'reordering'"
                type="1"
                class="ms-5 text-caption"
              >
                <li
                  v-for="(option, index) in (item.content?.items || item.options)"
                  :key="index"
                >
                  {{ option }}
                </li>
              </ol>
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
