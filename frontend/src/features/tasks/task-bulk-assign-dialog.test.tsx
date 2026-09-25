import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { TaskBulkAssignDialog } from '@/features/tasks/task-bulk-assign-dialog'
import { bulkTaskAction } from '@/features/tasks/api'

/** Spec 0156 D-6: bulk "Assegna" — payload shape, required field, and the 422 incompatible_tasks split. */

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, bulkTaskAction: vi.fn() }
})

// AC-017-style stub (mirrors `task-board-bulk-bar.test.tsx`): exercises the
// dialog's own submit wiring, not the async picker's network path.
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({ labels, onChange }: { labels: { triggerLabel: string }; onChange: (value: number[]) => void }) => (
    <button type="button" onClick={() => onChange([31])}>
      {labels.triggerLabel}
    </button>
  ),
}))

function fieldValidationError(status: number, data: unknown): AxiosError {
  const error = new AxiosError('failed')
  error.response = { status, statusText: 'error', data, headers: {}, config: { headers: new AxiosHeaders() } }
  return error
}

function renderDialog(taskIds = [1, 2]) {
  const onClose = vi.fn()
  const onSuccess = vi.fn()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TaskBulkAssignDialog taskIds={taskIds} onClose={onClose} onSuccess={onSuccess} />
    </QueryClientProvider>,
  )
  return { onClose, onSuccess }
}

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(bulkTaskAction).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('TaskBulkAssignDialog', () => {
  it('requires at least one assignee before submitting', async () => {
    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.assignDialog.confirm') }))

    expect(await screen.findByText(label('tasks.bulk.assignDialog.assigneesRequired'))).toBeInTheDocument()
    expect(bulkTaskAction).not.toHaveBeenCalled()
  })

  it('submits the correct payload, toasts success and closes (AC-007)', async () => {
    vi.mocked(bulkTaskAction).mockResolvedValueOnce({ affected: 2 })
    const { onClose, onSuccess } = renderDialog([1, 2])

    fireEvent.click(await screen.findByRole('button', { name: label('tasks.form.assignees') }))
    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.assignDialog.confirm') }))

    await waitFor(() =>
      expect(bulkTaskAction).toHaveBeenCalledWith({ action: 'assign', task_ids: [1, 2], assignee_ids: [31] }),
    )
    await waitFor(() => expect(toast.success).toHaveBeenCalledWith(label('tasks.bulk.success', { count: 2 })))
    expect(onClose).toHaveBeenCalled()
    expect(onSuccess).toHaveBeenCalledWith(2)
  })

  it('on 422 with incompatible_tasks: shows the reasons and applies NOTHING (all-or-nothing)', async () => {
    vi.mocked(bulkTaskAction).mockRejectedValueOnce(
      fieldValidationError(422, {
        success: false,
        message: 'x',
        incompatible_tasks: [{ id: 2, reason: 'Task bloccato.' }],
      }),
    )
    const { onClose, onSuccess } = renderDialog([1, 2])

    fireEvent.click(await screen.findByRole('button', { name: label('tasks.form.assignees') }))
    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.assignDialog.confirm') }))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith(
        label('tasks.bulk.incompatibleError', { count: 1 }),
        expect.objectContaining({
          description: label('tasks.bulk.incompatibleReason', { id: 2, reason: 'Task bloccato.' }),
        }),
      ),
    )
    expect(onClose).not.toHaveBeenCalled()
    expect(onSuccess).not.toHaveBeenCalled()
  })
})
