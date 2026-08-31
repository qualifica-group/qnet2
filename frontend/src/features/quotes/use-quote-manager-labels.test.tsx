import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useQuoteManagerLabels } from '@/features/quotes/use-quote-manager-labels'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'

/**
 * Spec 0087 D-8 — the offer's own REVENUE-line product categories win; the
 * linked Opportunity's product-line categories stand in only while there are
 * no REVENUE rows yet. Same univocity rule as spec 0080 (mirrored,
 * `use-opportunity-manager-labels.test.tsx`), exercised here through the
 * extra product -> category resolution step this hook owns (D-8 step 1).
 */

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

const fetchCategoryManagerLabelsMock = vi.fn()
const fetchOpportunityMock = vi.fn()
vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return {
    ...actual,
    fetchCategoryManagerLabels: (categoryId: number) => fetchCategoryManagerLabelsMock(categoryId),
    fetchOpportunity: (id: number) => fetchOpportunityMock(id),
  }
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function offerLine(productId: number | null): QuoteLineFormValues {
  return { product_id: productId, quantity: 1, unit_price: 10, vat_rate_id: null }
}

/** A products/for-select page carrying the `category_id` meta the resolver reads (spec 0075 AC-012). */
function productsPage(items: { id: number; categoryId: number }[]) {
  return {
    items: items.map(({ id, categoryId }) => ({
      id,
      label: `Product ${id}`,
      meta: { category_id: categoryId },
    })),
    pagination: { offset: 0, limit: 100, total: items.length },
  }
}

function opportunity(categoryIds: number[]): OpportunityDetailWithPermissions {
  return {
    id: 55,
    name: 'OPP_55',
    registry_id: 1,
    registry: null,
    status: { source: 'default', distinct_count: 0, entries: [] },
    referent_id: null,
    referent: null,
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    source_id: null,
    source: null,
    product_lines: categoryIds.map((id, index) => ({
      id: index + 1,
      business_function: { id: 40, name: 'BF' },
      product_category: { id, name: `Category ${id}` },
    })),
    lead_id: null,
    lead: null,
    managers: [],
    start_date: null,
    estimated_value: null,
    expected_close_date: null,
    success_probability: null,
    locked_fields: [],
    created_at: '2026-08-31T00:00:00Z',
    updated_at: '2026-08-31T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
  }
}

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchCategoryManagerLabelsMock.mockReset()
  fetchOpportunityMock.mockReset()
})

describe('useQuoteManagerLabels', () => {
  it('no revenue rows, no opportunity -> {}, no fetch at all', () => {
    const { result } = renderHook(() => useQuoteManagerLabels([], null), { wrapper: wrapper() })

    expect(result.current).toEqual({})
    expect(fetchForSelectMock).not.toHaveBeenCalled()
    expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalled()
  })

  it('D-8 fallback: no revenue rows yet -> resolves from the Opportunity’s own categories', async () => {
    fetchOpportunityMock.mockResolvedValue(opportunity([500]))
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '1': 'Commercial' })

    const { result } = renderHook(() => useQuoteManagerLabels([], 55), { wrapper: wrapper() })

    await waitFor(() => expect(result.current).toEqual({ '1': 'Commercial' }))
    expect(fetchCategoryManagerLabelsMock).toHaveBeenCalledWith(500)
  })

  it('D-8 step 1: a REVENUE row wins over the Opportunity fallback', async () => {
    fetchForSelectMock.mockResolvedValue(productsPage([{ id: 7, categoryId: 700 }]))
    fetchOpportunityMock.mockResolvedValue(opportunity([500]))
    fetchCategoryManagerLabelsMock.mockImplementation(async (categoryId: number) =>
      categoryId === 700 ? { '1': 'Operator' } : { '1': 'Commercial' },
    )

    const { result } = renderHook(() => useQuoteManagerLabels([offerLine(7)], 55), { wrapper: wrapper() })

    await waitFor(() => expect(result.current).toEqual({ '1': 'Operator' }))
    expect(fetchCategoryManagerLabelsMock).toHaveBeenCalledWith(700)
    expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalledWith(500)
  })

  it('ignores an in-progress row with no product picked yet', () => {
    const { result } = renderHook(() => useQuoteManagerLabels([offerLine(null)], null), { wrapper: wrapper() })

    expect(result.current).toEqual({})
    expect(fetchForSelectMock).not.toHaveBeenCalled()
  })

  it('several REVENUE products resolving to the SAME labels -> that set', async () => {
    fetchForSelectMock.mockResolvedValue(productsPage([{ id: 7, categoryId: 700 }, { id: 8, categoryId: 800 }]))
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '1': 'Operator' })

    const { result } = renderHook(() => useQuoteManagerLabels([offerLine(7), offerLine(8)], null), {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(result.current).toEqual({ '1': 'Operator' }))
  })

  it('several REVENUE products resolving to DIFFERENT labels -> {} (conflict, default)', async () => {
    fetchForSelectMock.mockResolvedValue(productsPage([{ id: 7, categoryId: 700 }, { id: 8, categoryId: 800 }]))
    fetchCategoryManagerLabelsMock.mockImplementation(async (categoryId: number) =>
      categoryId === 700 ? { '1': 'Operator' } : { '1': 'Consultant' },
    )

    const { result } = renderHook(() => useQuoteManagerLabels([offerLine(7), offerLine(8)], null), {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(fetchCategoryManagerLabelsMock).toHaveBeenCalledTimes(2))
    expect(result.current).toEqual({})
  })
})
