import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { ProductDetailView } from '@/features/products/product-detail'
import { ProductForm } from '@/features/products/product-form'
import {
  clickCreateSave,
  completeRequiredCreateRows,
  confirmOpenRow,
  EDITABLE,
  pencilOf,
  permissionsFor,
  productFixture,
  productWrapper,
} from '@/features/products/product-test-fixtures'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/**
 * Spec 0320 AC-013: the suppliers editor of the product record. The picker is
 * stubbed to pick supplier 11 "ACME" (the real one is covered by its own suite).
 */

const createProductMock = vi.fn()
const updateProductMock = vi.fn()

vi.mock('@/features/products/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/products/api')>()),
  createProduct: (...args: unknown[]) => createProductMock(...args),
  updateProduct: (...args: unknown[]) => updateProductMock(...args),
  fetchProductNextCode: () => Promise.resolve('PRD-0100'),
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

interface SupplierPickerStubProps {
  onItemChange?: (item: { id: number; label: string } | null) => void
  labels: { triggerLabel: string }
  value: number | null
  selectedItem?: { label: string } | null
}

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ onItemChange, labels, selectedItem, ...rest }: SupplierPickerStubProps) => (
    <button
      type="button"
      aria-label={labels.triggerLabel}
      aria-invalid={(rest as { 'aria-invalid'?: boolean })['aria-invalid']}
      aria-describedby={(rest as { 'aria-describedby'?: string })['aria-describedby']}
      onClick={() => onItemChange?.({ id: 11, label: 'ACME' })}
    >
      {selectedItem?.label ?? ''}
    </button>
  ),
}))

const apiGetMock = vi.fn()
vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  apiClient: { get: (...args: unknown[]) => apiGetMock(...args) },
}))

const COMMISSION = { configuration_id: 9, type: 'PERCENTAGE', value: '10.00', direction: 'PAID' }

/** Answers `GET /products/supplier-commissions` only; every other GET fails as it would offline. */
function answerCommissions(commission: typeof COMMISSION | null) {
  apiGetMock.mockImplementation((url: string, config?: { params?: { supplier_ids?: number[] } }) =>
    url === '/products/supplier-commissions'
      ? Promise.resolve({
          data: { data: (config?.params?.supplier_ids ?? []).map((id) => ({ supplier_id: id, commission })) },
        })
      : Promise.reject(new Error('not mocked')),
  )
}

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({ fetchResourceMeta: () => fetchResourceMetaMock() }))

vi.mock('@/features/product-categories/api', () => ({ fetchEffectiveAttributes: () => Promise.resolve([]) }))
vi.mock('@/features/products/use-product-attribute-layout', () => ({
  useProductAttributeLayout: () => ({ data: null, isLoading: false }),
}))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

