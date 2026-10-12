import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import { PRODUCT_ENUMS, permissionsFor, productFixture, productWrapper } from '@/features/products/product-test-fixtures'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/**
 * The product record card: typology badge (spec 0204), VAT rate and supplier,
 * price/cost/margin by usage (spec 0191). REQUIREMENT CHANGED (spec 0218): the
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

describe('ProductDetailView — VAT rate + Supplier', () => {
  it('renders the VAT rate and supplier names when assigned', () => {
    renderDetail(
      productFixture({
        vat_rate_id: 4,
        vat_rate: { id: 4, name: 'Standard 22%', rate: 22 },
        supplier_id: 11,
        supplier: { id: 11, name: 'ACME Supplies' },
      }),
    )

    expect(screen.getByText('Standard 22%')).toBeInTheDocument()
    expect(screen.getByText('ACME Supplies')).toBeInTheDocument()
  })

  it('keeps the VAT rate and supplier rows, with a pencil, when unassigned', () => {
    renderDetail(productFixture())

    expect(screen.getByRole('button', { name: 'Edit VAT' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit Supplier' })).toBeInTheDocument()
  })
})

describe('ProductDetailView — price/cost/margin by usage (spec 0191 AC-013)', () => {
  it('shows the price only, no cost nor margin, for a Sellable-only product', () => {
    renderDetail(productFixture({ usages: ['SALE'] }))

    expect(hasStat('Price')).toBe(true)
    expect(screen.queryByText('Cost')).not.toBeInTheDocument()
    expect(screen.queryByText('Margin')).not.toBeInTheDocument()
  })

  it('shows the cost only, no price nor margin, for a cost-only product', () => {
    renderDetail(productFixture({ usages: ['COST'] }))

    expect(hasStat('Cost')).toBe(true)
    expect(screen.queryByText('Price')).not.toBeInTheDocument()
    expect(screen.queryByText('Margin')).not.toBeInTheDocument()
  })

  it('shows price, cost and margin when both usages are on', () => {
    renderDetail(productFixture({ usages: ['SALE', 'COST'] }))

    expect(hasStat('Price')).toBe(true)
    expect(hasStat('Cost')).toBe(true)
    expect(screen.getByText('Margin')).toBeInTheDocument()
  })

  it('offers no Edit action: the record edits in place (spec 0218 AC-001)', () => {
    renderDetail(productFixture())

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
  })
})
