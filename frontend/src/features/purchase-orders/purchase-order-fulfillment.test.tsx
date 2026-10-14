import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import {
  closeLineResidual,
  createReceipt,
  deleteReceipt,
  fetchReceipts,
  reopenLineResidual,
} from '@/features/purchase-orders/api'
import { makeOrderLine, makePurchaseOrder, makeReceipt } from '@/features/purchase-orders/purchase-order-fixtures'
import { PurchaseOrderFulfillmentSection } from '@/features/purchase-orders/purchase-order-fulfillment-section'
import { PurchaseOrderReceiptsSection } from '@/features/purchase-orders/purchase-order-receipts-section'
import type { PurchaseOrder } from '@/features/purchase-orders/types'

vi.mock('@/features/purchase-orders/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-orders/api')>()),
  fetchReceipts: vi.fn(),
  createReceipt: vi.fn(),
  deleteReceipt: vi.fn(),
  closeLineResidual: vi.fn(),
  reopenLineResidual: vi.fn(),
}))

const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() }))
vi.mock('sonner', () => ({ toast: toastMock }))

function sentOrder(overrides: Partial<PurchaseOrder> = {}): PurchaseOrder {
  return makePurchaseOrder({
    status: 'sent_to_supplier',
    lines: [
      makeOrderLine({
        id: 11,
        position: 1,
        description: 'Consulting',
        quantity: '4.000',
        received_quantity: '1.000',
        invoiced_quantity: '0.500',
        residual_quantity: '3.000',
        fulfillment: 'partial',
      }),
      makeOrderLine({
        id: 12,
        position: 2,
        description: 'Licences',
        quantity: '2.000',
        received_quantity: '0.000',
        residual_quantity: '2.000',
        fulfillment: 'open',
      }),
      makeOrderLine({
        id: 13,
        position: 3,
        description: 'Cables',
        quantity: '5.000',
        received_quantity: '2.000',
        residual_quantity: '0.000',
        fulfillment: 'complete',
        residual_closed: {
          at: '2026-10-13T10:00:00Z',
          by: { id: 3, name: 'Mario Rossi' },
          reason: 'Supplier stopped production',
        },
      }),
    ],
    ...overrides,
  })
}

function withoutReceive(order: PurchaseOrder): PurchaseOrder {
  return { ...order, abilities: { ...order.abilities, receive: false } }
}

function axiosError(status: number, data: Record<string, unknown>) {
  return new AxiosError('error', String(status), undefined, undefined, { status, data } as never)
}

function renderWithProviders(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{ui}</ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(fetchReceipts).mockResolvedValue([])
})

describe('Fulfilment lines (AC-013)', () => {
  it('shows Ordered / Received / Invoiced / Residual per line with the state and the closed indicator', async () => {
    renderWithProviders(<PurchaseOrderFulfillmentSection order={sentOrder()} />)

    for (const name of ['Ordered', 'Received', 'Invoiced', 'Residual']) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument()
    }
    const row = screen.getByRole('row', { name: /Consulting/ })
    expect(within(row).getAllByRole('cell').map((cell) => cell.textContent)).toEqual(
      expect.arrayContaining(['4.000', '1.000', '0.500', '3.000']),
    )
    expect(within(row).getByText('Partial')).toBeInTheDocument()
    expect(within(screen.getByRole('row', { name: /Licences/ })).getByText('To receive')).toBeInTheDocument()

    const closedRow = screen.getByRole('row', { name: /Cables/ })
    expect(within(closedRow).getByText('Complete')).toBeInTheDocument()
    const indicator = within(closedRow).getByRole('img', { name: 'Residual closed' })
    indicator.focus()
    expect((await screen.findAllByText('Supplier stopped production')).length).toBeGreaterThan(0)
    expect(screen.getAllByText(/Closed by Mario Rossi/).length).toBeGreaterThan(0)
  })

  it('hides every action without abilities.receive', () => {
    renderWithProviders(<PurchaseOrderFulfillmentSection order={withoutReceive(sentOrder())} />)

    expect(screen.queryByRole('button', { name: 'Register receipt' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Close the residual/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Reopen the residual/ })).not.toBeInTheDocument()
  })

  it('hides Register receipt when no line has a residual left', () => {
    const order = sentOrder({ lines: [makeOrderLine({ residual_quantity: '0.000', fulfillment: 'complete' })] })
    renderWithProviders(<PurchaseOrderFulfillmentSection order={order} />)

    expect(screen.queryByRole('button', { name: 'Register receipt' })).not.toBeInTheDocument()
  })
})

