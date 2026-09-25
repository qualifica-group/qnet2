import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { TaskBulkCompleteDialog } from '@/features/tasks/task-bulk-complete-dialog'
import { bulkTaskAction } from '@/features/tasks/api'

/** Spec 0156 D-6: bulk "Completa" — closure_feedback + mandatory segnatempo, and the 422 incompatible_tasks split. */

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, bulkTaskAction: vi.fn() }
})

vi.mock('@/features/time-entries/form/time-entry-type-picker', () => ({
  useTimeEntryTypeOptions: () => ({
    options: [{ id: 2, label: 'Attività', meta: { color: 'blue', icon: 'clipboard-list' } }],
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
  TimeEntryTypePicker: ({ value }: { value: number | null }) => <div>{value ?? ''}</div>,
}))

function fieldValidationError(status: number, data: unknown): AxiosError {
  const error = new AxiosError('failed')
  error.response = { status, statusText: 'error', data, headers: {}, config: { headers: new AxiosHeaders() } }
  return error
}

function renderDialog(taskIds = [5]) {
  const onClose = vi.fn()
  const onSuccess = vi.fn()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TaskBulkCompleteDialog taskIds={taskIds} onClose={onClose} onSuccess={onSuccess} />
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

describe('TaskBulkCompleteDialog', () => {
  it('blocks the submit when the segnatempo minutes are empty', async () => {
    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.completeDialog.confirm') }))

    expect(await screen.findByText(label('timeEntries.form.minutesRequired'))).toBeInTheDocument()
    expect(bulkTaskAction).not.toHaveBeenCalled()
  })

  it('submits closure_feedback and time_entry together, toasts success and closes (AC-007)', async () => {
    vi.mocked(bulkTaskAction).mockResolvedValueOnce({ affected: 1 })
    const { onClose, onSuccess } = renderDialog([5])

    fireEvent.change(screen.getByLabelText(label('tasks.actions.completeDialog.feedback')), {
      target: { value: 'Consegnato' },
    })
    fireEvent.change(screen.getByLabelText(new RegExp(`^${label('timeEntries.form.minutes')}`)), {
      target: { value: '01:00' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.completeDialog.confirm') }))

    await waitFor(() =>
      expect(bulkTaskAction).toHaveBeenCalledWith({
        action: 'complete',
        task_ids: [5],
        closure_feedback: 'Consegnato',
        time_entry: expect.objectContaining({ minutes: 60 }),
      }),
    )
    await waitFor(() => expect(toast.success).toHaveBeenCalledWith(label('tasks.bulk.success', { count: 1 })))
    expect(onClose).toHaveBeenCalled()
    expect(onSuccess).toHaveBeenCalledWith(1)
  })

  it('omits closure_feedback when left blank', async () => {
    vi.mocked(bulkTaskAction).mockResolvedValueOnce({ affected: 1 })
    renderDialog([5])

    fireEvent.change(screen.getByLabelText(new RegExp(`^${label('timeEntries.form.minutes')}`)), {
      target: { value: '00:30' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.completeDialog.confirm') }))

    await waitFor(() => expect(bulkTaskAction).toHaveBeenCalled())
    const [payload] = vi.mocked(bulkTaskAction).mock.calls[0]
    // The key may be present with an `undefined` value (RHF's own object
    // literal) — JSON dropping it at the wire boundary is what actually
    // matters, so assert on the value, not `toHaveProperty`'s key presence.
    expect(payload.closure_feedback).toBeUndefined()
  })

  it('on 422 with incompatible_tasks: shows the reasons and applies NOTHING (e.g. a task requiring validation)', async () => {
    vi.mocked(bulkTaskAction).mockRejectedValueOnce(
      fieldValidationError(422, {
        success: false,
        message: 'x',
        incompatible_tasks: [{ id: 5, reason: 'Richiede validazione.' }],
      }),
    )
    const { onClose, onSuccess } = renderDialog([5])

    fireEvent.change(screen.getByLabelText(new RegExp(`^${label('timeEntries.form.minutes')}`)), {
      target: { value: '00:15' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.completeDialog.confirm') }))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith(
        label('tasks.bulk.incompatibleError', { count: 1 }),
        expect.objectContaining({
          description: label('tasks.bulk.incompatibleReason', { id: 5, reason: 'Richiede validazione.' }),
        }),
      ),
    )
    expect(onClose).not.toHaveBeenCalled()
    expect(onSuccess).not.toHaveBeenCalled()
  })
})
