import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createVatRate, fetchVatRate, updateVatRate } from '@/features/vat-rates/api'
import { apiClient } from '@/api/client'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
}))

const getMock = vi.mocked(apiClient.get)
const postMock = vi.mocked(apiClient.post)
const patchMock = vi.mocked(apiClient.patch)

const PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

beforeEach(() => {
  getMock.mockReset()
  postMock.mockReset()
  patchMock.mockReset()
})

/**
 * Laravel's `decimal:2` cast serializes `rate` as a string ("22.00"). The api
 * layer must coerce it to a number so `VatRateDetail.rate` is honest and the
 * plain `z.number()` form resolver accepts an untouched edit-mode value.
 */
describe('vat-rates api rate normalization', () => {
  it('coerces the wire decimal string to a number on fetch', async () => {
    getMock.mockResolvedValue({
      data: {
        data: { id: 9, name: 'IVA 22%', rate: '22.00', created_at: null },
        permissions: PERMISSIONS,
      },
    })

    const result = await fetchVatRate(9)

    expect(result.rate).toBe(22)
    expect(typeof result.rate).toBe('number')
  })

  it('coerces the wire decimal string to a number on create', async () => {
    postMock.mockResolvedValue({
      data: { data: { id: 3, name: 'IVA 10%', rate: '10.00', created_at: null } },
    })

    const result = await createVatRate({ name: 'IVA 10%', rate: 10 })

    expect(result.rate).toBe(10)
  })

  it('coerces the wire decimal string to a number on update', async () => {
    patchMock.mockResolvedValue({
      data: { data: { id: 3, name: 'IVA 4%', rate: '4.00', created_at: null }, permissions: PERMISSIONS },
    })

    const result = await updateVatRate(3, { rate: 4 })

    expect(result.rate).toBe(4)
    expect(result.permissions).toEqual(PERMISSIONS)
  })
})
