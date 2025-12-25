<script setup>
import { useCrudSubmit } from '@/composables/useCrudSubmit'
import api from '@/utils/api'
import DialogCloseBtn from '@core/components/DialogCloseBtn.vue'
import { requiredValidator } from '@core/utils/validators'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({
  data: {
    type: Object,
    required: false,
    default: () => ({
      name: '',
      permissions: [],
    }),
  },
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  dialogMode: {
    type: String,
    required: true,
    validator: value => ['add', 'edit'].includes(value),
  },
})

const emit = defineEmits([
  'update:isDialogVisible',
  'refresh',
])

const toast = useToast()
const permissionGroups = ref([])
const isSelectAll = ref(false)
const refForm = ref(null)
const isLoadingPermissions = ref(false)

const form = ref({
  name: '',
})

// Fetch available permissions from the API
const fetchPermissions = async () => {
  isLoadingPermissions.value = true
  try {
    const response = await api.get('/admin/permissions')
    
    if (response && response.permissions) {
      const permissionsList = response.permissions
      const groupedPermissions = {}
      
      permissionsList.forEach(permission => {
        const permName = permission.name
        let subject = 'Other'
        let action = permName
        
        if (permName.includes('.')) {
          const parts = permName.split('.')

          action = parts[0]
          subject = parts[1]
          
          subject = subject.replace(/_/g, ' ')
          subject = subject.charAt(0).toUpperCase() + subject.slice(1)
          
          if (!subject.includes('Management') && 
              subject !== 'Other' && 
              !['trash', 'admin panel', 'settings', 'translations', 'localization', 'pricing'].includes(subject.toLowerCase())) {
            subject = subject + ' Management'
          }
        }
        
        let actionLabel
        switch(action.toLowerCase()) {
        case 'view':
        case 'create':
        case 'edit':
        case 'delete':
        case 'restore':
        case 'ban':
        case 'unlock':
        case 'grade':
        case 'assign':
        case 'download':
        case 'access':
          actionLabel = action.charAt(0).toUpperCase() + action.slice(1)
          break
        case 'assignRole':
          actionLabel = 'Assign Role'
          break
        case 'assignPermission':
          actionLabel = 'Assign Permission'
          break
        case 'configureRevision':
          actionLabel = 'Configure Revision'
          break
        case 'addVideo':
          actionLabel = 'Add Video'
          break
        case 'analyzeWeakness':
          actionLabel = 'Analyze Weakness'
          break
        default:
          actionLabel = action.split('_')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ')
        }
        
        if (!groupedPermissions[subject]) {
          groupedPermissions[subject] = []
        }
        
        groupedPermissions[subject].push({
          name: permName,
          selected: false,
          label: actionLabel,
        })
      })
      
      const newPermissionGroups = Object.keys(groupedPermissions).map(subject => ({
        subject,
        actions: groupedPermissions[subject],
      }))
      
      newPermissionGroups.sort((a, b) => a.subject.localeCompare(b.subject))
      permissionGroups.value = newPermissionGroups
      
      if (props.data && props.data.permissions) {
        applySelectedPermissions(props.data.permissions)
      }
    }
  } catch (error) {
    console.error('Error fetching permissions:', error)
    toast.error('Failed to fetch permissions')
  } finally {
    isLoadingPermissions.value = false
  }
}

const applySelectedPermissions = permissions => {
  if (!permissions || !Array.isArray(permissions)) return
  
  const permissionNames = permissions.map(p => typeof p === 'string' ? p : p.name)
  
  permissionGroups.value.forEach(group => {
    group.actions.forEach(action => {
      action.selected = permissionNames.includes(action.name)
    })
  })
}

const selectedActionsCount = computed(() => {
  let count = 0
  permissionGroups.value.forEach(group => {
    group.actions.forEach(action => {
      if (action.selected) count++
    })
  })
  
  return count
})

const totalActionsCount = computed(() => {
  let count = 0
  permissionGroups.value.forEach(group => {
    count += group.actions.length
  })
  
  return count
})

const isIndeterminate = computed(() => {
  return selectedActionsCount.value > 0 && selectedActionsCount.value < totalActionsCount.value
})

watch(isSelectAll, val => {
  permissionGroups.value.forEach(group => {
    group.actions.forEach(action => {
      action.selected = val
    })
  })
})

watch(() => selectedActionsCount.value, () => {
  if (selectedActionsCount.value === 0) {
    isSelectAll.value = false
  } else if (selectedActionsCount.value === totalActionsCount.value) {
    isSelectAll.value = true
  }
})

watch(() => props.isDialogVisible, isVisible => {
  if (isVisible) {
    if (props.data) {
      form.value.name = props.data.name || ''
      
      if (permissionGroups.value.length > 0) {
        // Reset permissions
        permissionGroups.value.forEach(group => {
          group.actions.forEach(action => {
            action.selected = false
          })
        })

        // Apply permissions
        if (props.data.permissions) {
          applySelectedPermissions(props.data.permissions)
        }
      }
    } else {
      form.value.name = ''
      isSelectAll.value = false
    }
    
    nextTick(() => {
      refForm.value?.resetValidation()
    })
  }
})

