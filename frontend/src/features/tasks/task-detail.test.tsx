import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import { TaskDetailView } from '@/features/tasks/task-detail'
import { blockTask, uncompleteTask } from '@/features/tasks/api'
import {
  FULL_ACCESS_PERMISSIONS,
  taskDetailWithPermissions,
  taskRecurrenceDetail,
  taskStatus,
} from '@/features/tasks/task-fixtures'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

let granted: string[] = []

// Related-record links open their target in a modal through `useModuleOpener`,
// whose mode resolver reads the authenticated user's preference. The preference
// is not what these tests are about, so the resolver is stubbed rather than
// dragging an AuthProvider into every render.
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

// The activity log section is gated off in these fixtures, but the module is
// still imported: stub it so the detail test never depends on its own fetch.
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => null,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

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

// The validation-status picker mounts only inside the complete dialog's
// "richiedi validazione" branch; stubbed to a plain trigger so these suites
// exercise the actions bar/dialogs, not the async for-select network path
// (already covered by `async-paginated-select.test.tsx`).
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

// The "Segnatempo" section's type field mounts a second async for-select
// network path (spec 0123 D-1); stubbed with a single, already-active type
// matching the fixture's own `task_type_id` (2) so the section's default
// resolves without any interaction (mirrors the async-paginated-select stub above).
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

/** `FULL_ACCESS_PERMISSIONS` with only the given action flags set — the rest default to false/absent. */
function actionPermissions(actions: ResourcePermissions['actions']): ResourcePermissions {
  return { ...FULL_ACCESS_PERMISSIONS, actions }
}

/** A 409/422 shaped so `axios.isAxiosError` recognizes it (mirrors `use-task-form-server-errors.test.tsx`). */
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

function renderDetail(task: TaskDetailWithPermissions, confirmImpl: ConfirmFn = () => Promise.resolve(true)) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <ConfirmContext.Provider value={confirmImpl}>
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

describe('TaskDetailView — derived percentage (AC-084/D-6)', () => {
  it('renders the percentage the backend derived from the status', () => {
    renderDetail(taskDetailWithPermissions())

    expect(
      screen.getByRole('progressbar', { name: label('tasks.detail.completionPercentage') }),
    ).toHaveAttribute('aria-valuenow', '25')
  })

  it('follows the status: a closed task reads 100 without any write to the task', () => {
    renderDetail(
      taskDetailWithPermissions({
        task_status: taskStatus({ id: 6, name: 'Completato', system_key: 'closed_positive', completion_percentage: 100 }),
        completion_percentage: 100,
      }),
    )

    expect(
      screen.getByRole('progressbar', { name: label('tasks.detail.completionPercentage') }),
    ).toHaveAttribute('aria-valuenow', '100')
  })
})

describe('TaskDetailView — blocked flag is distinct from the status (AC-086)', () => {
  it('shows a dedicated badge alongside, not instead of, the status badge', () => {
    renderDetail(taskDetailWithPermissions({ is_blocked: true }))

    expect(screen.getByText(label('tasks.detail.blocked'))).toBeInTheDocument()
    // The status keeps its own badge: the flag never replaces it.
    expect(screen.getAllByText('In lavorazione').length).toBeGreaterThan(0)
  })

  it('omits the badge when the task is not blocked', () => {
    renderDetail(taskDetailWithPermissions())

    expect(screen.queryByText(label('tasks.detail.blocked'))).not.toBeInTheDocument()
  })
})

/** Spec 0120 AC-035: the series' rule, formatted, is the badge's own content. */
describe('TaskDetailView — recurring series badge (AC-035)', () => {
  it('shows the formatted rule when the task belongs to a series', () => {
    renderDetail(
      taskDetailWithPermissions({
        recurrence: taskRecurrenceDetail({ frequency: 'weekly', interval: 2, weekdays: [1, 3] }),
      }),
    )

    expect(screen.getByText('Every 2 weeks on Monday and Wednesday, until 31/03/2027')).toBeInTheDocument()
  })

  it('omits the badge for a task with no recurrence', () => {
    renderDetail(taskDetailWithPermissions({ recurrence: null }))

    expect(screen.queryByText(/Every|Ogni/)).not.toBeInTheDocument()
  })
})

