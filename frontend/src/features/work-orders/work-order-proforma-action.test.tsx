import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { ProformaSummary } from '@/features/proforma-requests/types'
import { ProformaRequestDialog } from '@/features/work-orders/proforma-request-dialog'
import { WorkOrderActionsMenu } from '@/features/work-orders/work-order-actions-menu'
import { hasWorkOrderAction, selectWorkOrderAction } from '@/features/work-orders/work-order-actions-menu-test-utils'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

const fetchSummaryMock = vi.fn<(id: number) => Promise<ProformaSummary>>()
const createRequestsMock = vi.fn()
vi.mock('@/features/proforma-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/proforma-requests/api')>()),
  fetchProformaSummary: (id: number) => fetchSummaryMock(id),
  createProformaRequests: (...args: unknown[]) => createRequestsMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function summary(overrides: Partial<ProformaSummary> = {}): ProformaSummary {
  return {
    status: 'none',
    last_requested_at: null,
    payment_method: { id: 1, name: 'Bonifico 30 gg' },
    work_order: { id: 7, code: 'C-2026-007' },
    ...overrides,
  }
}

function renderDialog(onSent = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ProformaRequestDialog workOrderId={7} workOrderCode="C-2026-007" open onOpenChange={vi.fn()} onSent={onSent} />
    </QueryClientProvider>,
  )
  return onSent
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchSummaryMock.mockReset()
  createRequestsMock.mockReset()
})

// REQUIREMENT CHANGED (spec 0405): the grid row has no "€" action; the dialog opens from
// the detail header and the "Responsabili e Partecipanti" dialog.
describe('proforma request dialog (spec 0193 AC-007)', () => {
  it('opens the modal with the work order code and payment method, then sends the note', async () => {
    fetchSummaryMock.mockResolvedValue(summary())
    createRequestsMock.mockResolvedValue([])
    const onSent = renderDialog()

    expect(await screen.findByRole('heading', { name: /Work order #C-2026-007/ })).toBeInTheDocument()
    expect(await screen.findByText('Bonifico 30 gg')).toBeInTheDocument()

    fireEvent.change(screen.getByRole('textbox', { name: /notes for accounting/i }), {
      target: { value: 'Please issue the proforma' },
    })
    fireEvent.click(screen.getByRole('button', { name: /send request/i }))

    await waitFor(() => expect(createRequestsMock).toHaveBeenCalledWith(7, { note: 'Please issue the proforma' }))
    await waitFor(() => expect(onSent).toHaveBeenCalled())
  })

  it('shows the last request date and disables sending when a request is already pending', async () => {
    fetchSummaryMock.mockResolvedValue(
      summary({ status: 'pending', last_requested_at: '2026-10-01T09:30:00Z', payment_method: null }),
    )
    renderDialog()

    expect(await screen.findByText(/last request on/i)).toBeInTheDocument()
    expect(screen.getByText('Not specified')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /send request/i })).toBeDisabled()
    expect(createRequestsMock).not.toHaveBeenCalled()
  })
})

// REQUIREMENT CHANGED (user directive 2026-11-27): the action is an item of the "..." actions menu.
describe('work order detail "Request proforma" action (ticket #427857)', () => {
  function renderHeader(requestProforma: boolean, onChanged = vi.fn()) {
    const workOrder = {
      id: 7,
      code: 'C-2026-007',
      title: 'Commessa',
      old_id: null,
      type: 'processing',
      is_multi_quote: false,
      is_force_closed: false,
      status: { value: 'in_progress', is_force_closed: false },
      permissions: { resource: { update: true }, fields: {}, actions: { request_proforma: requestProforma } },
    } as unknown as WorkOrderDetailWithPermissions
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <ConfirmDialogProvider>
          <WorkOrderActionsMenu workOrder={workOrder} onChanged={onChanged} />
        </ConfirmDialogProvider>
      </QueryClientProvider>,
    )
    return onChanged
  }

  it('opens the same proforma dialog as the grid and refreshes the record once sent', async () => {
    fetchSummaryMock.mockResolvedValue(summary())
    createRequestsMock.mockResolvedValue([])
    const onChanged = renderHeader(true)

    selectWorkOrderAction('Request proforma')

    expect(await screen.findByRole('heading', { name: /Work order #C-2026-007/ })).toBeInTheDocument()
    fireEvent.change(await screen.findByRole('textbox', { name: /notes for accounting/i }), {
      target: { value: 'Please issue' },
    })
    fireEvent.click(screen.getByRole('button', { name: /send request/i }))

    await waitFor(() => expect(createRequestsMock).toHaveBeenCalledWith(7, { note: 'Please issue' }))
    await waitFor(() => expect(onChanged).toHaveBeenCalled())
    expect(screen.queryByRole('heading', { name: /Work order #C-2026-007/ })).not.toBeInTheDocument()
  })

  it('is absent without the request_proforma flag', () => {
    renderHeader(false)

    expect(hasWorkOrderAction('Request proforma')).toBe(false)
    expect(fetchSummaryMock).not.toHaveBeenCalled()
  })
})
