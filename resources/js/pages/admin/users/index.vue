<script setup>
import api from '@/utils/api'
import { handleApiError } from '@/utils/apiErrorHandlers'
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'

definePage({
  meta: {
    action: 'view',
    subject: 'users',
  },
})

const toast = useToast()

// 👉 Store
const searchQuery = ref('')
const selectedRole = ref('')
const selectedStatus = ref('')
const isLoading = ref(false)
const editUser = ref(null)

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref('createdAt')
const orderBy = ref('desc')
const selectedRows = ref([])
const availableRoles = ref([])

// User statistics widgets
const widgetData = ref([
  {
    title: 'Total Users',
    value: '0',
    icon: 'tabler-users',
    iconColor: 'primary',
  },
  {
    title: 'Verified Users',
    value: '0',
    icon: 'tabler-user-check',
    iconColor: 'success',
  },
  {
    title: 'Pending Users',
    value: '0',
    icon: 'tabler-user-exclamation',
    iconColor: 'warning',
  },
  {
    title: 'Admin Users',
    value: '0',
    icon: 'tabler-user-shield',
    iconColor: 'secondary',
  },
])

// Fetch users
const usersData = ref({
  data: [],
  total: 0,
  currentPage: 1,
  perPage: 10,
  lastPage: 1,
})

