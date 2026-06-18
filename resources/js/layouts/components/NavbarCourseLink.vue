<script setup>
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
</script>

<template>
  <IconBtn
    class="me-2"
    to="/courses/select"
  >
    <VAvatar
      size="26"
      :color="authStore.user?.activeCourse?.thumbnail ? undefined : 'primary'"
      :variant="authStore.user?.activeCourse?.thumbnail ? undefined : 'tonal'"
    >
      <VImg
        v-if="authStore.user?.activeCourse?.thumbnail"
        :src="authStore.user.activeCourse.thumbnail"
        :alt="authStore.user.activeCourse.title"
        cover
      />
      <VIcon
        v-else
        size="20"
        icon="tabler-books"
      />
    </VAvatar>
    <VTooltip
      activator="parent"
      location="bottom"
    >
      {{ authStore.user?.activeCourse ? 'Switch Course' : 'Select Course' }}
    </VTooltip>
  </IconBtn>
</template>