// Compute extra data for useCrudSubmit
const extraData = computed(() => {
  const selectedPermissions = []

  permissionGroups.value.forEach(group => {
    group.actions.forEach(action => {
      if (action.selected) {
        selectedPermissions.push(action.name)
      }
    })
  })
  
  return { permissions: selectedPermissions }
})

// Custom emit for refresh
const customEmit = (event, ...args) => {
  if (event === 'saved') {
    emit('refresh', ...args)
  } else {
    emit(event, ...args)
  }
}

const { isLoading, validationErrors, onSubmit } = useCrudSubmit({
  formRef: refForm,
  form: form,
  apiEndpoint: computed(() => props.dialogMode === 'edit'
    ? `/admin/roles/${props.data.id}` 
    : '/admin/roles'),
  isUpdate: computed(() => props.dialogMode === 'edit'),
  extraData,
  isFormData: false,
  emit: customEmit,
  successMessage: computed(() => props.dialogMode === 'edit' ? 'Role updated successfully' : 'Role created successfully'),
})

const toggleSubjectActions = (group, value) => {
  group.actions.forEach(action => {
    action.selected = value
  })
}

const isAllSubjectActionsSelected = group => {
  return group.actions.every(action => action.selected)
}

const isSomeSubjectActionsSelected = group => {
  return group.actions.some(action => action.selected) && !isAllSubjectActionsSelected(group)
}

onMounted(fetchPermissions)
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    @update:model-value="val => $emit('update:isDialogVisible', val)"
  >
    <DialogCloseBtn @click="$emit('update:isDialogVisible', false)" />

    <VCard class="pa-sm-10 pa-2">
      <VCardText>
        <h4 class="text-h4 text-center mb-2">
          {{ props.dialogMode === 'edit' ? 'Edit' : 'Add New' }} Role
        </h4>
        <p class="text-body-1 text-center mb-6">
          Set Role Permissions
        </p>

        <VForm
          ref="refForm"
          @submit.prevent="onSubmit"
        >
          <AppTextField
            v-model="form.name"
            label="Role Name"
            placeholder="Enter Role Name"
            :rules="[requiredValidator]"
            :error-messages="validationErrors.name"
          />

          <div class="d-flex align-center justify-space-between my-6">
            <h5 class="text-h5 mb-0">
              Role Permissions
            </h5>
            <div
              v-if="validationErrors.permissions"
              class="text-error text-sm"
            >
              {{ validationErrors.permissions[0] }}
            </div>
          </div>

          <div
            v-if="isLoadingPermissions"
            class="d-flex justify-center my-4"
          >
            <VProgressCircular indeterminate />
          </div>

          <VTable
            v-else
            class="permission-table text-no-wrap mb-6"
          >
            <tr>
              <td>
                <h6 class="text-h6">
                  Administrator Access
                </h6>
              </td>
              <td>
                <div class="d-flex justify-end">
                  <VCheckbox
                    v-model="isSelectAll"
                    v-model:indeterminate="isIndeterminate"
                    label="Select All"
                  />
                </div>
              </td>
            </tr>

            <template
              v-for="(group, groupIndex) in permissionGroups"
              :key="groupIndex"
            >
              <tr>
                <td>
                  <div class="d-flex align-center">
                    <VCheckbox
                      :model-value="isAllSubjectActionsSelected(group)"
                      :indeterminate="isSomeSubjectActionsSelected(group)"
                      @update:model-value="toggleSubjectActions(group, $event)"
                    />
                    <h6 class="text-h6 mb-0">
                      {{ group.subject }}
                    </h6>
                  </div>
                </td>
                <td>
                  <div class="d-flex flex-wrap justify-end gap-2">
                    <VChip
                      v-for="(action, actionIndex) in group.actions"
                      :key="actionIndex"
                      :color="action.selected ? 'primary' : 'default'"
                      :variant="action.selected ? 'flat' : 'outlined'"
                      size="small"
                      class="ma-1"
                      @click="action.selected = !action.selected"
                    >
                      {{ action.label }}
                    </VChip>
                  </div>
                </td>
              </tr>
            </template>
          </VTable>

          <div class="d-flex align-center justify-center gap-4">
            <VBtn
              color="secondary"
              variant="tonal"
              :disabled="isLoading"
              @click="$emit('update:isDialogVisible', false)"
            >
              Cancel
            </VBtn>
            
            <VBtn
              type="submit"
              :loading="isLoading"
            >
              Submit
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.permission-table {
  td {
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    padding-block: 0.8rem;
    vertical-align: middle;
    
    &:first-child {
      width: 40%;
    }
    
    &:last-child {
      width: 60%;
    }
  }
}
</style>