// Headers for data table
const headers = [
  {
    title: 'User',
    key: 'fullName',
  },
  {
    title: 'Role',
    key: 'role',
  },
  {
    title: 'Email',
    key: 'email',
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

const updateOptions = options => {
  if (options.sortBy?.length) {
    sortBy.value = options.sortBy[0]?.key
    orderBy.value = options.sortBy[0]?.order
  }else{
    // means user cleared sorting
    sortBy.value = null
    orderBy.value = null
  }
  fetchUsers()
}

// Fetch users from API
const fetchUsers = async () => {
  isLoading.value = true
  try {
    const params = {
      page: page.value,
      perPage: itemsPerPage.value,
      search: searchQuery.value || undefined,
      role: selectedRole.value || undefined,
      status: selectedStatus.value || undefined,
      sortBy: sortBy.value || undefined,
      orderBy: orderBy.value || undefined,
    }
    
    const response = await api.get('/admin/users', { params })

    usersData.value = response
    
    // Update widget data with counts
    updateWidgetCounts()
  } catch (error) {
    handleApiError(error, toast, 'Failed to load users')
  } finally {
    isLoading.value = false
  }
}

// Update widget data with user counts
const updateWidgetCounts = () => {
  // Set total users
  widgetData.value[0].value = usersData.value.total.toString()
  
  // Count verified, unverified, and admin users
  let verifiedCount = 0
  let adminCount = 0
  
  usersData.value.data.forEach(user => {
    if (user.emailVerifiedAt) {
      verifiedCount++
    }
    
    if (user.roleNames?.some(role => role.toLowerCase().includes('admin'))) {
      adminCount++
    }
  })
  
  // Set verified users (approximate based on current page)
  widgetData.value[1].value = verifiedCount.toString()
  
  // Set unverified users (approximate based on current page)
  widgetData.value[2].value = (usersData.value.data.length - verifiedCount).toString()
  
  // Set admin users (approximate based on current page)
  widgetData.value[3].value = adminCount.toString()
}

// Fetch available roles
const fetchRoles = async () => {
  try {
    const response = await api.get('/admin/roles')

    availableRoles.value = response.roles
  } catch (error) {
    handleApiError(error, toast, 'Failed to load roles')
  }
}

// Watch for changes to trigger refetch
watch([searchQuery, selectedRole, selectedStatus, page, itemsPerPage], () => {
  fetchUsers()
})

// Computed properties
const users = computed(() => usersData.value.data || [])
const totalUsers = computed(() => usersData.value.total || 0)

// Roles for dropdown
const roles = computed(() => availableRoles.value)

// Status options for dropdown
const status = [
  {
    title: 'Verified',
    value: 'verified',
  },
  {
    title: 'Unverified',
    value: 'unverified',
  },
]

// Helper functions for UI
const resolveUserRoleVariant = role => {
  role = role?.toLowerCase() || ''
  
  if (role.includes('admin'))
    return {
      color: 'error',
      icon: 'tabler-crown',
    }
  if (role.includes('instructor'))
    return {
      color: 'warning',
      icon: 'tabler-device-laptop',
    }
  if (role.includes('content_manager'))
    return {
      color: 'info',
      icon: 'tabler-edit',
    }
  if (role.includes('student'))
    return {
      color: 'success',
      icon: 'tabler-user',
    }
  
  return {
    color: 'primary',
    icon: 'tabler-user',
  }
}

const resolveUserStatusVariant = stat => {
  if (stat)
    return 'success'
  
  return 'warning'
}

const isAddNewUserDrawerVisible = ref(false)
const isConfirmDialogVisible = ref(false)
const userToDelete = ref(null)
const isDeleting = ref(false)

// Show edit user drawer
const showEditUserDrawer = user => {
  editUser.value = user
  isAddNewUserDrawerVisible.value = true
}

// Delete user
const deleteUser = id => {
  userToDelete.value = id
  isConfirmDialogVisible.value = true
}

// Confirm delete user
const onConfirmDelete = async confirmed => {
  if (!confirmed) {
    isConfirmDialogVisible.value = false
    userToDelete.value = null
    
    return
  }

  isDeleting.value = true
  try {
    await api.post(`/admin/users/${userToDelete.value}`, {
      _method: 'DELETE',
    })
    
    toast.success('User deleted successfully')
    isConfirmDialogVisible.value = false
    userToDelete.value = null
    fetchUsers()
  } catch (error) {
    handleApiError(error, toast, 'Failed to delete user')
  } finally {
    isDeleting.value = false
  }
}

// Toggle user status
const toggleUserStatus = async user => {
  try {
    await api.post(`/admin/users/${user.id}/toggle-status`, {
      isActive: !user.emailVerifiedAt,
    })
    toast.success('User status updated successfully')
    fetchUsers()
  } catch (error) {
    handleApiError(error, toast, 'Failed to update user status')
  }
}

// Fetch data on component mount
onMounted(() => {
  fetchUsers()
  fetchRoles()
})
</script>

<template>
  <section>
    <!-- 👉 Widgets -->
    <div class="d-flex mb-6">
      <VRow>
        <template
          v-for="(data, id) in widgetData"
          :key="id"
        >
          <VCol
            cols="12"
            md="3"
            sm="6"
          >
            <VCard>
              <VCardText>
                <div class="d-flex justify-space-between">
                  <div class="d-flex flex-column gap-y-1">
                    <div class="text-body-1 text-high-emphasis">
                      {{ data.title }}
                    </div>
                    <div class="d-flex gap-x-2 align-center">
                      <h4 class="text-h4">
                        {{ data.value }}
                      </h4>
                    </div>
                  </div>
                  <VAvatar
                    :color="data.iconColor"
                    variant="tonal"
                    rounded
                    size="42"
                  >
                    <VIcon
                      :icon="data.icon"
                      size="26"
                    />
                  </VAvatar>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </template>
      </VRow>
    </div>

    <VCard class="mb-6">
      <VCardItem class="pb-4">
        <VCardTitle>Filters</VCardTitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <!-- 👉 Select Role -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedRole"
              placeholder="Select Role"
              :items="roles"
              item-title="name"
              item-value="name"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          
          <!-- 👉 Select Status -->
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedStatus"
              placeholder="Select Status"
              :items="status"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardText class="d-flex flex-wrap gap-4">
        <div class="me-3 d-flex gap-3">
          <AppSelect
            :model-value="itemsPerPage"
            :items="[
              { value: 10, title: '10' },
              { value: 25, title: '25' },
              { value: 50, title: '50' },
              { value: 100, title: '100' },
            ]"
            style="inline-size: 6.25rem;"
            @update:model-value="itemsPerPage = parseInt($event, 10)"
          />
        </div>
        <VSpacer />

        <div class="app-user-search-filter d-flex align-center flex-wrap gap-4">
          <!-- 👉 Search  -->
          <div style="inline-size: 15.625rem;">
            <AppTextField
              v-model="searchQuery"
              placeholder="Search User"
            />
          </div>

          <!-- 👉 Add user button -->
          <VBtn
            prepend-icon="tabler-plus"
            @click="isAddNewUserDrawerVisible = true; editUser = null"
          >
            Add New User
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <!-- SECTION datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="users"
        :headers="headers"
        :items-length="totalUsers"
        :loading="isLoading"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- User -->
        <template #[`item.user`]="{ item }">
          <div class="d-flex align-center gap-x-4">
            <VAvatar
              size="34"
              :color="resolveUserRoleVariant(item.roleNames?.[0]).color"
              variant="tonal"
            >
              <span>{{ (item.fullName || '').charAt(0).toUpperCase() }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <h6 class="text-base">
                {{ item.fullName }}
              </h6>
              <div class="text-sm">
                {{ item.email }}
              </div>
            </div>
          </div>
        </template>

        <!-- 👉 Role -->
        <template #[`item.role`]="{ item }">
          <div class="d-flex align-center gap-x-2">
            <VIcon
              :size="22"
              :icon="resolveUserRoleVariant(item.roleNames?.[0]).icon"
              :color="resolveUserRoleVariant(item.roleNames?.[0]).color"
            />

            <div class="text-capitalize text-high-emphasis text-body-1">
              {{ item.roleNames?.join(', ') || 'No Role' }}
            </div>
          </div>
        </template>

        <!-- Email -->
        <template #[`item.email`]="{ item }">
          <div class="text-body-1 text-high-emphasis">
            {{ item.email }}
          </div>
        </template>

        <!-- Status -->
        <template #[`item.status`]="{ item }">
          <VChip
            :color="resolveUserStatusVariant(item.emailVerifiedAt)"
            size="small"
            label
            class="text-capitalize"
          >
            {{ item.emailVerifiedAt ? 'Verified' : 'Unverified' }}
          </VChip>
        </template>

        <!-- Actions -->
        <template #[`item.actions`]="{ item }">
          <IconBtn @click="deleteUser(item.id)">
            <VIcon icon="tabler-trash" />
          </IconBtn>

          <IconBtn @click="showEditUserDrawer(item)">
            <VIcon icon="tabler-edit" />
          </IconBtn>

          <IconBtn @click="toggleUserStatus(item)">
            <VIcon :icon="item.emailVerifiedAt ? 'tabler-shield-x' : 'tabler-shield-check'" />
          </IconBtn>
        </template>

        <!-- pagination -->
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
    
    <!-- 👉 User Form Dialog -->
    <AddEditUserDialog
      v-model:is-dialog-visible="isAddNewUserDrawerVisible"
      :user-data="editUser"
      :dialog-mode="editUser ? 'edit' : 'add'"
      :roles="roles"
      @refresh="fetchUsers"
    />

    <ConfirmDialog
      v-model:is-dialog-visible="isConfirmDialogVisible"
      confirmation-question="Are you sure you want to delete this user?"
      confirm-msg="User deleted successfully"
      :loading="isDeleting"
      @confirm="onConfirmDelete"
    />
  </section>

  <ConfirmDialog
    v-model:is-dialog-visible="isConfirmDialogVisible"
    confirmation-question="Are you sure you want to delete this user?"
    confirm-msg="User deleted successfully"
    :loading="isDeleting"
    @confirm="onConfirmDelete"
  />
</template>
