import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import type { ForSelectItem, ForSelectParams } from '@/features/for-select/types'
import type { ProductLineRow } from '@/features/product-lines/types'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { isPristineLineRow } from '@/features/quotes/quote-line-values'
import type { RequestOfferLinesFormShape } from '@/features/request-management/request-offer-lines-section'
import {
  resetUncoveredOfferRows,
  useOfferLinesCoherence,
} from '@/features/request-management/use-offer-lines-coherence'

/**
 * Bug 2026-10-05: sostituendo la linea di prodotto, la riga d'offerta restava
 * sul prodotto della categoria appena tolta mentre il suo picker offriva gia'
 * solo i prodotti della nuova. Il cambio di classificazione svuota le righe
 * il cui prodotto non e' piu' coperto.
 */

const OLD_CATEGORY_ID = 10
const NEW_CATEGORY_ID = 20
const KEPT_CATEGORY_ID = 30

/** A for-select product as the endpoint returns it: `meta.category_id` is what the coherence reads. */
type ProductItem = ForSelectItem & { meta: { category_id: number } }

const OLD_PRODUCT: ProductItem = { id: 100, label: 'Corso vecchio', meta: { category_id: OLD_CATEGORY_ID } }
const KEPT_PRODUCT: ProductItem = { id: 300, label: 'Corso tenuto', meta: { category_id: KEPT_CATEGORY_ID } }
const PRODUCTS: ProductItem[] = [OLD_PRODUCT, KEPT_PRODUCT]

const toastWarningMock = vi.fn()
vi.mock('sonner', () => ({ toast: { warning: (...args: unknown[]) => toastWarningMock(...args) } }))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

function offerRow(productId: number, id?: number): QuoteLineFormValues {
  return {
    ...(id === undefined ? {} : { id }),
    product_id: productId,
    quantity: 1,
    unit_of_measure: null,
    unit_price: 50,
    vat_rate_id: 7,
  }
}

function productLines(...categoryIds: number[]): ProductLineRow[] {
  return categoryIds.map((categoryId) => ({ root_category_id: null, product_category_id: categoryId }))
}

const PRODUCTS_BY_ID = new Map<number, ForSelectItem>(PRODUCTS.map((product) => [product.id, product]))

describe('resetUncoveredOfferRows', () => {
  it('empties in place the row whose product category left the classification', () => {
    const rows = [offerRow(OLD_PRODUCT.id, 1), offerRow(KEPT_PRODUCT.id, 2)]

    const result = resetUncoveredOfferRows(rows, productLines(NEW_CATEGORY_ID, KEPT_CATEGORY_ID), PRODUCTS_BY_ID)

    expect(result.droppedLabels).toEqual(['Corso vecchio'])
    expect(result.rows).toHaveLength(2)
    expect(isPristineLineRow(result.rows[0])).toBe(true)
    expect(result.rows[1]).toBe(rows[1])
  })

  it('returns the same array when every product is still covered', () => {
    const rows = [offerRow(KEPT_PRODUCT.id)]

    const result = resetUncoveredOfferRows(rows, productLines(KEPT_CATEGORY_ID), PRODUCTS_BY_ID)

    expect(result.rows).toBe(rows)
    expect(result.droppedLabels).toEqual([])
  })

  it('keeps a product whose category is not resolved yet', () => {
    const rows = [offerRow(999)]

    const result = resetUncoveredOfferRows(rows, productLines(NEW_CATEGORY_ID), PRODUCTS_BY_ID)

    expect(result.rows).toBe(rows)
  })
})

describe('useOfferLinesCoherence', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('it')
  })

  beforeEach(() => {
    toastWarningMock.mockReset()
    fetchForSelectMock.mockReset()
    fetchForSelectMock.mockImplementation((_resource: string, params: ForSelectParams = {}) => {
      const items = PRODUCTS.filter((product) => params.ids?.includes(product.id))
      return Promise.resolve({
        items,
        pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1 },
        export_link: null,
      })
    })
  })

  function renderCoherence(rows: QuoteLineFormValues[]) {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const wrapper = ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={client}>{children}</QueryClientProvider>
    )

    const rendered = renderHook(
      () => {
        const form = useForm<RequestOfferLinesFormShape>({
          defaultValues: { product_lines: productLines(OLD_CATEGORY_ID), offer_lines: rows },
        })
        const resetUncovered = useOfferLinesCoherence(form.control)
        return { form, resetUncovered }
      },
      { wrapper },
    )

    return { ...rendered, client }
  }

  /** The by-ids product query has come back: the product -> category mapping is known. */
  async function productsResolved(client: QueryClient) {
    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalled())
    await waitFor(() => expect(client.isFetching()).toBe(0))
  }

  it('clears the picked product when its product line is replaced, with a notice naming it', async () => {
    const { result, client } = renderCoherence([offerRow(OLD_PRODUCT.id, 1)])
    await productsResolved(client)

    act(() => result.current.resetUncovered(productLines(NEW_CATEGORY_ID)))

    expect(isPristineLineRow(result.current.form.getValues('offer_lines')[0])).toBe(true)
    expect(toastWarningMock).toHaveBeenCalledWith(expect.stringContaining('Corso vecchio'))
  })

  it('leaves the rows alone when the product line still covers their product', async () => {
    const rows = [offerRow(OLD_PRODUCT.id, 1)]
    const { result, client } = renderCoherence(rows)
    await productsResolved(client)

    act(() => result.current.resetUncovered(productLines(OLD_CATEGORY_ID, NEW_CATEGORY_ID)))

    expect(result.current.form.getValues('offer_lines')[0].product_id).toBe(OLD_PRODUCT.id)
    expect(toastWarningMock).not.toHaveBeenCalled()
  })
})
