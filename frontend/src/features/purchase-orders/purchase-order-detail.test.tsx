import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { updatePurchaseOrder } from '@/features/purchase-orders/api'
import { makeOrderLine, makePurchaseOrder } from '@/features/purchase-orders/purchase-order-fixtures'
import { PurchaseOrderDetailView } from '@/features/purchase-orders/purchase-order-detail'
import type { PurchaseOrder } from '@/features/purchase-orders/types'
import { PURCHASE_ORDER_STATUS_FOR_SELECT_ITEMS } from '@/features/purchase-order-statuses/purchase-order-status-fixtures'

const FOR_SELECT_ITEMS: Record<string, unknown[]> = {
  'purchase-order-statuses': PURCHASE_ORDER_STATUS_FOR_SELECT_ITEMS,
}

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn(async (resource: string) => ({
    items: FOR_SELECT_ITEMS[resource] ?? [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 25, total_pages: 1 },
  })),
}))

vi.mock('@/features/purchase-orders/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-orders/api')>()),
  updatePurchaseOrder: vi.fn(),
}))

vi.mock('@/features/attachments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/attachments/api')>()),
  listAttachments: vi.fn().mockResolvedValue([]),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 3, name: 'Mario Rossi' } }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() } }))

function renderDetail(order: PurchaseOrder = makePurchaseOrder()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <ConfirmDialogProvider>
          <PurchaseOrderDetailView order={order} onBack={vi.fn()} onDeleted={vi.fn()} />
        </ConfirmDialogProvider>
      </QueryClientProvider>
    </MemoryRouter>,
  )
}

const TWO_LINES = [makeOrderLine({ id: 11 }), makeOrderLine({ id: 12, position: 2, description: 'Second' })]

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(updatePurchaseOrder).mockResolvedValue(makePurchaseOrder())
})

describe('ODA detail header, edited in place (spec 0257 AC-004)', () => {
  it('has no form Save button and shows the number and the locked company read-only', () => {
    renderDetail()

    expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument()
    expect(screen.getByText('3/2026')).toBeInTheDocument()
    expect(screen.getByText('Qualifica Srl')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Company' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit Company site' })).toBeInTheDocument()
  })

  it('saves only the edited field', async () => {
    renderDetail()

    fireEvent.click(screen.getByRole('button', { name: 'Edit Description' }))
    fireEvent.change(screen.getByLabelText(/^Description/), { target: { value: 'Toner' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updatePurchaseOrder).toHaveBeenCalledWith(5, { description: 'Toner' }))
  })

  it('shows a refusal on another field on the closed row of that field', async () => {
    const { AxiosError } = await import('axios')
    vi.mocked(updatePurchaseOrder).mockRejectedValue(
      new AxiosError('invalid', '422', undefined, undefined, {
        status: 422,
        data: { message: 'The supplier is not a supplier.', errors: { supplier_id: ['The supplier is not a supplier.'] } },
      } as never),
    )
    renderDetail()

    fireEvent.click(screen.getByRole('button', { name: 'Edit Description' }))
    fireEvent.change(screen.getByLabelText(/^Description/), { target: { value: 'Toner' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('The supplier is not a supplier.')).toBeInTheDocument()
  })

  it('shows the unqualified supplier notice without opening the modal', () => {
    renderDetail(makePurchaseOrder({ supplier: { id: 9, name: 'Shady Ltd', email: null, is_qualified_supplier: false } }))

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(screen.getByText('This supplier is not qualified.')).toBeInTheDocument()
  })
})

describe('ODA detail lines, one at a time (spec 0257 AC-004)', () => {
  it('shows the "RDA #id" badge and opens a generated line for editing', () => {
    renderDetail(
      makePurchaseOrder({
        lines: [makeOrderLine({ id: 11, purchase_request_line: { id: 70, purchase_request_id: 15 } }), TWO_LINES[1]!],
      }),
    )

    expect(within(screen.getByRole('listitem', { name: 'Line 1' })).getByText('RDA #15')).toBeInTheDocument()
    expect(within(screen.getByRole('listitem', { name: 'Line 2' })).queryByText(/RDA #/)).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Edit line 1' }))
    expect(screen.getByLabelText('Description 1')).toBeEnabled()
  })

  it('saves the edited line with the persisted line ids', async () => {
    renderDetail(makePurchaseOrder({ lines: TWO_LINES }))

    fireEvent.click(screen.getByRole('button', { name: 'Edit line 2' }))
    fireEvent.change(screen.getByLabelText('Description 2'), { target: { value: 'Edited' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updatePurchaseOrder).toHaveBeenCalledTimes(1))
    const [id, payload] = vi.mocked(updatePurchaseOrder).mock.calls[0]!
    expect(id).toBe(5)
    expect(Object.keys(payload)).toEqual(['lines'])
    expect(payload.lines?.map((line) => [line.id, line.description])).toEqual([
      [11, 'Consulting'],
      [12, 'Edited'],
    ])
  })

  it('extracts the VAT inside the open line (122.00 at 22% -> 100.00)', () => {
    renderDetail(makePurchaseOrder({ lines: [makeOrderLine({ unit_price: '122.00', quantity: '1.000' })] }))

    fireEvent.click(screen.getByRole('button', { name: 'Edit line 1' }))
    fireEvent.click(screen.getByRole('button', { name: 'Extract the VAT from the price 1' }))

    expect(screen.getByLabelText('Unit price 1')).toHaveValue(100)
  })

  it('deletes a line after confirmation, never the last one', async () => {
    const { unmount } = renderDetail()
    expect(screen.queryByRole('button', { name: 'Remove line 1' })).not.toBeInTheDocument()
    unmount()

    renderDetail(makePurchaseOrder({ lines: TWO_LINES }))
    fireEvent.click(screen.getByRole('button', { name: 'Remove line 1' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Delete line' }))

    await waitFor(() => expect(updatePurchaseOrder).toHaveBeenCalledTimes(1))
    expect(vi.mocked(updatePurchaseOrder).mock.calls[0]![1].lines?.map((line) => line.id)).toEqual([12])
  })

  it('refuses to open the merge dialog while an editor is open (AC-021)', async () => {
    renderDetail()

    fireEvent.click(screen.getByRole('button', { name: 'Edit line 1' }))
    fireEvent.click(screen.getByRole('button', { name: 'Merge' }))

    await waitFor(() => expect(toast.warning).toHaveBeenCalled())
  })
})

describe('ODA detail read-only', () => {
  it('locks everything on an approved order', async () => {
    renderDetail(makePurchaseOrder({ status: 'approved', lines: TWO_LINES }))

    expect(await screen.findByText('This ODA is approved and cannot be changed.')).toBeInTheDocument()
    expect(screen.queryAllByRole('button', { name: /^Edit / })).toHaveLength(0)
    expect(screen.queryByRole('button', { name: 'Add line' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Remove line 1' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument()
  })

  it('locks a draft the actor cannot update, without the status notice', () => {
    const order = makePurchaseOrder()
    order.abilities = { ...order.abilities, update: false }
    renderDetail(order)

    expect(screen.queryAllByRole('button', { name: /^Edit / })).toHaveLength(0)
    expect(screen.queryByText(/cannot be changed/)).not.toBeInTheDocument()
  })

  it('offers Delete on an editable order that can be deleted', () => {
    renderDetail()

    expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument()
  })
})
