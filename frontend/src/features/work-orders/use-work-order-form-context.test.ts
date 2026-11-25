// @vitest-environment jsdom
import { createElement, type ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fetchWorkOrderFormContext } from '@/features/work-orders/api'
import { useWorkOrderFormContext } from '@/features/work-orders/use-work-order-form-context'
import type { WorkOrderFormContext } from '@/features/work-orders/types'

vi.mock('@/features/work-orders/api', () => ({ fetchWorkOrderFormContext: vi.fn() }))

const RESOLVED: WorkOrderFormContext = { applicable_attributes: [], attribute_layout: null, locked_attributes: [] }

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => createElement(QueryClientProvider, { client }, children)
}

beforeEach(() => {
  vi.mocked(fetchWorkOrderFormContext).mockReset()
  vi.mocked(fetchWorkOrderFormContext).mockResolvedValue(RESOLVED)
})

// Spec 0431 (D-17): a work order born without an offer previews its flexible fields from its products and categories.
describe('useWorkOrderFormContext — work order without an offer', () => {
  it('asks for the fields of the chosen products and categories', async () => {
    renderHook(() => useWorkOrderFormContext([], undefined, { productIds: [41, 40], productCategoryIds: [7] }), {
      wrapper: wrapper(),
    })

    await waitFor(() =>
      expect(fetchWorkOrderFormContext).toHaveBeenCalledWith([], { productIds: [40, 41], productCategoryIds: [7] }),
    )
  })

  it('uses the saved set while products and categories are the saved ones', () => {
    const persisted = { quoteLineIds: [], standalone: { productIds: [40], productCategoryIds: [7] }, context: RESOLVED }

    const { result } = renderHook(
      () => useWorkOrderFormContext([], persisted, { productIds: [40], productCategoryIds: [7] }),
      { wrapper: wrapper() },
    )

    expect(result.current.context).toBe(RESOLVED)
    expect(fetchWorkOrderFormContext).not.toHaveBeenCalled()
  })

  it('asks nothing while nothing is picked', () => {
    renderHook(() => useWorkOrderFormContext([], undefined, { productIds: [], productCategoryIds: [] }), {
      wrapper: wrapper(),
    })

    expect(fetchWorkOrderFormContext).not.toHaveBeenCalled()
  })
})
