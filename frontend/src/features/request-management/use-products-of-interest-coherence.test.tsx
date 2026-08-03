import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { useProductsOfInterestCoherence } from '@/features/request-management/use-products-of-interest-coherence'

/**
 * Spec 0075, AC-017: on the FORM side of the coherence rule, changing the
 * product lines drops the products of interest the new classification no
 * longer covers — so the operator never reaches the server's refusal.
 */

const fetchForSelectMock = vi.fn()
const toastWarningMock = vi.fn()

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

vi.mock('sonner', () => ({
  toast: { warning: (...args: unknown[]) => toastWarningMock(...args) },
}))

const PRODUCTS = [
  { id: 4, label: 'Fibra 1000', meta: { category_id: 7 } },
  { id: 5, label: 'Gas casa', meta: { category_id: 9 } },
]

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderCoherence(productIds: number[]) {
  return renderHook(() => useProductsOfInterestCoherence(productIds), { wrapper: wrapper() })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  toastWarningMock.mockReset()
  fetchForSelectMock.mockResolvedValue({
    items: PRODUCTS,
    pagination: { offset: 0, limit: 25, total: PRODUCTS.length },
    export_link: null,
  })
})

describe('useProductsOfInterestCoherence (spec 0075, D-5)', () => {
  it('AC-017: drops the products whose category left the lines, naming them', async () => {
    const { result } = renderCoherence([4, 5])

    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalled())
    await waitFor(() =>
      expect(result.current([{ business_function_id: 3, product_category_id: 9 }])).toEqual([5]),
    )

    expect(toastWarningMock).toHaveBeenCalledWith(expect.stringContaining('Fibra 1000'))
  })

  it('keeps every product still covered, and says nothing', async () => {
    const { result } = renderCoherence([4, 5])

    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalled())
    await waitFor(() =>
      expect(
        result.current([
          { business_function_id: 3, product_category_id: 7 },
          { business_function_id: 3, product_category_id: 9 },
        ]),
      ).toEqual([4, 5]),
    )

    expect(toastWarningMock).not.toHaveBeenCalled()
  })

  it('keeps a product whose category is not resolved yet: the server has the last word', () => {
    const { result } = renderCoherence([4])

    expect(result.current([{ business_function_id: 3, product_category_id: 9 }])).toEqual([4])
    expect(toastWarningMock).not.toHaveBeenCalled()
  })

  it('drops a half-filled row from the covered set (its category is still null)', async () => {
    const { result } = renderCoherence([4])

    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalled())
    await waitFor(() =>
      expect(result.current([{ business_function_id: 3, product_category_id: null }])).toEqual([]),
    )
  })
})
