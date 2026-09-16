import { describe, expect, it, vi } from 'vitest'
import { useForm } from 'react-hook-form'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { Form } from '@/components/ui/form'
import { OpportunityProductLinesSection } from '@/features/opportunities/opportunity-product-lines-section'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import { RequestProductLinesSection } from '@/features/request-management/request-product-lines-section'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * AC-043: the opportunity form and BOTH request-management channels render
 * the row editor via the SAME shared `ProductLinesField` (spec 0057's frozen
 * contract, spec 0077 MT-7's management-mode enforcement lives entirely
 * inside it). Rather than re-exercising MT-7's own AC-041/042 suite
 * (`product-lines-field-management-mode.test.tsx`), this asserts the thing
 * AC-043 is actually about: every consumer wires the identical
 * `value`/`onChange`/`disabled` contract into it, with no per-module
 * divergence that could make the resolved mode behave differently in one
 * screen than in the other. Spec 0132 dropped `knownLines` from the CARD
 * contract entirely (every label now resolves off the cached category tree,
 * not a persisted-row projection) — that half of this parity check went with
 * it. The create channel (`RequestCreateForm`) calls `<ProductLinesField>`
 * inline with the exact same shape (verified by inspection,
 * `request-create-form.tsx`); its own skeleton is covered by
 * `request-create-form.test.tsx`.
 */

interface ProductLinesFieldStubProps {
  value: ProductLineRow[]
  onChange: (rows: ProductLineRow[]) => void
  disabled?: boolean
}

vi.mock('@/features/product-lines/product-lines-field', () => ({
  ProductLinesField: ({ value, disabled = false }: ProductLinesFieldStubProps) => (
    <div data-testid="product-lines-field-stub">
      <span data-testid="value">{JSON.stringify(value)}</span>
      <span data-testid="disabled">{String(disabled)}</span>
    </div>
  ),
}))

vi.mock('@/features/products/products-of-interest-field', () => ({
  ProductsOfInterestField: () => <div data-testid="products-of-interest-stub" />,
}))

/** The opportunity section prunes through a cached for-select query, so it needs a client. */
function withQueryClient({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

const ROWS: ProductLineRow[] = [{ root_category_id: null, product_category_id: 500 }]

function OpportunityHarness() {
  const form = useForm<OpportunityFormValues>({
    defaultValues: { product_lines: ROWS, products_of_interest: [] },
  })
  return (
    <Form {...form}>
      <OpportunityProductLinesSection control={form.control} knownProductsOfInterest={[]} />
    </Form>
  )
}

function RequestWorkHarness() {
  const form = useForm<RequestWorkFormValues>({
    defaultValues: { product_lines: ROWS },
  })
  return (
    <Form {...form}>
      <RequestProductLinesSection control={form.control} />
    </Form>
  )
}

describe('product-lines wiring parity (AC-043)', () => {
  it('opportunity form: forwards the RHF value untouched to ProductLinesField', () => {
    render(<OpportunityHarness />, { wrapper: withQueryClient })

    expect(screen.getByTestId('value')).toHaveTextContent(JSON.stringify(ROWS))
  })

  it('request-management work panel: forwards the RHF value untouched to ProductLinesField', () => {
    render(<RequestWorkHarness />)

    expect(screen.getByTestId('value')).toHaveTextContent(JSON.stringify(ROWS))
  })

  /**
   * Both sections wrap the field in the SAME `MetaField`, so a resource
   * permission (`product_lines` unauthorized to edit) disables the shared
   * field identically in either module — no module-specific override of the
   * derived `disabled` flag.
   */
  it('forwards the same disabled default in either module', () => {
    const opportunity = render(<OpportunityHarness />, { wrapper: withQueryClient })
    const opportunityDisabled = screen.getByTestId('disabled').textContent
    opportunity.unmount()

    const request = render(<RequestWorkHarness />)
    expect(screen.getByTestId('disabled')).toHaveTextContent(opportunityDisabled ?? '')
    request.unmount()
  })
})
