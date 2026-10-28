import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { InvoiceEditorAddLine } from '@/features/invoices/invoice-editor-add-line'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'

let picked: QuoteProductForSelectItem

vi.mock('@/features/quotes/quote-product-select', () => ({
  QuoteProductSelect: ({ onChange, triggerLabel }: { onChange: (id: number, item: QuoteProductForSelectItem) => void; triggerLabel: string }) => (
    <button type="button" onClick={() => onChange(picked.id, picked)}>
      {triggerLabel}
    </button>
  ),
}))

const META = {
  code: 'P-1',
  price: '80.00',
  cost: '20.00',
  vat_rate_id: null,
  vat_rate_name: null,
  vat_rate: null,
  unit_of_measure: null,
  product_typology: null,
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function pick(meta: Partial<QuoteProductForSelectItem['meta']>) {
  picked = { id: 7, label: 'Corso', meta: { ...META, ...meta } }
  const onAdd = vi.fn()
  render(<InvoiceEditorAddLine onAdd={onAdd} onRememberVat={vi.fn()} />)
  fireEvent.click(screen.getByRole('button', { name: i18n.t('invoiceEditor.lines.product') }))
  return onAdd
}

describe('InvoiceEditorAddLine — catalog line price (rev.2 D-19)', () => {
  it('takes the default supplier price when the product has suppliers', () => {
    const onAdd = pick({
      suppliers: [
        { supplier_id: 5, name: 'Acme', price: '170.00', cost: '70.00', is_default: false },
        { supplier_id: 6, name: 'Beta', price: '190.00', cost: '90.00', is_default: true },
      ],
    })

    expect(onAdd).toHaveBeenCalledWith(expect.objectContaining({ product_id: 7, unit_price: 190 }))
  })

  it('takes the standard price without suppliers', () => {
    expect(pick({ suppliers: [] })).toHaveBeenCalledWith(expect.objectContaining({ unit_price: 80 }))
  })

  it('falls back to 0 when the standard price is missing', () => {
    expect(pick({ price: null })).toHaveBeenCalledWith(expect.objectContaining({ unit_price: 0 }))
  })
})
