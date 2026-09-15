import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'

/**
 * Spec 0129 D-3/AC-025: a `null` `product_category` (a user's "all categories
 * of the function" row) renders as "all categories" instead of a name.
 * Offers/opportunities never carry a null category, so their existing
 * rendering (spec 0040 AC-101) is unaffected — covered here by the
 * non-null-category case.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ProductLinesReadOnlyList', () => {
  it('falls back to the empty marker when there is no row', () => {
    const { container } = render(<ProductLinesReadOnlyList lines={[]} />)
    expect(container).toHaveTextContent('—')
    expect(screen.queryAllByRole('listitem')).toHaveLength(0)
  })

  it('renders "function — category" for a confirmed pair', () => {
    render(
      <ProductLinesReadOnlyList
        lines={[
          { id: 1, business_function: { id: 4, name: 'Sales' }, product_category: { id: 21, name: 'Photovoltaic' } },
        ]}
      />,
    )

    expect(screen.getByRole('listitem')).toHaveTextContent('Sales')
    expect(screen.getByRole('listitem')).toHaveTextContent('Photovoltaic')
  })

  it('AC-025 — renders "function — all categories" for a null-category row', () => {
    render(
      <ProductLinesReadOnlyList
        lines={[{ id: 91, business_function: { id: 4, name: 'Sales' }, product_category: null }]}
      />,
    )

    expect(screen.getByRole('listitem')).toHaveTextContent('Sales')
    expect(screen.getByRole('listitem')).toHaveTextContent('All categories')
  })
})
