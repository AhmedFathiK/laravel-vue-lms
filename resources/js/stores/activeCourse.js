import { defineStore } from 'pinia'
import api from '@/utils/api'

export const useActiveCourse = defineStore('activeCourse', {
  state: () => ({
    // Initialize from localStorage BUT validate it later
    // We should not blindly trust localStorage if the API says otherwise
    activeCourseId: localStorage.getItem('active_course_id') ? parseInt(localStorage.getItem('active_course_id')) : null,
    activeCourse: null,
    isLoading: false,
    error: null,
  }),

  actions: {
    async fetchActiveCourse() {
      console.log('Store: fetchActiveCourse called')
      this.isLoading = true
      this.error = null
      try {
        const response = await api.get('/user/active-course')

        console.log('Store: API response', response)
        
        // Handle null response explicitly (user has no active course)
        if (!response) {
          console.log('Store: Response null, clearing state')
          this.activeCourse = null
          this.activeCourseId = null
          localStorage.removeItem('active_course_id')
          
          return
        }

        // API returns CourseResource which is wrapped in 'data' usually
        const course = response.data || response 

        if (course && course.id) {
          console.log('Store: Valid course found', course.id)
          this.activeCourse = course
          this.activeCourseId = course.id
          localStorage.setItem('active_course_id', course.id)
        } else {
          console.log('Store: Course object invalid or missing ID', course)
          this.activeCourse = null
          this.activeCourseId = null
          localStorage.removeItem('active_course_id')
        }
      } catch (error) {
        console.error('Failed to fetch active course:', error)

        // If 404 or other error, assume no active course
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
