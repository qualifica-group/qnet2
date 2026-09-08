import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { ProductLineRow } from '@/features/product-lines/types'
import { useRequestManagerLabels } from '@/features/request-management/use-request-manager-labels'

/**
 * User directive 2026-09-08: in Gestione Richieste the "Gestori Account" kept
 * the labels the server had resolved at load time, so changing the categoria
 * prodotto renamed nothing. The names must follow the FORM, with the server's
 * own precedence untouched (`QuoteManagerLabelResolver`, spec 0087 D-8): the
 * offer rows' product categories win, the request's own categorie prodotto
 * are the fallback.
 */

const fetchCategoryManagerLabelsMock = vi.fn()
vi.mock('@/features/opportunities/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/opportunities/api')>()),
  fetchCategoryManagerLabels: (...args: unknown[]) => fetchCategoryManagerLabelsMock(...args),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

/** The two categories this suite resolves against, each with its own denominations. */
const CONSULTING_CATEGORY = 7
const ENERGY_CATEGORY = 9

/** The product whose category is `ENERGY_CATEGORY`, as the for-select projects it (spec 0075 AC-012). */
const ENERGY_PRODUCT = 55

const LABELS_BY_CATEGORY: Record<number, Record<string, string>> = {
  [CONSULTING_CATEGORY]: { '1': 'Senior consultant', '2': 'Consultant' },
  [ENERGY_CATEGORY]: { '1': 'Energy manager' },
}

function productLine(categoryId: number | null): ProductLineRow {
  return { business_function_id: 1, product_category_id: categoryId }
}

/** A revenue row carrying only what the resolution reads: its product. */
function offerLine(productId: number | null): QuoteLineFormValues {
  return { product_id: productId } as QuoteLineFormValues
}

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

beforeEach(() => {
  fetchCategoryManagerLabelsMock.mockReset()
  fetchCategoryManagerLabelsMock.mockImplementation((categoryId: number) =>
    Promise.resolve(LABELS_BY_CATEGORY[categoryId] ?? {}),
  )
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue({
    items: [{ id: ENERGY_PRODUCT, label: 'Solar plant', meta: { category_id: ENERGY_CATEGORY } }],
    pagination: { total: 1, offset: 0, limit: 25 },
  })
})

describe('useRequestManagerLabels', () => {
  it('resolves the labels of the categoria prodotto the form carries', async () => {
    const { result } = renderHook(() => useRequestManagerLabels([], [productLine(CONSULTING_CATEGORY)]), { wrapper })

    await waitFor(() => expect(result.current).toEqual({ '1': 'Senior consultant', '2': 'Consultant' }))
  })

  /** The defect itself: the label must follow the field, not the load. */
  it('relabels when the categoria prodotto changes', async () => {
    const { result, rerender } = renderHook(
      ({ lines }: { lines: ProductLineRow[] }) => useRequestManagerLabels([], lines),
      { wrapper, initialProps: { lines: [productLine(CONSULTING_CATEGORY)] } },
    )

    await waitFor(() => expect(result.current).toEqual({ '1': 'Senior consultant', '2': 'Consultant' }))

    rerender({ lines: [productLine(ENERGY_CATEGORY)] })

    await waitFor(() => expect(result.current).toEqual({ '1': 'Energy manager' }))
  })

  /** Precedence unchanged (user decision 2026-09-08): revenue rows first, categoria prodotto as fallback. */
  it("prefers the offer rows' own product categories over the categoria prodotto", async () => {
    const { result } = renderHook(
      () => useRequestManagerLabels([offerLine(ENERGY_PRODUCT)], [productLine(CONSULTING_CATEGORY)]),
      { wrapper },
    )

    await waitFor(() => expect(result.current).toEqual({ '1': 'Energy manager' }))
    expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalledWith(CONSULTING_CATEGORY)
  })

  /** `null` = nothing to say: the caller keeps its own provisional labels rather than flashing the defaults. */
  it('stays null while a row has no category yet', async () => {
    const { result } = renderHook(() => useRequestManagerLabels([], [productLine(null)]), { wrapper })

    await waitFor(() => expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalled())
    expect(result.current).toBeNull()
  })

  it('stays null until every category has resolved', async () => {
    let release: (labels: Record<string, string>) => void = () => {}
    fetchCategoryManagerLabelsMock.mockImplementation(
      () => new Promise<Record<string, string>>((resolve) => (release = resolve)),
    )

    const { result } = renderHook(() => useRequestManagerLabels([], [productLine(CONSULTING_CATEGORY)]), { wrapper })

    expect(result.current).toBeNull()

    release({ '2': 'Consultant' })
    await waitFor(() => expect(result.current).toEqual({ '2': 'Consultant' }))
  })
})
