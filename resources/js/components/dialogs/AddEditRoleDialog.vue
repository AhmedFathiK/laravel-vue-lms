<script setup>
import api from '@/utils/api'
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import { VForm } from 'vuetify/components/VForm'

const props = defineProps({
  rolePermissions: {
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
})

const emit = defineEmits([
  'update:isDialogVisible',
  'update:rolePermissions',
  'submit',
])

const toast = useToast()

// Define permission subjects with their available actions
const permissionGroups = ref([
  {
    subject: 'User Management',
    actions: [
      { name: 'view.users', selected: false, label: 'View' },
      { name: 'create.users', selected: false, label: 'Create' },
      { name: 'edit.users', selected: false, label: 'Edit' },
      { name: 'delete.users', selected: false, label: 'Delete' },
      { name: 'ban.users', selected: false, label: 'Ban' },
      { name: 'assign_role.users', selected: false, label: 'Assign Role' },
    ],
  },

  // Other groups will be populated from API
])

const isSelectAll = ref(false)
const role = ref('')
const refPermissionForm = ref()
const isLoading = ref(false)

// Fetch available permissions from the API
const fetchPermissions = async () => {
  isLoading.value = true
  try {
    const response = await api.get('/admin/permissions')
    
    // Check if we have a valid response with permissions
    if (response && response.permissions) {
      // The API now returns a flat list of permissions
      const permissionsList = response.permissions
      
      // Group permissions by subject (second part after the dot)
      const groupedPermissions = {}
      
      permissionsList.forEach(permission => {
        // Get the permission name
        const permName = permission.name
        
        // Determine the subject and action
        let subject = 'Other'
        let action = permName
        
        if (permName.includes('.')) {
          // Handle dot notation (e.g., "view.users")
          const parts = permName.split('.')

          action = parts[0] // First part is the action
          subject = parts[1] // Second part is the subject
          
          // Replace underscores with spaces and capitalize subject for display
          subject = subject.replace(/_/g, ' ')
          subject = subject.charAt(0).toUpperCase() + subject.slice(1)
          
          // Add "Management" suffix for consistency
          if (!subject.includes('Management') && 
              subject !== 'Other' && 
              !['trash', 'admin panel', 'settings', 'translations', 'localization', 'pricing'].includes(subject.toLowerCase())) {
            subject = subject + ' Management'
          }
        }
        
        // Format action label
        let actionLabel
        
        // Clean up and capitalize the action
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
        case 'assign_role':
          actionLabel = 'Assign Role'
          break
        case 'assign_permission':
          actionLabel = 'Assign Permission'
          break
        case 'configure_revision':
          actionLabel = 'Configure Revision'
          break
        case 'add_video':
          actionLabel = 'Add Video'
          break
        case 'analyze_weakness':
          actionLabel = 'Analyze Weakness'
          break
        default:
          // Convert snake_case to Title Case
          actionLabel = action.split('_')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ')
        }
        
        // Create the subject group if it doesn't exist
        if (!groupedPermissions[subject]) {
          groupedPermissions[subject] = []
        }
        
        // Add permission to the appropriate subject group
        groupedPermissions[subject].push({
          name: permName,
          selected: false,
          label: actionLabel,
        })
      })
      
      // Convert to array format for the UI
      const newPermissionGroups = Object.keys(groupedPermissions).map(subject => ({
        subject,
        actions: groupedPermissions[subject],
      }))
      
      // Sort groups alphabetically
      newPermissionGroups.sort((a, b) => a.subject.localeCompare(b.subject))
      
      // Update the permission groups
      permissionGroups.value = newPermissionGroups
      
      // Apply any existing selected permissions
      if (props.rolePermissions && props.rolePermissions.permissions) {
        applySelectedPermissions(props.rolePermissions.permissions)
      }
    }
  } catch (error) {
    console.error('Error fetching permissions:', error)
    toast.error('Failed to fetch permissions')
  } finally {
    isLoading.value = false
  }
}

// Apply selected permissions to the UI
const applySelectedPermissions = permissions => {
  if (!permissions || !Array.isArray(permissions)) return
  
  permissionGroups.value.forEach(group => {
    group.actions.forEach(action => {
      action.selected = permissions.includes(action.name)
    })
  })
}

// Calculate total number of selected actions
const selectedActionsCount = computed(() => {
  let count = 0
   
  permissionGroups.value.forEach(group => {
    group.actions.forEach(action => {
      if (action.selected) count++
    })
  })
  
  return count
})

// Calculate total number of possible actions
const totalActionsCount = computed(() => {
  let count = 0
   
  permissionGroups.value.forEach(group => {
    count += group.actions.length
  })
  
  return count
})

