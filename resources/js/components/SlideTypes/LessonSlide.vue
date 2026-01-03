<script setup>
import { ref } from 'vue'
import ExplanationSlide from './ExplanationSlide.vue'
import FillBlankSlide from './FillBlankSlide.vue'
import MCQSlide from './MCQSlide.vue'
import MatchingSlide from './MatchingSlide.vue'
import ReorderingSlide from './ReorderingSlide.vue'
import TermSlide from './TermSlide.vue'

const props = defineProps({
  slide: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['answered', 'completed'])
const activeSlideRef = ref(null)

const handleAnswered = result => {
  emit('answered', result)
}

const handleCompleted = () => {
  emit('completed')
}

const submitAnswer = () => {
  if (activeSlideRef.value && activeSlideRef.value.submitAnswer) {
    activeSlideRef.value.submitAnswer()
  }
}

defineExpose({ submitAnswer })
</script>

<template>
  <div class="lesson-slide-wrapper">
    <!-- Non-Question Types -->
    <ExplanationSlide
      v-if="slide.type === 'explanation'"
      :key="slide.id"
      :slide="slide"
      @completed="handleCompleted"
    />
    
    <TermSlide
      v-else-if="slide.type === 'term'"
      :key="`term-${slide.id}`"
      :slide="slide"
      @completed="handleCompleted"
    />

    <!-- Question Types -->
    <template v-else-if="slide.question">
      <MCQSlide 
        v-if="slide.question.type === 'mcq'" 
        :key="slide.id"
        ref="activeSlideRef"
        :question="slide.question"
        @answered="handleAnswered"
      />
      
      <MatchingSlide
        v-else-if="slide.question.type === 'matching'"
        :key="`matching-${slide.id}`"
        ref="activeSlideRef"
        :question="slide.question"
        @answered="handleAnswered"
      />
      
      <ReorderingSlide
        v-else-if="slide.question.type === 'reordering'"
        :key="`reordering-${slide.id}`"
        ref="activeSlideRef"
        :question="slide.question"
        @answered="handleAnswered"
      />
      
      <FillBlankSlide
        v-else-if="['fill_blank', 'fill_blank_choices'].includes(slide.question.type)"
        :key="`fill_blank-${slide.id}`"
        ref="activeSlideRef"
        :question="slide.question"
        :type="slide.question.type"
        @answered="handleAnswered"
      />
      
      <div
        v-else
        class="text-center text-error pa-4 border-error border rounded"
      >
        <div class="text-h6">
          Unsupported Question Type
        </div>
        <div>Type: {{ slide.question.type }}</div>
      </div>
    </template>
    
    <div
      v-else
      class="text-center text-error pa-4 border-error border rounded"
    >
      <div class="text-h6">
        Unknown Slide Type
      </div>
      <div>Type: {{ slide.type }}</div>
    </div>
  </div>
</template>
