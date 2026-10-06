import { describe, expect, it, vi } from 'vitest'
import { apiClient } from '@/api/client'
import { updateWorkOrder } from '@/features/work-orders/api'

vi.mock('@/api/client', () => ({
  apiClient: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const PERMISSIONS = {
  resource: { view: true, create: false, update: true, delete: false, export: false, import: false },
  fields: { title: { visible: true, editable: true, required: true } },
  actions: { view_costs: true },
}

describe('updateWorkOrder', () => {
  // The in-place detail writes the PATCH result straight into the detail
  // cache: without the permissions its gates would read `undefined`.
  it('returns the saved work order together with its re-evaluated permissions', async () => {
    vi.mocked(apiClient.patch).mockResolvedValue({
      data: { success: true, message: 'ok', data: { id: 3, title: 'Nuovo titolo' }, permissions: PERMISSIONS },
    })

    const saved = await updateWorkOrder(3, { title: 'Nuovo titolo' })

    expect(apiClient.patch).toHaveBeenCalledWith('/work-orders/3', { title: 'Nuovo titolo' })
    expect(saved).toEqual({ id: 3, title: 'Nuovo titolo', permissions: PERMISSIONS })
  })
})
