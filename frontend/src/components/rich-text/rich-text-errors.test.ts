import { AxiosError } from 'axios'
import { describe, expect, it } from 'vitest'
import { isPayloadTooLargeError } from '@/components/rich-text/rich-text-errors'

function axiosErrorWithStatus(status: number): AxiosError {
  const error = new AxiosError('failed')
  error.response = {
    status,
    statusText: '',
    headers: {},
    // @ts-expect-error minimal fake config, only `status` matters here
    config: {},
    data: null,
  }
  return error
}

describe('isPayloadTooLargeError (413 follow-up)', () => {
  it('is true for a 413 AxiosError', () => {
    expect(isPayloadTooLargeError(axiosErrorWithStatus(413))).toBe(true)
  })

  it('is false for a different AxiosError status', () => {
    expect(isPayloadTooLargeError(axiosErrorWithStatus(422))).toBe(false)
  })

  it('is false for a non-Axios error', () => {
    expect(isPayloadTooLargeError(new Error('boom'))).toBe(false)
  })

  it('is false for a non-error value', () => {
    expect(isPayloadTooLargeError(null)).toBe(false)
    expect(isPayloadTooLargeError(undefined)).toBe(false)
    expect(isPayloadTooLargeError('nope')).toBe(false)
  })
})
