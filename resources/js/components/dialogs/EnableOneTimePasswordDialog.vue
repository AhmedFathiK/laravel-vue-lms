<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'

const props = defineProps({
  mobileNumber: {
    type: String,
    required: false,
    default: '',
  },
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  apiEndpoint: {
    type: String,
    required: false,
    default: '/api/user/two-factor-authentication',
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'submit',
  'refresh',
])

const form = ref({
  mobile: props.mobileNumber,
})

watch(() => props.mobileNumber, (val) => {
  form.value.mobile = val
})

const { isLoading, onSubmit, validationErrors } = useCrudSubmit({
  form,
  apiEndpoint: computed(() => props.apiEndpoint),
  isUpdate: computed(() => false),
  emit,
  successMessage: 'Mobile number verified successfully',
})

const dialogModelValueUpdate = val => {
  emit('update:isDialogVisible', val)
}
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    @update:model-value="dialogModelValueUpdate"
  >
    <!-- Dialog close btn -->
    <DialogCloseBtn @click="dialogModelValueUpdate(false)" />

    <VCard class="pa-2 pa-sm-10">
      <VCardText>
        <!-- 👉 Title -->
        <h5 class="text-h5 mb-2">
          Verify Your Mobile Number for SMS
        </h5>
        <p class="text-body-1 mb-6">
          Enter your mobile phone number with country code and  we will send you a verification code.
        </p>

        <VForm @submit.prevent="onSubmit">
          <AppTextField
            v-model="form.mobile"
            name="mobile"
            label="Phone Number"
            placeholder="+1 123 456 7890"
            type="number"
            class="mb-6"
            :error-messages="validationErrors.mobile"
          />

          <div class="d-flex flex-wrap justify-end gap-4">
            <VBtn
              color="secondary"
              variant="tonal"
              @click="dialogModelValueUpdate(false)"
            >
              Cancel
            </VBtn>
            <VBtn
              type="submit"
              :loading="isLoading"
            >
              continue
              <VIcon
                end
                icon="tabler-arrow-right"
                class="flip-in-rtl"
              />
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>
