import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { TaskFormBody } from '@/features/tasks/task-form-body'
import { FULL_ACCESS_PERMISSIONS, taskDetailWithPermissions } from '@/features/tasks/task-fixtures'
import type { TaskFormMode } from '@/features/tasks/types'

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
})

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

// The relation pickers read abilities to gate their quick-create slot.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

/** A stable `QueryClient` per test, never per render. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderForm(mode: TaskFormMode) {
  return render(
    <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
      <TaskFormBody mode={mode} onSuccess={vi.fn()} onCancel={vi.fn()} />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
}

/**
 * Labels are resolved through i18n rather than hard-coded, so these queries
 * keep working once the `tasks` bundle lands (microtask T-09) and the keys
 * start resolving to real Italian/English copy.
 */
const label = (key: string) => i18n.t(key)

/** `AsyncPaginatedSelect`'s trigger is `role="combobox"`, not "button". */
const picker = (key: string) => screen.getByRole('combobox', { name: label(key) })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

describe('TaskFormBody — anagrafica scopes the referente (AC-080)', () => {
  it('disables the referent picker while no anagrafica is chosen', () => {
    renderForm({ type: 'create' })

    expect(picker('tasks.form.referent')).toBeDisabled()
    expect(picker('tasks.form.registry')).toBeEnabled()
  })

  it('enables it and scopes the request to that anagrafica once one is set', async () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions() })

    const referent = picker('tasks.form.referent')
    expect(referent).toBeEnabled()

    fireEvent.click(referent)

    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledWith(
        'referents',
        expect.objectContaining({ params: { registry_id: 7 } }),
      ),
    )
  })

  it('never asks the server for an unscoped referent list', async () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions() })

    fireEvent.click(picker('tasks.form.referent'))

    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalled())
    const referentCalls = fetchForSelectMock.mock.calls.filter(([resource]) => resource === 'referents')
    for (const [, params] of referentCalls) {
      expect(params).toMatchObject({ params: { registry_id: 7 } })
    }
  })
})

describe('TaskFormBody — the parent picker never offers the task itself (AC-082)', () => {
  it('pushes exclude_id when editing an existing task', async () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions() })

    fireEvent.click(picker('tasks.form.parentTask'))

    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledWith(
        'tasks',
        expect.objectContaining({ params: { exclude_id: 90 } }),
      ),
    )
  })

  it('sends no exclude_id on create: there is no self to exclude yet', async () => {
    renderForm({ type: 'create' })

    fireEvent.click(picker('tasks.form.parentTask'))

    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalledWith('tasks', expect.anything()))
    const [, params] = fetchForSelectMock.mock.calls.find(([resource]) => resource === 'tasks') ?? []
    expect(params).toMatchObject({ params: undefined })
  })
})

describe('TaskFormBody — sub-task prefill locks the parent (AC-085)', () => {
  it('renders the parent picker disabled when opened as "crea sotto-task"', () => {
    renderForm({ type: 'create', parentTaskId: 90 })

    expect(picker('tasks.form.parentTask')).toBeDisabled()
  })

  it('leaves it editable on a plain create', () => {
    renderForm({ type: 'create' })

    expect(picker('tasks.form.parentTask')).toBeEnabled()
  })
})

describe('TaskFormBody — completion percentage is derived and read-only (AC-084)', () => {
  it('shows the persisted status percentage without any editable control', () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions() })

    const readout = screen.getByRole('progressbar', { name: label('tasks.form.completionPercentage') })
    expect(readout).toHaveAttribute('aria-valuenow', '25')
    // No input/spinbutton bound to it: the value is a projection of the
    // status (D-6), so there is nothing for the user to type.
    expect(
      screen.queryByRole('spinbutton', { name: label('tasks.form.completionPercentage') }),
    ).not.toBeInTheDocument()
  })
})

describe('TaskFormBody — the blocked flag is distinct from the status (AC-086)', () => {
  it('renders its own switch, next to but separate from the status picker', () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions({ is_blocked: true }) })

    const blocked = screen.getByRole('switch', { name: label('tasks.form.isBlocked') })
    expect(blocked).toBeChecked()
    expect(picker('tasks.form.status')).toBeInTheDocument()
  })

  it('toggles independently of the status', () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions() })

    const blocked = screen.getByRole('switch', { name: label('tasks.form.isBlocked') })
    expect(blocked).not.toBeChecked()

    fireEvent.click(blocked)

    expect(blocked).toBeChecked()
  })
})

/**
 * `GET /api/work-orders/for-select` is narrowed by `WorkOrderVisibilityScope`
 * and `ids[]` does not bypass it, so the picker's OPTION LIST may omit the
 * linked commessa. The trigger label must still be right: it is hydrated from
 * the Task's own detail, which D-9 deliberately does not obscure.
 */
describe('TaskFormBody — commessa label survives the visibility scope', () => {
  it('labels the trigger "{code} — {title}" from the task detail, not from the for-select', () => {
    renderForm({
      type: 'edit',
      task: taskDetailWithPermissions({
        work_order: { id: 9, code: 'COM-0001', title: 'Rifacimento impianto' },
      }),
    })

    expect(picker('tasks.form.workOrder')).toHaveTextContent('COM-0001 — Rifacimento impianto')
  })

  it('falls back to the bare code on an empty title, exactly as the backend resource does', () => {
    renderForm({
      type: 'edit',
      task: taskDetailWithPermissions({ work_order: { id: 9, code: 'COM-0001', title: '   ' } }),
    })

    const trigger = picker('tasks.form.workOrder')
    expect(trigger).toHaveTextContent('COM-0001')
    expect(trigger).not.toHaveTextContent('—')
  })
})

describe('TaskFormBody — assignees and watchers (AC-083)', () => {
  it('renders both multi-select pickers, bound to the users resource', () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions() })

    expect(screen.getByRole('button', { name: label('tasks.form.assignees') })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: label('tasks.form.watchers') })).toBeInTheDocument()
  })
})
