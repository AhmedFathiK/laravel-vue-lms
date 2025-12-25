<template>
  <span
    ref="el"
    class="drag-handle d-flex gap-1 justify-center align-center"
  >
    <slot>
      <IconBtn variant="plain">
        <VIcon icon="tabler-menu-2" />
      </IconBtn>
    </slot>
  </span>
</template>

<script setup>
import { onMounted, ref } from 'vue'

const el = ref(null)

onMounted(() => {
  if (!el.value)
    return

  el.value.sortableHandle = true
})
</script>

<style>
.drag-handle .v-btn {
  cursor: grab;
}
</style>
