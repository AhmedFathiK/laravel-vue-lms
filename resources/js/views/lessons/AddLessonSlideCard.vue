<script setup>
import addSlideImage from '@images/pages/pose-fs-9.png'
import videoPlaceholder from '@images/pages/default-course-image.png'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

const emit = defineEmits([
  "click:add",
])

const addItem = () => {
  emit("click:add")
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard class="slide-card">
        <VRow no-gutters>
          <VCol cols="12">
            <VCardText
              class="slide-content d-flex flex-column justify-center align-center"
              style="height:480px"
            >
              <VRow> 
                <VCol cols="12">
                  <div class="text-h3 text-center">
                    Add a New Slide
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  class="d-flex flex-column align-center justify-center"
                >
                  <div class="slide-img w-50">
                    <VImg
                      cover
                      :src="addSlideImage"
                      aspect-ratio="16/9"
                    /> 
                  </div>
                </VCol>
               
                <VCol cols="12">
                  <VBtn
                    class="w-100"
                    color="warning"
                    @click="addItem"
                  >
                    Add slide
                  </VBtn>
                </VCol>
              </VRow>
            </VCardText>
          </VCol>
        </VRow>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>
.slide-card{
  height: 480px;
  background: rgb(165,218,244);
  background: linear-gradient(90deg, rgba(165,218,244,0.5) 0%, rgba(73,167,218,0.5) 100%);
  cursor: pointer;
  user-select: none;
}
.slide-content{
  height: 480px;

}
</style>
