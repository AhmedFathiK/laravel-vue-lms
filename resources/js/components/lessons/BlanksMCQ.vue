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

const question = computed(() => {
  return currentSlide.value.question.text.split('[...]')
})

const blanksCount = computed(() => {
  //length is always +1 because of the separator split, so we deduct 1 to get blanks num
  return question.value.length - 1 
})

const questionAnswered = ref(false)

const chosenAnswers = ref([])
const dataChosenChoices = ref([])//imitates currentSlide choices answers number with values true or false to determine the chosen choices

const choose = (choice, index) => {
  chosenAnswers.value.push({
    text: choice.text,
    choiceIndex: index,
  })
  dataChosenChoices.value[index] = true
  if(chosenAnswers.value.length == blanksCount.value){
    questionAnswered.value = true
    checkAnswers()
  }
}

const unchoose = (choiceIndex, chosenAnswerIndex) => {
  chosenAnswers.value.splice(chosenAnswerIndex, 1)
  dataChosenChoices.value[choiceIndex] = false
}

const checkAnswers = () => {
  if(['video', 'image_with_audio'].includes(currentSlide.value.question.media_type)){
    plyr.value.player.stop()
  }

  let correctAnswers = 0
  for (const key in chosenAnswers.value) {

    const answer = chosenAnswers.value[key]

    const choice = currentSlide.value.question.answers[answer.choiceIndex]


    if  (key == choice.blank_index){
      answer.correct = true
      correctAnswers++
    }else{
      answer.correct = false
    }

    if(key == blanksCount.value - 1){
      
      if(correctAnswers != blanksCount.value){
        updateDrawerData({
          rightAnswer: currentSlide.value.question.right_answer,
          rightAnswerMeaning: currentSlide.value.question.right_answer_meaning,
          rightAnswerAudio: currentSlide.value.question.right_answer_audio,
          answerState: 'wrong',
          correctAnswerComment: currentSlide.value.question.correct_answer_comment,
          wrongAnswerComment: currentSlide.value.question.wrong_answer_comment,
        })
      }else{
        currentSlide.value.completed = true
        
        updateDrawerData({
          rightAnswer: currentSlide.value.question.right_answer,
          rightAnswerMeaning: currentSlide.value.question.right_answer_meaning,
          rightAnswerAudio: currentSlide.value.question.right_answer_audio,
          answerState: 'right',
          correctAnswerComment: currentSlide.value.question.correct_answer_comment,
          wrongAnswerComment: currentSlide.value.question.wrong_answer_comment,
        })
      }
      emit("update:data", currentSlide.value)

    }
  }
}

const determineBlankColor = chosenAnswer => {
  if(chosenAnswer && chosenAnswer.correct === true){
    return 'success'
  }else if (chosenAnswer && chosenAnswer.correct === false){
    return 'error'
  }else{
    return ''
  }
}

const resetComponent = () => {
  chosenAnswers.value=[]
  questionAnswered.value = false

  dataChosenChoices.value.forEach(choice => {
    return false
  })

  currentSlide.value = JSON.parse(JSON.stringify(props.data))

  if(currentSlide.value){
    for (let index = 0; index < currentSlide.value.question.answers.length; index++) {
      dataChosenChoices.value[index] = false  
    }
  }
  
}

watch(() => props.data, () => {
  resetComponent()
}, { immediate: true })

onMounted(() => {
  resetComponent()
  if(['video', 'image_with_audio'].includes(currentSlide.value.question.media_type)){
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
        v-if="['image','image_with_audio'].includes(currentSlide.question.media_type)"
        class="question-img-wrapper"
      >
        <VImg
          cover
          :src="currentSlide.question.image"
        />
      </div>
      <div
        v-if="currentSlide.question.media_type == 'video'"
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
        v-if="['audio','image_with_audio'].includes(currentSlide.question.media_type)"
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
      <template
        v-for="(element, index) in question"
        :key="index"
      >
        <span>{{ element }}</span>
        <VBtn
          v-if="index !== question.length - 1"
          class="choice-placeholder mx-3"
          variant="tonal"
          :color="determineBlankColor(chosenAnswers[index])"
          :disabled="questionAnswered || !chosenAnswers[index]"
          @click="unchoose(chosenAnswers[index].choiceIndex, index)"
        >
          {{ chosenAnswers[index] ? chosenAnswers[index].text : '' }}
        </VBtn>
      </template>
    </div>
    <div class="choices mt-5 text-center">
      <VBtn
        v-for="(choice, index) in currentSlide.question.answers"
        :key="index"
        :color="dataChosenChoices[index] ? 'primary' : 'black'"
        :variant="dataChosenChoices[index] ? 'tonal' : 'outlined'"
        :class="dataChosenChoices[index] || questionAnswered ? 'mx-2 unclickable' : 'mx-2'"
        @click="choose(choice, index)"
      >
        <template v-if="!dataChosenChoices[index]">
          {{ choice.text }}
        </template>
      </VBtn>
    </div>
  </div>
</template>

<style scoped>
.question-media-wrapper{
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

.choice-placeholder{
  min-height:1em ;
  min-width: 3.5em;
  border-bottom: 2px dashed #7367F0;
  text-align: center;
}
</style>
