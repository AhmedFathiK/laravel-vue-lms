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
      <div class="d-flex flex-column align-center justify-center pa-5">
        <span
          class="term-text text-primary"
          v-html="currentSlide.text"
        />
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.term-media-wrapper{
  margin-left:auto;
  margin-right:auto;
  max-width: 43.75rem;
  min-width: 25.25rem;
  width: 25vw;
}
.term-text{
  font-weight: 700;
  text-transform: none;
  font-size: 1.125rem;
  line-height: 1.5em;
  text-align: center;
  text-decoration: unset;
}
</style>
