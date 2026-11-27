import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { copyWorkOrderQuoteAttributes, deleteWorkOrder } from '@/features/work-orders/api'
import { WorkOrderActionsMenu } from '@/features/work-orders/work-order-actions-menu'
import {
  hasWorkOrderAction,
  openWorkOrderActionsMenu,
  selectWorkOrderAction,
} from '@/features/work-orders/work-order-actions-menu-test-utils'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * REQUIREMENT CHANGED (user directive 2026-11-27): the record actions left the
 * header for the "..." tab of the tab strip, like the legacy "Gestione
 * commessa". Delete (user request 2026-11-19) and "Copia dati dall'offerta"
 * (spec 0404 D-D2) keep their rules, now as menu items.
 */

vi.mock('@/features/work-orders/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-orders/api')>()),
  deleteWorkOrder: vi.fn(),
  copyWorkOrderQuoteAttributes: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const REFUSED = 'The work order cannot be deleted: it has linked accounting documents.'

function conflict(): AxiosError {
  const headers = new AxiosHeaders()
  return new AxiosError('Conflict', 'ERR_BAD_REQUEST', { headers }, null, {
    status: 409,
    statusText: 'Conflict',
    headers: {},
    config: { headers },
    data: { success: false, message: REFUSED },
  })
}

interface WorkOrderOptions {
  canDelete?: boolean
  update?: boolean
  hasOffer?: boolean
}

function workOrder({ canDelete = true, update = true, hasOffer = false }: WorkOrderOptions = {}) {
  return {
    id: 7,
    code: 'COM-0007',
    title: 'Commessa',
    old_id: null,
    type: 'processing',
    is_multi_quote: false,
    is_force_closed: false,
    open_tasks_count: 0,
    quote: hasOffer ? { id: 1 } : null,
    source_quotes: hasOffer ? [{ id: 1 }] : [],
    status: { value: 'in_progress', is_force_closed: false },
    permissions: { resource: { update }, fields: {}, actions: { delete: canDelete } },
  } as unknown as WorkOrderDetailWithPermissions
}

function renderMenu(
  record: WorkOrderDetailWithPermissions,
  { onDeleted, onChanged }: { onDeleted?: () => void; onChanged?: () => void } = {},
) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <WorkOrderActionsMenu workOrder={record} onDeleted={onDeleted} onChanged={onChanged} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

async function confirmDelete() {
  selectWorkOrderAction('Delete')
  expect(await screen.findByText(/Work order COM-0007 will be permanently deleted/)).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(deleteWorkOrder).mockReset()
  vi.mocked(copyWorkOrderQuoteAttributes).mockReset()
  vi.mocked(toast.error).mockReset()
  vi.mocked(toast.success).mockReset()
})

describe('work order actions menu', () => {
  it('lists the work actions, then the closure ones below a separator', () => {
    const record = workOrder({ hasOffer: true })
    record.permissions.actions = { delete: true, force_close: true, request_update: true, request_proforma: true }
    renderMenu(record, { onDeleted: vi.fn() })

    openWorkOrderActionsMenu()

    expect(screen.getAllByRole('menuitem').map((item) => item.textContent)).toEqual([
      'Work order progress',
      'Copy data from the offer',
      'Request update',
      'Request proforma',
      'Force close',
      'Delete',
    ])
    expect(screen.getByRole('separator')).toBeInTheDocument()
  })
})

describe('work order actions menu: "Delete" (user request 2026-11-19)', () => {
  it('asks naming the commessa, deletes it and lets the host leave the record', async () => {
    vi.mocked(deleteWorkOrder).mockResolvedValue()
    const onDeleted = vi.fn()
    renderMenu(workOrder(), { onDeleted })

    await confirmDelete()

    await waitFor(() => expect(onDeleted).toHaveBeenCalledOnce())
    expect(deleteWorkOrder).toHaveBeenCalledWith(7)
  })

  it('deletes nothing when the confirmation is cancelled', async () => {
    renderMenu(workOrder(), { onDeleted: vi.fn() })

    selectWorkOrderAction('Delete')
    fireEvent.click(await screen.findByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByText(/will be permanently deleted/)).not.toBeInTheDocument())
    expect(deleteWorkOrder).not.toHaveBeenCalled()
  })

  it('shows the server reason of a 409 and stays on the record', async () => {
    vi.mocked(deleteWorkOrder).mockRejectedValue(conflict())
    const onDeleted = vi.fn()
    renderMenu(workOrder(), { onDeleted })

    await confirmDelete()

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(REFUSED))
    expect(onDeleted).not.toHaveBeenCalled()
  })

  it('is absent without the delete permission', () => {
    renderMenu(workOrder({ canDelete: false }), { onDeleted: vi.fn() })
    expect(hasWorkOrderAction('Delete')).toBe(false)
  })

  it('is absent when the host gives no way to leave the record', () => {
    renderMenu(workOrder())
    expect(hasWorkOrderAction('Delete')).toBe(false)
  })
})

describe('work order actions menu: "Copy data from the offer" (spec 0404 D-D2)', () => {
  it('calls the endpoint and shows the server message as a toast', async () => {
    vi.mocked(copyWorkOrderQuoteAttributes).mockResolvedValue({
      message: '2 fields copied from the offer',
      workOrder: workOrder({ hasOffer: true }),
    })
    const onChanged = vi.fn()
    renderMenu(workOrder({ hasOffer: true }), { onChanged })

    selectWorkOrderAction('Copy data from the offer')

    await waitFor(() => expect(toast.success).toHaveBeenCalledWith('2 fields copied from the offer'))
    expect(copyWorkOrderQuoteAttributes).toHaveBeenCalledWith(7)
    expect(onChanged).toHaveBeenCalled()
  })

  it('is absent without update permission or without an offer', () => {
    renderMenu(workOrder({ update: false, hasOffer: true }))
    expect(hasWorkOrderAction('Copy data from the offer')).toBe(false)
  })

  it('is absent on a work order without an offer', () => {
    renderMenu(workOrder({ update: true, hasOffer: false }))
    expect(hasWorkOrderAction('Copy data from the offer')).toBe(false)
  })
})
