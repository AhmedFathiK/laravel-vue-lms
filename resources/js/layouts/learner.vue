<script setup>
import { useConfigStore } from '@core/stores/config'
import { AppContentLayoutNav } from '@layouts/enums'
import { watch } from 'vue'
import { useDisplay } from 'vuetify'

const LearnerLayoutWithHorizontalNav = defineAsyncComponent(() => import('./components/LearnerLayoutWithHorizontalNav.vue'))
const LearnerLayoutWithVerticalNav = defineAsyncComponent(() => import('./components/LearnerLayoutWithVerticalNav.vue'))
const configStore = useConfigStore()

const { mdAndDown } = useDisplay()

if(mdAndDown.value){
  configStore.appContentLayoutNav = 'vertical'
  configStore.isVerticalNavCollapsed = true
}else{
  configStore.appContentLayoutNav = 'horizontal'
}

watch(mdAndDown, () => {
  if(mdAndDown.value){
    configStore.appContentLayoutNav = 'vertical'
    configStore.isVerticalNavCollapsed = true
  }else{
    configStore.appContentLayoutNav = 'horizontal'
    configStore.isVerticalNavCollapsed = false
  }
})

// ℹ️ This will switch to vertical nav when define breakpoint is reached when in horizontal nav layout

// Remove below composable usage if you are not using horizontal nav layout in your app
//switchToVerticalNavOnLtOverlayNavBreakpoint()

const { layoutAttrs, injectSkinClasses } = useSkins()

injectSkinClasses()
</script>

<template>
  <Component
    v-bind="layoutAttrs"
    :is="configStore.appContentLayoutNav === AppContentLayoutNav.Vertical ? LearnerLayoutWithVerticalNav : LearnerLayoutWithHorizontalNav"
  >
    <AppLoadingIndicator ref="refLoadingIndicator" />

    <RouterView v-slot="{ Component }">
      <Suspense
        :timeout="0"
        @fallback="isFallbackStateActive = true"
        @resolve="isFallbackStateActive = false"
      >
        <Component :is="Component" />
      </Suspense>
    </RouterView>
  </Component>
</template>

<style lang="scss">
// As we are using `layouts` plugin we need its styles to be imported
@use "@layouts/styles/default-layout";
</style>
