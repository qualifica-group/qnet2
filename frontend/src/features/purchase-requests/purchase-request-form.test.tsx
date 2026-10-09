import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import type { ForSelectItem } from '@/features/for-select/types'
import { PurchaseRequestForm } from '@/features/purchase-requests/purchase-request-form'
import { makeLine, makeRequest } from '@/features/purchase-requests/purchase-request-fixtures'
import { toResourcePermissions } from '@/features/purchase-requests/purchase-request-permissions'
import type { PurchaseRequest } from '@/features/purchase-requests/types'
import type { ProductDetailWithPermissions } from '@/features/products/types'

const USERS: ForSelectItem[] = [
  { id: 3, label: 'Mario Rossi' },
  { id: 4, label: 'Anna Verdi' },
  { id: 9, label: 'Luca Neri' },
]
const FOR_SELECT_ITEMS: Record<string, unknown[]> = {
  users: USERS,
  'business-functions': [{ id: 6, label: 'IT', manager: { id: 9, name: 'Luca Neri' } }],
  products: [{ id: 20, label: 'Monitor' }],
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

vi.mock('@/features/attachments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/attachments/api')>()),
  listAttachments: vi.fn().mockResolvedValue([]),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() } }))

const CURRENT_USER = { id: 3, name: 'Mario Rossi' }

function renderForm(request?: PurchaseRequest) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const permissions = toResourcePermissions(request ?? makeRequest())
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <ResourcePermissionsProvider permissions={permissions}>
          <PurchaseRequestForm
            request={request}
            currentUser={CURRENT_USER}
            onSaved={vi.fn()}
            onCancel={vi.fn()}
            onDeleted={vi.fn()}
          />
        </ResourcePermissionsProvider>
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

async function pick(triggerName: RegExp, optionName: RegExp) {
  fireEvent.click(screen.getByRole('combobox', { name: triggerName }))
  fireEvent.click(await screen.findByRole('option', { name: optionName }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchProductMock.mockReset().mockResolvedValue({
    id: 20,
    name: 'Monitor 27',
    price: '199.00',
    unit_of_measure_id: 2,
    unit_of_measure: { id: 2, name: 'Box', symbol: 'bx' },
    vat_rate_id: 1,
    vat_rate: { id: 1, name: 'IVA 22%', rate: 22 },
  } as unknown as ProductDetailWithPermissions)
})

describe('PurchaseRequestForm header (AC-017)', () => {
  it('proposes the function manager when a business function is chosen', async () => {
    renderForm()

    await pick(/Business function/, /IT/)

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: /Function manager/ })).toHaveTextContent('Luca Neri'),
    )
  })

  it('shows the author read-only and defaults the requester to the signed-in user', async () => {
    renderForm()

    expect(screen.getByLabelText('Created by')).toBeDisabled()
    expect(screen.getByLabelText('Created by')).toHaveValue('Mario Rossi')
    await waitFor(() => expect(screen.getByRole('combobox', { name: /Requester/ })).toHaveTextContent('Mario Rossi'))
  })
})

describe('PurchaseRequestForm lines (AC-017)', () => {
  it('proposes description, unit, price and VAT rate when a product is picked', async () => {
    renderForm()

    await pick(/Product 1/, /Monitor/)

    await waitFor(() => expect(screen.getByLabelText('Description 1')).toHaveValue('Monitor 27'))
    expect(screen.getByLabelText('Unit price 1')).toHaveValue(199)
    await waitFor(() => expect(screen.getByRole('combobox', { name: /Unit 1/ })).toHaveTextContent('Box'))
    expect(screen.getByRole('combobox', { name: /VAT rate 1/ })).toHaveTextContent('IVA 22%')
    const line = screen.getByRole('listitem', { name: 'Line 1' })
    expect(within(line).getByText('242.78', { exact: false })).toBeInTheDocument()
  })

  it('renders a non-pending line read-only and keeps the trash only where the line can be deleted', () => {
    const request = makeRequest({
      lines: [
        makeLine({ id: 11, description: 'Pending line' }),
        makeLine({
          id: 12,
          position: 2,
          description: 'Ordered line',
          status: 'ordered',
          abilities: { update: false, delete: false, transitions: ['received'], capabilities: [] },
        }),
        makeLine({
          id: 13,
          position: 3,
          description: 'Rejected line',
          status: 'rejected',
          abilities: { update: false, delete: true, transitions: [], capabilities: [] },
        }),
      ],
    })
    renderForm(request)

    expect(screen.getByLabelText('Description 1')).toBeEnabled()
    expect(screen.getByLabelText('Description 2')).toBeDisabled()
    expect(screen.getByLabelText('Quantity 2')).toBeDisabled()
    expect(screen.queryByRole('button', { name: 'Extract the VAT from the price 2' })).not.toBeInTheDocument()

    expect(screen.getByRole('button', { name: 'Remove line 1' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Remove line 2' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Remove line 3' })).toBeInTheDocument()
  })

  it('offers the status change on a saved line only when the server allows a transition', () => {
    renderForm(
      makeRequest({
        lines: [
          makeLine({ id: 21, abilities: { update: true, delete: true, transitions: ['approved', 'rejected'], capabilities: [] } }),
          makeLine({ id: 22, position: 2, abilities: { update: true, delete: true, transitions: [], capabilities: [] } }),
        ],
      }),
    )

    const first = screen.getByRole('listitem', { name: 'Line 1' })
    const second = screen.getByRole('listitem', { name: 'Line 2' })
    expect(within(second).queryByRole('button', { name: 'Change line status' })).not.toBeInTheDocument()

    fireEvent.click(within(first).getByRole('button', { name: 'Change line status' }))
    const dialog = screen.getByRole('dialog')
    expect(within(dialog).getByText('Approved')).toBeInTheDocument()
    expect(within(dialog).getByText('Rejected')).toBeInTheDocument()
  })

  it('shows the line status and the ODA placeholder as plain read-only text', () => {
    renderForm(makeRequest({ lines: [makeLine({ status: 'approved' })] }))

    const line = screen.getByRole('listitem', { name: 'Line 1' })
    expect(within(line).getByText('Approved')).toBeInTheDocument()
    expect(within(line).getByText('ODA').nextElementSibling).toHaveTextContent('—')
    expect(within(line).queryByRole('textbox', { name: /Status/ })).not.toBeInTheDocument()
  })

  it('extracts the VAT from the unit price with the calculator (122.00 at 22% -> 100.00)', () => {
    renderForm(makeRequest({ lines: [makeLine({ unit_price: '122.00', quantity: '1.000' })] }))

    fireEvent.click(screen.getByRole('button', { name: 'Extract the VAT from the price 1' }))

    expect(screen.getByLabelText('Unit price 1')).toHaveValue(100)
  })

  it('locks the whole form of a closed RDA', () => {
    renderForm(makeRequest({ status: 'closed', abilities: { update: false, delete: false, close: false, notify_manager: false, view_activity: true } }))

    expect(screen.getByLabelText(/^Subject/)).toBeDisabled()
    expect(screen.getByLabelText('Description 1')).toBeDisabled()
    expect(screen.queryByRole('button', { name: 'Add line' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Remove line 1' })).not.toBeInTheDocument()
  })

  it('applies the field permissions: a read-only company is shown disabled (AC-015 client side)', () => {
    renderForm(makeRequest({ field_permissions: { company_id: { visible: true, editable: false, required: true } } }))

    expect(screen.getByRole('combobox', { name: /Company$/ })).toBeDisabled()
    expect(screen.getByRole('combobox', { name: /Operational site/ })).toBeEnabled()
  })
})
