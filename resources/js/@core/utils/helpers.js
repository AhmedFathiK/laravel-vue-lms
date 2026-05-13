// 👉 IsEmpty
export const isEmpty = value => {
  if (value === null || value === undefined || value === '')
    return true
  
  return !!(Array.isArray(value) && value.length === 0)
}

// 👉 IsNullOrUndefined
export const isNullOrUndefined = value => {
  return value === null || value === undefined
}

// 👉 IsEmptyArray
export const isEmptyArray = arr => {
  return Array.isArray(arr) && arr.length === 0
}

// 👉 IsObject
export const isObject = obj => obj !== null && !!obj && typeof obj === 'object' && !Array.isArray(obj)

// 👉 IsToday
export const isToday = date => {
  const today = new Date()
  
  return (date.getDate() === today.getDate()
        && date.getMonth() === today.getMonth()
        && date.getFullYear() === today.getFullYear())
}

/**
 * Determines the text direction (LTR or RTL) based on content.
 * If the text is a mix or doesn't contain definitive characters, it defaults to the UI direction.
 * 
 * @param {string} text - The text to analyze
 * @param {string} uiLanguageDirection - The current UI direction ('ltr' or 'rtl')
 * @returns {string} 'ltr' | 'rtl'
 */
export const getTextDirection = (text, uiLanguageDirection = 'ltr') => {
  if (!text || typeof text !== 'string') return uiLanguageDirection

  // Arabic/Persian/Hebrew character ranges
  const rtlRegex = /[\u0591-\u07FF\uFB1D-\uFDFD\uFE70-\uFEFC]/
  
  // Latin character ranges (English, French, German, Spanish, etc.)
  const ltrRegex = /[a-z\u00C0-\u00FF\u0100-\u017F]/i

  const hasRtl = rtlRegex.test(text)
  const hasLtr = ltrRegex.test(text)

  // If it's purely one or the other, return that direction
  if (hasRtl && !hasLtr) return 'rtl'
  if (hasLtr && !hasRtl) return 'ltr'

  // If it's a mix or has neither (e.g., just numbers/symbols), use UI direction
  return uiLanguageDirection
}
