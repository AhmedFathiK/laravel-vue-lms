<script setup>
import { computed, onMounted, ref, watch } from "vue"

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
})


const emit = defineEmits([
  'update:drawerContent',
  'update:data',
]) 

const plyr = ref()

const playerOptions = ref( {
  controls: ['play', 'progress', 'settings', 'fullscreen'],
  autoplay: true,
  preload: 'auto',
  width: '600px', // adjust width as needed
  height: '40px', // set a small height for an audio player
  fluid: false,
  responsive: true,
  muted: false, // set to true if you want the audio to start muted
})

const currentSlide = ref(JSON.parse(JSON.stringify(props.data)))

const updateDrawerData = data => {
  emit('update:drawerContent', {
    rightAnswer: data.rightAnswer,
    rightAnswerMeaning: data.rightAnswerMeaning,
    rightAnswerAudio: data.rightAnswerAudio,
    answerState: data.answerState,
    correctAnswerComment: data.correctAnswerComment,
    wrongAnswerComment: data.wrongAnswerComment,
  })
}

const correctAnswersCount = computed(() => {
  let counter = 0
  currentSlide.value.question.answers.forEach(choice => {
    if(choice.correct){
      counter++
    }
  })
  
  return counter
})

const questionAnswered = ref(false)

const chosenAnswers = ref([])

const handleChoosing = choice => {
  if(choice.chosen){
    choice.chosen = false
    chosenAnswers.value.splice(choice.chosenAnswerIndex, 1)
    choice.chosenAnswerIndex = null
  }else{    
    chosenAnswers.value.push(choice)
    choice.chosen = true
    choice.chosenAnswerIndex = chosenAnswers.value.length-1
  }
  
  if(chosenAnswers.value.length == correctAnswersCount.value){
    questionAnswered.value = true
    checkAnswers()
  }
}


const checkAnswers = () => {
  if(['video', 'image_with_audio'].includes(currentSlide.value.question.mediaType)){
    plyr.value.player.stop()
  }
  let correctAnswers = 0
  for (const key in chosenAnswers.value) {
    const answer = chosenAnswers.value[key]

    if  (answer.correct){
      correctAnswers++
    }
  }

      
  if(correctAnswers != correctAnswersCount.value){
    updateDrawerData({
      rightAnswer: currentSlide.value.question.rightAnswer,
      rightAnswerMeaning: currentSlide.value.question.rightAnswerMeaning,
      rightAnswerAudio: currentSlide.value.question.rightAnswerAudio,
      answerState: 'wrong',
      correctAnswerComment: currentSlide.value.question.correctAnswerComment,
      wrongAnswerComment: currentSlide.value.question.wrongAnswerComment,
    })
  }else{
    currentSlide.value.completed = true
        
    updateDrawerData({
      rightAnswer: currentSlide.value.question.rightAnswer,
      rightAnswerMeaning: currentSlide.value.question.rightAnswerMeaning,
      rightAnswerAudio: currentSlide.value.question.rightAnswerAudio,
      answerState: 'right',
      correctAnswerComment: currentSlide.value.question.correctAnswerComment,
      wrongAnswerComment: currentSlide.value.question.wrongAnswerComment,
    })
  }
  emit("update:data", currentSlide.value)

  
}

const determineAnswerColor = chosenAnswer => {
  if(questionAnswered.value && chosenAnswer.chosen ){
    return chosenAnswer.correct === true ? 'success' : 'error'
  }else if(!questionAnswered.value && chosenAnswer.chosen){
    return 'primary'
  }else{
    return ''
  }
  
}

const determineChoiceBtnVariant = choice => {
  if(!questionAnswered.value && choice.chosen){
    return 'tonal'
  }else if(questionAnswered.value && choice.chosen) {
    return 'flat'
  }else{
    return 'outlined'
  }
}

const resetComponent = () => {
  chosenAnswers.value=[]
  questionAnswered.value = false

  currentSlide.value = JSON.parse(JSON.stringify(props.data))

  for (const key in currentSlide.value.question.answers) {
    if (Object.hasOwnProperty.call(currentSlide.value.question.answers, key)) {
      const answer = currentSlide.value.question.answers[key]

      answer.chosen = false
      console.log(answer)
    }
  }
}

watch(() => props.data, () => {
  resetComponent()
}, { immediate: true })

onMounted(() => {
  resetComponent()
  if(['video', 'image_with_audio'].includes(currentSlide.value.question.mediaType)){
    plyr.value.player.play()
  }
})
</script>

<template>
  <div>
    <div class="text-h3 text-center pa-5">
      {{ currentSlide.title ? currentSlide.title : currentSlide.question.title }}
    </div>

    <VCard class="question-media-wrapper">
      <div
        v-if="['image','image_with_audio'].includes(currentSlide.question.mediaType)"
        class="question-img-wrapper"
      >
        <VImg
          cover
          :src="currentSlide.question.image"
        />
      </div>
      <div
        v-if="currentSlide.question.mediaType == 'video'"
        class="question-video-wrapper"
      >
        <VuePlyr
          ref="plyr"
          :options="playerOptions"
        >
          <video
          
            crossorigin
            :data-poster="currentSlide.question.image"
          >
            <source
              :src="currentSlide.question.video"
              type="video/mp4"
            >
          </video>
        </VuePlyr>
      </div>
      
      <VuePlyr
        v-if="['audio','image_with_audio'].includes(currentSlide.question.mediaType)"
        ref="plyr"
        :options="playerOptions"
      >
        <audio
          crossorigin
          playsinline
        >
          <source
            :src="currentSlide.question.audio"
            type="audio/mp3"
          >
        </audio>
      </VuePlyr>
    </VCard>
    <div class="d-flex d-flex justify-center align-center text-h4 mt-3">
      {{ currentSlide.question.text }}
    </div>
    <div class="choices mt-5 text-center">
      <VBtn
        v-for="(choice, index) in currentSlide.question.answers"
        :key="index"
        :color="determineAnswerColor(choice)"
        :variant="determineChoiceBtnVariant(choice)"
        :class="questionAnswered ? 'pa-4 my-2 unclickable' : 'pa-4 my-2'"
        width="100%"
        height="auto"
        @click="!questionAnswered ? handleChoosing(choice): void(0)"
      >
        {{ choice.text }}
      </VBtn>
    </div>
  </div>
</template>

<style scoped>
.question-media-wrapper , .choices{
  margin-left:auto;
  margin-right:auto;
  max-width: 43.75rem;
  min-width: 25.25rem;
  width: 25vw;
}

.question-img-wrapper, .question-video-wrapper{
  position: relative;
  overflow: hidden;
  min-height: 3.125rem;
}
</style>
