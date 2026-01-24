<script setup>
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'exams',
  },
})

const toast = useToast()
const { locale } = useI18n()
const router = useRouter()
const route = useRoute()

const courseId = computed(() => route.params.courseid)
const course = ref(null)

// Fetch course details for breadcrumbs/title
const fetchCourse = async () => {
  try {
    const response = await api.get(`/admin/courses/${courseId.value}`)

    course.value = response.data
  } catch (error) {
    console.error('Error fetching course:', error)
  }
}

// 👉 Store
const searchQuery = ref('')
const selectedStatus = ref(null)
const isLoading = ref(false)
const editExam = ref(null)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('createdAt')
const orderBy = ref('desc')

// Widget data
const widgetData = ref([
  {
    title: 'Total Exams',
    value: '0',
    icon: 'tabler-clipboard-list',
    iconColor: 'primary',
  },
  {
    title: 'Active Exams',
    value: '0',
    icon: 'tabler-check',
    iconColor: 'success',
  },
  {
    title: 'Draft Exams',
    value: '0',
    icon: 'tabler-file',
    iconColor: 'warning',
  },
])

// Fetch exams
const examsData = ref({
  items: [],
  totalItems: 0,
  currentPage: 1,
  perPage: 10,
  lastPage: 1,
})

// Headers
const headers = [
  { title: 'Title', key: 'title' },
  { title: 'Type', key: 'type' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false },
]

const updateOptions = options => {
  if (options.sortBy?.length) {
    sortBy.value = options.sortBy[0]?.key
    orderBy.value = options.sortBy[0]?.order
  }
  fetchExams()
}

// Fetch exams from API
const fetchExams = async () => {
  isLoading.value = true
  try {
    const params = {
      page: page.value,
      perPage: itemsPerPage.value,
      search: searchQuery.value || undefined,
      status: selectedStatus.value || undefined,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
    }
    
    const response = await api.get(`/admin/courses/${courseId.value}/exams`, { params })
    
    if (response && typeof response === 'object') {
      examsData.value = response
    } else {
      examsData.value = { items: [], totalItems: 0 }
    }
    
    updateWidgetCounts()
  } catch (error) {
    console.error('Error fetching exams:', error)
    examsData.value = { items: [], totalItems: 0 }
    updateWidgetCounts()
  } finally {
    isLoading.value = false
  }
}

const updateWidgetCounts = () => {
  if (!examsData.value) return

  const total = examsData.value.totalItems || examsData.value.total || 0

  widgetData.value[0].value = total.toString()
  
  // Approximate other counts based on current page (ideal would be separate stats API)
  const items = examsData.value.items || examsData.value.data || []
  const active = items.filter(e => e.status === 'published' && e.is_active).length
  const draft = items.filter(e => e.status === 'draft').length
  
  widgetData.value[1].value = active.toString()
  widgetData.value[2].value = draft.toString()
}

watch([searchQuery, selectedStatus, page, itemsPerPage], () => {
  fetchExams()
})

const exams = computed(() => {
  if (!examsData.value) return []
  
  return examsData.value.items || examsData.value.data || []
})

const totalExams = computed(() => {
  if (!examsData.value) return 0
  
  return examsData.value.totalItems || examsData.value.total || 0
})

const resolveStatusVariant = status => {
  if (status === 'published') return 'success'
  
  return 'warning'
}

// Dialogs
const isAddNewExamDialogVisible = ref(false)
const isDeletionDialogVisible = ref(false)
const examToDelete = ref(null)

const confirmDeleteExam = exam => {
  examToDelete.value = exam
  isDeletionDialogVisible.value = true
}

const handleDeleteConfirm = async result => {
  if (!result.confirmed || !examToDelete.value) return
  
  try {
    await api.delete(`/admin/courses/${courseId.value}/exams/${examToDelete.value.id}`)
    toast.success('Exam deleted successfully')
    fetchExams()
  } catch (error) {
    toast.error('Failed to delete exam')
  } finally {
    examToDelete.value = null
  }
}

const navigateToBuilder = examId => {
  router.push(`/admin/courses/${courseId.value}/exams/${examId}`)
}

const showEditExamDialog = exam => {
  // For now, we use the builder page for editing too, or a simple dialog for metadata
  // Let's redirect to builder for everything for simplicity in this turn
  navigateToBuilder(exam.id)
}

const createNewExam = () => {
  router.push(`/admin/courses/${courseId.value}/exams/create`)
}

onMounted(() => {
  fetchCourse()
  fetchExams()
})
</script>

<template>
  <section>
    <VBreadcrumbs
      :items="[
        { title: 'Admin', to: '/admin/dashboard' },
        { title: 'Courses', to: '/admin/courses' },
        { title: course?.title || 'Course', to: `/admin/courses/${courseId}` },
        { title: 'Exams', disabled: true }
      ]"
      class="mb-4"
    />
    
    <!-- Widgets -->
    <div class="d-flex mb-6">
      <VRow>
        <template
          v-for="(data, id) in widgetData"
          :key="id"
        >
          <VCol
            cols="12"
            md="4"
            sm="6"
          >
            <VCard>
              <VCardText>
                <div class="d-flex justify-space-between">
                  <div class="d-flex flex-column gap-y-1">
                    <div class="text-body-1 text-high-emphasis">
                      {{ data.title }}
                    </div>
                    <h4 class="text-h4">
                      {{ data.value }}
                    </h4>
                  </div>
                  <VAvatar
                    :color="data.iconColor"
                    variant="tonal"
                    rounded
                    size="42"
                  >
                    <VIcon
                      :icon="data.icon"
                      size="26"
                    />
                  </VAvatar>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </template>
      </VRow>
    </div>

    <VCard class="mb-6">
      <VCardText class="d-flex flex-wrap gap-4">
        <div class="me-3 d-flex gap-3">
          <AppSelect
            :model-value="itemsPerPage"
            :items="[10, 25, 50, 100]"
            style="inline-size: 6.25rem;"
            @update:model-value="itemsPerPage = parseInt($event, 10)"
          />
        </div>
        <VSpacer />
        <div class="d-flex align-center flex-wrap gap-4">
          <div style="inline-size: 15.625rem;">
            <AppTextField
              v-model="searchQuery"
              placeholder="Search Exam"
            />
          </div>
          <VBtn
            prepend-icon="tabler-plus"
            @click="createNewExam"
          >
            Create New Exam
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="exams"
        :headers="headers"
        :items-length="totalExams"
        :loading="isLoading"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <template #[`item.title`]="{ item }">
          <h6 class="text-base font-weight-medium">
            {{ item.title }}
          </h6>
        </template>

        <template #[`item.status`]="{ item }">
          <VChip
            :color="resolveStatusVariant(item.status)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ item.status }}
          </VChip>
        </template>

        <template #[`item.actions`]="{ item }">
          <IconBtn @click="confirmDeleteExam(item)">
            <VIcon icon="tabler-trash" />
          </IconBtn>
          <IconBtn @click="navigateToBuilder(item.id)">
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent">
              Builder
            </VTooltip>
          </IconBtn>
        </template>

        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalExams"
          />
        </template>
      </VDataTableServer>
    </VCard>
    
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isDeletionDialogVisible"
      confirmation-question="Are you sure you want to delete this exam?"
      @confirm="handleDeleteConfirm"
    />
  </section>
</template>
