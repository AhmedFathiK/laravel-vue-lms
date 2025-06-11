<script setup>
import UserInfoEditDialog from '@/components/dialogs/UserInfoEditDialog.vue'
import { onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

const toast = useToast()

// 👉 Store
const searchQuery = ref('')
const selectedRole = ref()
const selectedStatus = ref()
const isLoading = ref(true)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('created_at')
const orderBy = ref('desc')
const selectedRows = ref([])
const users = ref([])
const totalUsers = ref(0)
const roles = ref([])

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key || 'created_at'
  orderBy.value = options.sortBy[0]?.order || 'desc'
  fetchUsers()
}

// Headers
const headers = [
  {
    title: 'User',
    key: 'user',
  },
  {
    title: 'Role',
    key: 'role',
  },
  {
    title: 'Status',
    key: 'status',
  },
  {
    title: 'Actions',
    key: 'actions',
    sortable: false,
  },
]

// Fetch users from API
const fetchUsers = async () => {
  isLoading.value = true
  try {
    const queryParams = new URLSearchParams({
      search: searchQuery.value,
      role: selectedRole.value || '',
      status: selectedStatus.value || '',
      per_page: itemsPerPage.value,
      page: page.value,
      sortBy: sortBy.value,
      orderBy: orderBy.value,
    })
    
    const response = await fetch(`/api/admin/users?${queryParams.toString()}`)
    const data = await response.json()
    
    users.value = data.users.map(user => {
      return {
        id: user.id,
        fullName: user.name,
        email: user.email,
        role: user.role_names ? user.role_names[0] : 'No Role',
        status: user.email_verified_at ? 'active' : 'pending',
        avatar: null, // No avatars in the backend data
      }
    })
    
    totalUsers.value = data.totalUsers
  } catch (error) {
    console.error('Error fetching users:', error)
    toast.error('Failed to load users')
  } finally {
    isLoading.value = false
  }
}

// Fetch roles for filter
const fetchRoles = async () => {
  try {
    const response = await fetch('/api/admin/roles')
    const data = await response.json()
    
    roles.value = data.roles.map(role => ({
      title: role.name,
      value: role.name,
    }))
  } catch (error) {
    console.error('Error fetching roles:', error)
    toast.error('Failed to load roles')
  }
}

const resolveUserRoleVariant = role => {
  const roleLowerCase = role.toLowerCase()
  if (roleLowerCase === 'student')
    return {
      color: 'primary',
      icon: 'tabler-user',
    }
  if (roleLowerCase === 'instructor')
    return {
      color: 'warning',
      icon: 'tabler-settings',
    }
  if (roleLowerCase === 'manager')
    return {
      color: 'success',
      icon: 'tabler-chart-donut',
    }
  if (roleLowerCase === 'editor')
    return {
      color: 'info',
      icon: 'tabler-pencil',
    }
  if (roleLowerCase === 'admin')
    return {
      color: 'error',
      icon: 'tabler-device-laptop',
    }
  
  return {
    color: 'primary',
    icon: 'tabler-user',
  }
}

const resolveUserStatusVariant = stat => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'pending')
    return 'warning'
  if (statLowerCase === 'active')
    return 'success'
  if (statLowerCase === 'inactive')
    return 'secondary'
  
  return 'primary'
}

const isUserInfoEditDialogVisible = ref(false)

const addNewUser = async userData => {
  try {
    const response = await fetch('/api/admin/users', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(userData),
    })
    
    if (response.ok) {
      toast.success('User created successfully')
      await fetchUsers()
    } else {
      const error = await response.json()

      toast.error(error.message || 'Failed to create user')
    }
  } catch (error) {
    console.error('Error creating user:', error)
    toast.error('Failed to create user')
  }
}

