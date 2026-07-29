import { beforeEach, describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import {
  createCommissionConfiguration,
  deleteCommissionConfiguration,
  fetchCommissionConfiguration,
  updateCommissionConfiguration,
} from './api'
import type { CommissionConfigurationPayload } from './types'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const detail = {
  id: 1,
  name: 'Rule',
  recipient_role: 'COMMERCIAL',
  application_scope: 'PRODUCT',
  product_category_id: null,
  product_category: null,
  product_id: 3,
  product: { id: 3, name: 'Product' },
  commission_type: 'PERCENTAGE',
  value: '5.0000',
  priority: 1,
  valid_from: '2026-07-29',
  valid_until: null,
  status: 'ACTIVE',
  internal_note: null,
  created_at: '2026-07-29T00:00:00Z',
  updated_at: '2026-07-29T00:00:00Z',
} as const
const payload: CommissionConfigurationPayload = {
  name: 'Rule',
  recipient_role: 'COMMERCIAL',
  application_scope: 'PRODUCT',
  product_category_id: null,
  product_id: 3,
  commission_type: 'PERCENTAGE',
  value: 5,
  priority: 1,
  valid_from: '2026-07-29',
  valid_until: null,
  status: 'ACTIVE',
  internal_note: null,
}

describe('commission configuration API', () => {
  beforeEach(() => vi.clearAllMocks())

  it('unwraps detail with permissions', async () => {
    vi.mocked(apiClient.get).mockResolvedValue({ data: { data: detail, permissions: { resource: {}, fields: {}, actions: {} } } })
    await expect(fetchCommissionConfiguration(1)).resolves.toMatchObject({ id: 1, permissions: expect.any(Object) })
    expect(apiClient.get).toHaveBeenCalledWith('/commission-configurations/1')
  })

  it('creates, updates and deletes through the dedicated endpoints', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: { data: detail } })
    vi.mocked(apiClient.patch).mockResolvedValue({ data: { data: detail } })
    vi.mocked(apiClient.delete).mockResolvedValue({})
    await expect(createCommissionConfiguration(payload)).resolves.toEqual(detail)
    await expect(updateCommissionConfiguration(1, { name: 'Rule' })).resolves.toEqual(detail)
    await deleteCommissionConfiguration(1)
    expect(apiClient.post).toHaveBeenCalledWith('/commission-configurations', payload)
    expect(apiClient.patch).toHaveBeenCalledWith('/commission-configurations/1', { name: 'Rule' })
    expect(apiClient.delete).toHaveBeenCalledWith('/commission-configurations/1')
  })
})
