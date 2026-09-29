import { describe, expect, it } from 'vitest'
import { AxiosError, type AxiosResponse } from 'axios'
import { recordUnavailableReason } from '@/lib/record-unavailable-reason'

function httpError(status: number): AxiosError {
  return new AxiosError('failed', undefined, undefined, undefined, { status } as AxiosResponse)
}

describe('recordUnavailableReason', () => {
  it('maps a 404 to notFound and a 403 to forbidden', () => {
    expect(recordUnavailableReason(httpError(404))).toBe('notFound')
    expect(recordUnavailableReason(httpError(403))).toBe('forbidden')
  })

  it('returns null for any other failure, so the caller keeps its retry state', () => {
    expect(recordUnavailableReason(httpError(500))).toBeNull()
    expect(recordUnavailableReason(new AxiosError('Network Error'))).toBeNull()
    expect(recordUnavailableReason(new Error('boom'))).toBeNull()
  })
})
