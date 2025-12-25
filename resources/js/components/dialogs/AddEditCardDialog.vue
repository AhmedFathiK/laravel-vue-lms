<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  cardDetails: {
    type: Object,
    required: false,
    default: () => ({
      id: null,
      number: '',
      name: '',
      expiry: '',
      cvv: '',
      isPrimary: false,
      type: '',
    }),
  },
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits([
  'submit',
  'update:isDialogVisible',
  'refresh',
])

const refForm = ref(null)

const createDefaultForm = () => ({
  id: null,
  number: '',
  name: '',
  expiry: '',
  cvv: '',
  isPrimary: false,
  type: '',
})

const formData = ref(createDefaultForm())

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.cardDetails && (props.cardDetails.number || props.cardDetails.name)) {
      formData.value = JSON.parse(JSON.stringify(props.cardDetails))
    } else {
      formData.value = createDefaultForm()
    }
  }
})

const { isLoading, validationErrors, onSubmit } = useCrudSubmit({
  formRef: refForm,
  form: formData,

  // Note: These endpoints are placeholders. Ensure backend routes exist.
  apiEndpoint: computed(() => formData.value.id ? `/api/user/cards/${formData.value.id}` : '/api/user/cards'),
  isUpdate: computed(() => !!formData.value.id),
  emit,
  successMessage: computed(() => formData.value.id ? 'Card updated successfully' : 'Card added successfully'),
})

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 600"
    :model-value="props.isDialogVisible"
    @update:model-value="closeDialog"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="closeDialog" />

    <VCard class="pa-2 pa-sm-10">
      <!-- 👉 Title -->
      <VCardItem class="text-center">
        <VCardTitle>
          <h4 class="text-h4 mb-2">
            {{ formData.id || formData.name ? 'Edit Card' : 'Add New Card' }}
          </h4>
        </VCardTitle>
        <p class="text-body-1 mb-0">
          {{ formData.id || formData.name ? 'Edit your saved card details' : 'Add card for future billing' }}
        </p>
      </VCardItem>

      <VCardText class="pt-6">
        <VForm 
          ref="refForm"
          @submit.prevent="onSubmit"
        >
          <VRow>
            <!-- 👉 Card Number -->
            <VCol cols="12">
              <AppTextField
                v-model="formData.number"
                label="Card Number"
                placeholder="1356 3215 6548 7898"
                type="number"
                :error-messages="validationErrors.number"
              />
            </VCol>

            <!-- 👉 Card Name -->
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="formData.name"
                label="Name"
                placeholder="John Doe"
                :error-messages="validationErrors.name"
              />
            </VCol>

            <!-- 👉 Card Expiry -->
            <VCol
              cols="12"
              md="3"
            >
              <AppTextField
                v-model="formData.expiry"
                label="Expiry Date"
                placeholder="MM/YY"
                :error-messages="validationErrors.expiry"
              />
            </VCol>

            <!-- 👉 Card CVV -->
            <VCol
              cols="12"
              md="3"
            >
              <AppTextField
                v-model="formData.cvv"
                type="number"
                label="CVV Code"
                placeholder="654"
                :error-messages="validationErrors.cvv"
              />
            </VCol>

            <!-- 👉 Card Primary Set -->
            <VCol cols="12">
              <VSwitch
                v-model="formData.isPrimary"
                label="Save Card for future billing?"
                :error-messages="validationErrors.isPrimary"
              />
            </VCol>

            <!-- 👉 Card actions -->
            <VCol
              cols="12"
              class="text-center"
            >
              <VBtn
                class="me-4"
                type="submit"
                :loading="isLoading"
              >
                Submit
              </VBtn>
              <VBtn
                color="secondary"
                variant="tonal"
                :disabled="isLoading"
                @click="closeDialog"
              >
                Cancel
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
