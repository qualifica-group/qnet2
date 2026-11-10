import { describe, expect, it } from 'vitest'
import { AxiosError } from 'axios'
import { isTimeoutError } from '@/api/boot-request'

describe('isTimeoutError', () => {
  it.each(['ECONNABORTED', 'ETIMEDOUT'])('recognizes the axios timeout code %s', (code) => {
    expect(isTimeoutError(new AxiosError('timeout', code))).toBe(true)
  })

  it('rejects any other failure', () => {
    expect(isTimeoutError(new AxiosError('Network Error', 'ERR_NETWORK'))).toBe(false)
    expect(isTimeoutError(new Error('boom'))).toBe(false)
  })
})
