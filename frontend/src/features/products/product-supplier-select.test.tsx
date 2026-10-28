import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { ProductSupplierSelect } from '@/features/products/product-supplier-select'
import type { ProductSupplierOption } from '@/features/products/product-suppliers'

const SUPPLIERS: ProductSupplierOption[] = [
  { supplier_id: 7, name: 'Acme', price: '110.00', cost: '10.00', is_default: true },
  { supplier_id: 9, name: 'Beta', price: '112.50', cost: '12.50', is_default: false },
]

const labels = {
  placeholder: 'Supplier…',
  searchPlaceholder: 'Search suppliers…',
  empty: 'No supplier.',
  triggerLabel: 'Supplier of row 1',
}

describe('ProductSupplierSelect', () => {
  it('lists the suppliers with their cost and reports the picked option', () => {
    const onChange = vi.fn()
    render(<ProductSupplierSelect suppliers={SUPPLIERS} value={7} onChange={onChange} amount="cost" labels={labels} />)

    fireEvent.click(screen.getByRole('combobox', { name: /Supplier of row 1/ }))
    expect(screen.getByRole('option', { name: /Acme/ })).toHaveTextContent('10,00')
    fireEvent.click(screen.getByRole('option', { name: /Beta/ }))

    expect(onChange).toHaveBeenCalledWith(9, SUPPLIERS[1])
  })

  it('shows the sale price on each option when asked for the price (rev.2)', () => {
    render(<ProductSupplierSelect suppliers={SUPPLIERS} value={7} onChange={vi.fn()} amount="price" labels={labels} />)

    fireEvent.click(screen.getByRole('combobox', { name: /Supplier of row 1/ }))
    expect(screen.getByRole('option', { name: /Acme/ })).toHaveTextContent('110,00')
  })

  it('shows the chosen supplier in the trigger', () => {
    render(<ProductSupplierSelect suppliers={SUPPLIERS} value={9} onChange={vi.fn()} amount="cost" labels={labels} />)

    expect(screen.getByRole('combobox', { name: /Supplier of row 1/ })).toHaveTextContent('Beta')
  })

  it('keeps a saved supplier the product no longer lists, inert', () => {
    const onChange = vi.fn()
    render(
      <ProductSupplierSelect
        suppliers={SUPPLIERS}
        value={4}
        fallback={{ id: 4, name: 'Old Ltd' }}
        onChange={onChange}
        amount="cost"
        labels={labels}
      />,
    )

    expect(screen.getByRole('combobox')).toHaveTextContent('Old Ltd')
  })

  it('wires the error triad and the disabled state', () => {
    render(
      <ProductSupplierSelect
        suppliers={SUPPLIERS}
        value={null}
        onChange={vi.fn()}
        amount="cost"
        labels={labels}
        disabled
        aria-invalid
        aria-describedby="err-1"
      />,
    )

    const trigger = screen.getByRole('combobox')
    expect(trigger).toBeDisabled()
    expect(trigger).toHaveAttribute('aria-invalid', 'true')
    expect(trigger).toHaveAttribute('aria-describedby', 'err-1')
  })
})