describe('Register receipt dialog (AC-013)', () => {
  it('prefills the residuals, restores them with Receive all and sends only the lines with a quantity', async () => {
    const order = sentOrder()
    vi.mocked(createReceipt).mockResolvedValue({ receipt: makeReceipt(), order })
    renderWithProviders(<PurchaseOrderFulfillmentSection order={order} />)

    fireEvent.click(screen.getByRole('button', { name: 'Register receipt' }))
    const dialog = await screen.findByRole('dialog')
    const first = within(dialog).getByRole('spinbutton', { name: 'Received quantity of line 1' })
    const second = within(dialog).getByRole('spinbutton', { name: 'Received quantity of line 2' })
    expect(first).toHaveValue(3)
    expect(second).toHaveValue(2)
    // The closed line has no residual: it gets no row
    expect(within(dialog).queryByRole('spinbutton', { name: 'Received quantity of line 3' })).not.toBeInTheDocument()

    fireEvent.change(first, { target: { value: '1.5' } })
    fireEvent.change(second, { target: { value: '0' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Receive all' }))
    expect(first).toHaveValue(3)
    expect(second).toHaveValue(2)

    fireEvent.change(first, { target: { value: '1.5' } })
    fireEvent.change(second, { target: { value: '0' } })
    fireEvent.change(within(dialog).getByLabelText('Delivery note number'), { target: { value: 'DDT-7' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Register receipt' }))

    await waitFor(() => expect(createReceipt).toHaveBeenCalledTimes(1))
    const [orderId, payload] = vi.mocked(createReceipt).mock.calls[0]
    expect(orderId).toBe(5)
    expect(payload).toMatchObject({
      delivery_note_number: 'DDT-7',
      delivery_note_date: null,
      notes: null,
      lines: [{ purchase_order_line_id: 11, quantity: '1.500' }],
    })
    expect(payload.receipt_date).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('Receipt registered.'))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('maps the server error to the right row (not the sent index) with the accessible error triad', async () => {
    vi.mocked(createReceipt).mockRejectedValue(
      axiosError(422, { message: 'Invalid.', errors: { 'lines.0.quantity': ['Exceeds the residual.'] } }),
    )
    renderWithProviders(<PurchaseOrderFulfillmentSection order={sentOrder()} />)

    fireEvent.click(screen.getByRole('button', { name: 'Register receipt' }))
    const dialog = await screen.findByRole('dialog')
    // Line 1 is skipped, so the sent list is [line 2] and `lines.0` is the second row
    fireEvent.change(within(dialog).getByRole('spinbutton', { name: 'Received quantity of line 1' }), {
      target: { value: '0' },
    })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Register receipt' }))

    const field = within(dialog).getByRole('spinbutton', { name: 'Received quantity of line 2' })
    await waitFor(() => expect(field).toHaveAttribute('aria-invalid', 'true'))
    const alert = within(dialog).getByText('Exceeds the residual.')
    expect(alert).toHaveAttribute('role', 'alert')
    expect(field.getAttribute('aria-describedby')).toContain(alert.id)
    expect(within(dialog).getByRole('spinbutton', { name: 'Received quantity of line 1' })).toHaveAttribute(
      'aria-invalid',
      'false',
    )
  })

  it('validates on the client: above the residual, and at least one line', async () => {
    renderWithProviders(<PurchaseOrderFulfillmentSection order={sentOrder()} />)

    fireEvent.click(screen.getByRole('button', { name: 'Register receipt' }))
    const dialog = await screen.findByRole('dialog')
    const first = within(dialog).getByRole('spinbutton', { name: 'Received quantity of line 1' })
    fireEvent.change(first, { target: { value: '3.5' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Register receipt' }))
    expect(await within(dialog).findByText('The quantity cannot exceed the residual (3.000).')).toBeInTheDocument()

    fireEvent.change(first, { target: { value: '0' } })
    fireEvent.change(within(dialog).getByRole('spinbutton', { name: 'Received quantity of line 2' }), {
      target: { value: '0' },
    })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Register receipt' }))
    expect(await within(dialog).findByText('Enter the received quantity of at least one line.')).toBeInTheDocument()
    expect(createReceipt).not.toHaveBeenCalled()
  })

  it('shows a 409 as a form-level alert', async () => {
    vi.mocked(createReceipt).mockRejectedValue(axiosError(409, { message: 'The order cannot receive goods.' }))
    renderWithProviders(<PurchaseOrderFulfillmentSection order={sentOrder()} />)

    fireEvent.click(screen.getByRole('button', { name: 'Register receipt' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Register receipt' }))

    expect(await within(dialog).findByText('The order cannot receive goods.')).toBeInTheDocument()
    expect(within(dialog).getByRole('alert')).toHaveTextContent('The order cannot receive goods.')
  })
})

describe('Receipts list (AC-013)', () => {
  it('lists the receipts and cancels one after confirming', async () => {
    vi.mocked(fetchReceipts).mockResolvedValue([makeReceipt(), makeReceipt({ id: 71, abilities: { delete: false }, notes: null })])
    vi.mocked(deleteReceipt).mockResolvedValue(sentOrder())
    renderWithProviders(<PurchaseOrderReceiptsSection order={sentOrder()} />)

    expect(await screen.findAllByText(/Delivery note DDT-9/)).toHaveLength(2)
    expect(screen.getByText('Left at the gate')).toBeInTheDocument()
    expect(screen.getAllByText('by Mario Rossi')).toHaveLength(2)
    const cancelButtons = screen.getAllByRole('button', { name: /Cancel the receipt of/ })
    expect(cancelButtons).toHaveLength(1)

    fireEvent.click(cancelButtons[0])
    fireEvent.click(await screen.findByRole('button', { name: 'Cancel receipt' }))

    await waitFor(() => expect(deleteReceipt).toHaveBeenCalledWith(5, 70))
    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('Receipt cancelled.'))
  })

  it('shows the invoice a receipt came from, with or without its number', async () => {
    vi.mocked(fetchReceipts).mockResolvedValue([
      makeReceipt({ purchase_invoice: { id: 9, document_number: 'FA-9' } }),
      makeReceipt({ id: 71, purchase_invoice: { id: 10, document_number: null } }),
      makeReceipt({ id: 72, purchase_invoice: null }),
    ])
    renderWithProviders(<PurchaseOrderReceiptsSection order={sentOrder()} />)

    expect(await screen.findByText('from invoice no. FA-9')).toBeInTheDocument()
    expect(screen.getByText('from invoice')).toBeInTheDocument()
    expect(screen.getAllByText(/from invoice/)).toHaveLength(2)
  })

  it('shows the empty state and no cancel action without abilities.receive', async () => {
    renderWithProviders(<PurchaseOrderReceiptsSection order={sentOrder()} />)
    expect(await screen.findByText('No receipt registered for this order.')).toBeInTheDocument()
  })

  it('hides the cancel action without abilities.receive even if the receipt allows it', async () => {
    vi.mocked(fetchReceipts).mockResolvedValue([makeReceipt()])
    renderWithProviders(<PurchaseOrderReceiptsSection order={withoutReceive(sentOrder())} />)

    expect(await screen.findByText('Left at the gate')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Cancel the receipt of/ })).not.toBeInTheDocument()
  })
})

describe('Close and reopen residual (AC-014)', () => {
  it('offers Close residual only on lines that are not complete and requires a reason', async () => {
    vi.mocked(closeLineResidual).mockResolvedValue(sentOrder())
    renderWithProviders(<PurchaseOrderFulfillmentSection order={sentOrder()} />)

    expect(screen.getAllByRole('button', { name: /Close the residual of line/ })).toHaveLength(2)
    expect(screen.queryByRole('button', { name: 'Close the residual of line 3' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Close the residual of line 1' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Close residual' }))
    expect(await within(dialog).findByText('The reason is required.')).toBeInTheDocument()
    expect(closeLineResidual).not.toHaveBeenCalled()

    fireEvent.change(within(dialog).getByRole('textbox', { name: /Reason/ }), { target: { value: 'Never delivered' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Close residual' }))

    await waitFor(() => expect(closeLineResidual).toHaveBeenCalledWith(5, 11, 'Never delivered'))
    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('Residual closed.'))
  })

  it('shows a 409 inside the dialog', async () => {
    vi.mocked(closeLineResidual).mockRejectedValue(axiosError(409, { message: 'Invoiced more than received.' }))
    renderWithProviders(<PurchaseOrderFulfillmentSection order={sentOrder()} />)

    fireEvent.click(screen.getByRole('button', { name: 'Close the residual of line 2' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.change(within(dialog).getByRole('textbox', { name: /Reason/ }), { target: { value: 'Lost' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Close residual' }))

    expect(await within(dialog).findByText('Invoiced more than received.')).toBeInTheDocument()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('reopens a closed residual, only on lines that have one', async () => {
    vi.mocked(reopenLineResidual).mockResolvedValue(sentOrder())
    renderWithProviders(<PurchaseOrderFulfillmentSection order={sentOrder()} />)

    expect(screen.getAllByRole('button', { name: /Reopen the residual of line/ })).toHaveLength(1)
    fireEvent.click(screen.getByRole('button', { name: 'Reopen the residual of line 3' }))

    await waitFor(() => expect(reopenLineResidual).toHaveBeenCalledWith(5, 13))
    await waitFor(() => expect(toastMock.success).toHaveBeenCalledWith('Residual reopened.'))
  })
})