const deleteUser = async id => {
  if (!confirm('Are you sure you want to delete this user?')) return
  
  try {
    const response = await fetch(`/api/admin/users/${id}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    
    if (response.ok) {
      toast.success('User deleted successfully')
      
      // Delete from selectedRows
      const index = selectedRows.value.findIndex(row => row === id)
      if (index !== -1)
        selectedRows.value.splice(index, 1)
      
      // Refresh users
      await fetchUsers()
    } else {
      const error = await response.json()

      toast.error(error.message || 'Failed to delete user')
    }
  } catch (error) {
    console.error('Error deleting user:', error)
    toast.error('Failed to delete user')
  }
}

const toggleUserStatus = async userId => {
  try {
    const response = await fetch(`/api/admin/users/${userId}/toggle-status`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    
    if (response.ok) {
      toast.success('User status updated successfully')
      await fetchUsers()
    } else {
      const error = await response.json()

      toast.error(error.message || 'Failed to update user status')
    }
  } catch (error) {
    console.error('Error updating user status:', error)
    toast.error('Failed to update user status')
  }
}

// Watch for changes in search query, role filter, or status filter
watch([searchQuery, selectedRole, selectedStatus], () => {
  page.value = 1 // Reset to first page when filters change
  fetchUsers()
})

// Initialize data
onMounted(() => {
  fetchUsers()
  fetchRoles()
})
</script>

<template>
  <section>
    <VCard>
      <VCardText class="d-flex flex-wrap gap-4">
        <div class="d-flex gap-2 align-center">
          <p class="text-body-1 mb-0">
            Show
          </p>
          <AppSelect
            :model-value="itemsPerPage"
            :items="[
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: 100, title: '100' },
              { value: -1, title: 'All' },
            ]"
            style="inline-size: 5.5rem;"
            @update:model-value="itemsPerPage = parseInt($event, 10); fetchUsers()"
          />
        </div>

        <VSpacer />

        <div class="d-flex align-center flex-wrap gap-4">
          <!-- 👉 Search  -->
          <AppTextField
            v-model="searchQuery"
            placeholder="Search User"
            style="inline-size: 15.625rem;"
          />

          <!-- 👉 Role filter -->
          <AppSelect
            v-model="selectedRole"
            placeholder="Select Role"
            :items="roles"
            clearable
            clear-icon="tabler-x"
            style="inline-size: 10rem;"
          />
          
          <!-- 👉 Status filter -->
          <AppSelect
            v-model="selectedStatus"
            placeholder="Select Status"
            :items="[
              { title: 'Active', value: 'verified' },
              { title: 'Pending', value: 'unverified' }
            ]"
            clearable
            clear-icon="tabler-x"
            style="inline-size: 10rem;"
          />
          
          <!-- 👉 Add user button -->
          <VBtn
            prepend-icon="tabler-plus"
            @click="isUserInfoEditDialogVisible = true"
          >
            Add User
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- SECTION datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :items-per-page-options="[
          { value: 10, title: '10' },
          { value: 20, title: '20' },
          { value: 50, title: '50' },
          { value: -1, title: '$vuetify.dataFooter.itemsPerPageAll' },
        ]"
        :items="users"
        :items-length="totalUsers"
        :headers="headers"
        class="text-no-wrap"
        show-select
        :loading="isLoading"
        @update:options="updateOptions"
      >
        <!-- User -->
        <template #item.user="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              size="34"
              :variant="!item.avatar ? 'tonal' : undefined"
              :color="!item.avatar ? resolveUserRoleVariant(item.role).color : undefined"
            >
              <VImg
                v-if="item.avatar"
                :src="item.avatar"
              />
              <span v-else>{{ avatarText(item.fullName) }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <h6 class="text-base">
                <RouterLink
                  :to="{ name: 'apps-user-view-id', params: { id: item.id } }"
                  class="font-weight-medium text-link"
                >
                  {{ item.fullName }}
                </RouterLink>
              </h6>
              <div class="text-sm">
                {{ item.email }}
              </div>
            </div>
          </div>
        </template>

        <!-- 👉 Role -->
        <template #item.role="{ item }">
          <div class="d-flex align-center gap-x-2">
            <VIcon
              :size="22"
              :icon="resolveUserRoleVariant(item.role).icon"
              :color="resolveUserRoleVariant(item.role).color"
            />

            <div class="text-capitalize text-high-emphasis text-body-1">
              {{ item.role }}
            </div>
          </div>
        </template>

        <!-- Status -->
        <template #item.status="{ item }">
          <VChip
            :color="resolveUserStatusVariant(item.status)"
            size="small"
            label
            class="text-capitalize"
            @click="toggleUserStatus(item.id)"
          >
            {{ item.status }}
          </VChip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <IconBtn @click="deleteUser(item.id)">
            <VIcon icon="tabler-trash" />
          </IconBtn>

          <IconBtn>
            <VIcon icon="tabler-eye" />
          </IconBtn>

          <VBtn
            icon
            variant="text"
            color="medium-emphasis"
          >
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList>
                <VListItem :to="{ name: 'apps-user-view-id', params: { id: item.id } }">
                  <template #prepend>
                    <VIcon icon="tabler-eye" />
                  </template>

                  <VListItemTitle>View</VListItemTitle>
                </VListItem>

                <VListItem link>
                  <template #prepend>
                    <VIcon icon="tabler-pencil" />
                  </template>
                  <VListItemTitle>Edit</VListItemTitle>
                </VListItem>

                <VListItem @click="deleteUser(item.id)">
                  <template #prepend>
                    <VIcon icon="tabler-trash" />
                  </template>
                  <VListItemTitle>Delete</VListItemTitle>
                </VListItem>
              </VList>
            </VMenu>
          </VBtn>
        </template>

        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="totalUsers"
          />
        </template>
      </VDataTableServer>
      <!-- SECTION -->
    </VCard>

    <!-- 👉 Add New User -->
    <UserInfoEditDialog
      v-model:is-dialog-visible="isUserInfoEditDialogVisible"
      :roles="roles"
      @submit="addNewUser"
    />
  </section>
</template>

<style lang="scss">
.text-capitalize {
  text-transform: capitalize;
}

.user-list-name:not(:hover) {
  color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
}
</style> 
