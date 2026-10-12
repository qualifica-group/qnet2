import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { createPurchaseOrder, updatePurchaseOrder } from '@/features/purchase-orders/api'
import { makeOrderLine, makePurchaseOrder } from '@/features/purchase-orders/purchase-order-fixtures'
import { PurchaseOrderForm } from '@/features/purchase-orders/purchase-order-form'
import { toOrderResourcePermissions } from '@/features/purchase-orders/purchase-order-permissions'
import type { PurchaseOrder } from '@/features/purchase-orders/types'
import type { ProductDetailWithPermissions } from '@/features/products/types'

const FOR_SELECT_ITEMS: Record<string, unknown[]> = {
  registries: [
    { id: 8, label: 'Acme Supplies', meta: { is_qualified_supplier: true } },
    { id: 9, label: 'Shady Ltd', meta: { is_qualified_supplier: false } },
  ],
  companies: [{ id: 1, label: 'Qualifica Srl' }],
  'company-sites': [{ id: 2, label: 'Napoli' }],
  products: [{ id: 20, label: 'Monitor' }],
  'cost-types': [{ id: 1, label: 'Collaboratori' }],
  'units-of-measure': [{ id: 2, label: 'Box' }],
  'vat-rates': [{ id: 1, label: 'IVA 22%', meta: { rate: '22.00' } }],
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

vi.mock('@/features/purchase-orders/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-orders/api')>()),
  createPurchaseOrder: vi.fn(),
  updatePurchaseOrder: vi.fn(),
}))

vi.mock('@/features/attachments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/attachments/api')>()),
  listAttachments: vi.fn().mockResolvedValue([]),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() } }))

const onSaved = vi.fn()

function renderForm(order?: PurchaseOrder) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const permissions = order
    ? toOrderResourcePermissions(order)
    : { resource: { view: true, create: true, update: true, delete: false, export: false, import: false }, fields: {}, actions: {} }
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <ResourcePermissionsProvider permissions={permissions}>
          <PurchaseOrderForm order={order} onSaved={onSaved} onCancel={vi.fn()} onDeleted={vi.fn()} />
        </ResourcePermissionsProvider>
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

async function pick(triggerName: RegExp, optionName: RegExp) {
  fireEvent.click(screen.getByRole('combobox', { name: triggerName }))
  fireEvent.click(await screen.findByRole('option', { name: optionName }))
  // The popup must be gone before the next picker is touched.
  await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument())
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  fetchProductMock.mockResolvedValue({
    id: 20,
    name: 'Monitor 27',
    cost: '80.00',
    price: '199.00',
    unit_of_measure_id: 2,
    unit_of_measure: { id: 2, name: 'Box', symbol: 'bx' },
    vat_rate_id: 1,
    vat_rate: { id: 1, name: 'IVA 22%', rate: 22 },
  } as unknown as ProductDetailWithPermissions)
})

