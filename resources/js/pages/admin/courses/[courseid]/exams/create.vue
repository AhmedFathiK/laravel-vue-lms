<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/utils/api'
import ExamForm from '@/components/exams/ExamForm.vue'
import { appendFormData } from '@/composables/useCrudSubmit'

const router = useRouter()
const route = useRoute()
const toast = useToast()
const isLoading = ref(false)

const courseId = computed(() => route.params.courseid)

const handleCreate = async examData => {
  isLoading.value = true
  try {
    const formData = new FormData()

    const data = {
      ...examData,
      type: 'exam',
    }

    Object.entries(data).forEach(([key, value]) => {
      appendFormData(formData, key, value)
    })

    const response = await api.post(`/admin/courses/${courseId.value}/exams`, formData)
    const newExam = response.exam || response.data

    toast.success('Exam created successfully')
    router.replace(`/admin/courses/${courseId.value}/exams/${newExam.id}`)
  } catch (error) {
    toast.error('Failed to create exam')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <ExamForm
    :is-loading="isLoading"
    is-creating
    @save="handleCreate"
  />
</template>
