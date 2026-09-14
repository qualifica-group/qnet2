import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import { TaskDetailView } from '@/features/tasks/task-detail'
import { blockTask, completeTask, uncompleteTask } from '@/features/tasks/api'
import {
  FULL_ACCESS_PERMISSIONS,
  taskDetailWithPermissions,
  taskRecurrenceDetail,
  taskStatus,
} from '@/features/tasks/task-fixtures'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'

let granted: string[] = []

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
    completeTask: vi.fn(),
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

const label = (key: string) => i18n.t(key)

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
    <QueryClientProvider client={client}>
      <ConfirmContext.Provider value={confirmImpl}>
        <TaskDetailView task={task} onOpenSubtask={vi.fn()} onCreateSubtask={vi.fn()} />
      </ConfirmContext.Provider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  granted = ['tasks.view', 'tasks.create']
  vi.mocked(completeTask).mockReset()
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

/**
 * Spec 0121 D-2/D-3/D-6 RECTIFIES spec 0116 D-8: the path is derived
 * server-side (`permissions.actions.complete_to_validation`), never picked by
 * a switch in this dialog.
 */
describe('TaskDetailView — complete dialog (AC-018/AC-019/AC-020)', () => {
  /** The dialog mounts through Radix's own portal effect, a tick after the opener click (mirrors `contract-actions-refresh.test.tsx`). */
  async function openCompleteDialog(overrides: Partial<TaskDetailWithPermissions> = {}) {
    renderDetail(
      taskDetailWithPermissions({
        permissions: actionPermissions({ complete: true, complete_to_validation: false }),
        ...overrides,
      }),
    )
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.complete.label') }))
    return screen.findByRole('button', { name: label('tasks.actions.completeDialog.confirm') })
  }

  it('keeps the submit disabled while a required feedback is empty (AC-020)', async () => {
    const submit = await openCompleteDialog({ requires_closure_feedback: true })

    expect(submit).toBeDisabled()

    // A required `FormLabel` appends an `aria-hidden` "*", part of the label's
    // plain text content: anchor the match instead of an exact string (mirrors
    // `contract-terminate-dialog.test.tsx`'s `/^Termination date/`).
    fireEvent.change(screen.getByLabelText(new RegExp(`^${label('tasks.actions.completeDialog.feedback')}`)), {
      target: { value: 'Consegnato al cliente' },
    })

    expect(submit).toBeEnabled()
  })

  it('leaves the submit enabled with no feedback when none is required', async () => {
    const submit = await openCompleteDialog({ requires_closure_feedback: false })

    expect(submit).toBeEnabled()
  })

  it('with complete_to_validation false: no switch, no status picker, and an empty payload (AC-018)', async () => {
    vi.mocked(completeTask).mockResolvedValueOnce(taskDetailWithPermissions())
    await openCompleteDialog({ requires_closure_feedback: false })

    expect(screen.queryByRole('switch')).not.toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: label('tasks.actions.completeDialog.validationStatus') }),
    ).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.confirm') }))

    await waitFor(() => expect(completeTask).toHaveBeenCalledWith(90, {}))
  })

  it('with complete_to_validation true: "Invia in validazione" title, mandatory status picker, no switch (AC-019)', async () => {
    vi.mocked(completeTask).mockResolvedValueOnce(
      taskDetailWithPermissions({ task_status: taskStatus({ id: 50, group: 'in_validation' }) }),
    )
    await openCompleteDialog({
      requires_closure_feedback: false,
      permissions: actionPermissions({ complete: true, complete_to_validation: true }),
    })

    expect(
      screen.getByRole('heading', { name: label('tasks.actions.completeDialog.validationTitle') }),
    ).toBeInTheDocument()
    expect(screen.queryByRole('switch')).not.toBeInTheDocument()

    // Submitting without a chosen status shows the field error and never calls the API.
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.confirm') }))
    expect(
      await screen.findByText(label('tasks.actions.completeDialog.validationStatusRequired')),
    ).toBeInTheDocument()
    expect(completeTask).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.validationStatus') }))
    fireEvent.click(screen.getByRole('button', { name: label('tasks.actions.completeDialog.confirm') }))

    await waitFor(() => expect(completeTask).toHaveBeenCalledWith(90, { validation_status_id: 50 }))
  })
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
