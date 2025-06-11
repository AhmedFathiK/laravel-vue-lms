<script setup>
import avatar1 from '@images/avatars/avatar-1.png'
import avatar10 from '@images/avatars/avatar-10.png'
import avatar2 from '@images/avatars/avatar-2.png'
import avatar3 from '@images/avatars/avatar-3.png'
import avatar4 from '@images/avatars/avatar-4.png'
import avatar5 from '@images/avatars/avatar-5.png'
import avatar6 from '@images/avatars/avatar-6.png'
import avatar7 from '@images/avatars/avatar-7.png'
import avatar8 from '@images/avatars/avatar-8.png'
import avatar9 from '@images/avatars/avatar-9.png'
import girlUsingMobile from '@images/pages/girl-using-mobile.png'
import { onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()
const roles = ref([])
const isLoading = ref(true)
const isRoleDialogVisible = ref(false)
const roleDetail = ref()
const isAddRoleDialogVisible = ref(false)
const selectedRole = ref(null)
const avatarImages = [avatar1, avatar2, avatar3, avatar4, avatar5, avatar6, avatar7, avatar8, avatar9, avatar10]

// Fetch roles from the API
const fetchRoles = async () => {
  isLoading.value = true
  try {
    const response = await fetch('/api/admin/roles')
    const data = await response.json()
    
    console.log('Fetched roles data:', data)
    
    roles.value = data.roles.map(role => {
      // Assign random avatars for display purposes
      const userCount = role.user_count
      const userAvatars = []
      for (let i = 0; i < Math.min(userCount, 10); i++) {
        userAvatars.push(avatarImages[i % avatarImages.length])
      }
      
      return {
        id: role.id,
        role: role.name,
        users: userAvatars,
        userCount: role.user_count,
        permissions: role.permissions || [], // Store permissions directly
        isProtected: role.is_protected,
      }
    })
  } catch (error) {
    console.error('Error fetching roles:', error)
    toast.error('Failed to load roles')
  } finally {
    isLoading.value = false
  }
}

// Edit role permissions
const editPermission = value => {
  selectedRole.value = value
  isRoleDialogVisible.value = true
  
  // Create a properly formatted roleDetail object for the dialog
  roleDetail.value = {
    id: value.id,
    name: value.role,
    permissions: value.permissions || [],
  }
  
  // Debug
  console.log('Sending to dialog:', roleDetail.value)
}

// Handle role update from dialog
const handleRoleUpdate = async updatedRole => {
  try {
    // Debug the updated role data
    console.log('Updated role from dialog:', updatedRole)
    
    const response = await fetch(`/api/admin/roles/${updatedRole.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        name: updatedRole.name,
        permissions: updatedRole.permissions, // Use the new permissions format
      }),
    })
    
    if (response.ok) {
      toast.success('Role updated successfully')
      await fetchRoles()
    } else {
      const error = await response.json()

      toast.error(error.message || 'Failed to update role')
    }
  } catch (error) {
    console.error('Error updating role:', error)
    toast.error('Failed to update role')
  }
}

// Handle role creation from dialog
const handleRoleCreate = async newRole => {
  try {
    // Debug the new role data
    console.log('New role from dialog:', newRole)
    
    const response = await fetch('/api/admin/roles', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        name: newRole.name,
        permissions: newRole.permissions || [], // Use the permissions from the dialog
      }),
    })
    
    if (response.ok) {
      toast.success('Role created successfully')
      await fetchRoles()
    } else {
      const error = await response.json()

      toast.error(error.message || 'Failed to create role')
    }
  } catch (error) {
    console.error('Error creating role:', error)
    toast.error('Failed to create role')
  }
}

// Delete role
const deleteRole = async roleId => {
  if (!confirm('Are you sure you want to delete this role?')) return
  
  try {
    const response = await fetch(`/api/admin/roles/${roleId}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    
    if (response.ok) {
      toast.success('Role deleted successfully')
      await fetchRoles()
    } else {
      const error = await response.json()

      toast.error(error.message || 'Failed to delete role')
    }
  } catch (error) {
    console.error('Error deleting role:', error)
    toast.error('Failed to delete role')
  }
}

// Fetch roles on component mount
onMounted(fetchRoles)
</script>

<template>
  <VRow>
    <VCol
      v-if="isLoading"
      cols="12"
    >
      <VProgressLinear indeterminate />
    </VCol>
    
    <!-- 👉 Roles -->
    <VCol
      v-for="item in roles"
      v-else
      :key="item.role"
      cols="12"
      sm="6"
      lg="4"
    >
      <VCard>
        <VCardText class="d-flex align-center pb-4">
          <div class="text-body-1">
            Total {{ item.userCount }} users
          </div>

          <VSpacer />

          <div class="v-avatar-group">
            <template
              v-for="(user, index) in item.users"
              :key="index"
            >
              <VAvatar
                v-if="item.users.length > 4 && item.users.length !== 4 && index < 3"
                size="40"
                :image="user"
              />

              <VAvatar
                v-if="item.users.length === 4"
                size="40"
                :image="user"
              />
            </template>
            <VAvatar
              v-if="item.users.length > 4"
              :color="$vuetify.theme.current.dark ? '#373B50' : '#EEEDF0'"
            >
              <span>
                +{{ item.users.length - 3 }}
              </span>
            </VAvatar>
          </div>
        </VCardText>

        <VCardText>
          <div class="d-flex justify-space-between align-center">
            <div>
              <h5 class="text-h5">
                {{ item.role }}
              </h5>
              <div class="d-flex align-center gap-2">
                <a
                  href="javascript:void(0)"
                  @click="editPermission(item)"
                >
                  Edit Role
                </a>
                <span v-if="item.userCount === 0 && !item.isProtected">
                  |
                  <a
                    href="javascript:void(0)"
                    class="text-error"
                    @click="deleteRole(item.id)"
                  >
                    Delete
                  </a>
                </span>
              </div>
            </div>
            <IconBtn>
              <VIcon
                icon="tabler-copy"
                class="text-high-emphasis"
              />
            </IconBtn>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- 👉 Add New Role -->
    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <VCard
        class="h-100"
        :ripple="false"
      >
        <VRow
          no-gutters
          class="h-100"
        >
          <VCol
            cols="5"
            class="d-flex flex-column justify-end align-center mt-5"
          >
            <img
              width="85"
              :src="girlUsingMobile"
            >
          </VCol>

          <VCol cols="7">
            <VCardText class="d-flex flex-column align-end justify-end gap-4">
              <VBtn
                size="small"
                @click="isAddRoleDialogVisible = true"
              >
                Add New Role
              </VBtn>
              <div class="text-end">
                Add new role,<br> if it doesn't exist.
              </div>
            </VCardText>
          </VCol>
        </VRow>
      </VCard>
      <AddEditRoleDialog 
        v-model:is-dialog-visible="isAddRoleDialogVisible" 
        :role-permissions="{ id: null, name: '', permissions: [] }"
        @submit="handleRoleCreate"
      />
    </VCol>
  </VRow>

  <AddEditRoleDialog
    v-model:is-dialog-visible="isRoleDialogVisible"
    v-model:role-permissions="roleDetail"
    @submit="handleRoleUpdate"
  />
</template>
