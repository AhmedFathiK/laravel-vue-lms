import { defineStore } from 'pinia'
import api from '@/utils/api'

export const useActiveCourse = defineStore('activeCourse', {
  state: () => ({
    activeCourseId: localStorage.getItem('active_course_id') ? parseInt(localStorage.getItem('active_course_id')) : null,
    activeCourse: null,
    isLoading: false,
    error: null,
  }),

  actions: {
    async fetchActiveCourse() {
      this.isLoading = true
      this.error = null
      try {
        const response = await api.get('/user/active-course')
        
        if (!response) {
          this.activeCourse = null
          this.activeCourseId = null
          localStorage.removeItem('active_course_id')
          
          return
        }

        // API returns CourseResource which is wrapped in 'data' usually
        const course = response.data || response 

        if (course && course.id) {
          this.activeCourse = course
          this.activeCourseId = course.id
          localStorage.setItem('active_course_id', course.id)
        } else {
          this.activeCourse = null
          this.activeCourseId = null
          localStorage.removeItem('active_course_id')
        }
      } catch (error) {
        console.error('Failed to fetch active course:', error)
        this.error = error
        this.activeCourse = null
        this.activeCourseId = null
        localStorage.removeItem('active_course_id')
      } finally {
        this.isLoading = false
      }
    },

    async setActiveCourse(courseId) {
      this.isLoading = true
      try {
        await api.post('/user/active-course', { courseId: courseId })
        this.activeCourseId = courseId
        localStorage.setItem('active_course_id', courseId)

        // Fetch full details to update UI immediately
        await this.fetchActiveCourse()
        
        return true
      } catch (error) {
        console.error('Failed to set active course:', error)
        this.error = error
        
        return false
      } finally {
        this.isLoading = false
      }
    },
    
    clearActiveCourse() {
      this.activeCourse = null
      this.activeCourseId = null
      localStorage.removeItem('active_course_id')
    },
  },
  
  getters: {
    hasActiveCourse: state => !!state.activeCourseId,
  },
})
