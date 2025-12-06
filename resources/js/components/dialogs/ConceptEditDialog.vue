<script setup>
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  concept: {
    type: Object,
    default: null,
  },
  courseId: {
    type: [String, Number],
    required: true,
  },
})

const emit = defineEmits(['update:isDialogVisible', 'conceptSaved'])

const toast = useToast()
const isSubmitting = ref(false)
const conceptTypes = ref([])

// Form data
const formData = ref({
  title: '',
  content: '',
  type: '',
  status: 'active',
  courseId: props.courseId,
})

// Dialog title based on edit/create mode
const dialogTitle = computed(() => {
  return props.concept ? 'Edit Concept' : 'Add New Concept'
})

// Initialize form data when concept prop changes
watch(() => props.concept, () => {
  if (props.concept) {
    // Edit mode - populate form with concept data
    formData.value = {
      title: props.concept.title || '',
      content: props.concept.content || '',
      type: props.concept.type || '',
      status: props.concept.status || 'active',
      courseId: props.courseId,
    }
  } else {
    // Create mode - reset form
    formData.value = {
      title: '',
      content: '',
      type: '',
      status: 'active',
      courseId: props.courseId,
    }
  }
}, { immediate: true })

// Fetch concept types
const fetchConceptTypes = async () => {
  try {
    const response = await api.get('/admin/concepts/types')

    conceptTypes.value = response || []
  } catch (error) {
    console.error('Error fetching concept types:', error)
    toast.error('Failed to load concept types')
    conceptTypes.value = []
  }
}

// Form validation
const validateForm = () => {
  if (!formData.value.title) {
    toast.error('Title is required')
    
    return false
  }
  
  if (!formData.value.type) {
    toast.error('Type is required')
    
    return false
  }
  
  if (!formData.value.content) {
    toast.error('Content is required')
    
    return false
  }
  
  return true
}

// Submit form
const onSubmit = async () => {
  if (!validateForm()) return
  
  isSubmitting.value = true
  
  try {
    if (props.concept) {
      // Update existing concept
      await api.put(`/admin/concepts/${props.concept.id}`, formData.value)
      toast.success('Concept updated successfully')
    } else {
      // Create new concept
      await api.post('/admin/concepts', formData.value)
      toast.success('Concept created successfully')
    }
    
    // Close dialog and notify parent
    emit('update:isDialogVisible', false)
    emit('conceptSaved')
  } catch (error) {
    console.error('Error saving concept:', error)
    toast.error(error.response?.data?.message || 'Failed to save concept')
  } finally {
    isSubmitting.value = false
  }
}

// Close dialog
const closeDialog = () => {
  emit('update:isDialogVisible', false)
}

// Initialize
onMounted(() => {
  fetchConceptTypes()
})
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="700px"
    persistent
    @update:model-value="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        {{ dialogTitle }}
        <VSpacer />
        
        <VBtn
          variant="text"
          color="default"
          icon
          size="small"
          @click="closeDialog"
        >
          <VIcon
            size="24"
            icon="tabler-x"
          />
        </VBtn>
      </VCardTitle>
      
      <VDivider />
      
      <VCardText class="pa-4">
        <VForm @submit.prevent="onSubmit">
          <VRow>
            <!-- Title -->
            <VCol cols="12">
              <VTextField
                v-model="formData.title"
                label="Title"
                placeholder="Enter concept title"
                variant="outlined"
                :error-messages="formData.title ? '' : 'Title is required'"
              />
            </VCol>
            
            <!-- Type -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.type"
                label="Type"
                :items="conceptTypes"
                item-title="name"
                item-value="value"
                variant="outlined"
                :error-messages="formData.type ? '' : 'Type is required'"
              />
            </VCol>
            
            <!-- Status -->
            <VCol
              cols="12"
              md="6"
            >
              <VSelect
                v-model="formData.status"
                label="Status"
                :items="[{ title: 'Active', value: 'active' }, { title: 'Draft', value: 'draft' }]"
                item-title="title"
                item-value="value"
                variant="outlined"
              />
            </VCol>
            
            <!-- Content -->
            <VCol cols="12">
              <VTextarea
                v-model="formData.content"
                label="Content"
                placeholder="Enter concept content"
                variant="outlined"
                rows="5"
                :error-messages="formData.content ? '' : 'Content is required'"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
      
      <VDivider />
      
      <VCardActions class="pa-4">
        <VSpacer />
        
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
        >
          Cancel
        </VBtn>
        
        <VBtn
          color="primary"
          :loading="isSubmitting"
          @click="onSubmit"
        >
          {{ props.concept ? 'Update' : 'Create' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
