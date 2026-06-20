<script setup>
import { useRoute, useRouter } from 'vue-router'
import { ref, onMounted, computed, watch } from 'vue'
import api from '@/utils/api'
import { useToast } from 'vue-toastification'
import { SlickList, SlickItem, HandleDirective } from 'vue-slicksort'
import LessonBuilderEditor from '@/views/lessons/builder/LessonBuilderEditor.vue'

definePage({
  meta: {
    action: 'view',
    subject: 'slides',
  },
})

const vHandle = HandleDirective

const route = useRoute()
const router = useRouter()
const toast = useToast()

const courseId = computed(() => route.params.courseid)
const levelId = computed(() => route.params.levelid)
const lessonId = computed(() => route.params.lessonid)

const isLoading = ref(true)
const isSavingOrder = ref(false)
const course = ref(null)
const level = ref(null)
const lesson = ref(null)
const slides = ref([])
const activeSlideIndex = ref(-1)
const isDeleteDialogVisible = ref(false)
const slideToDeleteId = ref(null)

const activeSlide = computed(() => {
  if (activeSlideIndex.value >= 0 && activeSlideIndex.value < slides.value.length) {
    return slides.value[activeSlideIndex.value]
  }
  return null
})

const fetchInitialData = async () => {
  isLoading.value = true
  try {
    const [courseRes, levelRes, lessonRes, slidesRes] = await Promise.all([
      api.get(`/admin/courses/${courseId.value}`),
      api.get(`/admin/courses/${courseId.value}/levels/${levelId.value}`),
      api.get(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons/${lessonId.value}`),
      api.get(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons/${lessonId.value}/slides`)
    ])

    course.value = courseRes
    level.value = levelRes
    lesson.value = lessonRes
    slides.value = slidesRes

    if (slides.value.length > 0) {
      activeSlideIndex.value = 0
    }
  } catch (error) {
    console.error('Error fetching builder data:', error)
    toast.error('Failed to load lesson builder data')
  } finally {
    isLoading.value = false
  }
}

const selectSlide = (index) => {
  activeSlideIndex.value = index
}

const onReorder = async () => {
  isSavingOrder.value = true
  try {
    const order = slides.value.map(s => s.id)
    await api.patch(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons/${lessonId.value}/slides/order`, { order })
    toast.success('Order updated')
  } catch (error) {
    toast.error('Failed to update order')
    fetchInitialData()
  } finally {
    isSavingOrder.value = false
  }
}

const addSlide = async () => {
  try {
    const nextSortOrder = slides.value.length + 1
    const newSlideData = {
      lesson_id: parseInt(lessonId.value),
      type: 'explanation',
      title: 'New Slide',
      content: 'New explanation content',
      sort_order: nextSortOrder
    }

    const response = await api.post(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons/${lessonId.value}/slides`, newSlideData)
    slides.value.push(response)
    activeSlideIndex.value = slides.value.length - 1
    toast.success('Slide added')
  } catch (error) {
    toast.error('Failed to add slide')
  }
}

const updateLocalSlide = (updatedSlide) => {
  if (activeSlideIndex.value !== -1) {
    slides.value[activeSlideIndex.value] = {
      ...slides.value[activeSlideIndex.value],
      ...updatedSlide
    }
  }
}

const confirmDelete = (slideId) => {
  slideToDeleteId.value = slideId
  isDeleteDialogVisible.value = true
}

const handleDelete = async (confirmed) => {
  if (!confirmed) {
    isDeleteDialogVisible.value = false
    return
  }

  try {
    await api.delete(`/admin/courses/${courseId.value}/levels/${levelId.value}/lessons/${lessonId.value}/slides/${slideToDeleteId.value}`)

    const index = slides.value.findIndex(s => s.id === slideToDeleteId.value)
    if (index !== -1) {
      slides.value.splice(index, 1)
      if (activeSlideIndex.value >= slides.value.length) {
        activeSlideIndex.value = slides.value.length - 1
      }
    }

    toast.success('Slide deleted')
    isDeleteDialogVisible.value = false
  } catch (error) {
    toast.error('Failed to delete slide')
  }
}

onMounted(fetchInitialData)
</script>

