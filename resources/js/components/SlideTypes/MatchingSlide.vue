<script setup>
import VideoPlayer from '@/components/VideoPlayer.vue'
import { ref } from 'vue'

const props = defineProps({
  question: {
    type: Object,
    required: true,
  },
  isExam: {
    type: Boolean,
    default: false,
  },
  modelValue: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['answered', 'update:modelValue'])

const getPairs = () => {
  if (Array.isArray(props.question.content)) {
    return props.question.content
  }
  
  return props.question.content?.pairs || props.question.options || []
}

const selectedLeft = ref(null)
const matches = ref({}) // { leftIndex: rightIndex }
const isSubmitted = ref(false)

// Sync from modelValue if provided (Exam Mode)
watch(() => props.modelValue, newVal => {
  if (props.isExam && newVal && JSON.stringify(newVal) !== JSON.stringify(matches.value)) {
    // Break infinite loop: only update if different
    matches.value = { ...newVal }
  }
}, { immediate: true, deep: true })

// Sync to modelValue (Exam Mode)
watch(matches, newVal => {
  if (props.isExam) {
    // Always emit a clone so parent gets new reference and detects change
    emit('update:modelValue', { ...newVal })
  }
}, { deep: true })

const unmatchedRightItems = computed(() => {
  return rightItems.value.filter((item, index) => {
    // Return true if this item's originalIndex is NOT found in matches values
    return !Object.values(matches.value).includes(item.originalIndex)
  })
})

const handleLeftClick = index => {
  if (isSubmitted.value && !props.isExam) return
  
  // If clicking an empty slot or the left item row, select it
  selectedLeft.value = index
}

const handleMatchedItemClick = leftIndex => {
  if (isSubmitted.value && !props.isExam) return
  
  // Remove match, returning item to pool
  delete matches.value[leftIndex]
}

const handleOptionClick = item => {
  if (isSubmitted.value && !props.isExam) return
  
  if (selectedLeft.value !== null) {
    // Assign to selected left slot using originalIndex (stable across shuffles)
    matches.value[selectedLeft.value] = item.originalIndex
    
    // Auto-advance selection to next empty slot if available?
    // Optional improvement: find next empty left index
    const nextEmpty = leftItems.value.findIndex((_, i) => matches.value[i] === undefined)
    if (nextEmpty !== -1) {
      selectedLeft.value = nextEmpty
    } else {
      selectedLeft.value = null
    }
    
    if (!props.isExam) {
      checkCompletion()
    }
  }
}

const checkCompletion = () => {
  // If all left items are matched
  const pairs = getPairs()
  if (Object.keys(matches.value).length === pairs.length) {
    submitAnswer()
  }
}

const submitAnswer = () => {
  isSubmitted.value = true
  
  // matches values are now originalIndices. 
  // Correct if leftIndex == rightOriginalIndex
  const isCorrect = Object.entries(matches.value).every(([leftIndex, rightOriginalIndex]) => {
    return rightOriginalIndex === parseInt(leftIndex)
  })

  emit('answered', {
    correct: isCorrect,
    userAnswer: matches.value,
  })
}

// Helper to get text for matched item
const getMatchedText = originalIndex => {
  const item = rightItems.value.find(i => i.originalIndex === originalIndex)
  
  return item ? item.text : ''
}

// Prepare items
const leftItems = computed(() => getPairs().map((p, i) => ({ text: p.left, originalIndex: i })))

// Shuffle right items once on mount? 
// In script setup, top level code runs once.
const rightItems = ref([])

// Initialize
const init = () => {
  const items = getPairs().map((p, i) => ({ text: p.right, originalIndex: i }))

  // Shuffle
  for (let i = items.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));

    [items[i], items[j]] = [items[j], items[i]]
  }
  rightItems.value = items
  
  // Auto-select first left item
  if (leftItems.value.length > 0) {
    selectedLeft.value = 0
  }
}

init()


const getRowClass = index => {
  // Use 'border-thin' or specific width to avoid layout shifts, or ensure unselected has transparent border of same width
  // Vuetify 'border' class adds 1px border.
  // We will manage border color and width carefully.
  
  const base = 'd-flex align-stretch mb-4 rounded-lg overflow-hidden border transition-all'
  
  // If selected, use primary border and background tint
  if (selectedLeft.value === index && !isSubmitted.value) {
    // Add 2px border simulation or just color change
    // To prevent size jump, we can keep border-width constant (e.g. 2px) for all states
    // But Vuetify 'border' is 1px.
    // Let's use a specific class for selection that doesn't change box-model significantly
    // or use outline.
    return `${base} border-primary bg-primary-subtle elevation-4`
  }
  
  if (isSubmitted.value && !props.isExam) {
    const rightOriginalIndex = matches.value[index]
    if (rightOriginalIndex !== undefined) {
      const isCorrect = rightOriginalIndex === index
      
      return isCorrect 
        ? `${base} border-success bg-success-subtle` 
        : `${base} border-error bg-error-subtle`
    }
  }
  
  // Default unselected state
  // Use border-opacity-25 or similar to keep it subtle but present
  return `${base} border-dashed bg-surface`
}

