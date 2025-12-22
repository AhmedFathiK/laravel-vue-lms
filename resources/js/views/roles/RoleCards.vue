<script setup>
import api from '@/utils/api'
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
const isConfirmDialogVisible = ref(false)
const roleToDelete = ref(null)
const isDeleting = ref(false)
const selectedRole = ref(null)
const avatarImages = [avatar1, avatar2, avatar3, avatar4, avatar5, avatar6, avatar7, avatar8, avatar9, avatar10]

// Fetch roles from the API
const fetchRoles = async () => {
  isLoading.value = true
  try {
    const data = await api.get('/admin/roles')
    
    console.log('Fetched roles data:', data)
    
    roles.value = data.roles.map(role => {
      // Assign random avatars for display purposes
      const userCount = role.userCount
      const userAvatars = []
      for (let i = 0; i < Math.min(userCount, 10); i++) {
        userAvatars.push(avatarImages[i % avatarImages.length])
      }
      
      return {
        ...role,
        users: userAvatars,
      }
    })
  } catch (error) {
    handleApiError(error, toast, 'Failed to load roles')
  } finally {
    isLoading.value = false
  }
}

// Edit role permissions
const editPermission = value => {
  selectedRole.value = value
  isRoleDialogVisible.value = true
  
  // Create a properly formatted roleDetail object for the dialog
  roleDetail.value = { ...value }
  
  // Debug
  console.log('Sending to dialog:', roleDetail.value)
}

// Copy role permissions to a new role
const copyRole = value => {
  roleDetail.value = { 
    ...value, 
    id: null,
    name: `${value.name} (Copy)`,
  }
  isRoleDialogVisible.value = true
}

// Open delete confirmation
const deleteRole = id => {
  roleToDelete.value = id
  isConfirmDialogVisible.value = true
}

// Confirm delete
const onConfirmDelete = async confirmed => {
  if (!confirmed) {
    isConfirmDialogVisible.value = false
    roleToDelete.value = null
    
    return
  }

  isDeleting.value = true
  try {
    await api.post(`/admin/roles/${roleToDelete.value}`, {
      _method: 'DELETE',
    })
    
    toast.success('Role deleted successfully')
    isConfirmDialogVisible.value = false
    roleToDelete.value = null
    await fetchRoles()
  } catch (error) {
    handleApiError(error, toast, 'Failed to delete role')
  } finally {
    isDeleting.value = false
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
      class="d-flex justify-center align-center"
    >
      <VProgressCircular
        :size="40"
        width="3"
        indeterminate
        color="primary"
      />
    </VCol>
    
    <!-- 👉 Roles -->
    <VCol
      v-for="item in roles"
      v-else
      :key="item.name"
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
                {{ item.name }}
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
            <IconBtn @click="copyRole(item)">
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
      <VCard :ripple="false">
        <VRow
          no-gutters
          class="h-100"
        >
          <VCol
            cols="5"
            class="d-flex flex-column justify-end align-center mt-5"
          >
            <img
              width="80"
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
        @refresh="fetchRoles"
      />
    </VCol>
  </VRow>

  <AddEditRoleDialog
    v-model:is-dialog-visible="isRoleDialogVisible"
    :role-permissions="roleDetail"
    @refresh="fetchRoles"
  />

  <ConfirmDialog
    v-model:is-dialog-visible="isConfirmDialogVisible"
    confirmation-question="Are you sure you want to delete this role?"
    confirm-msg="Role deleted successfully"
    :loading="isDeleting"
    @confirm="onConfirmDelete"
  />
</template>
