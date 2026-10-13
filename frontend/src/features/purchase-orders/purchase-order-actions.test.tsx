import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import {
  changePurchaseOrderStatus,
  downloadPurchaseOrderPdf,
  fetchMergeCandidates,
  fetchMergeLogs,
  mergePurchaseOrders,
} from '@/features/purchase-orders/api'
import { makePurchaseOrder } from '@/features/purchase-orders/purchase-order-fixtures'
import { PurchaseOrderFormActions } from '@/features/purchase-orders/purchase-order-form-actions'
import type { MergeCandidate, MergeLog, PurchaseOrder } from '@/features/purchase-orders/types'
import { createOutboundEmailDraft } from '@/features/outbound-emails/api'
import type { OutboundEmail } from '@/features/outbound-emails/types'

vi.mock('@/features/purchase-orders/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-orders/api')>()),
  changePurchaseOrderStatus: vi.fn(),
  fetchMergeCandidates: vi.fn(),
  mergePurchaseOrders: vi.fn(),
  fetchMergeLogs: vi.fn(),
  downloadPurchaseOrderPdf: vi.fn(),
}))

vi.mock('@/features/purchase-order-statuses/for-select-api', async () => {
  const { PURCHASE_ORDER_STATUS_CATALOG_FIXTURE } = await import('@/features/purchase-order-statuses/purchase-order-status-fixtures')
  return { fetchPurchaseOrderStatusCatalog: async () => PURCHASE_ORDER_STATUS_CATALOG_FIXTURE }
})

vi.mock('@/features/outbound-emails/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/outbound-emails/api')>()),
  createOutboundEmailDraft: vi.fn(),
}))

vi.mock('@/features/outbound-emails/outbound-email-composer-dialog', () => ({
  OutboundEmailComposerDialog: ({ owner, emailId }: { owner: { type: string; id: number }; emailId: number }) => (
    <div role="dialog" aria-label="Email composer">{`${owner.type}/${owner.id}/${emailId}`}</div>
  ),
}))

const canMock = vi.hoisted(() => vi.fn<(permission: string) => boolean>())
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/purchase-invoices/purchase-invoice-editor-dialog', () => ({
  PurchaseInvoiceEditorDialog: ({ mode, purchaseOrderIds }: { mode: string; purchaseOrderIds?: number[] }) => (
    <div role="dialog" aria-label="Invoice editor">{`${mode}/${(purchaseOrderIds ?? []).join(',')}`}</div>
  ),
}))

const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }))
vi.mock('sonner', () => ({ toast: toastMock }))

const CANDIDATES: MergeCandidate[] = [
  {
    id: 21,
    code: '7/2026',
    order_date: '2026-10-01',
    description: 'Office supplies',
    status: 'draft',
    grand_total: '300.00',
    lines: [
      { id: 101, description: 'Paper', quantity: '1.000', unit_price: '100.00', taxable_amount: '100.00', total_amount: '122.00' },
      { id: 102, description: 'Toner', quantity: '2.000', unit_price: '100.00', taxable_amount: '200.00', total_amount: '244.00' },
    ],
  },
  {
    id: 22,
    code: '8/2026',
    order_date: '2026-10-02',
    description: 'Furniture',
    status: 'in_validation',
    grand_total: '50.00',
    lines: [
      { id: 201, description: 'Chair', quantity: '1.000', unit_price: '50.00', taxable_amount: '50.00', total_amount: '61.00' },
    ],
  },
]

const LOGS: MergeLog[] = [
  {
    id: 1,
    source_purchase_order_id: null,
    source_code: '5/2026',
    source_action: 'deleted',
    source_description: 'Old order',
    source_order_date: '2026-09-30',
    source_grand_total: '122.00',
    moved_lines: [{ description: 'Paper', quantity: '1.000', unit_price: '100.00', taxable_amount: '100.00', total_amount: '122.00' }],
    performed_by: { id: 3, name: 'Mario Rossi' },
    created_at: '2026-10-03T09:00:00Z',
  },
  {
    id: 2,
    source_purchase_order_id: 22,
    source_code: '8/2026',
    source_action: 'partial',
    source_description: 'Furniture',
    source_order_date: '2026-10-02',
    source_grand_total: '50.00',
    moved_lines: [{ description: 'Chair', quantity: '1.000', unit_price: '50.00', taxable_amount: '50.00', total_amount: '61.00' }],
    performed_by: { id: 3, name: 'Mario Rossi' },
    created_at: '2026-10-04T09:00:00Z',
  },
]

function axiosError(status: number, message: string) {
  return new AxiosError('error', String(status), undefined, undefined, {
    status,
    data: { success: false, message },
  } as never)
}

function renderActions(order: PurchaseOrder = makePurchaseOrder(), isDirty = false) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <PurchaseOrderFormActions order={order} isDirty={isDirty} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  canMock.mockReturnValue(true)
})

