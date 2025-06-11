<script setup>
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
      { name: 'view.user', selected: false, label: 'View' },
      { name: 'create.user', selected: false, label: 'Create' },
      { name: 'edit.user', selected: false, label: 'Edit' },
      { name: 'delete.user', selected: false, label: 'Delete' },
      { name: 'ban.user', selected: false, label: 'Ban' },
      { name: 'assign_role.user', selected: false, label: 'Assign Role' },
    ],
  },
  {
    subject: 'Role Management',
    actions: [
      { name: 'view.role', selected: false, label: 'View' },
      { name: 'create.role', selected: false, label: 'Create' },
      { name: 'edit.role', selected: false, label: 'Edit' },
      { name: 'delete.role', selected: false, label: 'Delete' },
      { name: 'assign_permission.role', selected: false, label: 'Assign Permission' },
    ],
  },
  {
    subject: 'Course Management',
    actions: [
      { name: 'view.course', selected: false, label: 'View' },
      { name: 'create.course', selected: false, label: 'Create' },
      { name: 'edit.course', selected: false, label: 'Edit' },
      { name: 'delete.course', selected: false, label: 'Delete' },
    ],
  },
  {
    subject: 'Level Management',
    actions: [
      { name: 'view.level', selected: false, label: 'View' },
      { name: 'create.level', selected: false, label: 'Create' },
      { name: 'edit.level', selected: false, label: 'Edit' },
      { name: 'delete.level', selected: false, label: 'Delete' },
      { name: 'unlock.level', selected: false, label: 'Unlock' },
    ],
  },
  {
    subject: 'Lesson Management',
    actions: [
      { name: 'view.lesson', selected: false, label: 'View' },
      { name: 'create.lesson', selected: false, label: 'Create' },
      { name: 'edit.lesson', selected: false, label: 'Edit' },
      { name: 'delete.lesson', selected: false, label: 'Delete' },
      { name: 'configure.lesson', selected: false, label: 'Configure' },
      { name: 'add_video.lesson', selected: false, label: 'Add Video' },
    ],
  },
  {
    subject: 'Slide Management',
    actions: [
      { name: 'view.slide', selected: false, label: 'View' },
      { name: 'create.slide', selected: false, label: 'Create' },
      { name: 'edit.slide', selected: false, label: 'Edit' },
      { name: 'delete.slide', selected: false, label: 'Delete' },
      { name: 'reorder.slide', selected: false, label: 'Reorder' },
    ],
  },
  {
    subject: 'Term Management',
    actions: [
      { name: 'view.term', selected: false, label: 'View' },
      { name: 'create.term', selected: false, label: 'Create' },
      { name: 'edit.term', selected: false, label: 'Edit' },
      { name: 'delete.term', selected: false, label: 'Delete' },
      { name: 'translate.term', selected: false, label: 'Translate' },
      { name: 'link.term', selected: false, label: 'Link' },
      { name: 'configure_revision.term', selected: false, label: 'Configure Revision' },
    ],
  },
  {
    subject: 'Assessment System',
    actions: [
      { name: 'view questions', selected: false, label: 'View Questions' },
      { name: 'create questions', selected: false, label: 'Create Questions' },
      { name: 'edit questions', selected: false, label: 'Edit Questions' },
      { name: 'delete questions', selected: false, label: 'Delete Questions' },
      { name: 'view exams', selected: false, label: 'View Exams' },
      { name: 'create exams', selected: false, label: 'Create Exams' },
      { name: 'edit exams', selected: false, label: 'Edit Exams' },
      { name: 'delete exams', selected: false, label: 'Delete Exams' },
      { name: 'view exam sections', selected: false, label: 'View Exam Sections' },
      { name: 'create exam sections', selected: false, label: 'Create Exam Sections' },
      { name: 'edit exam sections', selected: false, label: 'Edit Exam Sections' },
      { name: 'delete exam sections', selected: false, label: 'Delete Exam Sections' },
      { name: 'grade exams', selected: false, label: 'Grade Exams' },
    ],
  },
  {
    subject: 'Placement Tests',
    actions: [
      { name: 'view.placement_test', selected: false, label: 'View' },
      { name: 'create.placement_test', selected: false, label: 'Create' },
      { name: 'edit.placement_test', selected: false, label: 'Edit' },
      { name: 'assign.placement_test', selected: false, label: 'Assign' },
    ],
  },
  {
    subject: 'Gamification',
    actions: [
      { name: 'view.trophy', selected: false, label: 'View Trophies' },
      { name: 'manage.trophy', selected: false, label: 'Manage Trophies' },
      { name: 'assign.trophy', selected: false, label: 'Assign Trophies' },
    ],
  },
  {
    subject: 'Analytics',
    actions: [
      { name: 'view.user_stat', selected: false, label: 'View User Stats' },
      { name: 'view.course_stat', selected: false, label: 'View Course Stats' },
      { name: 'analyze_weakness.user_stat', selected: false, label: 'Analyze Weaknesses' },
    ],
  },
  {
    subject: 'Payments & Subscriptions',
    actions: [
      { name: 'view.payment', selected: false, label: 'View Payments' },
      { name: 'manage.subscription', selected: false, label: 'Manage Subscriptions' },
      { name: 'configure.pricing', selected: false, label: 'Configure Pricing' },
      { name: 'manage.receipt', selected: false, label: 'Manage Receipts' },
      { name: 'download.receipt', selected: false, label: 'Download Receipts' },
    ],
  },
  {
    subject: 'Settings',
    actions: [
      { name: 'access.setting', selected: false, label: 'Access Settings' },
      { name: 'manage.translation', selected: false, label: 'Manage Translations' },
      { name: 'manage.localization', selected: false, label: 'Manage Localization' },
    ],
  },
  {
    subject: 'Admin Panel',
    actions: [
      { name: 'access.admin_panel', selected: false, label: 'Access Admin Panel' },
    ],
  },
])

const isSelectAll = ref(false)
const role = ref('')
const refPermissionForm = ref()
const availablePermissions = ref([])

// Fetch available permissions from the API
const fetchPermissions = async () => {
  try {
    const response = await fetch('/api/admin/permissions')
    const data = await response.json()

    availablePermissions.value = data.permissions
  } catch (error) {
    console.error('Error fetching permissions:', error)
    toast.error('Failed to fetch permissions')
  }
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
    
    // Reset all permissions first
    permissionGroups.value.forEach(group => {
      group.actions.forEach(action => {
        action.selected = false
      })
    })
    
    // Map backend permissions to UI format if they exist
    if (newVal.permissions && Array.isArray(newVal.permissions)) {
      console.log('Mapping permissions:', newVal.permissions)
      
      // Loop through each permission string
      newVal.permissions.forEach(permission => {
        console.log('Processing permission:', permission, typeof permission)
        
        // Ensure permission is a string before processing
        if (typeof permission === 'string') {
          // Find the action that matches this permission exactly
          permissionGroups.value.forEach(group => {
            const matchingAction = group.actions.find(action => action.name === permission)
            if (matchingAction) {
              matchingAction.selected = true
              console.log(`Selected: ${group.subject} - ${matchingAction.label}`)
            }
          })
        }
      })
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

          <!-- 👉 Role Permissions -->
          <VTable class="permission-table text-no-wrap mb-6">
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
