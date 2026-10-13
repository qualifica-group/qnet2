import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { PurchaseInvoiceEditorAddLine } from '@/features/purchase-invoices/purchase-invoice-editor-add-line'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'

/** Spec 0251 AC-009: a catalog row carries the product's default cost type, nothing when it has none. */

const ITEM_META = {
  code: 'CST-1',
  price: null,
  cost: '10.00',
  vat_rate_id: null,
  vat_rate_name: null,
  vat_rate: null,
  unit_of_measure: null,
  product_typology: null,
}

let pickedItem: QuoteProductForSelectItem
vi.mock('@/features/quotes/quote-product-select', () => ({
  QuoteProductSelect: ({ onChange }: { onChange: (id: number, item: QuoteProductForSelectItem) => void }) => (
    <button type="button" onClick={() => onChange(pickedItem.id, pickedItem)}>
      pick-product
    </button>
  ),
}))

function renderAddLine() {
  const onAdd = vi.fn()
  render(<PurchaseInvoiceEditorAddLine onAdd={onAdd} onRememberVat={vi.fn()} />)
  return onAdd
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('PurchaseInvoiceEditorAddLine - cost type (spec 0251 AC-009)', () => {
  it('passes the product default cost type to the new row', () => {
    pickedItem = { id: 9, label: 'Fuel card', meta: { ...ITEM_META, cost_type: { id: 6, name: 'Travel', color: 'blue' } } } as QuoteProductForSelectItem
    const onAdd = renderAddLine()

    fireEvent.click(screen.getByRole('button', { name: 'pick-product' }))

    expect(onAdd).toHaveBeenCalledWith(expect.objectContaining({ product_id: 9, cost_type_id: 6 }))
  })

  it('adds no cost type when the product has none', () => {
    pickedItem = { id: 9, label: 'Fuel card', meta: ITEM_META } as QuoteProductForSelectItem
    const onAdd = renderAddLine()

    fireEvent.click(screen.getByRole('button', { name: 'pick-product' }))

    expect(onAdd.mock.calls[0][0]).not.toHaveProperty('cost_type_id')
  })
})