<template>
  <div>
    <VBreadcrumbs
      :items="[
        { title: 'Admin', disabled: true },
        { title: 'Courses', to: '/admin/courses' },
        { title: course ? course.title : 'Course', disabled: true },
        { title: level ? level.title : 'Level', to: `/admin/courses/${courseId}/levels` },
        { title: lesson ? lesson.title : 'Lesson', to: `/admin/courses/${courseId}/levels/${levelId}/lessons` },
        { title: 'Lesson Builder', disabled: true }
      ]"
      class="mb-4"
    />

    <VCard v-if="isLoading" class="text-center py-12">
      <VProgressCircular indeterminate color="primary" size="64" />
      <div class="mt-4 text-h6">Loading Lesson Builder...</div>
    </VCard>

    <div v-else-if="lesson" class="builder-container">
      <VRow>
        <!-- Sidebar -->
        <VCol cols="12" md="3" class="border-e pe-0 sidebar-col">
          <div class="sidebar-sticky-wrapper d-flex flex-column">
            <div class="d-flex justify-space-between align-center pa-4 flex-shrink-0">
              <span class="text-h6">Slides ({{ slides.length }})</span>
              <VBtn
                icon="tabler-plus"
                size="small"
                color="primary"
                variant="elevated"
                @click="addSlide"
              />
            </div>
            <VDivider />

            <div class="flex-grow-1 overflow-y-auto scrollable-slides-list">
              <SlickList
                v-model:list="slides"
                axis="y"
                use-drag-handle
                helper-class="slick-helper"
                class="slides-list"
                @update:list="onReorder"
              >
                <SlickItem
                  v-for="(slide, index) in slides"
                  :key="slide.id"
                  :index="index"
                  class="slide-item-wrapper"
                >
                  <div
                    class="slide-item d-flex align-center pa-3 mb-1 cursor-pointer"
                    :class="{ 'active': activeSlideIndex === index }"
                    @click="selectSlide(index)"
                  >
                    <VIcon
                      v-handle
                      icon="tabler-grip-vertical"
                      class="me-2 text-disabled drag-handle"
                    />
                    <div class="slide-number me-3 font-weight-bold">{{ index + 1 }}</div>
                    <div class="slide-info overflow-hidden">
                      <div class="text-truncate font-weight-medium">
                        {{ slide.title || 'Untitled Slide' }}
                      </div>
                      <div class="text-caption text-uppercase text-disabled">
                        {{ slide.type }}
                      </div>
                    </div>
                  </div>
                </SlickItem>
              </SlickList>

              <div v-if="slides.length === 0" class="pa-8 text-center text-disabled">
                <VIcon icon="tabler-presentation" size="48" class="mb-2" />
                <div>No slides yet. Click + to add one.</div>
              </div>
            </div>

            <VDivider />
            <div v-if="isSavingOrder" class="pa-2 bg-light-primary text-center">
              <VProgressLinear indeterminate height="2" />
              <small>Saving order...</small>
            </div>
          </div>
        </VCol>

        <!-- Main Editor -->
        <VCol cols="12" md="9" class="ps-0 main-col">
          <VCard v-if="activeSlide" flat rounded="0" class="builder-editor-card">
             <VCardText class="builder-editor-card-text">
                <LessonBuilderEditor
                  :slide="activeSlide"
                  :course-id="courseId"
                  :level-id="levelId"
                  :lesson-id="lessonId"
                  @update="updateLocalSlide"
                  @delete="confirmDelete"
                />
             </VCardText>
          </VCard>
          <VCard v-else flat rounded="0" class="h-100 bg-light-background d-flex align-center justify-center">
             <div class="text-center">
                <VIcon icon="tabler-layout-sidebar-right-expand" size="80" color="disabled" class="mb-4" />
                <div class="text-h5 text-disabled">Select a slide to start editing</div>
                <p class="text-disabled">Or click the plus button in the sidebar to create a new slide</p>
             </div>
          </VCard>
        </VCol>
      </VRow>
    </div>

    <ConfirmDialog
      v-model:is-dialog-visible="isDeleteDialogVisible"
      confirmation-question="Are you sure you want to delete this slide?"
      confirm-title="Delete Slide"
      confirm-msg="Slide deleted successfully."
      cancel-title="Cancel"
      cancel-msg="Delete cancelled."
      @confirm="handleDelete"
    />
  </div>
</template>

<style lang="scss" scoped>
.builder-container {
  min-height: calc(100vh - 160px);
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  display: flex;
  flex-direction: column;

  .v-row {
    margin: 0;
    flex-grow: 1;
  }
}

.sidebar-col {
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  display: flex;
  flex-direction: column;

  @media (min-width: 960px) {
    height: auto;
    min-height: calc(100vh - 160px);
  }

  @media (max-width: 959px) {
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-right: none !important;
    height: 400px;
  }
}

.sidebar-sticky-wrapper {
  flex-grow: 1;
  display: flex;
  flex-direction: column;

  @media (min-width: 960px) {
    position: sticky;
    top: 24px;
    height: calc(100vh - 200px);
  }

  @media (max-width: 959px) {
    height: 100%;
  }
}

.main-col {
  display: flex;
  flex-direction: column;
}

.builder-editor-card {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.builder-editor-card-text {
  padding: 24px;
  flex-grow: 1;
}

.slides-list {
  padding: 8px;
}

.slide-item {
  border: 1px solid transparent;
  border-radius: 6px;
  transition: all 0.2s ease;
  background: transparent;

  &:hover {
    background: rgba(var(--v-theme-primary), 0.04);
  }

  &.active {
    background: rgba(var(--v-theme-primary), 0.08);
    border-color: rgba(var(--v-theme-primary), 0.2);

    .slide-number {
      color: rgb(var(--v-theme-primary));
    }
  }

  .drag-handle {
    cursor: grab;
    &:active { cursor: grabbing; }
  }
}

.slick-helper {
  z-index: 1000;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  border-radius: 6px;
  opacity: 0.9;
  pointer-events: none;
}
</style>
