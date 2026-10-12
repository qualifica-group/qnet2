import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { PurchaseRequestForm } from '@/features/purchase-requests/purchase-request-form'
import { makeLine, makeRequest } from '@/features/purchase-requests/purchase-request-fixtures'
import { toResourcePermissions } from '@/features/purchase-requests/purchase-request-permissions'
import type { LineCapability, PurchaseRequest } from '@/features/purchase-requests/types'
import type { ProductDetailWithPermissions } from '@/features/products/types'

const FOR_SELECT_ITEMS: Record<string, unknown[]> = {
  products: [{ id: 20, label: 'Monitor' }],
  registries: [{ id: 12, label: 'Rossi Srl' }],
}

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn(async (resource: string) => ({
    items: FOR_SELECT_ITEMS[resource] ?? [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 25, total_pages: 1 },
  })),
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

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() } }))

function renderForm(request: PurchaseRequest) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
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

function lockedAbilities(updateSupplier: boolean) {
  const capabilities: LineCapability[] = updateSupplier ? ['fulfill'] : []
  return { update: false, delete: false, transitions: [], capabilities, update_supplier: updateSupplier }
}

const NO_UPDATE = { update: false, delete: false, close: false, notify_manager: false, view_activity: true }

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchProductMock.mockReset().mockResolvedValue({
    id: 20,
    name: 'Monitor 27',
    price: '199.00',
    unit_of_measure_id: 2,
    vat_rate_id: 1,
    vat_rate: { id: 1, name: 'IVA 22%', rate: 22 },
    supplier_id: 12,
    supplier: { id: 12, name: 'Rossi Srl' },
  } as unknown as ProductDetailWithPermissions)
})

describe('line supplier in the form (spec 0209 AC-012)', () => {
  it('has no supplier in the header but one picker per line', () => {
    renderForm(makeRequest({ lines: [makeLine({ id: 11 }), makeLine({ id: 12, position: 2 })] }))

    expect(screen.queryByRole('combobox', { name: /^Supplier$/ })).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Supplier 1' })).toBeEnabled()
    expect(screen.getByRole('combobox', { name: 'Supplier 2' })).toBeEnabled()
  })

  it('shows the supplier of a saved line', () => {
    renderForm(makeRequest({ lines: [makeLine({ supplier: { id: 12, name: 'Rossi Srl' } })] }))

    expect(screen.getByRole('combobox', { name: 'Supplier 1' })).toHaveTextContent('Rossi Srl')
  })

  it('proposes the supplier of the picked product, still editable', async () => {
    renderForm(makeRequest())

    fireEvent.click(screen.getByRole('combobox', { name: /Product 1/ }))
    fireEvent.click(await screen.findByRole('option', { name: /Monitor/ }))

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Supplier 1' })).toHaveTextContent('Rossi Srl'))
    expect(screen.getByRole('combobox', { name: 'Supplier 1' })).toBeEnabled()
  })
})

describe('supplier freeze on a locked line (spec 0209 AC-013)', () => {
  it('enables only the supplier of an approved line with update_supplier when the RDA is updatable', () => {
    renderForm(makeRequest({ lines: [makeLine({ status: 'approved', abilities: lockedAbilities(true) })] }))

    expect(screen.getByRole('combobox', { name: 'Supplier 1' })).toBeEnabled()
    expect(screen.getByLabelText('Description 1')).toBeDisabled()
    expect(screen.getByLabelText('Quantity 1')).toBeDisabled()
    expect(screen.getByLabelText('Unit price 1')).toBeDisabled()
    expect(screen.getByRole('combobox', { name: /Product 1/ })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: /Unit 1/ })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: /VAT rate 1/ })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument()
  })

  it('keeps the supplier disabled and shows no Save when the RDA itself is not updatable', () => {
    renderForm(
      makeRequest({
        lines: [makeLine({ status: 'approved', abilities: lockedAbilities(true) })],
        abilities: NO_UPDATE,
      }),
    )

    expect(screen.getByRole('combobox', { name: 'Supplier 1' })).toBeDisabled()
    expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument()
  })

  it('disables everything, supplier included, without update_supplier', () => {
    renderForm(makeRequest({ lines: [makeLine({ status: 'ordered', abilities: lockedAbilities(false) })] }))

    expect(screen.getByRole('combobox', { name: 'Supplier 1' })).toBeDisabled()
    expect(screen.getByLabelText('Description 1')).toBeDisabled()
  })
})

describe('compact line actions (spec 0209 AC-014)', () => {
  it('exposes the actions as icon-only buttons with an accessible name and no visible text', () => {
    renderForm(makeRequest())
    const line = screen.getByRole('listitem', { name: 'Line 1' })

    for (const name of ['Reason 1', 'Change line status', 'Extract the VAT from the price 1', 'Line documents 1', 'Remove line 1']) {
      expect(within(line).getByRole('button', { name })).toHaveTextContent('')
    }
  })

  it('opens the reason popover bound to the line and highlights the icon once written', async () => {
    renderForm(makeRequest({ lines: [makeLine({ reason: null })] }))
    const emptyClass = screen.getByRole('button', { name: 'Reason 1' }).className

    fireEvent.click(screen.getByRole('button', { name: 'Reason 1' }))
    const textarea = await screen.findByRole('textbox', { name: 'Reason 1' })
    expect(textarea).not.toHaveAttribute('readonly')
    fireEvent.change(textarea, { target: { value: 'Replaces the broken one' } })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Reason 1' }).className).not.toBe(emptyClass))
    expect(screen.getByRole('textbox', { name: 'Reason 1' })).toHaveValue('Replaces the broken one')
  })

  it('shows the reason of a locked line read-only', async () => {
    renderForm(makeRequest({ lines: [makeLine({ status: 'ordered', reason: 'Because', abilities: lockedAbilities(false) })] }))

    fireEvent.click(screen.getByRole('button', { name: 'Reason 1' }))

    expect(await screen.findByRole('textbox', { name: 'Reason 1' })).toHaveAttribute('readonly')
    expect(screen.getByRole('textbox', { name: 'Reason 1' })).toHaveValue('Because')
  })

  it('exposes taxable and VAT of the line on the total (accessible name and tooltip)', async () => {
    renderForm(makeRequest())

    const total = screen.getByLabelText(/^Total 1: /)
    expect(total).toHaveAccessibleName(/Taxable 200\.00, VAT 44\.00/)
    fireEvent.focus(total)

    expect((await screen.findAllByText(/^Taxable 200\.00, VAT 44\.00$/)).length).toBeGreaterThan(0)
  })
})
