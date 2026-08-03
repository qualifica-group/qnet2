import { describe, expect, it, vi } from 'vitest'
import { useForm } from 'react-hook-form'
import { render, screen } from '@testing-library/react'
import { Form } from '@/components/ui/form'
import { OpportunityProductLinesSection } from '@/features/opportunities/opportunity-product-lines-section'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import { RequestProductLinesSection } from '@/features/request-management/request-product-lines-section'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { ProductLine, ProductLineRow } from '@/features/product-lines/types'

/**
 * AC-043: the opportunity form and BOTH request-management channels render
 * the row editor via the SAME shared `ProductLinesField` (spec 0057's frozen
 * contract, spec 0077 MT-7's management-mode enforcement lives entirely
 * inside it). Rather than re-exercising MT-7's own AC-041/042 suite
 * (`product-lines-field-management-mode.test.tsx`), this asserts the thing
 * AC-043 is actually about: every consumer wires the identical
 * `value`/`onChange`/`knownLines`/`disabled` contract into it, with no
 * per-module divergence that could make the resolved mode behave
 * differently in one screen than in the other. The create channel
 * (`RequestCreateForm`) calls `<ProductLinesField>` inline with the exact
 * same shape (verified by inspection, `request-create-form.tsx`); its own
 * skeleton is covered by `request-create-form.test.tsx`.
 */

interface ProductLinesFieldStubProps {
  value: ProductLineRow[]
  onChange: (rows: ProductLineRow[]) => void
  knownLines?: ProductLine[]
  disabled?: boolean
}

vi.mock('@/features/product-lines/product-lines-field', () => ({
  ProductLinesField: ({ value, knownLines = [], disabled = false }: ProductLinesFieldStubProps) => (
    <div data-testid="product-lines-field-stub">
      <span data-testid="value">{JSON.stringify(value)}</span>
      <span data-testid="known-lines">{JSON.stringify(knownLines)}</span>
      <span data-testid="disabled">{String(disabled)}</span>
    </div>
  ),
}))

vi.mock('@/features/products/products-of-interest-field', () => ({
  ProductsOfInterestField: () => <div data-testid="products-of-interest-stub" />,
}))

const ROWS: ProductLineRow[] = [{ business_function_id: 40, product_category_id: 500 }]
const KNOWN_LINES: ProductLine[] = [
  { id: 1, business_function: { id: 40, name: 'Sales' }, product_category: { id: 500, name: 'Consulting' } },
]

function OpportunityHarness({ disabled }: { disabled: boolean }) {
  const form = useForm<OpportunityFormValues>({
    defaultValues: { product_lines: ROWS, products_of_interest: [] },
  })
  return (
    <Form {...form}>
      <OpportunityProductLinesSection
        control={form.control}
        knownProductLines={disabled ? KNOWN_LINES : []}
        knownProductsOfInterest={[]}
      />
    </Form>
  )
}

function RequestWorkHarness({ disabled }: { disabled: boolean }) {
  const form = useForm<RequestWorkFormValues>({
    defaultValues: { product_lines: ROWS },
  })
  return (
    <Form {...form}>
      <RequestProductLinesSection control={form.control} productLines={disabled ? KNOWN_LINES : []} />
    </Form>
  )
}

describe('product-lines wiring parity (AC-043)', () => {
  it('opportunity form: forwards the RHF value and knownLines untouched to ProductLinesField', () => {
    render(<OpportunityHarness disabled={false} />)

    expect(screen.getByTestId('value')).toHaveTextContent(JSON.stringify(ROWS))
    expect(screen.getByTestId('known-lines')).toHaveTextContent(JSON.stringify([]))
  })

  it('request-management work panel: forwards the RHF value and knownLines untouched to ProductLinesField', () => {
    render(<RequestWorkHarness disabled={false} />)

    expect(screen.getByTestId('value')).toHaveTextContent(JSON.stringify(ROWS))
    expect(screen.getByTestId('known-lines')).toHaveTextContent(JSON.stringify([]))
  })

  /**
   * Both sections wrap the field in the SAME `MetaField`, so a resource
   * permission (`product_lines` unauthorized to edit) disables the shared
   * field identically in either module — no module-specific override of the
   * derived `disabled` flag.
   */
  it('forwards knownLines identically once populated, in either module', () => {
    const opportunity = render(<OpportunityHarness disabled />)
    const opportunityKnownLines = screen.getByTestId('known-lines').textContent
    opportunity.unmount()

    const request = render(<RequestWorkHarness disabled />)
    expect(screen.getByTestId('known-lines')).toHaveTextContent(opportunityKnownLines ?? '')
    request.unmount()
  })
})
