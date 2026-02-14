<script setup>
defineOptions({
  name: 'AppTextField',
  inheritAttrs: false,
})

const elementId = computed(() => {
  const attrs = useAttrs()
  const _elementIdToken = attrs.id
  const _id = useId()
  
  return _elementIdToken ? `app-text-field-${ _elementIdToken }` : _id
})

const label = computed(() => useAttrs().label)

const attrs = useAttrs()

const onKeydown = e => {
  if (attrs.type === 'number' && (e.key === 'e' || e.key === 'E'))
    e.preventDefault()
}

const onPaste = e => {
  if (attrs.type === 'number') {
    const pastedData = (e.clipboardData || window.clipboardData).getData('text')
    if (/e/i.test(pastedData))
      e.preventDefault()
  }
}
</script>

<template>
  <div
    class="app-text-field flex-grow-1"
    :class="$attrs.class"
  >
    <VLabel
      v-if="label"
      :for="elementId"
      class="mb-1 text-body-2 text-wrap"
      style="line-height: 15px;"
      :text="label"
    />
    <VTextField
      v-bind="{
        ...$attrs,
        class: null,
        label: undefined,
        variant: 'outlined',
        id: elementId,
      }"
      @keydown="onKeydown"
      @paste="onPaste"
    >
      <template
        v-for="(_, name) in $slots"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="slotProps || {}"
        />
      </template>
    </VTextField>
  </div>
</template>