describe('PurchaseOrderForm lines (AC-020)', () => {
  it('proposes description, unit, purchase price (cost) and VAT rate when a product is picked', async () => {
    renderForm()

    await pick(/Product 1/, /Monitor/)

    await waitFor(() => expect(screen.getByLabelText('Description 1')).toHaveValue('Monitor 27'))
    expect(screen.getByLabelText('Unit price 1')).toHaveValue(80)
    await waitFor(() => expect(screen.getByRole('combobox', { name: /Unit 1/ })).toHaveTextContent('Box'))
    expect(screen.getByRole('combobox', { name: /VAT rate 1/ })).toHaveTextContent('IVA 22%')
    const line = screen.getByRole('listitem', { name: 'Line 1' })
    expect(within(line).getByText('97.60', { exact: false })).toBeInTheDocument()
  })

  it('shows the "RDA #id" badge on a generated line and keeps it editable', () => {
    renderForm(
      makePurchaseOrder({
        lines: [
          makeOrderLine({ id: 11, purchase_request_line: { id: 70, purchase_request_id: 15 } }),
          makeOrderLine({ id: 12, position: 2, description: 'Manual line' }),
        ],
      }),
    )

    const first = screen.getByRole('listitem', { name: 'Line 1' })
    const second = screen.getByRole('listitem', { name: 'Line 2' })
    expect(within(first).getByText('RDA #15')).toBeInTheDocument()
    expect(within(second).queryByText(/RDA #/)).not.toBeInTheDocument()
    expect(screen.getByLabelText('Description 1')).toBeEnabled()
    expect(screen.getByLabelText('Quantity 1')).toBeEnabled()
  })

  it('extracts the VAT from the unit price with the calculator (122.00 at 22% -> 100.00)', () => {
    renderForm(makePurchaseOrder({ lines: [makeOrderLine({ unit_price: '122.00', quantity: '1.000' })] }))

    fireEvent.click(screen.getByRole('button', { name: 'Extract the VAT from the price 1' }))

    expect(screen.getByLabelText('Unit price 1')).toHaveValue(100)
  })

  it('gives a new line the work order of the header', async () => {
    renderForm()
    FOR_SELECT_ITEMS['work-orders'] = [{ id: 31, label: 'WO-31' }]

    await pick(/^Work order$/, /WO-31/)
    fireEvent.click(screen.getByRole('button', { name: 'Add line' }))

    await waitFor(() => expect(screen.getByRole('combobox', { name: /Work order 2/ })).toHaveTextContent('WO-31'))
    delete FOR_SELECT_ITEMS['work-orders']
  })
})

describe('PurchaseOrderForm unqualified supplier (AC-020, D-15)', () => {
  it('opens the modal on an unqualified pick: Abandon clears the field', async () => {
    renderForm()

    await pick(/^Supplier$/, /Shady Ltd/)
    const dialog = await screen.findByRole('alertdialog')
    expect(within(dialog).getByText('Unqualified supplier')).toBeInTheDocument()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Abandon' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(screen.getByRole('combobox', { name: /^Supplier$/ })).not.toHaveTextContent('Shady Ltd')
    expect(screen.queryByText('This supplier is not qualified.')).not.toBeInTheDocument()
  })

  it('Proceed anyway keeps the supplier and leaves the red notice under the field', async () => {
    renderForm()

    await pick(/^Supplier$/, /Shady Ltd/)
    fireEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Proceed anyway' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(screen.getByRole('combobox', { name: /^Supplier$/ })).toHaveTextContent('Shady Ltd')
    expect(screen.getByRole('alert')).toHaveTextContent('This supplier is not qualified.')
  })

  it('does not open the modal for a qualified supplier', async () => {
    renderForm()

    await pick(/^Supplier$/, /Acme Supplies/)

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(screen.queryByText('This supplier is not qualified.')).not.toBeInTheDocument()
  })

  it('opening an existing order with an unqualified supplier shows the notice and no modal', () => {
    renderForm(
      makePurchaseOrder({ supplier: { id: 9, name: 'Shady Ltd', email: null, is_qualified_supplier: false } }),
    )

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(screen.getByText('This supplier is not qualified.')).toBeInTheDocument()
  })
})

describe('PurchaseOrderForm header and save (AC-020)', () => {
  it('shows Copies only while creating', () => {
    const { unmount } = renderForm()
    expect(screen.getByRole('spinbutton', { name: /Copies/ })).toHaveValue(1)
    unmount()

    renderForm(makePurchaseOrder())
    expect(screen.queryByRole('spinbutton', { name: /Copies/ })).not.toBeInTheDocument()
    expect(screen.getByLabelText('Number')).toHaveValue('3/2026')
    expect(screen.getByLabelText('Number')).toBeDisabled()
  })

  it('creates the monthly copies, tells how many and hands the first order to the caller', async () => {
    const created = makePurchaseOrder({ id: 5 })
    vi.mocked(createPurchaseOrder).mockResolvedValue({ order: created, createdIds: [5, 6, 7] })
    renderForm()

    await pick(/^Supplier$/, /Acme Supplies/)
    await pick(/^Company$/, /Qualifica/)
    await pick(/^Company site$/, /Napoli/)
    fireEvent.change(screen.getByLabelText(/^Description(?! \d)/), { target: { value: 'Monthly consulting' } })
    fireEvent.change(screen.getByRole('spinbutton', { name: /Copies/ }), { target: { value: '3' } })
    fireEvent.change(screen.getByLabelText('Description 1'), { target: { value: 'Consulting' } })
    await pick(/Cost type 1/, /Collaboratori/)
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createPurchaseOrder).toHaveBeenCalledTimes(1))
    expect(createPurchaseOrder).toHaveBeenCalledWith(
      expect.objectContaining({ supplier_id: 8, company_id: 1, company_site_id: 2, copies: 3 }),
    )
    await waitFor(() => expect(onSaved).toHaveBeenCalledWith(created, [5, 6, 7]))
    expect(toast.success).toHaveBeenCalledWith('3 purchase orders created.')
  })

  it('updates with the persisted line ids and without copies', async () => {
    const order = makePurchaseOrder()
    vi.mocked(updatePurchaseOrder).mockResolvedValue(order)
    renderForm(order)

    fireEvent.click(screen.getByRole('button', { name: 'Update' }))

    await waitFor(() => expect(updatePurchaseOrder).toHaveBeenCalledTimes(1))
    const [id, payload] = vi.mocked(updatePurchaseOrder).mock.calls[0]!
    expect(id).toBe(5)
    expect(payload).not.toHaveProperty('copies')
    expect(payload.lines.map((line) => line.id)).toEqual([11])
  })

  it('maps a server 422 onto the supplier input', async () => {
    const { AxiosError } = await import('axios')
    vi.mocked(updatePurchaseOrder).mockRejectedValue(
      new AxiosError('invalid', '422', undefined, undefined, {
        status: 422,
        data: { errors: { supplier_id: ['The supplier is not a supplier.'] } },
      } as never),
    )
    renderForm(makePurchaseOrder())

    fireEvent.click(screen.getByRole('button', { name: 'Update' }))

    expect(await screen.findByText('The supplier is not a supplier.')).toBeInTheDocument()
  })
})

