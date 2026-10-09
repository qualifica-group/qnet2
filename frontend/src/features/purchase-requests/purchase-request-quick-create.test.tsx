import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { resolveQuickCreate } from '@/features/quick-create/quick-create-registry'
import { PurchaseRequestForm } from '@/features/purchase-requests/purchase-request-form'
import { makeRequest } from '@/features/purchase-requests/purchase-request-fixtures'
import { toResourcePermissions } from '@/features/purchase-requests/purchase-request-permissions'
import type { ProductDetailWithPermissions } from '@/features/products/types'

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn().mockResolvedValue({
    items: [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 25, total_pages: 1 },
  }),
}))

const fetchProductMock = vi.fn<(id: number) => Promise<ProductDetailWithPermissions>>()
vi.mock('@/features/products/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/products/api')>()),
  fetchProduct: (id: number) => fetchProductMock(id),
}))

vi.mock('@/features/attachments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/attachments/api')>()),
  listAttachments: vi.fn().mockResolvedValue([]),
}))

// The real module forms need their own metadata round trips: stubs isolate the wiring under test.
vi.mock('@/features/products/product-form', () => ({
  ProductForm: ({ onSuccess }: { onSuccess: (product: { id: number; name: string }) => void }) => (
    <button type="button" onClick={() => onSuccess({ id: 30, name: 'Docking station' })}>
      stub-create-product
    </button>
  ),
}))
vi.mock('@/features/registries/registry-form', () => ({
  RegistryForm: ({ isSupplierPreset }: { isSupplierPreset?: boolean }) => (
    <p>{`registry-form supplier preset: ${String(isSupplierPreset)}`}</p>
  ),
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() } }))

function renderForm() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const request = makeRequest()
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <ResourcePermissionsProvider permissions={toResourcePermissions(request)}>
          <PurchaseRequestForm
            request={request}
            currentUser={{ id: 3, name: 'Mario Rossi' }}
            onSaved={vi.fn()}
            onCancel={vi.fn()}
            onDeleted={vi.fn()}
          />
        </ResourcePermissionsProvider>
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset().mockReturnValue(true)
  fetchProductMock.mockReset().mockResolvedValue({
    id: 30,
    name: 'Docking station',
    price: '80.00',
    unit_of_measure_id: 2,
    vat_rate_id: 1,
    vat_rate: { id: 1, name: 'IVA 22%', rate: 22 },
  } as unknown as ProductDetailWithPermissions)
})

describe('quick-create product (AC-019)', () => {
  it('is registered for the products select with the products.create permission', () => {
    expect(resolveQuickCreate('products')?.permission).toBe('products.create')
  })

  it('creates a product from the line select and selects it in the line', async () => {
    renderForm()

    const plus = screen.getAllByRole('button', { name: 'Create product' })[0]!
    fireEvent.click(plus)
    fireEvent.click(await screen.findByRole('button', { name: 'stub-create-product' }))

    await waitFor(() => expect(screen.getByRole('combobox', { name: /Product 1/ })).toHaveTextContent('Docking station'))
    await waitFor(() => expect(screen.getByLabelText('Description 1')).toHaveValue('Docking station'))
    expect(fetchProductMock).toHaveBeenCalledWith(30)
    expect(screen.getByLabelText('Unit price 1')).toHaveValue(80)
  })

  it('hides the "+" without the products.create permission', () => {
    canMock.mockImplementation((permission) => permission !== 'products.create')
    renderForm()

    expect(screen.queryByRole('button', { name: 'Create product' })).not.toBeInTheDocument()
  })
})

describe('quick-create supplier (D-14)', () => {
  it('opens the registry form with the supplier flag preset', async () => {
    renderForm()
    const registryPlusButtons = screen.getAllByRole('button', { name: 'Create registry' })

    // customer first, supplier second: only the supplier picker presets the flag
    fireEvent.click(registryPlusButtons[1]!)

    expect(await screen.findByText('registry-form supplier preset: true')).toBeInTheDocument()
  })

  it('leaves the flag unset for the customer picker', async () => {
    renderForm()

    fireEvent.click(screen.getAllByRole('button', { name: 'Create registry' })[0]!)

    expect(await screen.findByText('registry-form supplier preset: undefined')).toBeInTheDocument()
  })
})
