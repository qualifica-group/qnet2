import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { TaskBulkDateDialog } from '@/features/tasks/task-bulk-date-dialog'
import { bulkTaskAction } from '@/features/tasks/api'

/**
 * Spec 0156 D-6/contract: bulk "Data inizio"/"Data fine" — a SINGLE `date`
 * field per action (unlike the task board's own two-field dialog), the
 * required-field guard and the 422 incompatible_tasks split.
 */

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, bulkTaskAction: vi.fn() }
})

function fieldValidationError(status: number, data: unknown): AxiosError {
  const error = new AxiosError('failed')
  error.response = { status, statusText: 'error', data, headers: {}, config: { headers: new AxiosHeaders() } }
  return error
}

function renderDialog(action: 'start_date' | 'end_date', taskIds = [4]) {
  const onClose = vi.fn()
  const onSuccess = vi.fn()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TaskBulkDateDialog action={action} taskIds={taskIds} onClose={onClose} onSuccess={onSuccess} />
    </QueryClientProvider>,
  )
  return { onClose, onSuccess }
}

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)

// The dialog title itself starts with the same field label ("Start date —
// 1 tasks"), so `getByLabelText` from the whole document also matches the
// dialog's own `aria-labelledby` container; scoping to the `<form>` (a
// descendants-only query) keeps the match to the actual input.
const dateField = (fieldLabel: string) =>
  within(document.getElementById('task-bulk-date-form') as HTMLElement).getByLabelText(
    new RegExp(`^${label(fieldLabel)}`),
  )

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(bulkTaskAction).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe.each([
  { action: 'start_date' as const, fieldLabel: 'tasks.form.startDate' },
  { action: 'end_date' as const, fieldLabel: 'tasks.form.endDate' },
])('TaskBulkDateDialog — $action', ({ action, fieldLabel }) => {
  it('shows the field-specific title and label', () => {
    renderDialog(action)

    expect(
      screen.getByRole('heading', { name: label('tasks.bulk.dateDialog.title', { field: label(fieldLabel), count: 1 }) }),
    ).toBeInTheDocument()
    expect(dateField(fieldLabel)).toBeInTheDocument()
  })

  it('requires a date before submitting', async () => {
    renderDialog(action)

    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.dateDialog.confirm') }))

    expect(await screen.findByText(label('tasks.bulk.dateDialog.dateRequired'))).toBeInTheDocument()
    expect(bulkTaskAction).not.toHaveBeenCalled()
  })

  it('submits the single date field, toasts success and closes (AC-007)', async () => {
    vi.mocked(bulkTaskAction).mockResolvedValueOnce({ affected: 1 })
    const { onClose, onSuccess } = renderDialog(action, [4])

    fireEvent.change(dateField(fieldLabel), { target: { value: '2026-10-01' } })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.dateDialog.confirm') }))

    await waitFor(() =>
      expect(bulkTaskAction).toHaveBeenCalledWith({ action, task_ids: [4], date: '2026-10-01' }),
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
        incompatible_tasks: [{ id: 4, reason: 'Fuori intervallo del padre.' }],
      }),
    )
    const { onClose, onSuccess } = renderDialog(action, [4])

    fireEvent.change(dateField(fieldLabel), { target: { value: '2026-10-01' } })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.dateDialog.confirm') }))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith(
        label('tasks.bulk.incompatibleError', { count: 1 }),
        expect.objectContaining({
          description: label('tasks.bulk.incompatibleReason', { id: 4, reason: 'Fuori intervallo del padre.' }),
        }),
      ),
    )
    expect(onClose).not.toHaveBeenCalled()
    expect(onSuccess).not.toHaveBeenCalled()
  })

  it('wires a field-scoped 422 (malformed request, no incompatible_tasks) inline instead', async () => {
    vi.mocked(bulkTaskAction).mockRejectedValueOnce(
      fieldValidationError(422, { success: false, message: 'x', errors: { date: ['La data non è valida.'] } }),
    )
    renderDialog(action, [4])

    fireEvent.change(dateField(fieldLabel), { target: { value: '2026-10-01' } })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.bulk.dateDialog.confirm') }))

    expect(await screen.findByText('La data non è valida.')).toBeInTheDocument()
    expect(toast.error).not.toHaveBeenCalled()
  })
})
