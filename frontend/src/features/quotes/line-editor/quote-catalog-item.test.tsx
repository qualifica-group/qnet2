import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { QuoteCatalogItem } from '@/features/quotes/line-editor/quote-catalog-item'
import type { QuoteCatalogProduct } from '@/features/quotes/line-editor/types'

const WITH_SUPPLIERS: QuoteCatalogProduct = {
  id: 1,
  label: 'Corso Multi',
  meta: {
    category_id: 1,
    code: 'P-1',
    description: null,
    price: '80.00',
    cost: '20.00',
    vat_rate_id: null,
    vat_rate_name: null,
    vat_rate: null,
    unit_of_measure: null,
    product_typology: null,
    suppliers: [
      { supplier_id: 5, name: 'Acme', price: '170.00', cost: '70.00', is_default: true },
      { supplier_id: 6, name: 'Beta', price: '190.00', cost: '90.00', is_default: false },
    ],
  },
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderItem(item: QuoteCatalogProduct, variant: 'revenue' | 'cost') {
  render(
    <QuoteCatalogItem
      item={item}
      variant={variant}
      categoryPath={null}
      singleSelect
      selected={false}
      alreadyAdded={false}
      disabled={false}
      onActivate={vi.fn()}
    />,
  )
}

describe('QuoteCatalogItem — price shown (rev.2 D-17)', () => {
  it('shows the default supplier price on a revenue row, not the standard price', () => {
    renderItem(WITH_SUPPLIERS, 'revenue')

    expect(screen.getByRole('option')).toHaveTextContent('170,00')
    expect(screen.getByRole('option')).not.toHaveTextContent('80,00')
  })

  it('shows the default supplier cost on a cost row', () => {
    renderItem(WITH_SUPPLIERS, 'cost')

    expect(screen.getByRole('option')).toHaveTextContent('70,00')
  })

  it('shows the standard price when the product has no suppliers', () => {
    renderItem({ ...WITH_SUPPLIERS, meta: { ...WITH_SUPPLIERS.meta, suppliers: [] } }, 'revenue')

    expect(screen.getByRole('option')).toHaveTextContent('80,00')
  })
})
