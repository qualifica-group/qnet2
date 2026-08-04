import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { resolveManagerLabels, useOpportunityManagerLabels } from '@/features/opportunities/use-opportunity-manager-labels'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * Spec 0080, decision 1 — the `OpportunityManagerLabelResolver` univocity
 * rule, mirrored client-side: zero categories -> `{}`; one -> its labels;
 * several -> the shared result only when every one resolves IDENTICALLY,
 * otherwise `{}` (a conflict). Compared on the resolved set, never the
 * category id (AC-021/AC-022).
 */

const fetchCategoryManagerLabelsMock = vi.fn()
vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return {
    ...actual,
    fetchCategoryManagerLabels: (categoryId: number) => fetchCategoryManagerLabelsMock(categoryId),
  }
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function line(productCategoryId: number | null): ProductLineRow {
  return { business_function_id: 40, product_category_id: productCategoryId }
}

describe('resolveManagerLabels (pure)', () => {
  it('AC-023: no categories -> {}', () => {
    expect(resolveManagerLabels([])).toEqual({})
  })

  it('AC-020: one category -> its own labels', () => {
    expect(resolveManagerLabels([{ '1': 'Commercial' }])).toEqual({ '1': 'Commercial' })
  })

  it('AC-022: several categories resolving to the SAME set -> that set (not a conflict)', () => {
    expect(resolveManagerLabels([{ '1': 'Commercial' }, { '1': 'Commercial' }])).toEqual({ '1': 'Commercial' })
  })

  it('order of keys does not matter for the equality check', () => {
    expect(resolveManagerLabels([{ '1': 'A', '2': 'B' }, { '2': 'B', '1': 'A' }])).toEqual({ '1': 'A', '2': 'B' })
  })

  it('AC-021: several categories resolving to DIFFERENT sets -> {} (conflict, default)', () => {
    expect(resolveManagerLabels([{ '1': 'Commercial' }, { '1': 'Consultant' }])).toEqual({})
  })
})

describe('useOpportunityManagerLabels', () => {
  beforeEach(() => {
    fetchCategoryManagerLabelsMock.mockReset()
  })

  it('AC-023: no product lines -> {}, no fetch', () => {
    const { result } = renderHook(() => useOpportunityManagerLabels([]), { wrapper: wrapper() })

    expect(result.current).toEqual({})
    expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalled()
  })

  it('ignores in-progress rows with no category picked yet', () => {
    const { result } = renderHook(() => useOpportunityManagerLabels([line(null)]), { wrapper: wrapper() })

    expect(result.current).toEqual({})
    expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalled()
  })

  it('AC-020: one category -> its resolved labels', async () => {
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '1': 'Commercial' })

    const { result } = renderHook(() => useOpportunityManagerLabels([line(500)]), { wrapper: wrapper() })

    await waitFor(() => expect(result.current).toEqual({ '1': 'Commercial' }))
    expect(fetchCategoryManagerLabelsMock).toHaveBeenCalledWith(500)
  })

  it('dedupes repeated product lines on the same category into a single fetch', async () => {
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '1': 'Commercial' })

    renderHook(() => useOpportunityManagerLabels([line(500), line(500)]), { wrapper: wrapper() })

    await waitFor(() => expect(fetchCategoryManagerLabelsMock).toHaveBeenCalledTimes(1))
  })

  it('AC-022: two DIFFERENT categories resolving to the SAME labels -> that set', async () => {
    fetchCategoryManagerLabelsMock.mockImplementation(async () => ({ '1': 'Commercial' }))

    const { result } = renderHook(() => useOpportunityManagerLabels([line(500), line(600)]), {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(result.current).toEqual({ '1': 'Commercial' }))
  })

  it('AC-021: two DIFFERENT categories resolving to DIFFERENT labels -> {} (default)', async () => {
    fetchCategoryManagerLabelsMock.mockImplementation(async (categoryId: number) =>
      categoryId === 500 ? { '1': 'Commercial' } : { '1': 'Consultant' },
    )

    const { result } = renderHook(() => useOpportunityManagerLabels([line(500), line(600)]), {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(fetchCategoryManagerLabelsMock).toHaveBeenCalledTimes(2))
    expect(result.current).toEqual({})
  })
})
