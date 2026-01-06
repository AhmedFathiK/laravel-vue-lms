<script setup>
import ExplanationSlide from '@/components/SlideTypes/ExplanationSlide.vue'
import FillBlankChoicesSlide from '@/components/SlideTypes/FillBlankChoicesSlide.vue'
import FillBlankSlide from '@/components/SlideTypes/FillBlankSlide.vue'
import MatchingSlide from '@/components/SlideTypes/MatchingSlide.vue'
import MCQSlide from '@/components/SlideTypes/MCQSlide.vue'
import ReorderingSlide from '@/components/SlideTypes/ReorderingSlide.vue'
import TermSlide from '@/components/SlideTypes/TermSlide.vue'
import { ref } from 'vue'

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
        v-if="(slide.question.type || slide.type) === 'mcq'" 
        :key="slide.id"
        ref="activeSlideRef"
        :question="slide.question"
        @answered="handleAnswered"
      />
      
      <MatchingSlide
        v-else-if="(slide.question.type || slide.type) === 'matching'"
        :key="`matching-${slide.id}`"
        ref="activeSlideRef"
        :question="slide.question"
        @answered="handleAnswered"
      />
      
      <ReorderingSlide
        v-else-if="(slide.question.type || slide.type) === 'reordering'"
        :key="`reordering-${slide.id}`"
        ref="activeSlideRef"
        :question="slide.question"
        @answered="handleAnswered"
      />
      
      <FillBlankSlide
        v-else-if="(slide.question.type || slide.type) === 'fill_blank'"
        :key="`fill_blank-${slide.id}`"
        ref="activeSlideRef"
        :question="slide.question"
        @answered="handleAnswered"
      />

      <FillBlankChoicesSlide
        v-else-if="(slide.question.type || slide.type) === 'fill_blank_choices'"
        :key="`fill_blank_choices-${slide.id}`"
        ref="activeSlideRef"
        :question="slide.question"
        @answered="handleAnswered"
      />
      
      <div
        v-else
        class="text-center text-error pa-4 border-error border rounded"
      >
        <div class="text-h6">
          Unsupported Question Type
        </div>
        <div>Type: {{ slide.question.type || slide.type }}</div>
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