describe('TaskDetailView — people (D-10/AC-083)', () => {
  it('shows the server-side creator and the chosen requester as distinct fields', () => {
    renderDetail(taskDetailWithPermissions())

    expect(screen.getByText('Carla Conti')).toBeInTheDocument()
    expect(screen.getByText('Bruno Bianchi')).toBeInTheDocument()
  })

  it('lists assignees and watchers separately, so one user may be in both', () => {
    renderDetail(
      taskDetailWithPermissions({
        assignees: [{ id: 31, name: 'Dario Dini' }],
        watchers: [{ id: 31, name: 'Dario Dini' }],
      }),
    )

    expect(screen.getAllByText('Dario Dini')).toHaveLength(2)
  })
})

describe('TaskDetailView — related records and people', () => {
  it('links the anagrafica, the referent, the opportunity and the commessa to their pages', () => {
    granted = [...granted, 'registries.view', 'referents.view', 'opportunities.view', 'work-orders.view']
    renderDetail(
      taskDetailWithPermissions({
        opportunity: { id: 8, name: 'Rinnovo Acme' },
        work_order: { id: 9, code: 'C-009', title: 'Impianto' },
      }),
    )

    const hrefOf = (name: string) => screen.getByRole('link', { name }).getAttribute('href')
    expect(hrefOf('Acme')).toBe('/registries/7')
    expect(hrefOf('Ada Alberti')).toBe('/referents/11')
    expect(hrefOf('Rinnovo Acme')).toBe('/opportunities/8')
    expect(hrefOf('C-009 — Impianto')).toBe('/work-orders/9')
  })

  it('shows every person through the user profile hover card, not a page link', () => {
    renderDetail(taskDetailWithPermissions())

    for (const name of ['Carla Conti', 'Bruno Bianchi', 'Dario Dini', 'Fabio Fini']) {
      expect(screen.getByRole('button', { name: label('common.viewProfile', { name }) })).toBeInTheDocument()
      expect(screen.queryByRole('link', { name })).not.toBeInTheDocument()
    }
  })
})

describe('TaskDetailView — sub-tasks (AC-085)', () => {
  it('renders the children carried by the detail, with no extra request', () => {
    renderDetail(
      taskDetailWithPermissions({
        subtasks: [
          {
            id: 101,
            title: 'Preparare il preventivo',
            task_status: { id: 2, name: 'Aperto', color: 'amber', icon: null },
            completion_percentage: 0,
            assignees: [],
          },
        ],
      }),
    )

    expect(screen.getByRole('button', { name: 'Preparare il preventivo' })).toBeInTheDocument()
  })

  /** Spec 0123 D-9/AC-036: wired from `permissions.actions.create_subtask`, not a bare ability. */
  it('shows "Crea sotto-task" iff permissions.actions.create_subtask is true', () => {
    renderDetail(taskDetailWithPermissions({ permissions: actionPermissions({ create_subtask: true }) }))

    expect(screen.getByRole('button', { name: label('tasks.detail.createSubtask') })).toBeInTheDocument()
  })

  it('hides "Crea sotto-task" when create_subtask is false', () => {
    renderDetail(taskDetailWithPermissions({ permissions: actionPermissions({ create_subtask: false }) }))

    expect(screen.queryByRole('button', { name: label('tasks.detail.createSubtask') })).not.toBeInTheDocument()
  })
})

/** Spec 0121 D-7/AC-021: the section now gates on EITHER flag, not just the feedback one. */
describe('TaskDetailView — closure section (AC-021)', () => {
  it('omits the section when neither flag is active', () => {
    renderDetail(taskDetailWithPermissions())
    expect(screen.queryByText(label('tasks.detail.sections.closure'))).not.toBeInTheDocument()
  })

  it('shows the section and the recorded feedback when the feedback flag alone is active', () => {
    renderDetail(
      taskDetailWithPermissions({
        requires_closure_feedback: true,
        closure_feedback: 'Consegnato al cliente',
      }),
    )

    expect(screen.getByText(label('tasks.detail.sections.closure'))).toBeInTheDocument()
    expect(screen.getByText('Consegnato al cliente')).toBeInTheDocument()
  })

  it('shows the section when the validation flag alone is active, with both flag values readable', () => {
    renderDetail(taskDetailWithPermissions({ requires_validation: true }))

    expect(screen.getByText(label('tasks.detail.sections.closure'))).toBeInTheDocument()
    expect(screen.getByText(label('tasks.detail.requiresValidation'))).toBeInTheDocument()
    expect(screen.getByText(label('tasks.detail.requiresClosureFeedback'))).toBeInTheDocument()
  })
})

/**
 * Each button is the AND of the client-side availability rule (WHEN, given
 * the task's own phase) and the server flag (the actual authorization):
 * absent from the DOM whenever either is false, never merely disabled.
 */
