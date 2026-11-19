import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { deleteWorkOrder } from '@/features/work-orders/api'
import { WorkOrderDetailHeader } from '@/features/work-orders/work-order-detail-header'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

vi.mock('@/features/work-orders/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-orders/api')>()),
  deleteWorkOrder: vi.fn(),
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

function renderHeader({ canDelete = true, onDeleted }: { canDelete?: boolean; onDeleted?: () => void } = {}) {
  const workOrder = {
    id: 7,
    code: 'COM-0007',
    title: 'Commessa',
    old_id: null,
    type: 'processing',
    is_multi_quote: false,
    is_force_closed: false,
    status: { value: 'in_progress', is_force_closed: false },
    permissions: { resource: { update: true }, fields: {}, actions: { delete: canDelete } },
  } as unknown as WorkOrderDetailWithPermissions
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <WorkOrderDetailHeader workOrder={workOrder} onDeleted={onDeleted} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

async function confirmDelete() {
  fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
  expect(await screen.findByText(/Work order COM-0007 will be permanently deleted/)).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(deleteWorkOrder).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('work order detail "Delete" action (user request 2026-11-19)', () => {
  it('asks naming the commessa, deletes it and lets the host leave the record', async () => {
    vi.mocked(deleteWorkOrder).mockResolvedValue()
    const onDeleted = vi.fn()
    renderHeader({ onDeleted })

    await confirmDelete()

    await waitFor(() => expect(onDeleted).toHaveBeenCalledOnce())
    expect(deleteWorkOrder).toHaveBeenCalledWith(7)
  })

  it('deletes nothing when the confirmation is cancelled', async () => {
    renderHeader({ onDeleted: vi.fn() })

    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByText(/will be permanently deleted/)).not.toBeInTheDocument())
    expect(deleteWorkOrder).not.toHaveBeenCalled()
  })

  it('shows the server reason of a 409 and stays on the record', async () => {
    vi.mocked(deleteWorkOrder).mockRejectedValue(conflict())
    const onDeleted = vi.fn()
    renderHeader({ onDeleted })

    await confirmDelete()

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(REFUSED))
    expect(onDeleted).not.toHaveBeenCalled()
  })

  it('is absent without the delete permission', () => {
    renderHeader({ canDelete: false, onDeleted: vi.fn() })

    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument()
  })
})
