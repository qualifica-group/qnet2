import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { TaskFormBody } from '@/features/tasks/task-form-body'
import { EDITABLE_FIELD, FULL_ACCESS_PERMISSIONS, taskDetailWithPermissions } from '@/features/tasks/task-fixtures'
import type { ResourcePermissions } from '@/features/authorization/types'
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

// The connected actor `useTaskForm` reads to prefill the requester on create (spec 0118 D-1).
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Utente Corrente' } }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// Tiptap itself is covered end to end by rich-text-editor.test.tsx (AC-017/
// AC-018/AC-019); only the WIRING matters here, so a native control stands
// in — it also keeps `toBeDisabled()`/`toBeEnabled()` meaningful (jest-dom's
// matcher only recognizes native form elements, never Tiptap's own div).
vi.mock('@/components/rich-text/rich-text-editor', () => ({
  RichTextEditor: (p: {
    id?: string
    value: string | null
    onChange: (html: string | null) => void
    placeholder?: string
    disabled?: boolean
  }) => (
    <textarea
      id={p.id}
      placeholder={p.placeholder}
      disabled={p.disabled}
      value={p.value ?? ''}
      onChange={(event) => p.onChange(event.target.value === '' ? null : event.target.value)}
    />
  ),
}))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

/** A stable `QueryClient` per test, never per render. */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderForm(mode: TaskFormMode, permissions: ResourcePermissions = FULL_ACCESS_PERMISSIONS) {
  return render(
    <ResourcePermissionsProvider permissions={permissions}>
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

/**
 * D-5: the ceiling itself lives server-side (`TasksAuthorization::fieldPermissionCeiling()`)
 * and is applied by the generic, already-tested `MetaField` — this does not
 * re-derive the rule, it only proves the TASK form actually wires it up: an
 * assignee's `permissions.fields` (mirrored here exactly as the backend shapes
 * it) must lock a protected field while leaving a free one editable. A drift
 * in the ceiling would silently unlock a protected field with nothing here to
 * catch it otherwise (AC-046).
 */
describe('TaskFormBody — protected fields are locked for an assignee (AC-046)', () => {
  it('disables a protected field while a free one stays editable', () => {
    renderForm(
      { type: 'edit', task: taskDetailWithPermissions() },
      {
        resource: FULL_ACCESS_PERMISSIONS.resource,
        fields: {
          // PROTECTED (D-5): mandato fields, readable but not writable by an assignee.
          title: { ...EDITABLE_FIELD, editable: false, readonly: true },
          // FREE (D-5): execution fields, an assignee may still write these.
          description: { ...EDITABLE_FIELD },
        },
        actions: {},
      },
    )

    expect(screen.getByRole('textbox', { name: label('tasks.form.title') })).toBeDisabled()
    expect(screen.getByRole('textbox', { name: label('tasks.form.description') })).toBeEnabled()
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

describe('TaskFormBody — assignees and watchers', () => {
  it('renders both multi-select pickers, bound to the users resource', () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions() })

    expect(screen.getByRole('button', { name: label('tasks.form.assignees') })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: label('tasks.form.watchers') })).toBeInTheDocument()
  })
})

/** Spec 0118 D-9/AC-035: the watchers picker never OFFERS the creator/requester/assignees. */
describe('TaskFormBody — watchers picker excludes overlapping people (spec 0118 D-9/AC-035)', () => {
  const USERS_PAGE = {
    items: [
      { id: 99, label: 'Utente Corrente' },
      { id: 5, label: 'Carol' },
      { id: 6, label: 'Dave' },
    ],
    pagination: { offset: 0, limit: 25, total: 3 },
    export_link: null,
  }

  beforeEach(() => {
    fetchForSelectMock.mockImplementation(async (resource: string) =>
      resource === 'users' ? USERS_PAGE : EMPTY_PAGE,
    )
  })

  it('never offers the prefilled requester (the connected actor) as a watcher on create', async () => {
    renderForm({ type: 'create' })

    fireEvent.click(screen.getByRole('button', { name: label('tasks.form.watchers') }))

    await waitFor(() => expect(screen.getByRole('option', { name: /Carol/ })).toBeInTheDocument())
    expect(screen.queryByRole('option', { name: /Utente Corrente/ })).not.toBeInTheDocument()
  })

  it('drops an assignee from the watchers list as soon as it is picked', async () => {
    renderForm({ type: 'create' })

    const assigneesTrigger = screen.getByRole('button', { name: label('tasks.form.assignees') })
    fireEvent.click(assigneesTrigger)
    fireEvent.click(await screen.findByRole('option', { name: /Carol/ }))
    // Close this popover before opening the next one, or the still-mounted
    // "Carol" option here would make the watchers assertion ambiguous.
    fireEvent.click(assigneesTrigger)

    fireEvent.click(screen.getByRole('button', { name: label('tasks.form.watchers') }))

    await waitFor(() => expect(screen.getByRole('option', { name: /Dave/ })).toBeInTheDocument())
    expect(screen.queryByRole('option', { name: /Carol/ })).not.toBeInTheDocument()
  })

  it('excludes the persisted creator in edit mode', async () => {
    renderForm({
      type: 'edit',
      task: taskDetailWithPermissions({ creator: { id: 6, name: 'Dave' } }),
    })

    fireEvent.click(screen.getByRole('button', { name: label('tasks.form.watchers') }))

    await waitFor(() => expect(screen.getByRole('option', { name: /Carol/ })).toBeInTheDocument())
    expect(screen.queryByRole('option', { name: /Dave/ })).not.toBeInTheDocument()
  })
})

/** Spec 0118 D-3: the server derives the initial status, so create never offers the picker. */
describe('TaskFormBody — the status picker only exists in edit mode (spec 0118 D-3)', () => {
  it('has no Stato control on create', () => {
    renderForm({ type: 'create' })

    expect(screen.queryByRole('combobox', { name: label('tasks.form.status') })).not.toBeInTheDocument()
  })

  it('still renders the Stato control in edit mode', () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions() })

    expect(screen.getByRole('combobox', { name: label('tasks.form.status') })).toBeInTheDocument()
  })
})

