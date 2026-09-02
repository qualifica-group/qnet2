import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductDetailView } from '@/features/products/product-detail'
import type { ProductDetailWithPermissions } from '@/features/products/types'

// Isolates the view from the activity log network/section, out of scope here.
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div data-testid="activity-log" />,
}))

function product(overrides: Partial<ProductDetailWithPermissions> = {}): ProductDetailWithPermissions {
  return {
    id: 5,
    code: 'PRD-0005',
    name: 'ThinkPad X1',
    description: null,
    cost: 800,
    price: 1200,
    category_id: 3,
    category: { id: 3, name: 'Laptops' },
    product_type: 'SERVICE',
    created_at: '2026-01-01T00:00:00Z',
    vat_rate_id: null,
    vat_rate: null,
    supplier_id: null,
    supplier: null,
    unit_of_measure_id: 1,
    unit_of_measure: { id: 1, name: 'Unit', symbol: 'pz' },
    product_typology_id: 1,
    product_typology: { id: 1, name: 'Ente' },
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ProductDetailView — VAT rate + Supplier', () => {
  it('renders the VAT rate and supplier names when assigned', () => {
    render(
      <ProductDetailView
        product={product({
          vat_rate_id: 4,
          vat_rate: { id: 4, name: 'Standard 22%', rate: 22 },
          supplier_id: 11,
          supplier: { id: 11, name: 'ACME Supplies' },
        })}
      />,
    )

    expect(screen.getByText('Standard 22%')).toBeInTheDocument()
    expect(screen.getByText('ACME Supplies')).toBeInTheDocument()
  })

  it('omits the VAT rate and supplier fields when unassigned', () => {
    render(<ProductDetailView product={product()} />)

    expect(screen.queryByText('VAT')).not.toBeInTheDocument()
    expect(screen.queryByText('Supplier')).not.toBeInTheDocument()
  })
})
