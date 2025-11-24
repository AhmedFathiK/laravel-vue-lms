<script setup>
import videoPlaceholder from '@images/pages/default-course-image.png'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  slideNumber: {
    type: Number,
    required: true,
  },
  isLoading: {
    type: Boolean,
    required: false,
  },
})

const emit = defineEmits([
  "click:edit",
  "click:delete",
])

const editItem = () => {
  emit("click:edit", props.slideNumber)
}


const deleteItem = () => {
  emit("click:delete", props.slideNumber)
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard class="slide-card">
        <VRow no-gutters>
          <VCol cols="12">
            <VCardItem>
              <VRow>
                <VCol
                  md="2"
                  class="slide-number"
                >
                  <DragHandle />
                </VCol>
                <VCol
                  md="6"
                  class="slide-number d-flex gap-1 justify-start align-center"
                >
                  #{{ slideNumber }}
                </VCol>
                <VCol md="4">
                  <div class="d-flex gap-1 justify-end align-center">
                    <VSpacer class="d-none d-lg-block" />
                    <IconBtn
                      size="small"
                      @click="editItem"
                    >
                      <VIcon
                        size="24"
                        icon="tabler-edit"
                        color="warning"
                      />
                    </IconBtn>
                      
                    <IconBtn
                      size="small"
                      @click="deleteItem"
                    >
                      <VIcon
                        size="24"
                        icon="tabler-trash"
                        color="error"
                      />
                    </IconBtn>
                  </div>
                </VCol>
              </VRow>
            </VCardItem>

            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  class="d-flex flex-column align-center justify-center"
                >
                  <p class="slide-title">
                    <template v-if="['course_term', 'explanation'].includes(data.type)">
                      {{ data.title }}
                    </template>
                    <template v-else>
                      {{ data.question.title ? data.question.title : data.title }}
                    </template>
                  </p>
                  <div
                    v-if="data.type != 'explanation'"
                    class="slide-img w-50"
                  >
                    <VImg
                      v-if="data.course_term_id && ['image', 'image_with_audio'].includes(data.term.media_type) && data.term.image"
                      cover
                      :src="data.term.image"
                      height="162.13"
                    /> 

                    <VImg
                      v-else
                      cover
                      :src="videoPlaceholder"
                    />
                  </div>
                  <div
                    v-if="!['course_term', 'blanks_mcq'].includes(data.type)"
                    class="slide-text"
                  >
                    <PerfectScrollbar
                      v-if="data.type == 'explanation'"
                      :options="{ wheelPropagation: false, suppressScrollX: true }"
                      style="block-size: 21rem;"
                    >
                      <VRow>
                        <VCol
                          cols="12"
                          class="d-flex justify-center flex-column pt-0"
                        >
                          <div class="d-flex flex-column py-2">
                            <div
                              class="mb-2 pa-1"
                              v-html="data.text"
                            />
                          </div>
                        </VCol>
                      </VRow>
                    </PerfectScrollbar>
                    <template v-else>
                      {{ data.question.text }}
                    </template>
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  class="text-center"
                >
                  <PerfectScrollbar
                    v-if="data.type == 'matching'"
                    :options="{ wheelPropagation: false }"
                    style="max-block-size: 7.1rem;"
                  >
                    <VRow>
                      <VCol cols="6">
                        <VList>
                          <VListItem
                            v-for="(answer, index) in data.question.answers"
                            :key="index"
                            color="primary"
                            rounded="xl"
                            class="my-2"
                          >
                            <VListItemTitle>
                              <div class="matching-item pa-1 text-truncate">
                                {{ answer.column_a }}
                              </div>
                            </VListItemTitle>
                          </VListItem>
                        </VList>
                      </VCol>
                      <VCol cols="6">
                        <VList>
                          <VListItem
                            v-for="(answer, index) in data.question.answers"
                            :key="index"
                            color="primary"
                            rounded="xl"
                            class="my-2"
                          >
                            <VListItemTitle>
                              <div class="matching-item pa-1 text-truncate">
                                {{ answer.column_b }}
                              </div>
                            </VListItemTitle>
                          </vlistitem>
                        </VList>
                      </VCol>
                    </VRow>
                  </PerfectScrollbar>
                  <PerfectScrollbar
                    v-if="data.type == 'mcq'"
                    :options="{ wheelPropagation: false }"
                    style="max-block-size: 7.1rem;"
                  >
                    <VRow>
                      <VCol
                        cols="12"
                        class="d-flex justify-center flex-column"
                      >
                        <div
                          v-for="(answer, index) in data.question.answers"
                          :key="index"
                          class="mcq-answer my-1 overflow-y-auto pa-1"
                        >
                          {{ answer.text }}
                        </div>
                      </VCol>
                    </VRow>
                  </PerfectScrollbar>
                  <VRow v-if="data.type == 'true_false'">
                    <VCol
                      cols="12"
                      class="d-flex justify-center flex-column"
                    >
                      <div class="mcq-answer my-1">
                        True
                      </div>
                      <div class="mcq-answer my-1">
                        False
                      </div>
                    </VCol>
                  </VRow>
                  <PerfectScrollbar
                    v-if="data.type == 'blanks_mcq'"
                    :options="{ wheelPropagation: false }"
                    style="max-block-size: 9.4rem;"
                  >
                    <VRow>
                      <VCol
                        cols="12"
                        class="d-flex justify-center flex-column"
                      >
                        <div class="blanks-mcq-question mb-4 pa-2">
                          <template
                            v-for="(questionPart, index) in data.question.text.split('[...]')"
                            :key="index"
                          >
                            <div class="blanks-mcq-question-text-placeholder mx-2 pt-1">
                              {{ questionPart }}
                            </div>
                          
                            <div
                              v-if="index < data.question.text.split('[...]').length - 1"
                              class="blank-placeholder ma-1"
                            />
                          <!--
                            {{ questionPart }}
                            
                            <div
                            v-else
                            class="blanks-mcq-question-text-placeholder mx-2 pt-1"
                            >
                            {{ questionPart }}
                            </div> 
                          -->
                          </template>
                        </div>
                        <div class="blanks-mcq-choices">
                          <div class="blanks-choice-placeholder pt-1">
                            A
                          </div>
                          <div class="blanks-choice-placeholder mx-2 pt-1">
                            B
                          </div>
                          <div class="blanks-choice-placeholder pt-1">
                            C
                          </div>
                        </div>
                      </VCol>
                    </VRow>
                  </PerfectScrollbar>
                  <VRow v-if="data.type == 'course_term'">
                    <VCol
                      cols="12"
                      class="d-flex justify-center flex-column pt-0"
                    >
                      <div class="course-term d-flex flex-column py-2">
                        <div class="term-text-placeholder mb-2 pa-1">
                          {{ data.term.term }}
                        </div>
                        <div class="term-meaning-text-placeholder pa-1">
                          {{ data.term.meaning }}
                        </div>
                      </div>
                      <div class="term-example">
                        Example
                        <div class="example-placeholder mb-2 text-start pa-1">
                          {{ data.term.example }}
                        </div>
                        <div class="example-meaning-placeholder pa-1">
                          {{ data.term.example_meaning }}
                        </div>
                      </div>
                    </VCol>
                  </VRow>
                </VCol>
              </VRow>
            </VCardText>
          </VCol>
        </VRow>
      </VCard>
    </VCol>
  </VRow>
</template>

<style lang="scss" scoped>
.slide-card{
  height: 480px;
  background: rgb(165,218,244);
 background: linear-gradient(90deg, rgba(165,218,244,0.5) 0%, rgba(73,167,218,0.5) 100%);
 user-select: none;
}
.v-list{
  background-color: rgba(var(--v-theme-background));
}

.matching-item{
    
  width:100%;
  border-radius: 6px;
  background-color:rgba(var(--v-theme-surface));
}
.mcq-answer {
  height:50px;
  width:100%;
  background-color: rgba(var(--v-theme-background));
  border-radius: 6px;
  display: flex;
  justify-content: center;
  align-items: center;
}
.mcq-answer-placeholder{
  height:20px;
  width:90%;
  border-radius: 6px;
  background-color:rgba(var(--v-theme-surface));
}
.blank-placeholder{
  height:30px;
  width: 40px;
  border-radius: 6px;
  border-bottom: 1px dashed;
  background-color:rgba(var(--v-theme-surface));
}
.blanks-mcq-question{
  width:100%;
  background-color: rgba(var(--v-theme-background));
  border-radius: 6px;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-wrap: wrap;
}
.blanks-mcq-question-text-placeholder{
  height:30px;
  border-radius: 6px;
  background-color:rgba(var(--v-theme-surface));
}
.blanks-mcq-choices{
  height:50px;
  width:100%;
  border-radius: 6px;
  display: flex;
  justify-content: center;
  align-items: center;
}
.blanks-choice-placeholder{
  height:30px;
  width:40px;
  border-radius: 6px;
  
  background-color:rgba(var(--v-theme-surface));
}
.course-term{
  width:100%;
  border-radius: 6px;
  display: flex;
  justify-content: center;
  align-items: center;
}
.term-meaning-text-placeholder, .term-text-placeholder {
  height:30px;
  border-radius: 6px;
  background-color:rgba(var(--v-theme-surface));
}
.term-example{
  width:100%;
  display: flex;
  flex-direction: column;
  justify-content: start;
  align-items: start;
}
.example-placeholder{
  border-radius: 6px;
  background-color:rgba(var(--v-theme-surface));
}
.example-meaning-placeholder{
  border-radius: 6px;
  background-color:rgba(var(--v-theme-surface));
}
</style>
