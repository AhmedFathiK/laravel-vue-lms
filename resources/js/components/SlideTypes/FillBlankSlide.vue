<script setup>
import { VideoPlayer } from '@videojs-player/vue'
import 'video.js/dist/video-js.css'
import { computed, ref } from 'vue'

const props = defineProps({
  question: {
    type: Object,
    required: true,
  },
  type: {
    type: String,
    default: 'fill_blank', // or 'fill_blank_choices'
  },
})

const emit = defineEmits(['answered'])

const getBlanksData = () => {
  if (Array.isArray(props.question.content)) {
    return props.question.content
  }
  
  return props.question.content?.blanks || []
}

const userAnswers = ref({}) // { blankIndex: value }
const isSubmitted = ref(false)

// Parse question text to find blanks
// Assuming format like "The capital of France is [Paris]" or similar
// Or maybe "The capital of France is ______" and we have separate answers array.
// Let's assume standard format: "Text before %blank% text after" or similar placeholder
// Or structure: parts: ['The capital of France is ', 'blank', '.']
// Let's implement a robust parser assuming standard "[answer]" format for blanks in text.

const parts = computed(() => {
  // Regex to split by square brackets
  // Example: "The capital is [Paris]." -> ["The capital is ", "[Paris]", "."]
  const text = props.question.questionText
  const regex = /(\[[^\]]+\])/g
  
  return text ? text.split(regex) : []
})

const blanks = computed(() => {
  const text = props.question.questionText
  const matches = text ? (text.match(/\[([^\]]+)\]/g) || []) : []
    
  // If we have content.correctAnswer (array of answers corresponding to blanks)
  // we should use that instead of parsing the blank text itself for the answer
  let correctAnswers = props.question.content?.correctAnswer || props.question.correctAnswer || []
  
  const blanksData = getBlanksData()
  if (blanksData.length > 0) {
    correctAnswers = blanksData.map(b => {
      // If correctAnswer is provided (could be index or value)
      const rawCorrect = b.correctAnswer !== undefined ? b.correctAnswer : b.answer
      
      // If it's an index and options exist, resolve to value
      if (b.options && Array.isArray(b.options)) {
        // Check if rawCorrect is a numeric index
        const index = parseInt(rawCorrect)
        if (!isNaN(index) && index >= 0 && index < b.options.length) {
          return b.options[index]
        }
      }
      
      return rawCorrect
    })
  }

  return matches.map((m, i) => {
    let answer = m.replace('[', '').replace(']', '')
        
    // If we have explicit correct answers array, use it
    // The array might contain strings or arrays of strings (alternatives)
    if (correctAnswers[i]) {
      answer = correctAnswers[i]
    }
        
    return {
      original: m,
      answer: answer, // This can be string or array
      index: i,
    }
  })
})

// Initialize user answers
const init = () => {
  blanks.value.forEach((b, i) => {
    userAnswers.value[i] = ''
  })
}

init()

const getChoices = blankIndex => {
  // For fill_blank_choices, we need options.
  // In new structure: content.blanks[index].options
  const blanksData = getBlanksData()
  if (blanksData[blankIndex]) {
    return blanksData[blankIndex].options
  }
  
  if (props.question.content?.blanks && props.question.content.blanks[blankIndex]) {
    return props.question.content.blanks[blankIndex].options
  }
    
  // Legacy fallback
  return props.question.options || []
}

// Drag and Drop Logic for fill_blank_choices
const draggedItem = ref(null)

const allOptions = computed(() => {
  if (props.type !== 'fill_blank_choices') return []
  
  // Get global options
  // Prioritize content.options (new structure)
  const content = props.question.content || {}
  
  // Case 1: content.options exists (Standard new structure)
  if (content.options && Array.isArray(content.options)) {
    return content.options
  }
  
  // Case 2: Aggregate options from all blanks (New Structure provided by user)
  // Structure: { blanks: [ { options: [...] }, ... ] }
  if (content.blanks && Array.isArray(content.blanks)) {
    const aggregatedOptions = new Set()
    
    content.blanks.forEach(blank => {
      if (blank.options && Array.isArray(blank.options)) {
        blank.options.forEach(opt => aggregatedOptions.add(opt))
      }
    })
    
    if (aggregatedOptions.size > 0) {
      return Array.from(aggregatedOptions)
    }
  }
  
  return []
})

