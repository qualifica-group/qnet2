import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import { ProductForm } from '@/features/products/product-form'
import {
  clickCreateSave,
  completeRequiredCreateRows,
  confirmOpenRow,
  pencilOf,
  permissionsFor,
  productFixture,
  productWrapper,
} from '@/features/products/product-test-fixtures'
import { buildBalance } from '@/features/stock/stock-fixtures'

/** Spec 0307 AC-016: "Internal use" / "Physical good" on the product form and the "Stock" section of the detail. */

const createProductMock = vi.fn()
const fetchStockBalancesMock = vi.fn()
const permissions = new Set<string>()

vi.mock('@/features/products/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/products/api')>()),
  createProduct: (...args: unknown[]) => createProductMock(...args),
  fetchProductNextCode: () => Promise.resolve('PRD-0100'),
}))
vi.mock('@/features/stock/api', () => ({
  fetchStockBalances: (...args: unknown[]) => fetchStockBalancesMock(...args),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/config/use-config', async () => {
  const { PRODUCT_ENUMS } = await import('@/features/products/product-test-fixtures')
  return { useEnumOptions: (key: string) => PRODUCT_ENUMS[key] ?? [] }
})
vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: [], isPending: false, isError: false, refetch: () => {} }),
}))
vi.mock('@/components/ui/searchable-select', async () => ({
  SearchableSelect: (await import('@/features/products/product-test-stubs')).StubSearchableSelect,
}))
vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/products/product-test-stubs')).StubAsyncSelect,
}))
const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({ fetchResourceMeta: () => fetchResourceMetaMock() }))
vi.mock('@/features/product-categories/api', () => ({ fetchEffectiveAttributes: () => Promise.resolve([]) }))
vi.mock('@/features/products/use-product-attribute-layout', () => ({
  useProductAttributeLayout: () => ({ data: null, isLoading: false }),
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => permissions.has(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.restoreAllMocks()
  permissions.clear()
  createProductMock.mockReset()
  fetchStockBalancesMock.mockReset()
  fetchStockBalancesMock.mockResolvedValue([
    buildBalance({ id: 1, quantity: '4.000' }),
    buildBalance({ id: 2, quantity: '1.000', user: { id: 7, name: 'Anna Rossi', is_active: true } }),
  ])
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('Product form - internal use (spec 0307 AC-016)', () => {
  it('offers the "Internal use" checkbox and the "Physical good" type', async () => {
    const { wrapper } = productWrapper()
    render(<ProductForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
    await screen.findByRole('button', { name: 'Edit Code' })

    fireEvent.click(pencilOf('Offer usage'))
    expect(screen.getByRole('checkbox', { name: 'Internal use' })).toBeInTheDocument()
    confirmOpenRow('Done')
    fireEvent.click(pencilOf('Type'))
    fireEvent.click(screen.getByRole('combobox'))
    expect(await screen.findByRole('option', { name: 'Physical good' })).toBeInTheDocument()
  })

  it('shows the error on the usages field before sending when Internal use is picked with type Service', async () => {
    const { wrapper } = productWrapper()
    render(<ProductForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    fireEvent.click(pencilOf('Offer usage'))
    fireEvent.click(screen.getByRole('checkbox', { name: 'Internal use' }))
    confirmOpenRow('Done')
    clickCreateSave()

    expect(await screen.findByText(i18n.t('products.form.usagesInternalNeedsPhysical'))).toBeInTheDocument()
    expect(createProductMock).not.toHaveBeenCalled()
  })
})

describe('Product detail - Stock section (spec 0307 AC-016)', () => {
  const tracked = () => productFixture({ product_type: 'PHYSICAL', usages: ['INTERNAL'], is_stock_tracked: true, stock_quantity: '5.000' })

  it('lists the rows (site, person or "In site", quantity and unit) for a tracked product with stock.viewAny', async () => {
    permissions.add('stock.viewAny')
    const { wrapper } = productWrapper()
    render(<ProductDetailView product={tracked()} />, { wrapper })

    expect(await screen.findByText('In site')).toBeInTheDocument()
    expect(screen.getByText('4 pz')).toBeInTheDocument()
    expect(screen.getByText('Anna Rossi')).toBeInTheDocument()
    expect(screen.getByText('1 pz')).toBeInTheDocument()
    expect(fetchStockBalancesMock).toHaveBeenCalledWith({ product_id: 5 })
  })

  it('has no section and sends no request without stock.viewAny', () => {
    const { wrapper } = productWrapper()
    render(<ProductDetailView product={tracked()} />, { wrapper })

    expect(screen.queryByText('In site')).not.toBeInTheDocument()
    expect(fetchStockBalancesMock).not.toHaveBeenCalled()
  })

  it('has no section for a product that is not tracked', async () => {
    permissions.add('stock.viewAny')
    const { wrapper } = productWrapper()
    render(<ProductDetailView product={productFixture()} />, { wrapper })

    expect((await screen.findAllByText('ThinkPad X1')).length).toBeGreaterThan(0)
    expect(fetchStockBalancesMock).not.toHaveBeenCalled()
  })
})