const getSlotClass = index => {
  // The right side of the row (slot)
  return 'flex-grow-1 d-flex align-center justify-center pa-4 cursor-pointer min-h-60'
}
</script>

<template>
  <div
    class="matching-slide mx-auto"
    style="max-width: 900px;"
  >
    <div class="text-h4 text-center mb-6 font-weight-bold">
      {{ question.questionText }}
    </div>

    <!-- Media Handling -->
    <div
      v-if="question.mediaUrl"
      class="mb-6 d-flex justify-center"
    >
      <VImg
        v-if="question.mediaType === 'image'"
        :src="question.mediaUrl"
        max-height="400"
        class="rounded-lg"
        contain
      />
      <div
        v-else-if="['video', 'audio'].includes(question.mediaType)"
        class="w-100"
        style="max-width: 600px;"
      >
        <VideoPlayer
          :key="question.mediaUrl"
          :src="question.mediaUrl"
          :type="question.mediaUrl.includes('youtube') ? 'youtube' : (question.mediaUrl.includes('vimeo') ? 'vimeo' : 'hosted')"
          class="rounded-lg overflow-hidden elevation-2"
        />
      </div>
    </div>

    <div class="matching-container">
      <!-- Rows Container -->
      <div class="rows-container mb-8">
        <div 
          v-for="(leftItem, index) in leftItems" 
          :key="index"
          :class="getRowClass(index)"
          @click="handleLeftClick(index)"
        >
          <!-- Left Item (Fixed) -->
          <div class="left-item flex-grow-1 d-flex align-center justify-start pa-4 border-e bg-surface">
            <span class="text-body-1 font-weight-medium">{{ leftItem.text }}</span>
          </div>

          <!-- Right Slot (Droppable) -->
          <div :class="getSlotClass(index)">
            <!-- If matched, show the right item -->
            <div
              v-if="matches[index] !== undefined"
              class="matched-item w-100 h-100 d-flex align-center justify-center bg-info text-on-info elevation-1 rounded px-4 py-2 font-weight-bold"
              style="--v-theme-overlay-multiplier: var(--v-theme-info-overlay-multiplier)"
              @click.stop="handleMatchedItemClick(index)"
            >
              <span class="text-body-1">{{ getMatchedText(matches[index]) }}</span>
              <VIcon
                v-if="!isSubmitted || isExam"
                icon="tabler-x"
                size="small"
                class="ms-2"
              />
            </div>
            
            <!-- If empty, show placeholder -->
            <div
              v-else
              class="text-caption text-disabled font-italic"
            >
              {{ selectedLeft === index ? 'Select an option below' : 'Tap to select' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Options Bank (Sticky Bottom or Just Below) -->
      <div class="options-bank">
        <div class="text-h6 mb-4">
          Available Options
        </div>
        <div class="d-flex flex-wrap gap-4 justify-center">
          <div
            v-for="item in unmatchedRightItems"
            :key="item.originalIndex"
            class="option-card cursor-pointer pa-4 rounded border bg-surface elevation-1 transition-all"
            @click="handleOptionClick(item)"
          >
            {{ item.text }}
          </div>
          
          <div 
            v-if="unmatchedRightItems.length === 0 && (!isSubmitted || isExam)" 
            class="text-body-1 text-medium-emphasis font-italic py-4"
          >
            All items matched!
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.matching-slide {
  user-select: none;
}
.min-h-60 {
  min-height: 60px;
}
.gap-4 {
  gap: 16px;
}
/* Ensure consistent border width to prevent layout shifts */
.border {
  border-width: 2px !important;
}
/* Ensure half-width for left item */
.left-item {
  width: 50%;
  flex-basis: 50%;
  flex-grow: 0;
  flex-shrink: 0;
}
.border-e {
  border-right: 2px solid rgba(var(--v-theme-on-surface), 0.12) !important;
}
.option-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
}
/* 
  Use Vuetify CSS variables for consistent theming 
  These variables automatically adjust based on light/dark theme
*/
.bg-primary-subtle {
  background-color: rgba(var(--v-theme-primary), 0.12) !important;
}
.bg-success-subtle {
  background-color: rgba(var(--v-theme-success), 0.12) !important;
}
.bg-error-subtle {
  background-color: rgba(var(--v-theme-error), 0.12) !important;
}
/* Tonal Info Style for Matched Items */
.bg-info {
  background-color: rgb(var(--v-theme-info)) !important;
  color: rgb(var(--v-theme-on-info)) !important;
}
/* We can also simulate 'tonal' by using opacity if desired, 
   but direct theme color is robust. 
   For true 'tonal' look (lighter background, darker text):
*/
.matched-item {
  /* Tonal-like appearance using Info color with opacity for background */
  background-color: rgba(var(--v-theme-info), 0.2) !important; 
  color: rgb(var(--v-theme-info)) !important;
}

.transition-all {
  transition: background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}
</style>
