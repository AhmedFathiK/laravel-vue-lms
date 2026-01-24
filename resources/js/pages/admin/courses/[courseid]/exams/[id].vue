<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/utils/api'
import ExamForm from '@/components/exams/ExamForm.vue'
import { appendFormData } from '@/composables/useCrudSubmit'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const examId = route.params.id
const courseId = computed(() => route.params.courseid)

const exam = ref(null)
const isLoading = ref(false)

const fetchExam = async () => {
  isLoading.value = true
  try {
    const response = await api.get(`/admin/courses/${courseId.value}/exams/${examId}`)

    exam.value = response
  } catch (error) {
    toast.error('Failed to load exam')
    router.push(`/admin/courses/${courseId.value}/exams`)
  } finally {
    isLoading.value = false
  }
}

const handleUpdate = async examData => {
  isLoading.value = true
  try {
    const formData = new FormData()

    const data = {
      ...examData,
      type: 'exam',
      _method: 'PUT',
    }

    Object.entries(data).forEach(([key, value]) => {
      appendFormData(formData, key, value)
    })

    await api.post(`/admin/courses/${courseId.value}/exams/${examId}`, formData)
    toast.success('Exam saved successfully')
  } catch (error) {
    toast.error('Failed to save exam')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchExam()
})
</script>

<template>
  <ExamForm
    v-if="exam"
    :initial-data="exam"
    :is-loading="isLoading"
    :is-creating="false"
    @save="handleUpdate"
  />
  <div
    v-else
    class="d-flex justify-center align-center h-100"
  >
    <VProgressCircular
      indeterminate
      color="primary"
    />
  </div>
</template>
