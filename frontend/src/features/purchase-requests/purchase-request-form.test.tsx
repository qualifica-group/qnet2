import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { seedLineStatusCatalog } from '@/features/purchase-request-line-statuses/line-status-catalog-fixtures'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import type { ForSelectItem } from '@/features/for-select/types'
import { PurchaseRequestForm } from '@/features/purchase-requests/purchase-request-form'
import { makeRequest } from '@/features/purchase-requests/purchase-request-fixtures'
import { toResourcePermissions } from '@/features/purchase-requests/purchase-request-permissions'
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

vi.mock('@/features/purchase-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-requests/api')>()),
  fetchApproverOptions: vi.fn(async () => ({ source: 'reports_to', approvers: [{ id: 9, name: 'Luca Neri' }] })),
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

function renderForm() {
  const client = seedLineStatusCatalog(new QueryClient({ defaultOptions: { queries: { retry: false } } }))
  const permissions = toResourcePermissions(makeRequest())
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <ResourcePermissionsProvider permissions={permissions}>
          <PurchaseRequestForm currentUser={CURRENT_USER} onSaved={vi.fn()} onCancel={vi.fn()} />
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
  it('offers the approvers of the requester and preselects the only one', async () => {
    renderForm()

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
})
