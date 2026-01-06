<script setup>
import DeletionConfirmDialog from '@/components/dialogs/DeletionConfirmDialog.vue'
import api from '@/utils/api'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { requiredValidator } from '@core/utils/validators'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'

const router = useRouter()
const toast = useToast()
const route = useRoute()
const { locale } = useI18n()

const isLoading = ref(false)
const course = ref(null)
const categories = ref([])
const isDialogVisible = ref(false)
const editingCategory = ref(null)
const refForm = ref(null)

const categoryForm = ref({
  title: { en: '' },
  courseId: null,
})

// Password confirmation dialog
const isPasswordDialogVisible = ref(false)
const categoryToDelete = ref(null)

// Route params
const courseId = computed(() => route.params.courseid)

// Fetch course details
const fetchCourse = async () => {
  if (!courseId.value) return
  
  isLoading.value = true
  try {
    const response = await api.get(`/admin/courses/${courseId.value}`)

    course.value = response.course || response
    categoryForm.value.courseId = courseId.value
  } catch (error) {
    console.error('Error fetching course:', error)
  } finally {
    isLoading.value = false
  }
}

// Fetch categories
const fetchCategories = async () => {
  if (!courseId.value) return
  
  isLoading.value = true
  try {
    const response = await api.get(`/admin/courses/${courseId.value}/concept-categories`)

    categories.value = response
  } catch (error) {
    console.error('Error fetching categories:', error)
  } finally {
    isLoading.value = false
  }
}

// Open dialog for adding new category
const openAddDialog = () => {
  editingCategory.value = null
  categoryForm.value = {
    title: { en: '' },
    courseId: courseId.value,
  }
  isDialogVisible.value = true
}

// Open dialog for editing category
const openEditDialog = category => {
  editingCategory.value = category
  categoryForm.value = {
    title: { en: category.title },
    courseId: courseId.value,
  }
  isDialogVisible.value = true
}

// Submit form
const onSubmit = async () => {
  const { valid } = await refForm.value.validate()
  if (!valid) return

  isLoading.value = true
  try {
    if (editingCategory.value) {
      await api.put(`/admin/courses/${courseId.value}/concept-categories/${editingCategory.value.id}`, categoryForm.value)
      toast.success('Category updated successfully')
    } else {
      await api.post(`/admin/courses/${courseId.value}/concept-categories`, categoryForm.value)
      toast.success('Category created successfully')
    }
    isDialogVisible.value = false
    fetchCategories()
  } catch (error) {
    console.error('Error saving category:', error)
    toast.error(error.response?.data?.message || 'Failed to save category')
  } finally {
    isLoading.value = false
  }
}

// Confirm deletion
const confirmDeleteCategory = category => {
  categoryToDelete.value = category
  isPasswordDialogVisible.value = true
}

// Delete category
const handleDeleteConfirm = async () => {
  if (!categoryToDelete.value) return
  
  try {
    await api.delete(`/admin/courses/${courseId.value}/concept-categories/${categoryToDelete.value.id}`)
    toast.success('Category deleted successfully')
    fetchCategories()
  } catch (error) {
    console.error('Error deleting category:', error)
    toast.error(error.response?.data?.message || 'Failed to delete category')
  } finally {
    categoryToDelete.value = null
    isPasswordDialogVisible.value = false
  }
}

// Initialize
onMounted(() => {
  fetchCourse()
  fetchCategories()
})
</script>

<template>
  <section>
    <!-- Breadcrumb Navigation -->
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Courses', to: '/admin/courses' },
        { title: course?.title || 'Course', to: `/admin/courses/${courseId}/concepts` },
        { title: 'Concept Categories', disabled: true },
      ]"
      class="mb-4"
    />
    
    <VCard>
      <VCardItem>
        <VCardTitle>Concept Categories - {{ course?.title }}</VCardTitle>
        
        <template #append>
          <VBtn
            prepend-icon="tabler-plus"
            @click="openAddDialog"
          >
            Add Category
          </VBtn>
        </template>
      </VCardItem>
      
      <VDivider />
      
      <VCardText>
        <VTable class="text-no-wrap">
          <thead>
            <tr>
              <th>Title</th>
              <th class="text-center">
                Actions
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="categories.length === 0">
              <td
                colspan="2"
                class="text-center pa-4"
              >
                No categories found.
              </td>
            </tr>
            <tr
              v-for="category in categories"
              :key="category.id"
            >
              <td>{{ category.title }}</td>
              <td class="text-center">
                <VBtn
                  icon
                  variant="text"
                  color="default"
                  size="small"
                  @click="openEditDialog(category)"
                >
                  <VIcon
                    size="20"
                    icon="tabler-edit"
                  />
                </VBtn>
                
                <VBtn
                  icon
                  variant="text"
                  color="default"
                  size="small"
                  @click="confirmDeleteCategory(category)"
                >
                  <VIcon
                    size="20"
                    icon="tabler-trash"
                  />
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
        
        <div class="mt-6">
          <VBtn
            color="primary"
            variant="text"
            @click="router.push(`/admin/courses/${courseId}/concepts`)"
          >
            ← Back to Concepts
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <!-- Add/Edit Category Dialog -->
    <VDialog
      v-model="isDialogVisible"
      max-width="500"
    >
      <DialogCloseBtn @click="isDialogVisible = false" />
      <VCard :title="editingCategory ? 'Edit Category' : 'Add Category'">
        <VCardText>
          <VForm
            ref="refForm"
            @submit.prevent="onSubmit"
          >
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="categoryForm.title.en"
                  label="Title (English)"
                  :rules="[requiredValidator]"
                  placeholder="Enter category title"
                />
              </VCol>
              <VCol
                cols="12"
                class="d-flex justify-end gap-2"
              >
                <VBtn
                  color="secondary"
                  variant="tonal"
                  @click="isDialogVisible = false"
                >
                  Cancel
                </VBtn>
                <VBtn
                  type="submit"
                  :loading="isLoading"
                >
                  {{ editingCategory ? 'Update' : 'Create' }}
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- Deletion Confirmation Dialog -->
    <DeletionConfirmDialog
      v-model:is-dialog-visible="isPasswordDialogVisible"
      confirmation-question="Are you sure you want to delete this category? All concepts in this category will be uncategorized."
      @confirm="handleDeleteConfirm"
    />
  </section>
</template>
