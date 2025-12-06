<script setup>
import RoleCards from '@/views/roles/RoleCards.vue'

definePage({
  meta: {
    action: 'view',
    subject: 'roles',
  },
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <h4 class="text-h4 mb-1">
        Roles List
      </h4>
      <p class="text-body-1 mb-0">
        A role provided access to predefined menus and features so that depending on assigned role an administrator can have access to what he need
      </p>
    </VCol>

    <!-- 👉 Roles Cards -->
    <VCol cols="12">
      <RoleCards />
    </VCol>
  </VRow>
</template>