describe('PurchaseOrderFormActions - gating', () => {
  it('shows Merge only with the ability on an editable order, and History only with logs', () => {
    const { unmount } = renderActions(makePurchaseOrder({ merge_logs_count: 0 }))
    expect(screen.getByRole('button', { name: 'Merge' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Merge history' })).not.toBeInTheDocument()
    unmount()

    const approved = makePurchaseOrder({ status: 'approved', merge_logs_count: 2 })
    const { unmount: unmountApproved } = renderActions(approved)
    expect(screen.queryByRole('button', { name: 'Merge' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Merge history' })).toBeInTheDocument()
    unmountApproved()

    const noAbilities = makePurchaseOrder()
    noAbilities.abilities = { ...noAbilities.abilities, merge: false, send: false }
    renderActions(noAbilities)
    expect(screen.queryByRole('button', { name: 'Merge' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Send to supplier' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Print PDF' })).toBeInTheDocument()
  })

  it('hides Send to supplier on an approved order when abilities.send is false', () => {
    const order = makePurchaseOrder({ status: 'approved' })
    order.abilities = { ...order.abilities, send: false }
    const { unmount } = renderActions(order)
    expect(screen.queryByRole('button', { name: 'Send to supplier' })).not.toBeInTheDocument()
    unmount()

    renderActions(makePurchaseOrder({ status: 'approved' }))
    expect(screen.getByRole('button', { name: 'Send to supplier' })).toBeInTheDocument()
  })
})

describe('PurchaseOrderFormActions - Register invoice (spec 0250 AC-013)', () => {
  it.each(['approved', 'sent_to_supplier', 'partially_fulfilled', 'fulfilled'] as const)(
    'opens the invoice editor preloaded with the order when the order is %s',
    (status) => {
      renderActions(makePurchaseOrder({ status }))

      fireEvent.click(screen.getByRole('button', { name: 'Register invoice' }))

      expect(screen.getByRole('dialog', { name: 'Invoice editor' })).toHaveTextContent('create/5')
    },
  )

  it.each(['draft', 'in_validation', 'cancelled'] as const)('is hidden when the order is %s', (status) => {
    renderActions(makePurchaseOrder({ status }))

    expect(screen.queryByRole('button', { name: 'Register invoice' })).not.toBeInTheDocument()
  })

  it('is hidden without the purchase-invoices.create permission', () => {
    canMock.mockImplementation((permission) => permission !== 'purchase-invoices.create')
    renderActions(makePurchaseOrder({ status: 'approved' }))

    expect(screen.queryByRole('button', { name: 'Register invoice' })).not.toBeInTheDocument()
  })
})

describe('Status dialog (AC-021)', () => {
  it('offers only the allowed transitions and keeps Confirm disabled until one is chosen', async () => {
    vi.mocked(changePurchaseOrderStatus).mockResolvedValue(makePurchaseOrder({ status: 'approved' }))
    renderActions(makePurchaseOrder({ abilities: { ...makePurchaseOrder().abilities, transitions: ['approved', 'cancelled'] } }))

    fireEvent.click(screen.getByRole('button', { name: 'Change status' }))
    const dialog = await screen.findByRole('dialog')

    // Labels come from the status catalog, which loads asynchronously.
    await waitFor(() =>
      expect(within(dialog).getAllByRole('radio').map((radio) => radio.closest('label')?.textContent)).toEqual([
        'Approved',
        'Cancelled',
      ]),
    )
    expect(within(dialog).queryByRole('radio', { name: 'In validation' })).not.toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Change status' })).toBeDisabled()

    fireEvent.click(within(dialog).getByRole('radio', { name: 'Approved' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Change status' }))

    await waitFor(() => expect(changePurchaseOrderStatus).toHaveBeenCalledWith(5, 'approved'))
    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('Status changed to Approved.'))
  })

  it('says so and cannot confirm when no transition is available', async () => {
    renderActions(makePurchaseOrder({ abilities: { ...makePurchaseOrder().abilities, transitions: [] } }))

    fireEvent.click(screen.getByRole('button', { name: 'Change status' }))
    const dialog = await screen.findByRole('dialog')

    expect(within(dialog).getByText(/No status change is available/)).toBeInTheDocument()
    expect(within(dialog).queryAllByRole('radio')).toHaveLength(0)
    expect(within(dialog).getByRole('button', { name: 'Change status' })).toBeDisabled()
  })

  it('shows the server message when the change is refused (422)', async () => {
    vi.mocked(changePurchaseOrderStatus).mockRejectedValue(axiosError(422, 'Transition not allowed.'))
    renderActions()

    fireEvent.click(screen.getByRole('button', { name: 'Change status' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(await within(dialog).findByRole('radio', { name: 'Cancelled' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Change status' }))

    await waitFor(() => expect(toastMock.error).toHaveBeenCalledWith('Transition not allowed.'))
  })
})

describe('Merge dialog (AC-021)', () => {
  it('selects a whole order or single lines and keeps Confirm disabled without selection', async () => {
    vi.mocked(fetchMergeCandidates).mockResolvedValue(CANDIDATES)
    vi.mocked(mergePurchaseOrders).mockResolvedValue({ order: makePurchaseOrder(), deletedIds: [22] })
    renderActions()

    fireEvent.click(screen.getByRole('button', { name: 'Merge' }))
    const dialog = await screen.findByRole('dialog')
    await within(dialog).findByText('7/2026')

    expect(within(dialog).getByRole('button', { name: 'Merge 0 lines' })).toBeDisabled()
    expect(within(dialog).getByText('Orders left without lines are deleted.')).toBeInTheDocument()

    fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Select all lines of order 7/2026' }))
    expect(within(dialog).getByRole('checkbox', { name: 'Select line Paper' })).toBeChecked()
    expect(within(dialog).getByRole('checkbox', { name: 'Select line Toner' })).toBeChecked()
    expect(within(dialog).getByRole('button', { name: 'Merge 2 lines' })).toBeEnabled()

    fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Select line Toner' }))
    expect(within(dialog).getByRole('checkbox', { name: 'Select all lines of order 7/2026' })).toHaveAttribute(
      'aria-checked',
      'mixed',
    )

    fireEvent.click(within(dialog).getByRole('checkbox', { name: 'Select line Chair' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Merge 2 lines' }))

    await waitFor(() => expect(mergePurchaseOrders).toHaveBeenCalledWith(5, [101, 201]))
    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('2 lines merged.'))
    expect(toastMock.info).toHaveBeenCalledWith('1 emptied order was deleted.')
  })

  it('shows the server message when the merge is refused', async () => {
    vi.mocked(fetchMergeCandidates).mockResolvedValue(CANDIDATES)
    vi.mocked(mergePurchaseOrders).mockRejectedValue(axiosError(422, 'Different supplier.'))
    renderActions()

    fireEvent.click(screen.getByRole('button', { name: 'Merge' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(await within(dialog).findByRole('checkbox', { name: 'Select line Chair' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Merge 1 line' }))

    await waitFor(() => expect(toastMock.error).toHaveBeenCalledWith('Different supplier.'))
  })

  it('does not open while the form has unsaved changes and asks to save first', () => {
    renderActions(makePurchaseOrder(), true)

    fireEvent.click(screen.getByRole('button', { name: 'Merge' }))

    expect(toastMock.warning).toHaveBeenCalledWith(
      'Save the changes to the order before merging: the merge takes effect immediately.',
    )
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(fetchMergeCandidates).not.toHaveBeenCalled()
  })
})

describe('Merge history dialog', () => {
  it('lists the logs with their action badge, source, user and moved amounts', async () => {
    vi.mocked(fetchMergeLogs).mockResolvedValue(LOGS)
    renderActions(makePurchaseOrder({ merge_logs_count: 2 }))

    fireEvent.click(screen.getByRole('button', { name: 'Merge history' }))
    const dialog = await screen.findByRole('dialog')

    expect(await within(dialog).findByText('5/2026')).toBeInTheDocument()
    expect(within(dialog).getByText('Order deleted')).toBeInTheDocument()
    expect(within(dialog).getByText('Partial merge')).toBeInTheDocument()
    expect(within(dialog).getAllByText(/by Mario Rossi/)).toHaveLength(2)
    expect(within(dialog).getAllByText('122.00').length).toBeGreaterThan(0)
  })
})

describe('PDF and send to supplier', () => {
  it('downloads the PDF and shows the server message when no layout is active', async () => {
    vi.mocked(downloadPurchaseOrderPdf).mockRejectedValueOnce(axiosError(422, 'No active layout.'))
    renderActions()

    fireEvent.click(screen.getByRole('button', { name: 'Print PDF' }))
    await waitFor(() => expect(toastMock.error).toHaveBeenCalledWith('No active layout.'))

    vi.mocked(downloadPurchaseOrderPdf).mockResolvedValueOnce(undefined)
    fireEvent.click(screen.getByRole('button', { name: 'Print PDF' }))
    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('PDF generated.'))
    expect(downloadPurchaseOrderPdf).toHaveBeenCalledWith(5)
  })

  it('creates the draft on /purchase-orders/{id}/emails with the PDF attached and opens the composer', async () => {
    vi.mocked(createOutboundEmailDraft).mockResolvedValue({ id: 99 } as unknown as OutboundEmail)
    renderActions()

    fireEvent.click(screen.getByRole('button', { name: 'Send to supplier' }))

    expect(await screen.findByRole('dialog', { name: 'Email composer' })).toHaveTextContent('purchase-orders/5/99')
    expect(createOutboundEmailDraft).toHaveBeenCalledWith({ type: 'purchase-orders', id: 5 }, { attach_pdf: true })
  })
})
