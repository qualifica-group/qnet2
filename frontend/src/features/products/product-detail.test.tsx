import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import { PRODUCT_ENUMS, permissionsFor, productFixture, productWrapper } from '@/features/products/product-test-fixtures'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/**
 * The product record card: typology badge (spec 0204), VAT rate and supplier,
 * price/cost/margin for every usage (spec 0320 rev.3). REQUIREMENT CHANGED (spec 0218): the
 * card has no Edit action, and the pricing rows are always there — an empty
 * one shows a dash, since it is where the value is set.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div data-testid="activity-log" />,
}))
vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: (key: string) => PRODUCT_ENUMS[key] ?? [],
}))
vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: () => {} }),
}))
vi.mock('@/features/product-categories/api', () => ({
  fetchEffectiveAttributes: () => Promise.resolve([]),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

function renderDetail(product: ProductDetailWithPermissions) {
  const { wrapper } = productWrapper()
  return render(<ProductDetailView product={product} />, { wrapper })
}

/** The first supplier row of the read-only suppliers table (row 0 is the header). */
function firstSupplierRow(): HTMLElement {
  return within(screen.getByRole('table', { name: 'Suppliers' })).getAllByRole('row')[1]
}

/** The KPI tile labelled `label`, outside the suppliers table that reuses the same column names. */
function kpiStat(label: string): HTMLElement | undefined {
  return screen.getAllByText(label).find((element) => element.closest('[role="table"]') === null)
}

/** The KPI tile labelled `label` (the strip labels are the grid's column names). */
function hasStat(label: string): boolean {
  return screen.queryAllByText(label).some((element) => element.closest('dt') === null)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('ProductDetailView — typology badge (spec 0204 AC-005)', () => {
  it('shows the typology as a badge in its colour, in the header and in the classification', () => {
    renderDetail(productFixture())

    const badges = screen.getAllByText('Ente')
    expect(badges).toHaveLength(2)
    for (const badge of badges) {
      expect(badge).toHaveClass('bg-violet-100')
    }
  })
})

describe('ProductDetailView — VAT rate + Suppliers', () => {
  it('renders the VAT rate and the suppliers with the amounts the usages make relevant and the default badge', () => {
    renderDetail(
      productFixture({
        usages: ['SALE', 'COST'],
        vat_rate_id: 4,
        vat_rate: { id: 4, name: 'Standard 22%', rate: 22 },
        suppliers: [{ supplier_id: 11, name: 'ACME Supplies', price: '110.00', cost: '10.00', is_default: true }],
      }),
    )

    expect(screen.getByText('Standard 22%')).toBeInTheDocument()
    expect(screen.getByText('ACME Supplies')).toBeInTheDocument()
    // Spec 0320 UI: the suppliers render as a price list table (amounts in euro), not as an inline list.
    expect(firstSupplierRow()).toHaveTextContent(/110,00/)
    expect(firstSupplierRow()).toHaveTextContent(/10,00/)
    expect(screen.getByText('Default')).toBeInTheDocument()
  })

  // REQUIREMENT CHANGED (rev.3 D-20): the supplier shows price and cost whatever the usages.
  it.each([['SALE'], ['COST'], ['INTERNAL']] as const)('shows the supplier price and cost for the %s usage', (usage) => {
    const suppliers = [{ supplier_id: 11, name: 'ACME Supplies', price: '110.00', cost: '10.00', is_default: true }]
    renderDetail(productFixture({ usages: [usage], product_type: 'PHYSICAL', suppliers }))

    expect(firstSupplierRow()).toHaveTextContent(/110,00/)
    expect(firstSupplierRow()).toHaveTextContent(/10,00/)
  })

  it('AC-031: notes the commission of a supplier read-only, in the resource wording', () => {
    const suppliers = [
      {
        supplier_id: 11,
        name: 'ACME Supplies',
        price: '110.00',
        cost: '0.00',
        is_default: true,
        commission: { configuration_id: 3, type: 'PERCENTAGE' as const, value: '10.00', direction: 'PAID' as const },
      },
      { supplier_id: 12, name: 'Beta Srl', price: '120.00', cost: '12.00', is_default: false, commission: null },
    ]
    renderDetail(productFixture({ suppliers }))

    expect(screen.getAllByText(/Commission/)).toHaveLength(1)
    expect(screen.getByText('Commission 10% paid')).toBeInTheDocument()
  })

  it('shows the Standard price row only while the product has no suppliers (rev.2 D-15)', () => {
    const { unmount } = renderDetail(productFixture({ usages: ['SALE'], suppliers: [] }))
    expect(screen.getByRole('button', { name: 'Edit Standard price' })).toBeInTheDocument()
    unmount()

    renderDetail(
      productFixture({
        usages: ['SALE'],
        suppliers: [{ supplier_id: 11, name: 'ACME Supplies', price: '110.00', cost: '10.00', is_default: true }],
      }),
    )
    expect(screen.queryByRole('button', { name: 'Edit Standard price' })).not.toBeInTheDocument()
  })

  it('reads the KPI price and cost from the default supplier (rev.2 D-18)', () => {
    renderDetail(
      productFixture({
        usages: ['SALE', 'COST'],
        price: '999.00',
        cost: '1.00',
        suppliers: [{ supplier_id: 11, name: 'ACME Supplies', price: '110.00', cost: '10.00', is_default: true }],
      }),
    )
    const margin = kpiStat('Margin')?.closest('div')
    expect(margin).toHaveTextContent('100.00')
  })

  it('keeps the VAT rate and supplier rows, with a pencil, when unassigned', () => {
    renderDetail(productFixture())

    expect(screen.getByRole('button', { name: 'Edit VAT' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit Suppliers' })).toBeInTheDocument()
  })
})

// REQUIREMENT CHANGED (spec 0320 rev.3 D-20, supersedes spec 0191 AC-013): every product has price, cost and margin.
describe('ProductDetailView — price/cost/margin for every usage (rev.3 D-20)', () => {
  it.each([[['SALE']], [['COST']], [['SALE', 'COST']]] as const)('shows price, cost and margin for usages %s', (usages) => {
    renderDetail(productFixture({ usages: [...usages] }))

    expect(hasStat('Price')).toBe(true)
    expect(hasStat('Cost')).toBe(true)
    expect(screen.getByText('Margin')).toBeInTheDocument()
  })

  it('offers no Edit action: the record edits in place (spec 0218 AC-001)', () => {
    renderDetail(productFixture())

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })
})
