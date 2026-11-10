import { describe, expect, it } from 'vitest'
import { AxiosError } from 'axios'
import { configQueryOptions } from '@/features/config/query-options'

describe('configQueryOptions.retry', () => {
  it('retries a transient failure up to three times', () => {
    const networkError = new AxiosError('Network Error', 'ERR_NETWORK')

    expect(configQueryOptions.retry(0, networkError)).toBe(true)
    expect(configQueryOptions.retry(2, networkError)).toBe(true)
    expect(configQueryOptions.retry(3, networkError)).toBe(false)
  })

  it('never retries a timeout, so the Retry screen shows at once', () => {
    expect(configQueryOptions.retry(0, new AxiosError('timeout', 'ECONNABORTED'))).toBe(false)
  })
})
