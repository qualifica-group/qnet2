import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchEffectiveAttributes } from '@/features/product-categories/api'
import { apiClient } from '@/api/client'

/** Spec 0061: the effective-attributes endpoint is context-scoped (`?context=product|opportunity`). */

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn() },
}))

const getMock = vi.mocked(apiClient.get)

beforeEach(() => {
  getMock.mockReset()
  getMock.mockResolvedValue({ data: { data: [] } })
})

describe('fetchEffectiveAttributes context wiring (spec 0061)', () => {
  it('defaults to the opportunity context when none is given (backward compatible)', async () => {
    await fetchEffectiveAttributes(7)

    expect(getMock).toHaveBeenCalledWith('/product-categories/7/effective-attributes', {
      params: { context: 'opportunity' },
    })
  })

  it('sends the product context when requested', async () => {
    await fetchEffectiveAttributes(7, 'product')

    expect(getMock).toHaveBeenCalledWith('/product-categories/7/effective-attributes', {
      params: { context: 'product' },
    })
  })

  it('sends the opportunity context explicitly', async () => {
    await fetchEffectiveAttributes(7, 'opportunity')

    expect(getMock).toHaveBeenCalledWith('/product-categories/7/effective-attributes', {
      params: { context: 'opportunity' },
    })
  })
})
