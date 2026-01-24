import { ref } from 'vue'
import { useToast } from 'vue-toastification'

export function useExamForm() {
  const toast = useToast()

  const exam = ref({
    title: '',
    description: '',
    timeLimit: 60,
    passingPercentage: 70,
    maxAttempts: 0,
    status: 'draft',
    isActive: false,
    showAnswers: false,
    randomizeQuestions: false,
    sections: [],
  })

  const activeSection = ref(0) // Open first section by default

  // Section Management
  const isAddSectionDialogVisible = ref(false)
  const newSectionTitle = ref('')

  const addSection = () => {
    if (!newSectionTitle.value) return

    exam.value.sections.push({
      title: newSectionTitle.value,
      order: exam.value.sections.length + 1,
      questions: [],
      context: {
        id: null,
        title: '',
        content: '',
        mediaType: 'none',
        mediaUrl: null,
      },
    })

    newSectionTitle.value = ''
    isAddSectionDialogVisible.value = false
    activeSection.value = [exam.value.sections.length - 1]
  }

  const deleteSection = index => {
    if (!confirm('Remove this section?')) return
    exam.value.sections.splice(index, 1)

    // Reorder remaining sections
    exam.value.sections.forEach((s, i) => {
      s.order = i + 1
    })
  }

  const isQuestionInExam = questionId => {
    return exam.value.sections.some(section => 
      section.questions.some(q => q.id === questionId),
    )
  }

  const moveSectionUp = index => {
    if (index === 0) return
    const sections = [...exam.value.sections]
    const temp = sections[index]

    sections[index] = sections[index - 1]
    sections[index - 1] = temp

    // Update order
    sections.forEach((s, i) => {
      s.order = i + 1
    })

    exam.value.sections = sections
    activeSection.value = [index - 1]
  }

  const moveSectionDown = index => {
    if (index === exam.value.sections.length - 1) return
    const sections = [...exam.value.sections]
    const temp = sections[index]

    sections[index] = sections[index + 1]
    sections[index + 1] = temp

    // Update order
    sections.forEach((s, i) => {
      s.order = i + 1
    })

    exam.value.sections = sections
    activeSection.value = [index + 1]
  }

  // Question Management
  const isAddQuestionDialogVisible = ref(false)
  const currentSectionIndex = ref(null)
  const editingQuestionIndex = ref(null)

  const questionForm = ref({
    type: 'mcq',
    questionText: '',
    points: 1,
    options: [],
    correctAnswer: null,
  })

  const openAddQuestion = sectionIndex => {
    currentSectionIndex.value = sectionIndex
    editingQuestionIndex.value = null
    questionForm.value = {
      type: 'mcq',
      questionText: '',
      marks: 1,
      options: ['', ''],
      correctAnswer: [],
    }
    isAddQuestionDialogVisible.value = true
  }

  const openEditQuestion = (sectionIndex, questionIndex) => {
    currentSectionIndex.value = sectionIndex
    editingQuestionIndex.value = questionIndex
    questionForm.value = JSON.parse(JSON.stringify(exam.value.sections[sectionIndex].questions[questionIndex]))
    isAddQuestionDialogVisible.value = true
  }

  const saveQuestion = questionData => {
    const section = exam.value.sections[currentSectionIndex.value]
    
    // Check if question already exists in any section of the exam
    const isDuplicate = exam.value.sections.some((s, sIdx) => {
      return s.questions.some((q, qIdx) => {
        // Skip checking against itself if we're editing
        if (sIdx === currentSectionIndex.value && qIdx === editingQuestionIndex.value) {
          return false
        }
        
        return q.id === questionData.id
      })
    })

    if (isDuplicate) {
      toast.error('This question is already added to the exam.')
      
      return
    }

    if (editingQuestionIndex.value !== null) {
      section.questions[editingQuestionIndex.value] = {
        ...questionData,
        order: section.questions[editingQuestionIndex.value].order,
      }
    } else {
      section.questions.push({
        ...questionData,
        order: section.questions.length + 1,
      })
    }

    isAddQuestionDialogVisible.value = false
  }

  const deleteQuestion = (sectionIndex, questionIndex) => {
    if (!confirm('Remove this question?')) return
    exam.value.sections[sectionIndex].questions.splice(questionIndex, 1)

    // Reorder
    exam.value.sections[sectionIndex].questions.forEach((q, i) => {
      q.order = i + 1
    })
  }

  return {
    exam,
    activeSection,
    isAddSectionDialogVisible,
    newSectionTitle,
    addSection,
    deleteSection,
    moveSectionUp,
    moveSectionDown,
    isAddQuestionDialogVisible,
    currentSectionIndex,
    editingQuestionIndex,
    questionForm,
    openAddQuestion,
    openEditQuestion,
    saveQuestion,
    deleteQuestion,
  }
}
