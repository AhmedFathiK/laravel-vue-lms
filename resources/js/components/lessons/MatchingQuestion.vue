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

const systemSoundsplyr = ref()
const answeringSoundTrack = ref()

const currentSlide = ref(JSON.parse(JSON.stringify(props.data)))
const questionAnswered = ref(false)

const columnA =computed(()=> {
  let items = []
  for (let index = 0; index < currentSlide.value.question.answers.length; index++) {
    const item = currentSlide.value.question.answers[index]

    items.push({
      text: item.column_a,
      matchIndex: index,
    })
  }

  items = shuffleArray(items)

  for (let index = 0; index < items.length; index++) {
    items[index].indexInColumn = index
  }
  
  return items
})

const columnB =computed(()=> {
  let items = []
  for (let index = 0; index < currentSlide.value.question.answers.length; index++) {
    const item = currentSlide.value.question.answers[index]

    items.push({
      text: item.column_b,
      matchIndex: index,
    })
  }
  items = shuffleArray(items)
  
  for (let index = 0; index < items.length; index++) {
    items[index].indexInColumn = index
  }
  
  return items
})

function shuffleArray(array) {
  //Iterate over the array from the last element to the first.
  //For each element, generate a random index between 0 and the current index.
  //Swap the current element with the element at the random index.

  for (let i = array.length - 1; i > 0; i--) {
    // Generate a random index between 0 and i (inclusive)
    const j = Math.floor(Math.random() * (i + 1));
    
    // Swap elements array[i] and array[j]
    [array[i], array[j]] = [array[j], array[i]]
  }
  
  return array
}


const columnASelectedItems = ref()
const columnBSelectedItems = ref()
const rightMatchedItemsIndices = ref([])
const wrongMatchedItemsIndices = ref([])

const itemA = computed(()=> {
  return columnASelectedItems.value && columnASelectedItems.value.length > 0 ? columnASelectedItems.value[0] : null
})

const itemB = computed(()=> {
  return columnBSelectedItems.value && columnBSelectedItems.value.length > 0 ? columnBSelectedItems.value[0] : null
})

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

const columnAShakedItemIndex = ref(null)
const columnBShakedItemIndex = ref(null)

const shakeWrongItems = () => {
  if (itemA.value) {
    columnAShakedItemIndex.value = itemA.value.indexInColumn
  }
  if (itemB.value) {
    columnBShakedItemIndex.value = itemB.value.indexInColumn
  }

  // After the animation duration, remove the shake class and reset selections
  setTimeout(() => {
    columnAShakedItemIndex.value = null
    columnBShakedItemIndex.value = null

    //unselect items
    columnASelectedItems.value = null
    columnBSelectedItems.value = null
  }, 820) // Should match the animation duration in ms
}

const playAnsweringSound = () => {
  
  let rightAnswersCount = rightMatchedItemsIndices.value.length
  let wrongAnswersCount = wrongMatchedItemsIndices.value.length
  let itemsCount = currentSlide.value.question.answers.length

  //this condition to prevent playing sound on last items as it'll be played by lesson drawer
  if(rightAnswersCount + wrongAnswersCount < itemsCount){
    const player = systemSoundsplyr.value

    player.load()
    player.play()
  }
}

const checkAnswers = () => {
  if(['video', 'image_with_audio'].includes(currentSlide.value.question.media_type)){
    plyr.value.player.stop()
  }

  if (itemA.value !== null && itemB.value !== null) {
    if(currentSlide.value.prevent_wrong_answers){
      if(itemA.value.matchIndex == itemB.value.matchIndex){
        rightMatchedItemsIndices.value.push(itemA.value.matchIndex)
        columnASelectedItems.value = null
        columnBSelectedItems.value = null
        answeringSoundTrack.value ='/audio/system-sounds/right.mp3'

      }else{
        answeringSoundTrack.value ='/audio/system-sounds/wrong.mp3'
        shakeWrongItems()
      }
    }else{
      if(itemA.value.matchIndex == itemB.value.matchIndex){
        rightMatchedItemsIndices.value.push(itemA.value.matchIndex)
        answeringSoundTrack.value ='/audio/system-sounds/right.mp3'
      }else {
        answeringSoundTrack.value ='/audio/system-sounds/wrong.mp3'
        wrongMatchedItemsIndices.value.push(itemA.value.matchIndex)
        wrongMatchedItemsIndices.value.push(itemB.value.matchIndex)
      }
    }      
    
    //this is to unselect current item except when wrong items are not allowed as there will be 
    //shaking animation that still needs the items selected. 
    //After the animation finishes, it will un select current items
    if(!currentSlide.value.prevent_wrong_answers){ 
      columnASelectedItems.value = null
      columnBSelectedItems.value = null
    }
    playAnsweringSound()
  }
  
  
  checkIfQuestionGotAnswered()
}


