import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { createPurchaseOrder } from '@/features/purchase-orders/api'
import { makePurchaseOrder } from '@/features/purchase-orders/purchase-order-fixtures'
import { PurchaseOrderForm } from '@/features/purchase-orders/purchase-order-form'
import { PURCHASE_ORDER_STATUS_FOR_SELECT_ITEMS } from '@/features/purchase-order-statuses/purchase-order-status-fixtures'
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
  'purchase-order-statuses': PURCHASE_ORDER_STATUS_FOR_SELECT_ITEMS,
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

function renderForm() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const permissions = { resource: { view: true, create: true, update: true, delete: false, export: false, import: false }, fields: {}, actions: {} }
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <ResourcePermissionsProvider permissions={permissions}>
          <PurchaseOrderForm onSaved={onSaved} onCancel={vi.fn()} />
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

})

describe('PurchaseOrderForm header and save (AC-020)', () => {
  it('shows Copies while creating', () => {
    renderForm()
    expect(screen.getByRole('spinbutton', { name: /Copies/ })).toHaveValue(1)
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
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0]!)

    await waitFor(() => expect(createPurchaseOrder).toHaveBeenCalledTimes(1))
    expect(createPurchaseOrder).toHaveBeenCalledWith(
      expect.objectContaining({ supplier_id: 8, company_id: 1, company_site_id: 2, copies: 3 }),
    )
    await waitFor(() => expect(onSaved).toHaveBeenCalledWith(created, [5, 6, 7]))
    expect(toast.success).toHaveBeenCalledWith('3 purchase orders created.')
  })
})
