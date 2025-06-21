<script setup>
import api from '@/utils/api'
import { computed, ref, useAttrs, watch } from "vue"
import { useI18n } from "vue-i18n"
import { useToast } from "vue-toastification"

const props = defineProps({
  apiLink: { type: String, required: true },
  apiMethod: { type: String, default: 'get', validator: value => ['get', 'post', 'put', 'patch', 'delete'].includes(value.toLowerCase()) },
  apiRequestData: { type: Object, default: () => ({}) },
  apiSearchKey: { type: String, default: 'search' },
  minimumSearchChars: { type: Number, default: 4 },
  preSelectedItems: { type: Array, default: () => [] },
  normalLabel: { type: Boolean, default: false },
  noDataText: { type: String },
})

const emit = defineEmits(['update:modelValue', 'search', 'item-selected'])

defineOptions({
  name: 'AppServerSideAutocomplete',
  inheritAttrs: false,
})

const toast = useToast()
const { t } = useI18n()

// Component state
const loading = ref(false)
const search = ref('')
const attrs = useAttrs()
const searchResults = ref([])
const fieldHasItemsSelected = ref(false)

// Generate a unique ID for the component
const elementId = computed(() => {
  const _elementIdToken = attrs.id || attrs.label
  
  return _elementIdToken ? `app-autocomplete-${_elementIdToken}-${Math.random().toString(36).slice(2, 7)}` : undefined
})

const label = computed(() => attrs.label)

// Dynamic no data text based on search state
const searchNoDataText = ref(props.noDataText || t(`Please enter ${props.minimumSearchChars} or more characters`))

// Search API function
const searchApi = async () => {
  loading.value = true
  searchNoDataText.value = t("Searching...")
  searchResults.value = []

  try {
    let response
    if (props.apiMethod.toLowerCase() === "get") {
      response = await api.get(props.apiLink, { 
        params: { ...props.apiRequestData, [props.apiSearchKey]: search.value }, 
      })
    } else {
      response = await axios.post(props.apiLink, { 
        ...props.apiRequestData, 
        "_method": props.apiMethod, 
        [props.apiSearchKey]: search.value, 
      })
    }
    
    if (response.data.length === 0) {
      searchNoDataText.value = t("No results found")
    } else {
      searchResults.value = response.data
      emit('search', response.data)
    }
  } catch (error) {
    console.log(error)
    toast.error("Error fetching results.")
    searchNoDataText.value = t(`Please enter ${props.minimumSearchChars} or more characters`)
  } finally {
    loading.value = false
  }
}

// Watch for search changes
watch(search, newValue => {
  const minChars = props.minimumSearchChars
  
  // Ensure search value is a string
  if (newValue === null || newValue === undefined) {
    search.value = ''
    
    return
  }

  if (newValue.length < minChars) {
    searchNoDataText.value = t("Please enter {number} or more characters", { 
      number: (minChars - newValue.length), 
    })
  } else if (newValue.length >= minChars && !fieldHasItemsSelected.value) {
    searchApi()
  }
})

// Handle item selection
const onSelecting = item => {
  if (!('multiple' in attrs)) {
    fieldHasItemsSelected.value = true
  }
  
  emit('item-selected', item)
}

// Clear the field
const clearField = () => {
  const minChars = props.minimumSearchChars

  fieldHasItemsSelected.value = false
  searchResults.value = []
  
  if (minChars > 0) {
    searchNoDataText.value = t("Please enter {number} or more characters", { 
      number: (minChars - (search.value?.length || 0)),
    })
  } else {
    searchApi()
  }
  
  emit('update:modelValue', null)
}

// Watch for prop changes
watch(() => props.apiLink, () => {
  if (fieldHasItemsSelected.value) return
  clearField()
})

watch(() => props.apiRequestData, () => {
  if (fieldHasItemsSelected.value) return
  clearField()
}, { deep: true })

// Initialize with preselected items if provided
watch(() => props.preSelectedItems, newItems => {
  if (newItems && newItems.length) {
    searchResults.value = [...newItems]
  }
}, { immediate: true })
</script>

<template>
  <div
    class="app-autocomplete flex-grow-1"
    :class="$attrs.class"
  >
    <VLabel
      v-if="label && !props.normalLabel"
      :for="elementId"
      class="mb-1 text-body-2 text-high-emphasis"
      :text="label"
    />
    <VAutocomplete
      v-bind="{
        ...$attrs,
        class: null,
        ...(!$attrs.hasOwnProperty('label') && props.normalLabel ? { label: t('Select an Option') } : {}),
        ...($attrs.hasOwnProperty('label') && !props.normalLabel ? { label: undefined } : {}),
        variant: 'outlined',
        id: elementId,
        'no-filter': true,
        clearable: true,
        loading: loading,
        menuProps: {
          maxHeight: '200px',
          contentClass: [
            'app-inner-list',
            'app-autocomplete__content',
            'v-autocomplete__content',
          ],
        },
      }"
      v-model:search="search"
      :items="searchResults"
      :no-data-text="searchNoDataText"
      @click:clear="clearField"
      @update:model-value="onSelecting"
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
    </VAutocomplete>
  </div>
</template>

<style lang="scss">
.app-autocomplete {
  .v-autocomplete__content {
    .v-list-item {
      min-height: 40px;
      padding: 8px 16px;
    }
  }
}
</style> 
