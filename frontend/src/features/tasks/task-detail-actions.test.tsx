import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { TaskDetailView } from '@/features/tasks/task-detail'
import { blockTask, uncompleteTask } from '@/features/tasks/api'
import { FULL_ACCESS_PERMISSIONS, taskDetailWithPermissions, taskStatus } from '@/features/tasks/task-fixtures'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

/**
 * AC-044 (action error split) and the card's "Modifica" action, split out of
 * `task-detail.test.tsx` (engineering.md §6, file-size split — mirrors
 * `task-complete-dialog.test.tsx`'s own split from the same file).
 */

let granted: string[] = []

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => granted.includes(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => null,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// The real Tiptap read-only renderer is covered by rich-text-content.test.tsx (AC-020).
vi.mock('@/components/rich-text/rich-text-content', () => ({
  RichTextContent: ({ html }: { html: string | null }) => (html ? <span>{html}</span> : null),
}))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return {
    ...actual,
    uncompleteTask: vi.fn(),
    approveTask: vi.fn(),
    rejectTask: vi.fn(),
    blockTask: vi.fn(),
    unblockTask: vi.fn(),
  }
})

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    labels,
    onChange,
  }: {
    labels: { triggerLabel: string }
    onChange: (value: number | null) => void
  }) => (
    <button type="button" onClick={() => onChange(50)}>
      {labels.triggerLabel}
    </button>
  ),
}))

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

function actionPermissions(actions: ResourcePermissions['actions']): ResourcePermissions {
  return { ...FULL_ACCESS_PERMISSIONS, actions }
}

function actionError(status: 409 | 422): AxiosError {
  const error = new AxiosError('failed')
  error.response = {
    status,
    statusText: status === 409 ? 'Conflict' : 'Unprocessable Content',
    data: { success: false, message: 'failed' },
    headers: {},
    config: { headers: new AxiosHeaders() },
  }
  return error
}

function renderDetail(task: TaskDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <ConfirmContext.Provider value={() => Promise.resolve(true)}>
          <TaskDetailView task={task} onOpenSubtask={vi.fn()} onCreateSubtask={vi.fn()} />
        </ConfirmContext.Provider>
      </QueryClientProvider>
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  granted = ['tasks.view', 'tasks.create']
  vi.mocked(uncompleteTask).mockReset()
  vi.mocked(blockTask).mockReset()
  vi.mocked(toast.success).mockReset()
  vi.mocked(toast.error).mockReset()
})

describe('TaskDetailView — action errors (AC-044)', () => {
  it('shows the dedicated "task bloccato" message on a 409, not a generic error', async () => {
    vi.mocked(uncompleteTask).mockRejectedValueOnce(actionError(409))
    renderDetail(
      taskDetailWithPermissions({
        task_status: taskStatus({ group: 'closed_positive' }),
        permissions: actionPermissions({ uncomplete: true }),
      }),
    )

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.uncomplete.label') }))

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(label('tasks.actions.errors.blocked')))
  })

  it('shows the wrong-phase message on a 422, not the blocked one', async () => {
    vi.mocked(uncompleteTask).mockRejectedValueOnce(actionError(422))
    renderDetail(
      taskDetailWithPermissions({
        task_status: taskStatus({ group: 'closed_positive' }),
        permissions: actionPermissions({ uncomplete: true }),
      }),
    )

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.uncomplete.label') }))

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(label('tasks.actions.errors.wrongPhase')))
  })

  it('asks for confirmation before blocking and surfaces the same split on failure', async () => {
    vi.mocked(blockTask).mockRejectedValueOnce(actionError(409))
    renderDetail(taskDetailWithPermissions({ permissions: actionPermissions({ block: true }) }))

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.block.label') }))

    await waitFor(() => expect(blockTask).toHaveBeenCalledWith(90))
    await waitFor(() => expect(toast.error).toHaveBeenCalledWith(label('tasks.actions.errors.blocked')))
  })
})

/** The "Modifica" action lives on the task card, gated by `onEdit` AND `permissions.resource.update`. */
describe('TaskDetailView — edit action on the card', () => {
  function renderWithEdit(task: TaskDetailWithPermissions, onEdit?: () => void) {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <MemoryRouter>
        <QueryClientProvider client={client}>
          <ConfirmContext.Provider value={() => Promise.resolve(true)}>
            <TaskDetailView task={task} onEdit={onEdit} onOpenSubtask={vi.fn()} onCreateSubtask={vi.fn()} />
          </ConfirmContext.Provider>
        </QueryClientProvider>
      </MemoryRouter>,
    )
  }

  it('calls onEdit when the actor can update', () => {
    const onEdit = vi.fn()
    renderWithEdit(taskDetailWithPermissions(), onEdit)

    fireEvent.click(screen.getByRole('button', { name: label('common.edit') }))

    expect(onEdit).toHaveBeenCalledOnce()
  })

  it('hides the action without onEdit', () => {
    renderWithEdit(taskDetailWithPermissions())

    expect(screen.queryByRole('button', { name: label('common.edit') })).not.toBeInTheDocument()
  })

  it('hides the action when the actor cannot update', () => {
    renderWithEdit(
      taskDetailWithPermissions({
        permissions: { ...FULL_ACCESS_PERMISSIONS, resource: { ...FULL_ACCESS_PERMISSIONS.resource, update: false } },
      }),
      vi.fn(),
    )

    expect(screen.queryByRole('button', { name: label('common.edit') })).not.toBeInTheDocument()
  })
})