// Determine if indeterminate state for select all checkbox
const isIndeterminate = computed(() => {
  return selectedActionsCount.value > 0 && selectedActionsCount.value < totalActionsCount.value
})

// Toggle select all permissions
watch(isSelectAll, val => {
  permissionGroups.value.forEach(group => {
    group.actions.forEach(action => {
      action.selected = val
    })
  })
})

// Update select all state based on selected permissions
watch(() => selectedActionsCount.value, () => {
  if (selectedActionsCount.value === 0) {
    isSelectAll.value = false
  } else if (selectedActionsCount.value === totalActionsCount.value) {
    isSelectAll.value = true
  }
})

// Map backend permissions to UI format when editing a role
watch(() => props.rolePermissions, newVal => {
  console.log('AddEditRoleDialog received props.rolePermissions:', newVal)
  
  if (newVal && newVal.name) {
    role.value = newVal.name
    
    // Apply selected permissions if permissions are already loaded
    if (permissionGroups.value.length > 0) {
      // Reset all permissions first
      permissionGroups.value.forEach(group => {
        group.actions.forEach(action => {
          action.selected = false
        })
      })
      
      // Apply selected permissions
      if (newVal.permissions && Array.isArray(newVal.permissions)) {
        applySelectedPermissions(newVal.permissions)
      }
    }
  }
}, { deep: true, immediate: true })

// Submit form with updated permissions
const onSubmit = () => {
  // Convert UI permissions format to backend format
  const selectedPermissions = []
  
  // Collect all selected permissions by their exact name
  permissionGroups.value.forEach(group => {
    group.actions.forEach(action => {
      if (action.selected) {
        selectedPermissions.push(action.name)
      }
    })
  })
  
  // Debug the permissions being sent back
  console.log('Submitting permissions:', selectedPermissions)
  
  const roleData = {
    id: props.rolePermissions.id,
    name: role.value,
    permissions: selectedPermissions,
  }

  // Debug the role data being sent back
  console.log('Submitting role data:', roleData)

  // Update the parent component's data
  emit('update:rolePermissions', roleData)
  
  // Submit the data to the parent for API handling
  emit('submit', roleData)
  
  // Close the dialog
  emit('update:isDialogVisible', false)
  
  // Reset the form
  isSelectAll.value = false
  refPermissionForm.value?.reset()
}

// Reset form and close dialog
const onReset = () => {
  emit('update:isDialogVisible', false)
  isSelectAll.value = false
  refPermissionForm.value?.reset()
}

// Toggle all actions for a specific subject
const toggleSubjectActions = (group, value) => {
  group.actions.forEach(action => {
    action.selected = value
  })
}

// Check if all actions in a subject are selected
const isAllSubjectActionsSelected = group => {
  return group.actions.every(action => action.selected)
}

// Check if some actions in a subject are selected
const isSomeSubjectActionsSelected = group => {
  return group.actions.some(action => action.selected) && !isAllSubjectActionsSelected(group)
}

// Fetch permissions when component is mounted
onMounted(fetchPermissions)
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    @update:model-value="onReset"
  >
    <!-- 👉 Dialog close btn -->
    <DialogCloseBtn @click="onReset" />

    <VCard class="pa-sm-10 pa-2">
      <VCardText>
        <!-- 👉 Title -->
        <h4 class="text-h4 text-center mb-2">
          {{ props.rolePermissions.name ? 'Edit' : 'Add New' }} Role
        </h4>
        <p class="text-body-1 text-center mb-6">
          Set Role Permissions
        </p>

        <!-- 👉 Form -->
        <VForm
          ref="refPermissionForm"
          @submit.prevent="onSubmit"
        >
          <!-- 👉 Role name -->
          <AppTextField
            v-model="role"
            label="Role Name"
            placeholder="Enter Role Name"
            :rules="[v => !!v || 'Role name is required']"
          />

          <h5 class="text-h5 my-6">
            Role Permissions
          </h5>

          <div
            v-if="isLoading"
            class="d-flex justify-center my-4"
          >
            <VProgressCircular indeterminate />
          </div>

          <!-- 👉 Role Permissions -->
          <VTable
            v-else
            class="permission-table text-no-wrap mb-6"
          >
            <!-- 👉 Select All -->
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

            <!-- 👉 Permission groups with actions -->
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

          <!-- 👉 Actions button -->
          <div class="d-flex align-center justify-center gap-4">
            <VBtn type="submit">
              Submit
            </VBtn>

            <VBtn
              color="secondary"
              variant="tonal"
              @click="onReset"
            >
              Cancel
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
