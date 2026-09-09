import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import type { ForSelectParams, PaginatedResponse } from '@/features/for-select/types'
import type { ForSelectItem } from '@/features/for-select/types'
import { EMPTY_LINE_ROW } from '@/features/quotes/use-quote-lines-field'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'
import {
  RequestOfferLinesField,
  type RequestOfferLinesFormShape,
} from '@/features/request-management/request-offer-lines-section'
import { seedSoleProductRows } from '@/features/request-management/use-offer-lines-autofill'

/**
 * Autofill della riga d'offerta (direttiva utente 2026-09-09): selezionare
 * una categoria che espone UN SOLO prodotto crea da sola la riga, precompilata
 * come la creerebbe una scelta manuale. Le categorie con cui il form si apre
 * non vengono mai sondate: la direttiva parla della categoria che l'operatore
 * SELEZIONA, non di quelle gia' sul record.
 */

const BUSINESS_FUNCTION_ID = 40
const SOLE_CATEGORY_ID = 500
const CROWDED_CATEGORY_ID = 501

const SOLE_PRODUCT: QuoteProductForSelectItem = {
  id: 900,
  label: 'Fibra 1000',
  subtitle: 'Consulting',
  meta: {
    code: 'FIB-1000',
    price: '49.90',
    cost: '10.00',
    vat_rate_id: 7,
    vat_rate_name: 'IVA 22%',
    vat_rate: '22.00',
    unit_of_measure: null,
    product_typology: null,
  },
}

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
}))

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: vi.fn() }),
}))

/** One for-select page carrying `items`, with the `total` the probe reads. */
function page(items: ForSelectItem[], total = items.length): PaginatedResponse<ForSelectItem> {
  return { items, pagination: { total, offset: 0, limit: 25, total_pages: 1 }, export_link: null }
}

interface HarnessProps {
  /** The category the form OPENS on: `null` = none picked yet (the create form's own start). */
  initialCategoryId?: number | null
}

function Harness({ initialCategoryId = null }: HarnessProps) {
  const form = useForm<RequestOfferLinesFormShape>({
    defaultValues: {
      product_lines: [{ business_function_id: BUSINESS_FUNCTION_ID, product_category_id: initialCategoryId }],
      offer_lines: [EMPTY_LINE_ROW],
    },
  })

  return (
    // The FormProvider every real surface mounts this field inside.
    <Form {...form}>
      <button
        type="button"
        onClick={() =>
          form.setValue('product_lines', [
            { business_function_id: BUSINESS_FUNCTION_ID, product_category_id: SOLE_CATEGORY_ID },
          ])
        }
      >
        pick-sole
      </button>
      <button
        type="button"
        onClick={() =>
          form.setValue('product_lines', [
            { business_function_id: BUSINESS_FUNCTION_ID, product_category_id: CROWDED_CATEGORY_ID },
          ])
        }
      >
        pick-crowded
      </button>
      <RequestOfferLinesField
        control={form.control}
        knownLines={[]}
        vatRatePercentFor={() => null}
        rememberVatRatePercent={() => {}}
      />
    </Form>
  )
}

function renderHarness(props: HarnessProps = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((_resource: string, params: ForSelectParams = {}) => {
    const categoryIds = (params.params?.category_ids ?? []) as number[]
    if (categoryIds.includes(SOLE_CATEGORY_ID)) {
      return Promise.resolve(page([SOLE_PRODUCT]))
    }
    if (categoryIds.includes(CROWDED_CATEGORY_ID)) {
      return Promise.resolve(page([SOLE_PRODUCT, { id: 901, label: 'Fibra 2000' }], 2))
    }
    return Promise.resolve(page([]))
  })
})

describe('offer-line autofill', () => {
  it('creates the row by itself when the picked category exposes a single product', async () => {
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'pick-sole' }))

    await waitFor(() => expect(screen.getByLabelText('Quantità riga 1')).toHaveValue(1))
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toHaveValue(49.9)
  })

  it('leaves the row untouched when the picked category exposes more than one product', async () => {
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'pick-crowded' }))

    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledWith(
        'products',
        expect.objectContaining({ params: { category_ids: [CROWDED_CATEGORY_ID] } }),
      ),
    )
    expect(screen.getByLabelText('Quantità riga 1')).toHaveValue(null)
    expect(screen.getByLabelText('Prezzo unitario riga 1')).toHaveValue(null)
  })

  it('never probes the category the form opened on: a hydrated request gains no row', async () => {
    renderHarness({ initialCategoryId: SOLE_CATEGORY_ID })

    await waitFor(() => expect(screen.getByLabelText('Quantità riga 1')).toHaveValue(null))
    expect(fetchForSelectMock).not.toHaveBeenCalledWith(
      'products',
      expect.objectContaining({ params: { category_ids: [SOLE_CATEGORY_ID] } }),
    )
  })

  it('seeds a category only once, so the operator can delete the row it created', async () => {
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: 'pick-sole' }))
    await waitFor(() => expect(screen.getByLabelText('Quantità riga 1')).toHaveValue(1))

    fireEvent.click(screen.getByRole('button', { name: 'Rimuovi riga 1' }))

    await waitFor(() => expect(screen.queryByLabelText('Quantità riga 1')).not.toBeInTheDocument())
  })
})

describe('seedSoleProductRows', () => {
  const filledRow: QuoteLineFormValues = {
    ...EMPTY_LINE_ROW,
    product_id: 900,
    quantity: 2,
    unit_price: 10,
  }

  it('fills a pristine row instead of appending next to it', () => {
    const seeded = seedSoleProductRows([EMPTY_LINE_ROW], [SOLE_PRODUCT], 200)

    expect(seeded).toHaveLength(1)
    expect(seeded[0]).toMatchObject({ product_id: 900, quantity: 1, unit_price: 49.9, vat_rate_id: 7 })
  })

  it('returns the very same array when the product is already on the card', () => {
    const rows = [filledRow]

    expect(seedSoleProductRows(rows, [SOLE_PRODUCT], 200)).toBe(rows)
  })

  it('appends nothing past the row ceiling a single-managed category imposes', () => {
    const rows = [{ ...filledRow, product_id: 901 }]

    expect(seedSoleProductRows(rows, [SOLE_PRODUCT], 1)).toBe(rows)
  })
})