/** Spec 0118 D-1: `requester_id` is required now, and the actor is the requester most of the time. */
describe('TaskFormBody — requester prefill on create (spec 0118 D-1)', () => {
  it('prefills the Richiedente picker with the connected actor, still enabled', () => {
    renderForm({ type: 'create' })

    const requester = picker('tasks.form.requester')
    expect(requester).toHaveTextContent('Utente Corrente')
    expect(requester).toBeEnabled()
  })

  it('does not override the persisted requester in edit mode', () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions() })

    expect(picker('tasks.form.requester')).toHaveTextContent('Bruno Bianchi')
  })
})

/**
 * Spec 0118 D-7/AC-027: the task does not exist yet on create, so staging is
 * the only place to collect files before the first save; in edit mode the
 * same job belongs to the Documenti tab of the detail (`DocumentsSection`),
 * and mounting both would duplicate it.
 */
describe('TaskFormBody — attachment staging mounts on create only (spec 0118 D-7/AC-027)', () => {
  it('renders the staging section on create', () => {
    renderForm({ type: 'create' })

    expect(screen.getByText(label('tasks.form.attachments.title'))).toBeInTheDocument()
    expect(
      screen.getByLabelText(label('tasks.form.attachments.add'), { exact: false }),
    ).toBeInTheDocument()
  })

  it('never mounts it in edit mode', () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions() })

    expect(screen.queryByText(label('tasks.form.attachments.title'))).not.toBeInTheDocument()
  })
})

/**
 * Spec 0121 D-7/AC-017: the two closure flags live in the form, in both
 * modes, and `closure_feedback` is no longer one of its fields — it moved to
 * the completion pop-up entirely.
 */
describe('TaskFormBody — closure section shows both flags, no feedback field (AC-017)', () => {
  it('renders both switches on create', () => {
    renderForm({ type: 'create' })

    expect(screen.getByRole('switch', { name: label('tasks.form.requiresClosureFeedback') })).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: label('tasks.form.requiresValidation') })).toBeInTheDocument()
    expect(screen.queryByRole('textbox', { name: label('tasks.form.closureFeedback') })).not.toBeInTheDocument()
  })

  it('renders both switches, seeded from the persisted task, in edit mode', () => {
    renderForm({ type: 'edit', task: taskDetailWithPermissions({ requires_validation: true }) })

    expect(screen.getByRole('switch', { name: label('tasks.form.requiresClosureFeedback') })).not.toBeChecked()
    expect(screen.getByRole('switch', { name: label('tasks.form.requiresValidation') })).toBeChecked()
    expect(screen.queryByRole('textbox', { name: label('tasks.form.closureFeedback') })).not.toBeInTheDocument()
  })
})

// The recurrence section's own suite moved to `task-form-recurrence-section.test.tsx`
// (engineering.md §6, file-size split).
