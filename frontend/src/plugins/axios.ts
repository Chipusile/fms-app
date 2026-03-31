import axios from 'axios'
import type { ApiError } from '@/types'

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || '/api/v1'
const csrfUrl = import.meta.env.VITE_CSRF_URL || '/sanctum/csrf-cookie'

const api = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true, // Required for Sanctum SPA cookie auth
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

// Response interceptor for consistent error handling
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Session expired — redirect to login
      const currentPath = window.location.pathname
      if (currentPath !== '/login') {
        window.location.href = '/login'
      }
    }

    const apiError: ApiError = {
      message: error.response?.data?.message || 'An unexpected error occurred',
      code: error.response?.data?.code,
      errors: error.response?.data?.errors,
    }

    return Promise.reject(apiError)
  },
)

/**
 * Initialise CSRF cookie before authenticated requests.
 * Sanctum requires this for SPA authentication.
 */
export async function initCsrf(): Promise<void> {
  await axios.get(csrfUrl, {
    withCredentials: true,
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
    },
  })
}

export default api
