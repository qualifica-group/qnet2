import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { useTaskBulkActionsSlot } from '@/features/tasks/use-task-bulk-actions-slot'
import { bulkTaskAction } from '@/features/tasks/api'
import type { TableSelection } from '@/features/table/use-bulk-actions-slot'

/**
 * Spec 0156 D-6: the tasks list's bulk-actions dropdown, built by
 * `getBulkActions`. Covers the permission gate (each action key requires
 * ITS OWN base permission, mirroring the server's `TaskBulkController::BASE_PERMISSIONS`)
 * and the four body-less actions (uncomplete/block/unblock/delete): confirm
 * -> `POST /api/tasks/bulk` -> success refreshes+clears selection, cancel or
 * a rejected task changes NOTHING. The four dialog-opening actions
 * (assign/complete/priority/dates) have their own dedicated test files.
 */

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

const confirmMock = vi.fn()
vi.mock('@/components/confirm-dialog-context', () => ({ useConfirm: () => confirmMock }))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, bulkTaskAction: vi.fn() }
})

const SELECTION: TableSelection = { ids: [1, 2], rows: [] }

function Harness({ selection = SELECTION }: { selection?: TableSelection }) {
  const { getBulkActions, dialogSlot } = useTaskBulkActionsSlot({ refresh, clearSelection })
  const items = getBulkActions(selection)
  return (
    <>
      {items.map((item) => (
        <button key={item.key} type="button" onClick={item.onSelect}>
          {item.key}
        </button>
      ))}
      {dialogSlot}
    </>
  )
}

const refresh = vi.fn()
const clearSelection = vi.fn()

function renderHarness(selection?: TableSelection) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <Harness selection={selection} />
    </QueryClientProvider>,
  )
}

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)

beforeEach(() => {
  canMock.mockReset()
  confirmMock.mockReset()
  refresh.mockReset()
  clearSelection.mockReset()
  vi.mocked(bulkTaskAction).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('useTaskBulkActionsSlot — permission gating (mirrors TaskBulkController::BASE_PERMISSIONS)', () => {
  it('offers no action at all without any base permission', () => {
    canMock.mockReturnValue(false)
    renderHarness()

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('offers every action with full permissions', () => {
    canMock.mockReturnValue(true)
    renderHarness()

    for (const key of ['assign', 'complete', 'uncomplete', 'block', 'unblock', 'priority', 'start_date', 'end_date', 'delete']) {
      expect(screen.getByRole('button', { name: key })).toBeInTheDocument()
    }
  })

  it('tasks.update alone offers assign/priority/dates only', () => {
    canMock.mockImplementation((permission) => permission === 'tasks.update')
    renderHarness()

    expect(screen.getByRole('button', { name: 'assign' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'priority' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'start_date' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'end_date' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'complete' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'block' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'delete' })).not.toBeInTheDocument()
  })

  it('tasks.complete alone offers complete/uncomplete only', () => {
    canMock.mockImplementation((permission) => permission === 'tasks.complete')
    renderHarness()

    expect(screen.getByRole('button', { name: 'complete' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'uncomplete' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'assign' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'delete' })).not.toBeInTheDocument()
  })

  it('tasks.block alone offers block/unblock only', () => {
    canMock.mockImplementation((permission) => permission === 'tasks.block')
    renderHarness()

    expect(screen.getByRole('button', { name: 'block' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'unblock' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'complete' })).not.toBeInTheDocument()
  })

  it('tasks.delete alone offers delete only', () => {
    canMock.mockImplementation((permission) => permission === 'tasks.delete')
    renderHarness()

    expect(screen.getByRole('button', { name: 'delete' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'assign' })).not.toBeInTheDocument()
  })

  it('returns nothing at all for an empty selection, even with full permissions', () => {
    canMock.mockReturnValue(true)
    renderHarness({ ids: [], rows: [] })

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})

describe('useTaskBulkActionsSlot — body-less actions (uncomplete/block/unblock/delete)', () => {
  beforeEach(() => canMock.mockReturnValue(true))

  it('uncomplete: confirms, submits, refreshes and clears the selection on success', async () => {
    confirmMock.mockResolvedValue(true)
    vi.mocked(bulkTaskAction).mockResolvedValueOnce({ affected: 2 })
    renderHarness()

    screen.getByRole('button', { name: 'uncomplete' }).click()

    await waitFor(() => expect(bulkTaskAction).toHaveBeenCalledWith({ action: 'uncomplete', task_ids: [1, 2] }))
    await waitFor(() => expect(toast.success).toHaveBeenCalledWith(label('tasks.bulk.success', { count: 2 })))
    expect(refresh).toHaveBeenCalledTimes(1)
    expect(clearSelection).toHaveBeenCalledTimes(1)
  })

  it('does nothing when the user cancels the confirmation', async () => {
    confirmMock.mockResolvedValue(false)
    renderHarness()

    screen.getByRole('button', { name: 'block' }).click()

    await waitFor(() => expect(confirmMock).toHaveBeenCalled())
    expect(bulkTaskAction).not.toHaveBeenCalled()
    expect(refresh).not.toHaveBeenCalled()
  })

  it('delete: destructive confirm, submits action "delete"', async () => {
    confirmMock.mockResolvedValue(true)
    vi.mocked(bulkTaskAction).mockResolvedValueOnce({ affected: 2 })
    renderHarness()

    screen.getByRole('button', { name: 'delete' }).click()

    await waitFor(() => expect(bulkTaskAction).toHaveBeenCalledWith({ action: 'delete', task_ids: [1, 2] }))
    expect(confirmMock).toHaveBeenCalledWith(expect.objectContaining({ tone: 'destructive' }))
  })

  it('on 422 with incompatible_tasks: shows the reasons, changes nothing (AC-007)', async () => {
    confirmMock.mockResolvedValue(true)
    const error = Object.assign(new Error('failed'), {
      isAxiosError: true,
      response: {
        status: 422,
        data: { success: false, message: 'x', incompatible_tasks: [{ id: 2, reason: 'Task bloccato.' }] },
      },
    })
    vi.mocked(bulkTaskAction).mockRejectedValueOnce(error)
    renderHarness()

    screen.getByRole('button', { name: 'unblock' }).click()

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith(
        label('tasks.bulk.incompatibleError', { count: 1 }),
        expect.objectContaining({
          description: label('tasks.bulk.incompatibleReason', { id: 2, reason: 'Task bloccato.' }),
        }),
      ),
    )
    expect(refresh).not.toHaveBeenCalled()
    expect(clearSelection).not.toHaveBeenCalled()
  })
})

describe('useTaskBulkActionsSlot — dialog-opening actions', () => {
  beforeEach(() => canMock.mockReturnValue(true))

  it('assign: opens the TaskBulkAssignDialog for the current selection', async () => {
    renderHarness()

    screen.getByRole('button', { name: 'assign' }).click()

    expect(
      await screen.findByRole('heading', { name: label('tasks.bulk.assignDialog.title', { count: 2 }) }),
    ).toBeInTheDocument()
  })

  it('priority: opens the TaskBulkPriorityDialog for the current selection', async () => {
    renderHarness()

    screen.getByRole('button', { name: 'priority' }).click()

    expect(
      await screen.findByRole('heading', { name: label('tasks.bulk.priorityDialog.title', { count: 2 }) }),
    ).toBeInTheDocument()
  })
})
