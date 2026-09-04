import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TaskDetailView } from '@/features/tasks/task-detail'
import { taskDetailWithPermissions, taskStatus } from '@/features/tasks/task-fixtures'
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

const label = (key: string) => i18n.t(key)

function renderDetail(task: TaskDetailWithPermissions) {
  render(<TaskDetailView task={task} onOpenSubtask={vi.fn()} onCreateSubtask={vi.fn()} />)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  granted = ['tasks.view', 'tasks.create']
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

describe('TaskDetailView — closure feedback (D-7)', () => {
  it('shows the closure section only when the task requires a feedback', () => {
    renderDetail(taskDetailWithPermissions())
    expect(screen.queryByText(label('tasks.detail.sections.closure'))).not.toBeInTheDocument()
  })

  it('shows the recorded feedback when required', () => {
    renderDetail(
      taskDetailWithPermissions({
        requires_closure_feedback: true,
        closure_feedback: 'Consegnato al cliente',
      }),
    )

    expect(screen.getByText('Consegnato al cliente')).toBeInTheDocument()
  })
})
