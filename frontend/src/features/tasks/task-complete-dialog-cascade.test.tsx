import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { TaskCompleteDialog } from '@/features/tasks/task-complete-dialog'
import { completeTask, fetchTaskOpenSubtasks } from '@/features/tasks/api'
import { FULL_ACCESS_PERMISSIONS, taskDetailWithPermissions } from '@/features/tasks/task-fixtures'
import type { TaskOpenSubtask } from '@/features/tasks/types'

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, completeTask: vi.fn(), fetchTaskOpenSubtasks: vi.fn() }
})

// Same stub as task-complete-dialog.test.tsx: one active type, the fixture's own (2).
vi.mock('@/features/time-entries/form/time-entry-type-picker', () => ({
  useTimeEntryTypeOptions: () => ({
    options: [{ id: 2, label: 'Attività', meta: { color: 'blue', icon: 'clipboard-list' } }],
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
  TimeEntryTypePicker: ({ value }: { value: number | null }) => <div>{value ?? ''}</div>,
}))

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)

const SUBTASKS: TaskOpenSubtask[] = [
  { id: 91, title: 'Child', depth: 1, requires_time_entry: true, requires_closure_feedback: false },
  { id: 92, title: 'Grandchild', depth: 2, requires_time_entry: true, requires_closure_feedback: false },
]

function openCascadeDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const task = taskDetailWithPermissions({
    open_subtasks_count: 1,
    requires_closure_feedback: false,
    permissions: { ...FULL_ACCESS_PERMISSIONS, actions: { complete: true, complete_to_validation: false } },
  })
  render(
    <QueryClientProvider client={client}>
      <TaskCompleteDialog open onOpenChange={vi.fn()} task={task} />
    </QueryClientProvider>,
  )
  return task
}

function fillMinutes(value: string) {
  fireEvent.change(screen.getByLabelText(new RegExp(`^${label('timeEntries.form.minutes')}`)), { target: { value } })
}

function incompatibleError(): AxiosError {
  const error = new AxiosError('failed')
  error.response = {
    status: 422,
    statusText: 'Unprocessable Content',
    data: {
      success: false,
      message: 'failed',
      errors: { task_ids: ['failed'] },
      incompatible_tasks: [{ id: 91, reason: 'Not allowed.' }],
    },
    headers: {},
    config: { headers: new AxiosHeaders() },
  }
  return error
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(completeTask).mockReset()
  vi.mocked(fetchTaskOpenSubtasks).mockReset()
  vi.mocked(toast.error).mockReset()
  vi.mocked(fetchTaskOpenSubtasks).mockResolvedValue(SUBTASKS)
})

/** Spec 0418: a parent's Completa closes its open sub-tasks, the segnatempo is a total split among them (AC-008). */
describe('TaskCompleteDialog — parent with open sub-tasks', () => {
  it('announces the sub-tasks and splits the total over the task and each open sub-task', async () => {
    vi.mocked(completeTask).mockResolvedValueOnce(taskDetailWithPermissions())
    const task = openCascadeDialog()

    expect(screen.getByText(label('tasks.actions.completeDialog.closesSubtasks', { count: 1 }))).toBeInTheDocument()
    expect(await screen.findByText('› Child')).toBeInTheDocument()
    expect(screen.getByText('› › Grandchild')).toBeInTheDocument()

    fillMinutes('01:30')
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.confirm') }))

    await waitFor(() => expect(completeTask).toHaveBeenCalled())
    const [id, payload] = vi.mocked(completeTask).mock.calls[0]
    expect(id).toBe(task.id)
    expect(payload.time_entry?.minutes).toBe(90)
    expect(payload.time_entry_shares).toEqual([
      { task_id: task.id, minutes: 30 },
      { task_id: 91, minutes: 30 },
      { task_id: 92, minutes: 30 },
    ])
  })

  it('lists the refused sub-tasks of an incompatible_tasks 422 in one toast', async () => {
    vi.mocked(completeTask).mockRejectedValueOnce(incompatibleError())
    openCascadeDialog()
    await screen.findByText('› Child')

    fillMinutes('01:30')
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.confirm') }))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith(label('tasks.bulk.incompatibleError', { count: 1 }), {
        description: label('tasks.bulk.incompatibleReason', { id: 91, reason: 'Not allowed.' }),
      }),
    )
  })
})
