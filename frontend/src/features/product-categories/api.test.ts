import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchEffectiveAttributes, fetchEffectiveManagerLabels } from '@/features/product-categories/api'
import { apiClient } from '@/api/client'

/** Spec 0061/0084: the effective-attributes endpoint is context-scoped (`?context=product|quote`), with no default slice. */

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn() },
}))

const getMock = vi.mocked(apiClient.get)

beforeEach(() => {
  getMock.mockReset()
  getMock.mockResolvedValue({ data: { data: [] } })
})

describe('fetchEffectiveAttributes context wiring (spec 0061)', () => {
  it('sends the product context when requested', async () => {
    await fetchEffectiveAttributes(7, 'product')

    expect(getMock).toHaveBeenCalledWith('/product-categories/7/effective-attributes', {
      params: { context: 'product' },
    })
  })

  it('sends the quote context when requested', async () => {
    await fetchEffectiveAttributes(7, 'quote')

    expect(getMock).toHaveBeenCalledWith('/product-categories/7/effective-attributes', {
      params: { context: 'quote' },
    })
  })
})

/** Spec 0080: unwraps `data.data.manager_labels`, distinct from the flat-array shape of `fetchEffectiveAttributes`. */
describe('fetchEffectiveManagerLabels (spec 0080)', () => {
  it('requests the category-scoped endpoint and returns the resolved labels', async () => {
    getMock.mockResolvedValueOnce({ data: { data: { manager_labels: { '1': 'Commercial', '2': 'Operator' } } } })

    const result = await fetchEffectiveManagerLabels(7)

    expect(getMock).toHaveBeenCalledWith('/product-categories/7/effective-manager-labels')
    expect(result).toEqual({ '1': 'Commercial', '2': 'Operator' })
  })
})
