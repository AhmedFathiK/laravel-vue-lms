<script setup>
import { useActiveCourse } from '@/stores/activeCourse'

const activeCourseStore = useActiveCourse()
</script>

<template>
  <IconBtn
    class="me-2"
    to="/courses/select"
  >
    <VAvatar
      size="26"
      :color="activeCourseStore.activeCourse?.thumbnail ? undefined : 'primary'"
      :variant="activeCourseStore.activeCourse?.thumbnail ? undefined : 'tonal'"
    >
      <VImg
        v-if="activeCourseStore.activeCourse?.thumbnail"
        :src="activeCourseStore.activeCourse.thumbnail"
        :alt="activeCourseStore.activeCourse.title"
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
      {{ activeCourseStore.activeCourse ? 'Switch Course' : 'Select Course' }}
    </VTooltip>
  </IconBtn>
</template>
