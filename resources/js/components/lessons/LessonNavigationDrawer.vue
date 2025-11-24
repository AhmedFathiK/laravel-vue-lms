
<script setup>
import { computed, ref } from "vue"

const props = defineProps({
  drawerData: {
    type: Object,
    required: true,
  },
  isDrawerVisible: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits([
  'nextSlide',
])

const systemSoundsplyr = ref()

const answeringSoundTrack = computed(() => {
  return props.drawerData.answerState === 'wrong' 
    ? '/audio/system-sounds/wrong.mp3' 
    : '/audio/system-sounds/right.mp3'
})




watch(props, () => {
  if(props.isDrawerVisible){
    const player = systemSoundsplyr.value

    if(props.drawerData.answerState ){
      player.load()
      player.play()
    }else{
      player.pause()
      player.currentTime = 0
    }
  }
  
})



const nextSlide = () => {
  emit("nextSlide")
}

const test = ref()
</script>

<template>
  <VNavigationDrawer
    :model-value="props.isDrawerVisible"
    permanent 
    location="bottom"
    class="footer-drawer px-3 px-md-15 py-3"
  >
    <div class="d-none">
      <audio
        ref="systemSoundsplyr"
        controlslist="nodownload"
      >

        <source
          :src="answeringSoundTrack"
          type="audio/mp3"
        >
      </audio>
    </div>
    <VRow>
      <VCol
        cols="12"
        sm="4"
        class="answer-comment-title d-flex flex-column flex-md-row align-center justify-end justify-sm-center"
      >
        <VIcon
          v-if="drawerData.answerState == 'right'"
          icon="tabler-circle-check"
          size="4rem"
          color="success"
        />
        <VIcon
          v-if="drawerData.answerState == 'wrong'"
          icon="tabler-playstation-x"
          size="4rem"
          color="error"
        />
        <span
          v-if="drawerData.answerState"
          class="text-h5 text-sm-h4 text-md-h3"
        >
          {{ drawerData.answerState == 'right' ? 'Correct answer' : ' Wrong answer' }}
        </span>
      </VCol>
      <VCol
        cols="12"
        sm="4"
      >
        <div
          v-if="drawerData.rightAnswer"
          class="correct_answer_wrapper mb-2 rounded"
        >
          <div class="d-flex d-flex justify-start align-center mb-2">
            <AppIconAudioPlayer
              v-if="drawerData.rightAnswerAudio"
              size="1.5rem"
              :sources="[{link: drawerData.rightAnswerAudio, type: 'audio/mp3'}]"
            />
            <span>{{ drawerData.rightAnswer }}</span>
          </div>
          <span>
            {{ drawerData.rightAnswerMeaning }}
          </span>
        </div> 
        <div v-html="drawerData.answerState == 'right' ? drawerData.correctAnswerComment : drawerData.wrongAnswerComment" />
      </VCol>
      <VCol
        cols="12"
        sm="4"
        class="d-flex align-center justify-center"
      >
        <VBtn
          class="rounded"
          size="x-large"
          @click="nextSlide"
        >
          Continue
        </VBtn>
      </VCol>
    </VRow>
  </VNavigationDrawer>
</template>

<style scoped>
.footer-drawer {
  height: auto !important;
}

.answer-comment-title{
  font-size: 2rem;
  display: flex;
  align-items: center;
  white-space: nowrap;
}

.correct_answer_wrapper{
  padding: 10px;
  background-color: rgba(var(--v-theme-primary), 0.3);
  
}
</style>