function renderCreate() {
  const { wrapper } = productWrapper()
  render(<ProductForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
}

function renderDetail(product: ProductDetailWithPermissions) {
  const { wrapper } = productWrapper()
  render(<ProductDetailView product={product} />, { wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createProductMock.mockReset()
  updateProductMock.mockReset()
  apiGetMock.mockReset()
  answerCommissions(null)
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

const TWO_SUPPLIERS = [
  { supplier_id: 11, name: 'ACME', price: '110.00', cost: '10.00', is_default: true },
  { supplier_id: 12, name: 'Beta', price: '112.50', cost: '12.50', is_default: false },
]

function unprocessable(errors: Record<string, string[]>): AxiosError {
  return new AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { message: 'Invalid', errors },
  } as AxiosResponse)
}

describe('Product record — suppliers editor (spec 0320)', () => {
  it('lists the suppliers read-only with their cost and the default badge, hiding the standard cost', () => {
    renderDetail(productFixture({ usages: ['SALE', 'COST'], suppliers: TWO_SUPPLIERS }))

    expect(screen.getByRole('link', { name: /ACME/ })).toBeInTheDocument()
    // Spec 0320 UI: amounts read in euro inside the suppliers price list table.
    const table = screen.getByRole('table', { name: 'Suppliers' })
    expect(table).toHaveTextContent(/12,50/)
    expect(table).toHaveTextContent(/112,50/)
    expect(screen.getAllByText('Default')).toHaveLength(1)
    expect(screen.queryByRole('button', { name: 'Edit Standard cost' })).not.toBeInTheDocument()
  })

  it('shows the standard cost row when the product has no suppliers', () => {
    renderDetail(productFixture({ usages: ['SALE', 'COST'], suppliers: [] }))

    expect(pencilOf('Standard cost')).toBeInTheDocument()
  })

  it('makes a lone added row the default and keeps one default when another is chosen', () => {
    updateProductMock.mockResolvedValue(productFixture())
    renderDetail(productFixture())

    fireEvent.click(pencilOf('Suppliers'))
    fireEvent.click(screen.getByRole('button', { name: 'Add supplier' }))
    expect(screen.getByRole('radio', { name: 'Supplier 1 is the default' })).toBeChecked()

    fireEvent.click(screen.getByRole('button', { name: 'Add supplier' }))
    fireEvent.click(screen.getByRole('radio', { name: 'Supplier 2 is the default' }))
    expect(screen.getByRole('radio', { name: 'Supplier 1 is the default' })).not.toBeChecked()
    expect(screen.getByRole('radio', { name: 'Supplier 2 is the default' })).toBeChecked()
  })

  it('removes a row and hands the default to the remaining one', () => {
    renderDetail(productFixture({ suppliers: TWO_SUPPLIERS }))

    fireEvent.click(pencilOf('Suppliers'))
    fireEvent.click(screen.getByRole('button', { name: 'Remove supplier 1' }))

    expect(screen.getAllByRole('radio')).toHaveLength(1)
    expect(screen.getByRole('radio', { name: 'Supplier 1 is the default' })).toBeChecked()
  })

  it('PATCHes the whole list, without names, when a supplier row is saved', async () => {
    updateProductMock.mockResolvedValue(productFixture({ suppliers: [] }))
    renderDetail(productFixture({ usages: ['SALE', 'COST'] }))

    fireEvent.click(pencilOf('Suppliers'))
    fireEvent.click(screen.getByRole('button', { name: 'Add supplier' }))
    fireEvent.click(screen.getByRole('button', { name: 'Supplier 1' }))
    expect(screen.getByRole('spinbutton', { name: 'Price of supplier 1' })).toHaveValue(0)
    expect(screen.getByRole('spinbutton', { name: 'Cost of supplier 1' })).toHaveValue(0)
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Price of supplier 1' }), { target: { value: '19' } })
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Cost of supplier 1' }), { target: { value: '9.5' } })
    confirmOpenRow('Save')

    await waitFor(() =>
      expect(updateProductMock).toHaveBeenCalledWith(5, {
        suppliers: [{ supplier_id: 11, price: 19, cost: 9.5, is_default: true }],
      }),
    )
  })

  // REQUIREMENT CHANGED (rev.3 D-20 / AC-031): both Price and Cost are always asked, whatever the usages.
  it.each([['SALE'], ['COST']] as const)('AC-031: asks Price and Cost on a %s-only product, the cost defaulting to 0', async (usage) => {
    updateProductMock.mockResolvedValue(productFixture({ suppliers: [] }))
    renderDetail(productFixture({ usages: [usage] }))

    fireEvent.click(pencilOf('Suppliers'))
    fireEvent.click(screen.getByRole('button', { name: 'Add supplier' }))
    fireEvent.click(screen.getByRole('button', { name: 'Supplier 1' }))
    expect(screen.getByRole('spinbutton', { name: 'Cost of supplier 1' })).toHaveValue(0)
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Price of supplier 1' }), { target: { value: '30' } })
    confirmOpenRow('Save')

    await waitFor(() =>
      expect(updateProductMock).toHaveBeenCalledWith(5, {
        suppliers: [{ supplier_id: 11, price: 30, cost: 0, is_default: true }],
      }),
    )
  })

  it('AC-031: a supplier with a commission has the Cost disabled at 0, with the note, and saves 0', async () => {
    answerCommissions(COMMISSION)
    updateProductMock.mockResolvedValue(productFixture({ suppliers: [] }))
    renderDetail(productFixture({ usages: ['COST'] }))

    fireEvent.click(pencilOf('Suppliers'))
    fireEvent.click(screen.getByRole('button', { name: 'Add supplier' }))
    fireEvent.click(screen.getByRole('button', { name: 'Supplier 1' }))
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Price of supplier 1' }), { target: { value: '30' } })

    const cost = screen.getByRole('spinbutton', { name: 'Cost of supplier 1' })
    expect(await screen.findByText('Commission 10% paid')).toBeInTheDocument()
    expect(cost).toBeDisabled()
    expect(cost).toHaveValue(0)
    expect(cost).toHaveAccessibleDescription('Commission 10% paid')
    expect(apiGetMock).toHaveBeenCalledWith('/products/supplier-commissions', { params: { product_id: 5, supplier_ids: [11] } })

    confirmOpenRow('Save')
    await waitFor(() =>
      expect(updateProductMock).toHaveBeenCalledWith(5, {
        suppliers: [{ supplier_id: 11, price: 30, cost: 0, is_default: true }],
      }),
    )
  })

  it('AC-031: zeroes a typed cost once the supplier turns out to have a fixed commission', async () => {
    answerCommissions({ configuration_id: 9, type: 'FIXED_AMOUNT', value: '25.00', direction: 'RECEIVED' })
    renderDetail(productFixture({ suppliers: TWO_SUPPLIERS.slice(0, 1).map((s) => ({ ...s, cost: '10.00' })) }))

    fireEvent.click(pencilOf('Suppliers'))

    expect(await screen.findByText(/Fixed commission .*25,00.* received/)).toBeInTheDocument()
    await waitFor(() => expect(screen.getByRole('spinbutton', { name: 'Cost of supplier 1' })).toHaveValue(0))
  })

  it('AC-031: while creating, looks the commissions up by category and makes no request without one', async () => {
    answerCommissions(COMMISSION)
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    fireEvent.click(pencilOf('Suppliers'))
    fireEvent.click(screen.getByRole('button', { name: 'Add supplier' }))
    fireEvent.click(screen.getByRole('button', { name: 'Supplier 1' }))
    await act(async () => {})
    expect(apiGetMock).not.toHaveBeenCalled()
    confirmOpenRow('Done')

    fireEvent.click(pencilOf('Category'))
    fireEvent.click(screen.getByTestId('category-select'))
    confirmOpenRow('Done')
    fireEvent.click(pencilOf('Suppliers'))

    expect(await screen.findByText('Commission 10% paid')).toBeInTheDocument()
    expect(apiGetMock).toHaveBeenCalledWith('/products/supplier-commissions', {
      params: { category_id: expect.any(Number), supplier_ids: [11] },
    })
  })

  it('refuses an incomplete row on the client with the ARIA error triad', async () => {
    renderDetail(productFixture({ usages: ['SALE', 'COST'] }))

    fireEvent.click(pencilOf('Suppliers'))
    fireEvent.click(screen.getByRole('button', { name: 'Add supplier' }))
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Cost of supplier 1' }), { target: { value: '' } })
    confirmOpenRow('Save')

    const costInput = await screen.findByRole('spinbutton', { name: 'Cost of supplier 1' })
    await waitFor(() => expect(costInput).toHaveAttribute('aria-invalid', 'true'))
    const errorId = costInput.getAttribute('aria-describedby') as string
    expect(document.getElementById(errorId)).toHaveAttribute('role', 'alert')
    expect(updateProductMock).not.toHaveBeenCalled()
  })

  it('maps the server 422 of a row onto that row', async () => {
    updateProductMock.mockRejectedValue(unprocessable({ 'suppliers.0.cost': ['The cost must be at least 0.'] }))
    renderDetail(productFixture({ usages: ['SALE', 'COST'], suppliers: TWO_SUPPLIERS }))

    fireEvent.click(pencilOf('Suppliers'))
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Cost of supplier 1' }), { target: { value: '11' } })
    confirmOpenRow('Save')

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('The cost must be at least 0.')
    expect(screen.getByRole('spinbutton', { name: 'Cost of supplier 1' })).toHaveAttribute('aria-invalid', 'true')
  })

  it('creates a product sending the suppliers and no supplier_id', async () => {
    createProductMock.mockResolvedValue(productFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Code' })

    completeRequiredCreateRows()
    fireEvent.click(pencilOf('Suppliers'))
    fireEvent.click(screen.getByRole('button', { name: 'Add supplier' }))
    fireEvent.click(screen.getByRole('button', { name: 'Supplier 1' }))
    fireEvent.change(screen.getByRole('spinbutton', { name: 'Price of supplier 1' }), { target: { value: '10' } })
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createProductMock).toHaveBeenCalledTimes(1))
    const payload = createProductMock.mock.calls[0][0]
    expect(payload.suppliers).toEqual([{ supplier_id: 11, price: 10, cost: 0, is_default: true }])
    expect(payload).not.toHaveProperty('supplier_id')
  })

  it('hides the suppliers row when the suppliers field permission hides it', async () => {
    renderDetail(
      productFixture({
        suppliers: TWO_SUPPLIERS,
        permissions: permissionsFor({
          suppliers: { ...EDITABLE, visible: false, hidden: true },
        }),
      }),
    )

    expect(screen.queryByText('ACME')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Suppliers' })).not.toBeInTheDocument()
  })
})
