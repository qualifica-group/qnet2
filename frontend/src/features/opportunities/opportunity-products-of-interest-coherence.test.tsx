import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { OpportunityProductLinesSection } from '@/features/opportunities/opportunity-product-lines-section'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * User directive 2026-08-05: the opportunity form applies the Gestione
 * Richieste rule on the two classification controls — the products picker
 * never leaves the categories of the rows above it (no whole-catalogue
 * escape), and re-pointing a row drops the products that classification no
 * longer covers, naming them.
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

/** The row editor is covered by its own suite (spec 0057): here it is only the event source. */
vi.mock('@/features/product-lines/product-lines-field', () => ({
  ProductLinesField: ({ onChange }: { onChange: (rows: ProductLineRow[]) => void }) => (
    <button type="button" onClick={() => onChange([{ business_function_id: 3, product_category_id: 9 }])}>
      Re-point the row
    </button>
  ),
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

function Harness({ onValues }: { onValues: (values: OpportunityFormValues) => void }) {
  const form = useForm<OpportunityFormValues>({
    defaultValues: {
      product_lines: [{ business_function_id: 3, product_category_id: 7 }],
      products_of_interest: [4, 5],
    },
  })

  onValues(form.watch() as OpportunityFormValues)

  return (
    <Form {...form}>
      <OpportunityProductLinesSection
        control={form.control}
        knownProductLines={[]}
        knownProductsOfInterest={[]}
      />
    </Form>
  )
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

describe('opportunity form: products of interest coherence (user directive 2026-08-05)', () => {
  it('drops the products the re-pointed classification no longer covers, naming them', async () => {
    let values: OpportunityFormValues | null = null
    render(<Harness onValues={(next) => (values = next)} />, { wrapper: wrapper() })

    // The click is inside the retry on purpose: pruning KEEPS a product whose
    // category the by-ids query has not resolved yet (the server has the last
    // word), so the drop is only observable once those labels land.
    await waitFor(() => {
      fireEvent.click(screen.getByRole('button', { name: 'Re-point the row' }))
      expect(values?.products_of_interest).toEqual([5])
    })

    expect(toastWarningMock).toHaveBeenCalledWith(expect.stringContaining('Fibra 1000'))
  })

  it('scopes the picker to the rows above, with no whole-catalogue escape', () => {
    render(<Harness onValues={() => {}} />, { wrapper: wrapper() })

    expect(
      screen.getByText('Only products of the product categories selected above.'),
    ).toBeInTheDocument()
  })
})
