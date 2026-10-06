import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, renderHook, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { createRowActionsRenderer } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { ProformaSummary } from '@/features/proforma-requests/types'
import { WORK_ORDER_ACTION_ICONS, useProformaActionState } from '@/features/work-orders/proforma-row-action'
import { WorkOrderProformaDialog } from '@/features/work-orders/work-order-proforma-dialog'

const fetchSummaryMock = vi.fn<(id: number) => Promise<ProformaSummary>>()
const createRequestsMock = vi.fn()
vi.mock('@/features/proforma-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/proforma-requests/api')>()),
  fetchProformaSummary: (id: number) => fetchSummaryMock(id),
  createProformaRequests: (...args: unknown[]) => createRequestsMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const PROFORMA_ACTION: TableActionDefinition = {
  key: 'proforma',
  label: 'proformaRequests.cell.none',
  icon: 'euro',
  type: 'action',
  confirm: false,
}

function row(status: string): TableRow {
  return { id: 7, code: 'C-2026-007', proforma_status: status, actions: ['proforma'] }
}

function summary(overrides: Partial<ProformaSummary> = {}): ProformaSummary {
  return {
    status: 'none',
    last_requested_at: null,
    payment_method: { id: 1, name: 'Bonifico 30 gg' },
    work_order: { id: 7, code: 'C-2026-007' },
    ...overrides,
  }
}

function renderActionCell(status: string, onAction = vi.fn()) {
  const resolveActionState = renderHook(() => useProformaActionState()).result.current
  const Cell = createRowActionsRenderer([PROFORMA_ACTION], onAction, {
    resolveActionState,
    iconMap: WORK_ORDER_ACTION_ICONS,
  })
  const params = { data: row(status) } as unknown as ICellRendererParams
  return render(
    <ConfirmDialogProvider>
      <Cell {...params} />
    </ConfirmDialogProvider>,
  )
}

function renderDialog(onSent = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <WorkOrderProformaDialog row={row('none')} onClose={vi.fn()} onSent={onSent} />
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

describe('work order "€" row action (spec 0193 AC-007)', () => {
  it('is grey/blue and clickable for none/pending, yellow and disabled once issued', () => {
    const onAction = vi.fn()
    const none = renderActionCell('none', onAction)
    const grey = screen.getByRole('button', { name: /request proforma issue/i })
    expect(grey).toHaveClass('text-muted-foreground')
    fireEvent.click(grey)
    expect(onAction).toHaveBeenCalledWith(PROFORMA_ACTION, expect.objectContaining({ id: 7 }))
    none.unmount()

    const pending = renderActionCell('pending')
    expect(screen.getByRole('button', { name: /proforma requested/i })).toHaveClass('text-blue-600')
    pending.unmount()

    renderActionCell('issued')
    const yellow = screen.getByRole('button', { name: /proforma issued/i })
    expect(yellow).toHaveClass('text-yellow-600')
    expect(yellow).toBeDisabled()
  })

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
