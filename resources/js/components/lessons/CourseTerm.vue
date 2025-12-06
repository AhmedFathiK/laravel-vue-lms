<script setup>
import { onMounted, watch } from "vue"

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
})


const emit = defineEmits([
  'update:drawerContent',
])

const currentSlide = ref(props.data)

const playerOptions = ref( {
  sources: [{
    src: 'https://www.learningcontainer.com/wp-content/uploads/2020/02/Kalimba.mp3',
    type: 'audio/mp3', // adjust type based on your audio file format
  }],
  controls: ['play', 'progress', 'settings'],
  autoplay: false,
  preload: 'auto',
  width: '600px', // adjust width as needed
  height: '40px', // set a small height for an audio player
  fluid: false,
  responsive: true,
  muted: false, // set to true if you want the audio to start muted
})

const updateDrawerData = () => {
  emit('update:drawerContent', {
    rightAnswer: null,
    rightAnswerMeaning: null,
    rightAnswerAudio: null,
    answerState: null,
    'correctAnswerComment': null,
    'wrongAnswerComment': null,
  })
}

watch(() => props.data, () => {
  updateDrawerData()
})
onMounted(() => {
  updateDrawerData()
})
</script>

<template>
  <div>
    <h3 class="text-center pt-5">
      {{ currentSlide.title }}
    </h3>

    <VCard class="term-media-wrapper mt-5">
      <div
        v-if="['image','image_with_audio'].includes(currentSlide.term.mediaType)"
        class="term-img-wrapper"
      >
        <VImg
          cover
          :src="currentSlide.term.image"
        />
      </div>
      <div
        v-if="currentSlide.term.mediaType == 'video'"
        class="term-video-wrapper"
      >
        <VuePlyr
          ref="plyr"
          :options="playerOptions"
        >
          <video
          
            crossorigin
            :data-poster="currentSlide.term.image"
          >
            <source
              :src="currentSlide.term.video"
              type="video/mp4"
            >
          </video>
        </VuePlyr>
      </div>
      
      <VuePlyr
        v-if="['audio','image_with_audio'].includes(currentSlide.term.mediaType)"
        ref="plyr"
        :options="playerOptions"
      >
        <audio
          crossorigin
          playsinline
        >
          <source
            :src="currentSlide.term.audio"
            type="audio/mp3"
          >
        </audio>
      </VuePlyr>
      <div class="d-flex flex-column align-center justify-center pa-5">
        <span class="term-text text-primary">{{ currentSlide.term.term }}</span>
        <span class="term-meaning text-secondary">{{ currentSlide.term.meaning }}</span>
      </div>  
    </VCard>
    <div
      v-if="currentSlide.term.example"
      class="term-example mt-5"
    >
      <p>Example</p>
      <div class="d-flex d-flex justify-start align-center">
        <AppIconAudioPlayer
          v-if="currentSlide.term.exampleAudio"
          :sources="[{link: 'https://server6.mp3quran.net/thubti/001.mp3', type: 'audio/mp3'}]"
        />
        <span>{{ currentSlide.term.example }}</span>
      </div>
      <span>{{ currentSlide.term.exampleMeaning }}</span>
    </div>
  </div>
</template>

<style scoped>
.term-media-wrapper, .term-example{
  margin-left:auto;
  margin-right:auto;
  max-width: 43.75rem;
  min-width: 25.25rem;
  width: 25vw;
}
.term-img-wrapper{
  position: relative;
  overflow: hidden;
  min-height: 3.125rem;
}
.term-text{
  font-weight: 700;
  text-transform: none;
  font-size: 1.125rem;
  line-height: 1.5em;
  text-align: center;
  text-decoration: unset;
}

.term-meaning{
  font-weight: 700;
  text-transform: none;
  font-size: 1rem;
  line-height: 1.5em;
  text-align: center;
  text-decoration: unset;
}
</style>