describe('PurchaseOrderForm read-only (AC-020)', () => {
  it('locks everything on an approved order', () => {
    renderForm(makePurchaseOrder({ status: 'approved' }))

    expect(screen.getByRole('status')).toHaveTextContent('This ODA is approved and cannot be changed.')
    expect(screen.getByLabelText('Description 1')).toBeDisabled()
    expect(screen.getByLabelText('Quantity 1')).toBeDisabled()
    expect(screen.getByRole('combobox', { name: /^Supplier$/ })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: /Cost type 1/ })).toBeDisabled()
    expect(screen.queryByRole('button', { name: 'Add line' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Update' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Remove line 1' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Close' })).toBeInTheDocument()
  })

  it('locks a draft the actor cannot update, without the status notice', () => {
    const order = makePurchaseOrder()
    order.abilities = { ...order.abilities, update: false }
    renderForm(order)

    expect(screen.getByLabelText('Description 1')).toBeDisabled()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Update' })).not.toBeInTheDocument()
  })

  it('applies the field permissions: a read-only company is shown disabled', () => {
    renderForm(makePurchaseOrder({ field_permissions: { company_id: { visible: true, editable: false, required: true } } }))

    expect(screen.getByRole('combobox', { name: /^Company$/ })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: /Operational site/ })).toBeEnabled()
  })

  it('offers Delete on an editable order that can be deleted', () => {
    renderForm(makePurchaseOrder())

    expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Remove line 1' })).toBeInTheDocument()
  })
})
