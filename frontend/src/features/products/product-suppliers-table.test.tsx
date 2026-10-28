import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductSuppliersTable } from '@/features/products/product-suppliers-table'
import { productWrapper } from '@/features/products/product-test-fixtures'
import type { ProductRecordSupplier } from '@/features/products/product-record'

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

const SUPPLIERS: ProductRecordSupplier[] = [
  { supplier_id: 1, name: 'ACME Supplies', price: '150.00', cost: '110.00', is_default: true, commission: null },
  {
    supplier_id: 2,
    name: 'Beta Srl',
    price: '120.00',
    cost: '0.00',
    is_default: false,
    commission: { configuration_id: 3, type: 'PERCENTAGE', value: '10.00', direction: 'PAID' },
  },
  { supplier_id: 3, name: 'Gamma', price: '50.00', cost: '80.00', is_default: false, commission: null },
]

function renderTable(suppliers: ProductRecordSupplier[]) {
  const { wrapper } = productWrapper()
  return render(<ProductSuppliersTable suppliers={suppliers} />, { wrapper })
}

function rowOf(name: string): HTMLElement {
  const row = within(screen.getByRole('table', { name: 'Suppliers' }))
    .getAllByRole('row')
    .find((element) => element.textContent?.includes(name))
  if (!row) {
    throw new Error(`No row for ${name}`)
  }
  return row
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ProductSuppliersTable (spec 0320 UI)', () => {
  it('lists every supplier with price, cost, margin and the default badge on the default one only', () => {
    renderTable(SUPPLIERS)

    expect(screen.getAllByRole('columnheader').map((header) => header.textContent)).toEqual(['Supplier', 'Price', 'Cost', 'Margin'])
    expect(rowOf('ACME Supplies')).toHaveTextContent(/150,00/)
    expect(rowOf('ACME Supplies')).toHaveTextContent(/40,00/)
    expect(rowOf('ACME Supplies')).toHaveTextContent('26,7%')
    expect(within(rowOf('ACME Supplies')).getByText('Default')).toBeInTheDocument()
    expect(within(rowOf('Gamma')).queryByText('Default')).not.toBeInTheDocument()
  })

  it('shows the commission instead of the cost and no margin for a supplier paid through a commission', () => {
    renderTable(SUPPLIERS)

    const row = rowOf('Beta Srl')
    expect(within(row).getByText('Commission 10% paid')).toBeInTheDocument()
    expect(row).toHaveTextContent('—')
  })

  it('marks a negative margin as destructive', () => {
    renderTable(SUPPLIERS)

    expect(within(rowOf('Gamma')).getByText(/-30,00/)).toHaveClass('text-destructive')
  })

  it('renders the empty state without suppliers', () => {
    renderTable([])

    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    expect(screen.getByText('No suppliers')).toBeInTheDocument()
    expect(screen.getByText('Without suppliers the Standard price and Standard cost apply.')).toBeInTheDocument()
  })
})