const availableOptions = computed(() => {
  const options = [...allOptions.value]
  const used = Object.values(userAnswers.value).filter(Boolean)
  
  const available = []
  
  // Simple frequency counting removal
  // We want to remove 'used' items from 'options'
  // But we must respect duplicates. 
  // e.g. options=['a','a'], used=['a'] -> available=['a']
  
  const usedCounts = used.reduce((acc, val) => {
    acc[val] = (acc[val] || 0) + 1
    
    return acc
  }, {})
  
  for (const opt of options) {
    if (usedCounts[opt] > 0) {
      usedCounts[opt]--
    } else {
      available.push(opt)
    }
  }
  
  return available
})

const handleDragStart = (item, sourceIndex = null) => {
  if (isSubmitted.value) return
  draggedItem.value = { item, sourceIndex }
}

const handleDrop = targetIndex => {
  if (isSubmitted.value || !draggedItem.value) return
  
  const { item, sourceIndex } = draggedItem.value
  
  // If moving from one blank to another
  if (sourceIndex !== null) {
    // If target is occupied, swap? Or just overwrite?
    // Let's swap for better UX
    const existingAtTarget = userAnswers.value[targetIndex]

    userAnswers.value[targetIndex] = item
    userAnswers.value[sourceIndex] = existingAtTarget // could be undefined/empty
  } else {
    // Dragging from bank
    userAnswers.value[targetIndex] = item
  }
  
  draggedItem.value = null
  checkAutoSubmit()
}

const clearBlank = index => {
  if (isSubmitted.value) return
  userAnswers.value[index] = ''
}

const checkAutoSubmit = () => {
  // Only for fill_blank_choices
  if (props.type !== 'fill_blank_choices') return
  
  const totalBlanks = blanks.value.length
  const filledBlanks = Object.values(userAnswers.value).filter(Boolean).length
  
  if (filledBlanks === totalBlanks) {
    submitAnswer()
  }
}

const submitAnswer = () => {
  isSubmitted.value = true
    
  // Check all blanks
  const isCorrect = blanks.value.every((b, i) => {
    const userVal = userAnswers.value[i] || ''
        
    // If answer is array of alternatives
    if (Array.isArray(b.answer)) {
      return b.answer.some(ans => userVal.toLowerCase().trim() === ans.toLowerCase().trim())
    }
        
    // Case insensitive check?
    return userVal.toLowerCase().trim() === String(b.answer).toLowerCase().trim()
  })
    
  emit('answered', {
    correct: isCorrect,
    userAnswer: userAnswers.value,
  })
}

const getInputClass = index => {
  const base = 'd-inline-block mx-2'
  if (isSubmitted.value) {
    const blank = blanks.value[index]
    const user = userAnswers.value[index].toLowerCase().trim()
        
    let isCorrect = false
    if (Array.isArray(blank.answer)) {
      isCorrect = blank.answer.some(ans => user === ans.toLowerCase().trim())
    } else {
      isCorrect = user === String(blank.answer).toLowerCase().trim()
    }
        
    return isCorrect ? 'text-success font-weight-bold' : 'text-error text-decoration-line-through'
  }
  
  return ''
}
</script>