const checkIfQuestionGotAnswered = () => {
  let rightAnswersCount = rightMatchedItemsIndices.value.length
  let wrongAnswersCount = wrongMatchedItemsIndices.value.length
  let itemsCount = currentSlide.value.question.answers.length
  if(rightAnswersCount + wrongAnswersCount >= itemsCount){
    questionAnswered.value = true
    if(wrongAnswersCount > 0){
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

const resetComponent = () => {
  wrongMatchedItemsIndices.value = []
  rightMatchedItemsIndices.value = []
  questionAnswered.value = false
  currentSlide.value = JSON.parse(JSON.stringify(props.data))
}

watch([itemA, itemB], () =>{
  checkAnswers()
})

watch(() => props.data, () => {
  resetComponent()
}, { immediate: true })

onMounted(() => {
  //resetComponent()
  if(['video', 'image_with_audio'].includes(currentSlide.value.question.media_type)){
    plyr.value.player.play()
  }
})
</script>

<template>
  <div>
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
      {{ currentSlide.question.text }}
    </div>
    <div class="d-flex justify-center mt-5">
      <VCard
        max-width="300"
        class="mx-5 px-2"
      >
        <VList
          v-model:selected="columnASelectedItems"
          class="px-5"
        >
          <VListItem
            v-for="(item, i) in columnA"
            :key="i"
            :value="item"
            color="primary"
            rounded="xl"
            class="my-2"
            :class="[{'correct':rightMatchedItemsIndices.includes(item.matchIndex), 'wrong':wrongMatchedItemsIndices.includes(item.matchIndex) || item.indexInColumn === columnAShakedItemIndex, 'shake': item.indexInColumn === columnAShakedItemIndex}]"
          >
            <VListItemTitle>
              {{ item.text }}
            </VListItemTitle>
          </VListItem>
        </VList>
      </VCard>
      <VCard
        max-width="300"
        class="mx-5 px-2"
      >
        <VList
          v-model:selected="columnBSelectedItems"
          class="px-5"
        >
          <VListItem
            v-for="(item, i) in columnB"
            :key="i"
            :value="item"
            color="primary"
            rounded="xl"
            class="my-2"
            :class="[{'correct':rightMatchedItemsIndices.includes(item.matchIndex), 'wrong':wrongMatchedItemsIndices.includes(item.matchIndex) || item.indexInColumn === columnBShakedItemIndex, 'shake': item.indexInColumn === columnBShakedItemIndex}]"
          >
            <VListItemTitle>
              {{ item.text }}
            </VListItemTitle>
          </VListItem>
        </VList>
      </VCard>
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
.correct {
  pointer-events: none;
  color: white;
  background-color: rgba(var(--v-theme-success), 80%);
}
.wrong {
  pointer-events: none;
  color: white !important;
  background-color: rgba(var(--v-theme-error), 80%);
}

.shake {
  animation: shake 0.82s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
  transform: translate3d(0, 0, 0);
}

@keyframes shake {
  10%,
  90% {
    transform: translate3d(-1px, 0, 0);
  }

  20%,
  80% {
    transform: translate3d(2px, 0, 0);
  }

  30%,
  50%,
  70% {
    transform: translate3d(-4px, 0, 0);
  }

  40%,
  60% {
    transform: translate3d(4px, 0, 0);
  }
}
</style>
