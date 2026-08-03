import { beforeAll, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'

/**
 * User directive 2026-08-03: an Italian UI was getting English server errors.
 * The API renders its messages in the request locale (backend
 * `App\Http\Middleware\SetLocale`), and this header is what tells it which one
 * — the app's OWN active language, not the browser's preference list.
 */

/** Captures the outgoing config instead of performing a request. */
async function capturedConfig(): Promise<InternalAxiosRequestConfig> {
  let captured: InternalAxiosRequestConfig | undefined

  await apiClient.get('/ping', {
    adapter: async (config) => {
      captured = config as InternalAxiosRequestConfig

      return { data: null, status: 200, statusText: 'OK', headers: {}, config }
    },
  })

  return captured as InternalAxiosRequestConfig
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('apiClient', () => {
  it('sends the app language as Accept-Language', async () => {
    await i18n.changeLanguage('it')

    expect((await capturedConfig()).headers['Accept-Language']).toBe('it')
  })

  it('follows a language switch without rebuilding the client', async () => {
    await i18n.changeLanguage('it')
    expect((await capturedConfig()).headers['Accept-Language']).toBe('it')

    await i18n.changeLanguage('en')
    expect((await capturedConfig()).headers['Accept-Language']).toBe('en')
  })
})