<template>
  <div
    class="fill-blank-slide mx-auto"
    style="max-width: 800px;"
  >
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
          :src="question.mediaUrl"
          controls
          class="rounded-lg overflow-hidden"
          fluid
        />
      </div>
    </div>

    <div class="text-h5 text-center mb-8 lh-loose">
      <template
        v-for="(part, i) in parts"
        :key="i"
      >
        <!-- If part is a blank (starts with [) -->
        <span v-if="part.startsWith('[') && part.endsWith(']')">
          <!-- Find blank index -->
          <template v-for="(blank, bIndex) in blanks">
            <span
              v-if="blank.original === part"
              :key="bIndex"
              class="d-inline-block"
            >
                        
              <!-- Input for Type Fill Blank -->
              <VTextField
                v-if="type === 'fill_blank'"
                v-model="userAnswers[bIndex]"
                variant="underlined"
                density="compact"
                class="d-inline-block mx-2"
                style="width: 150px; text-align: center;"
                :disabled="isSubmitted"
                :error="isSubmitted && userAnswers[bIndex].toLowerCase().trim() !== blank.answer.toLowerCase().trim()"
                :success="isSubmitted && userAnswers[bIndex].toLowerCase().trim() === blank.answer.toLowerCase().trim()"
                hide-details
              />

              <!-- Drop Zone for Fill Blank Choices -->
              <span
                v-else-if="type === 'fill_blank_choices'"
                class="d-inline-flex align-center justify-center mx-2 drop-zone"
                :class="{
                  'drop-zone-filled': userAnswers[bIndex],
                  'drop-zone-empty': !userAnswers[bIndex],
                  'error-border': isSubmitted && userAnswers[bIndex] !== blank.answer,
                  'success-border': isSubmitted && userAnswers[bIndex] === blank.answer
                }"
                @dragover.prevent
                @drop="handleDrop(bIndex)"
                @click="clearBlank(bIndex)"
              >
                <VChip
                  v-if="userAnswers[bIndex]"
                  :draggable="true"
                  class="cursor-grab"
                  :color="isSubmitted ? (userAnswers[bIndex] === blank.answer ? 'success' : 'error') : 'primary'"
                  @dragstart="handleDragStart(userAnswers[bIndex], bIndex)"
                >
                  {{ userAnswers[bIndex] }}
                </VChip>
              </span>
                        
              <!-- Correction display -->
              <div
                v-if="isSubmitted && type === 'fill_blank' && userAnswers[bIndex].toLowerCase().trim() !== blank.answer.toLowerCase().trim()"
                class="text-success text-caption text-center"
              >
                {{ blank.answer }}
              </div>
            </span>
          </template>
        </span>
            
        <!-- Normal Text -->
        <span v-else>{{ part }}</span>
      </template>
    </div>
    
    <!-- Word Bank for Fill Blank Choices -->
    <div
      v-if="type === 'fill_blank_choices' && !isSubmitted"
      class="d-flex justify-center flex-wrap gap-4 mt-8 pa-4 border rounded bg-surface"
    >
      <VChip
        v-for="(option, idx) in availableOptions"
        :key="idx"
        :draggable="true"
        class="cursor-grab elevation-2"
        color="default"
        variant="outlined"
        @dragstart="handleDragStart(option)"
      >
        {{ option }}
      </VChip>
      
      <div
        v-if="availableOptions.length === 0 && !isSubmitted"
        class="text-caption text-medium-emphasis"
      >
        {{ availableOptions }}
        All items placed
      </div>
    </div>

    <div class="d-flex justify-center mt-8">
      <VBtn 
        v-if="!isSubmitted && type === 'fill_blank'" 
        color="primary" 
        size="large" 
        @click="submitAnswer"
      >
        Check Answers
      </VBtn>
    </div>
  </div>
</template>

<style scoped>
.lh-loose {
    line-height: 2.5;
}
.vertical-align-middle {
    vertical-align: middle;
}
.drop-zone {
  min-width: 60px;
  display: inline-flex;
  min-height: 32px;
  border-bottom: 2px dashed rgba(var(--v-theme-on-surface), 0.38);
  vertical-align: middle;
  transition: all 0.2s;
  margin-bottom: -4px; /* Align better with text baseline */
}
.drop-zone-empty {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
  border-radius: 4px 4px 0 0;
}
.drop-zone-filled {
  border-bottom: none;
  min-width: auto; /* Allow shrinking to fit content */
}
.error-border {
  border-bottom: 2px solid rgb(var(--v-theme-error)) !important;
}
.success-border {
  border-bottom: 2px solid rgb(var(--v-theme-success)) !important;
}
.cursor-grab {
  cursor: grab;
}
.cursor-grab:active {
  cursor: grabbing;
}
</style>
