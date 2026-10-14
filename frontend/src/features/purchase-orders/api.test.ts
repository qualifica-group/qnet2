import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  changePurchaseOrderStatus,
  createPurchaseOrder,
  fetchMergeCandidates,
  mergePurchaseOrders,
  updatePurchaseOrder,
} from '@/features/purchase-orders/api'
import { makePurchaseOrder } from '@/features/purchase-orders/purchase-order-fixtures'
import type { PurchaseOrderPayload } from '@/features/purchase-orders/types'

const { getMock, postMock, patchMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  postMock: vi.fn(),
  patchMock: vi.fn(),
}))

vi.mock('@/api/client', () => ({
  apiClient: { get: getMock, post: postMock, patch: patchMock, delete: vi.fn() },
}))

const PAYLOAD: PurchaseOrderPayload = {
  supplier_id: 8,
  company_id: 1,
  company_site_id: 2,
  operational_site_id: null,
  order_date: '2026-10-12',
  description: 'Monthly consulting',
  general_notes: null,
  payment_method_id: null,
  is_internal_user: false,
  customer_id: null,
  work_order_id: null,
  quote_id: null,
  opportunity_id: null,
  sector_ids: [],
  sector_notes: null,
  copies: 3,
  lines: [],
}

beforeEach(() => {
  getMock.mockReset()
  postMock.mockReset()
  patchMock.mockReset()
})

describe('purchase orders api (spec 0210 contract)', () => {
  it('create resolves with the first order and every created id (meta.created_ids)', async () => {
    const order = makePurchaseOrder()
    postMock.mockResolvedValue({ data: { success: true, message: 'ok', data: order, meta: { created_ids: [5, 6, 7] } } })

    const result = await createPurchaseOrder(PAYLOAD)

    expect(postMock).toHaveBeenCalledWith('/purchase-orders', PAYLOAD)
    expect(result).toEqual({ order, createdIds: [5, 6, 7] })
  })

  it('create falls back to the single created id when meta is absent', async () => {
    postMock.mockResolvedValue({ data: { success: true, message: 'ok', data: makePurchaseOrder({ id: 9 }) } })

    expect((await createPurchaseOrder(PAYLOAD)).createdIds).toEqual([9])
  })

  it('update PATCHes the partial payload to the order (spec 0257 D-1)', async () => {
    patchMock.mockResolvedValue({ data: { success: true, message: 'ok', data: makePurchaseOrder() } })

    await updatePurchaseOrder(5, PAYLOAD)

    expect(patchMock).toHaveBeenCalledWith('/purchase-orders/5', PAYLOAD)
  })

  it('status change posts only to_status', async () => {
    postMock.mockResolvedValue({ data: { success: true, message: 'ok', data: makePurchaseOrder() } })

    await changePurchaseOrderStatus(5, 'approved')

    expect(postMock).toHaveBeenCalledWith('/purchase-orders/5/status', { to_status: 'approved' })
  })

  it('merge posts line_ids and reads meta.deleted_purchase_order_ids', async () => {
    postMock.mockResolvedValue({
      data: { success: true, message: 'ok', data: makePurchaseOrder(), meta: { deleted_purchase_order_ids: [12] } },
    })

    const result = await mergePurchaseOrders(5, [101, 102])

    expect(postMock).toHaveBeenCalledWith('/purchase-orders/5/merge', { line_ids: [101, 102] })
    expect(result.deletedIds).toEqual([12])
  })

  it('merge candidates unwraps the envelope', async () => {
    getMock.mockResolvedValue({ data: { success: true, message: 'ok', data: [] } })

    expect(await fetchMergeCandidates(5)).toEqual([])
    expect(getMock).toHaveBeenCalledWith('/purchase-orders/5/merge-candidates')
  })
})
