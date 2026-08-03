import axios from 'axios'
import i18n from '@/i18n'
import { env } from '@/config/env'
import { tokenStorage } from '@/api/token-storage'

/**
 * Custom DOM event dispatched when the API rejects a request with 401.
 * The AuthProvider listens for it to clear the session and redirect to login.
 */
export const UNAUTHORIZED_EVENT = 'auth:unauthorized'

export const apiClient = axios.create({
  baseURL: env.apiUrl,
  headers: {
    Accept: 'application/json',
  },
  // NOTE: do NOT force a global `Content-Type`. Axios infers it per request:
  // `application/json` for plain-object bodies and `multipart/form-data` WITH
  // the boundary for FormData (file uploads). A forced `application/json` here
  // would strip the multipart boundary and break every file upload (the server
  // could no longer parse the body).
})

// Attach the Bearer token to every outgoing request, plus the language the
// API must answer in (user directive 2026-08-03: an Italian UI was surfacing
// English server messages). The header carries the APP's active language, not
// the browser's `navigator.languages` default: the interface already follows
// the user's own `locale`, and a validation message must speak the same
// language as the form it lands in. Read per request, so a language switch
// applies without rebuilding the client.
apiClient.interceptors.request.use((config) => {
  const token = tokenStorage.get()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  config.headers['Accept-Language'] = i18n.language
  return config
})

// On 401 the token is invalid/expired: drop it and notify the app once.
// A failed login is not a session expiry — let the form handle that response.
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      const isLoginRequest = error.config?.url?.endsWith('/auth/login')
      if (!isLoginRequest) {
        tokenStorage.clear()
        window.dispatchEvent(new Event(UNAUTHORIZED_EVENT))
      }
    }
    return Promise.reject(error)
  },
)
