<template>
  <span class="drag-handle d-flex gap-1 justify-center align-center">
    <slot>
      <IconBtn variant="plain">
        <VIcon icon="tabler-menu-2" />
      </IconBtn>
    </slot>
  </span>
</template>
  
<script>
// Makeshift handle because the directive doesnt work with vitepress
export default {
  mounted() {
    this.$el.sortableHandle = true
  },
}
</script>

<style>
.drag-handle .v-btn {
    cursor: grab;
}
</style>