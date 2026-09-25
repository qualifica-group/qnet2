import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { TaskBulkPriorityDialog } from '@/features/tasks/task-bulk-priority-dialog'
import { bulkTaskAction } from '@/features/tasks/api'

/** Spec 0156 D-6: bulk "Priorità" — payload shape, required field, and the 422 incompatible_tasks split. */

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, bulkTaskAction: vi.fn() }
})

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ labels, onChange }: { labels: { triggerLabel: string }; onChange: (value: number | null) => void }) => (
    <button type="button" onClick={() => onChange(7)}>
      {labels.triggerLabel}
    </button>
  ),
}))

function fieldValidationError(status: number, data: unknown): AxiosError {
  const error = new AxiosError('failed')
  error.response = { status, statusText: 'error', data, headers: {}, config: { headers: new AxiosHeaders() } }
  return error
}

function renderDialog(taskIds = [4]) {
  const onClose = vi.fn()
  const onSuccess = vi.fn()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TaskBulkPriorityDialog taskIds={taskIds} onClose={onClose} onSuccess={onSuccess} />
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

describe('TaskBulkPriorityDialog', () => {
  it('requires a priority before submitting', async () => {
    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.priorityDialog.confirm') }))

    expect(await screen.findByText(label('tasks.bulk.priorityDialog.priorityRequired'))).toBeInTheDocument()
    expect(bulkTaskAction).not.toHaveBeenCalled()
  })

  it('submits task_priority_id, toasts success and closes (AC-007)', async () => {
    vi.mocked(bulkTaskAction).mockResolvedValueOnce({ affected: 1 })
    const { onClose, onSuccess } = renderDialog([4])

    fireEvent.click(await screen.findByRole('button', { name: label('tasks.form.priority') }))
    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.priorityDialog.confirm') }))

    await waitFor(() =>
      expect(bulkTaskAction).toHaveBeenCalledWith({ action: 'priority', task_ids: [4], task_priority_id: 7 }),
    )
    await waitFor(() => expect(toast.success).toHaveBeenCalledWith(label('tasks.bulk.success', { count: 1 })))
    expect(onClose).toHaveBeenCalled()
    expect(onSuccess).toHaveBeenCalledWith(1)
  })

  it('on 422 with incompatible_tasks: shows the reasons and applies NOTHING', async () => {
    vi.mocked(bulkTaskAction).mockRejectedValueOnce(
      fieldValidationError(422, {
        success: false,
        message: 'x',
        incompatible_tasks: [{ id: 4, reason: 'Task eliminato.' }],
      }),
    )
    const { onClose, onSuccess } = renderDialog([4])

    fireEvent.click(await screen.findByRole('button', { name: label('tasks.form.priority') }))
    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.priorityDialog.confirm') }))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith(
        label('tasks.bulk.incompatibleError', { count: 1 }),
        expect.objectContaining({
          description: label('tasks.bulk.incompatibleReason', { id: 4, reason: 'Task eliminato.' }),
        }),
      ),
    )
    expect(onClose).not.toHaveBeenCalled()
    expect(onSuccess).not.toHaveBeenCalled()
  })

  it('wires a field-scoped 422 (malformed request, no incompatible_tasks) inline instead', async () => {
    vi.mocked(bulkTaskAction).mockRejectedValueOnce(
      fieldValidationError(422, {
        success: false,
        message: 'x',
        errors: { task_priority_id: ['La priorità selezionata non è valida.'] },
      }),
    )
    renderDialog([4])

    fireEvent.click(await screen.findByRole('button', { name: label('tasks.form.priority') }))
    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.priorityDialog.confirm') }))

    expect(await screen.findByText('La priorità selezionata non è valida.')).toBeInTheDocument()
    expect(toast.error).not.toHaveBeenCalled()
  })
})
