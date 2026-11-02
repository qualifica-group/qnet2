import { beforeAll, describe, expect, it } from 'vitest'
import type { InternalAxiosRequestConfig } from 'axios'
import i18n from '@/i18n'
import { AxiosError } from 'axios'
import { apiClient } from '@/api/client'
import { forbiddenMessage } from '@/lib/forbidden-message'

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

  it('turns a JSON error blob into data, so a 403 reads as its specific sentence', async () => {
    const envelope = {
      success: false,
      message: 'Forbidden',
      authorization: { reason: 'missing_permission', permission: 'quotes.export' },
    }
    const failure = apiClient
      .get('/export', {
        responseType: 'blob',
        adapter: async (config) => {
          throw new AxiosError('Forbidden', '403', config, undefined, {
            status: 403,
            statusText: 'Forbidden',
            headers: {},
            config,
            data: new Blob([JSON.stringify(envelope)], { type: 'application/json' }),
          })
        },
      })
      .catch((error: unknown) => error)

    const error = await failure

    expect((error as AxiosError).response?.data).toEqual(envelope)
    expect(forbiddenMessage(error, i18n)).toMatch(/You do not have the «.+» permission on the «.+» module\./)
  })

  it('leaves a non-JSON error body as a Blob', async () => {
    const error = await apiClient
      .get('/export', {
        responseType: 'blob',
        adapter: async (config) => {
          throw new AxiosError('Bad', '500', config, undefined, {
            status: 500,
            statusText: 'Error',
            headers: {},
            config,
            data: new Blob(['<html>oops</html>']),
          })
        },
      })
      .catch((e: unknown) => e)

    expect((error as AxiosError).response?.data).toBeInstanceOf(Blob)
  })
})