describe('TaskDetailView — actions bar gating (AC-041)', () => {
  it('renders "Complete" when the server flag is true and the phase allows it', () => {
    renderDetail(taskDetailWithPermissions({ permissions: actionPermissions({ complete: true }) }))

    expect(screen.getByRole('button', { name: label('tasks.actions.complete.label') })).toBeInTheDocument()
  })

  it('omits "Complete" when the flag is false, even on an open phase', () => {
    renderDetail(taskDetailWithPermissions({ permissions: actionPermissions({ complete: false }) }))

    expect(screen.queryByRole('button', { name: label('tasks.actions.complete.label') })).not.toBeInTheDocument()
  })

  it('omits every action button for a pure observer (no flag granted)', () => {
    renderDetail(taskDetailWithPermissions())

    for (const key of ['complete', 'uncomplete', 'approve', 'reject', 'block', 'unblock', 'request_update']) {
      expect(
        screen.queryByRole('button', { name: label(`tasks.actions.${key === 'request_update' ? 'requestUpdate' : key}.label`) }),
      ).not.toBeInTheDocument()
    }
  })

  it('omits "Approve"/"Reject" outside the in_validation phase even when the flags are true', () => {
    renderDetail(
      taskDetailWithPermissions({
        task_status: taskStatus({ group: 'open' }),
        permissions: actionPermissions({ approve: true, reject: true }),
      }),
    )

    expect(screen.queryByRole('button', { name: label('tasks.actions.approve.label') })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('tasks.actions.reject.label') })).not.toBeInTheDocument()
  })
})

/** Spec 0123 D-6/AC-023: the reason `complete`/`approve` are missing when the task has open sub-tasks. */
describe('TaskDetailView — open sub-tasks reason (AC-023)', () => {
  it('shows the pluralized reason next to the actions when open_subtasks_count > 0', () => {
    renderDetail(taskDetailWithPermissions({ open_subtasks_count: 2, permissions: actionPermissions({}) }))

    expect(
      screen.getByText(label('tasks.actions.openSubtasksBlocking', { count: 2 })),
    ).toBeInTheDocument()
  })

  it('uses the singular form for exactly one open sub-task', () => {
    renderDetail(taskDetailWithPermissions({ open_subtasks_count: 1, permissions: actionPermissions({}) }))

    expect(
      screen.getByText(label('tasks.actions.openSubtasksBlocking', { count: 1 })),
    ).toBeInTheDocument()
  })

  it('shows no reason when there are no open sub-tasks', () => {
    renderDetail(taskDetailWithPermissions({ open_subtasks_count: 0, permissions: actionPermissions({}) }))

    expect(screen.queryByText(label('tasks.actions.openSubtasksBlocking', { count: 1 }))).not.toBeInTheDocument()
  })
})

/** Spec 0118 D-10: the seventh action, gated exactly like the other six. */
describe('TaskDetailView — "Richiedi aggiornamento" gating (AC-059/AC-061)', () => {
  it('renders the button when the server flag is true on an open phase', () => {
    renderDetail(taskDetailWithPermissions({ permissions: actionPermissions({ request_update: true }) }))

    expect(
      screen.getByRole('button', { name: label('tasks.actions.requestUpdate.label') }),
    ).toBeInTheDocument()
  })

  it('is absent from the DOM, not merely disabled, when the flag is false', () => {
    renderDetail(taskDetailWithPermissions({ permissions: actionPermissions({ request_update: false }) }))

    expect(
      screen.queryByRole('button', { name: label('tasks.actions.requestUpdate.label') }),
    ).not.toBeInTheDocument()
  })

  it('is absent outside the completable phase even when the flag is true (client availability veto)', () => {
    renderDetail(
      taskDetailWithPermissions({
        task_status: taskStatus({ group: 'in_validation' }),
        permissions: actionPermissions({ request_update: true }),
      }),
    )

    expect(
      screen.queryByRole('button', { name: label('tasks.actions.requestUpdate.label') }),
    ).not.toBeInTheDocument()
  })
})

// The "Completa" pop-up's own tests (AC-018/AC-019/AC-020, spec 0123
// AC-039/AC-040) moved to the dedicated `task-complete-dialog.test.tsx`
// (renders `TaskCompleteDialog` directly, engineering.md §6 file-size split):
// `TaskActionsBar` mounts it unconditionally regardless of which action is
// available, so a `TaskDetailView` render exercises it too, but the dialog's
// OWN behaviour is tested closer to its unit there.

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
