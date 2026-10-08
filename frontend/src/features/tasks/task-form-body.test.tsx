import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { TaskFormBody } from '@/features/tasks/task-form-body'
import { openInlineEditor, queryInlineEditButton } from '@/features/tasks/task-detail-test-helpers'
import { FULL_ACCESS_PERMISSIONS, taskDetailWithPermissions } from '@/features/tasks/task-fixtures'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { TaskCreateFormMode } from '@/features/tasks/types'

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

function renderForm(mode: TaskCreateFormMode, permissions: ResourcePermissions = FULL_ACCESS_PERMISSIONS) {
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

/*
 * Spec 0195 D-8: the create form replicates the detail — every row starts
 * CLOSED and opens on its pencil (`openInlineEditor`), so each case below
 * opens the row it is about before touching its control.
 */

describe('TaskFormBody — anagrafica scopes the referente (AC-080)', () => {
  it('offers no referent editor while no anagrafica is chosen, the anagrafica one stays', () => {
    renderForm({ type: 'create' })

    expect(queryInlineEditButton(label('tasks.detail.referent'))).not.toBeInTheDocument()
    openInlineEditor(label('tasks.detail.registry'))
    expect(picker('tasks.form.registry')).toBeEnabled()
  })
})

describe('TaskFormBody — the parent picker never offers the task itself (AC-082)', () => {
  it('sends no exclude_id on create: there is no self to exclude yet', async () => {
    renderForm({ type: 'create' })
    openInlineEditor(label('tasks.form.parentTask'))

    fireEvent.click(picker('tasks.form.parentTask'))

    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalledWith('tasks', expect.anything()))
    const [, params] = fetchForSelectMock.mock.calls.find(([resource]) => resource === 'tasks') ?? []
    expect(params).toMatchObject({ params: undefined })
  })
})

/**
 * Spec 0156 D-4: duplicate hydrates the relation pickers from the source
 * task (registry/commessa/etc.), like the detail, but NEVER shows a parent —
 * the link is explicitly dropped — and reads as a bare create everywhere else.
 */
describe('TaskFormBody — duplicate mode hydration (spec 0156 D-4)', () => {
  it('never offers the source task as a parent, even though the source had one', async () => {
    const source = taskDetailWithPermissions({
      parent_task_id: 55,
      parent_task: { id: 55, title: 'Task padre' },
    })
    renderForm({ type: 'duplicate', source })
    openInlineEditor(label('tasks.form.parentTask'))

    expect(picker('tasks.form.parentTask')).not.toHaveTextContent('Task padre')

    fireEvent.click(picker('tasks.form.parentTask'))
    await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalledWith('tasks', expect.anything()))
    const [, params] = fetchForSelectMock.mock.calls.find(([resource]) => resource === 'tasks') ?? []
    expect(params).toMatchObject({ params: undefined })
  })

  it('hydrates the commessa label from the source, closed and open, like the detail', () => {
    const source = taskDetailWithPermissions({
      work_order: { id: 9, code: 'COM-0001', title: 'Rifacimento impianto' },
    })
    renderForm({ type: 'duplicate', source })

    expect(screen.getByText('COM-0001 — Rifacimento impianto')).toBeInTheDocument()
    openInlineEditor(label('tasks.detail.workOrder'))
    expect(picker('tasks.form.workOrder')).toHaveTextContent('COM-0001 — Rifacimento impianto')
  })

  it('leaves the parent picker enabled, unlike "crea sotto-task"', () => {
    renderForm({ type: 'duplicate', source: taskDetailWithPermissions() })
    openInlineEditor(label('tasks.form.parentTask'))

    expect(picker('tasks.form.parentTask')).toBeEnabled()
  })
})

describe('TaskFormBody — sub-task prefill locks the parent (AC-085)', () => {
  it('offers no parent editor when opened as "crea sotto-task"', () => {
    renderForm({ type: 'create', parentTaskId: 90 })

    expect(queryInlineEditButton(label('tasks.form.parentTask'))).not.toBeInTheDocument()
  })

  it('leaves it editable on a plain create', () => {
    renderForm({ type: 'create' })
    openInlineEditor(label('tasks.form.parentTask'))

    expect(picker('tasks.form.parentTask')).toBeEnabled()
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
    openInlineEditor(label('tasks.detail.watchers'))

    fireEvent.click(screen.getByRole('button', { name: label('tasks.form.watchers') }))

    await waitFor(() => expect(screen.getByRole('option', { name: /Carol/ })).toBeInTheDocument())
    expect(screen.queryByRole('option', { name: /Utente Corrente/ })).not.toBeInTheDocument()
  })

  it('drops an assignee from the watchers list as soon as it is picked', async () => {
    renderForm({ type: 'create' })
    openInlineEditor(label('tasks.detail.assignees'))

    const assigneesTrigger = screen.getByRole('button', { name: label('tasks.form.assignees') })
    fireEvent.click(assigneesTrigger)
    fireEvent.click(await screen.findByRole('option', { name: /Carol/ }))
    // Close this popover, then move to the watchers row (the draft keeps Carol).
    fireEvent.click(assigneesTrigger)
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.apply') }))
    openInlineEditor(label('tasks.detail.watchers'))

    fireEvent.click(screen.getByRole('button', { name: label('tasks.form.watchers') }))

    await waitFor(() => expect(screen.getByRole('option', { name: /Dave/ })).toBeInTheDocument())
    expect(screen.queryByRole('option', { name: /Carol/ })).not.toBeInTheDocument()
  })
})

/**
 * Spec 0118 D-3 RECTIFIED by spec 0154 D-10: a manually picked initial status
 * is now allowed on create too (optional, the server still derives it when
 * left unpicked) — the Stato control renders on create too.
 */
describe('TaskFormBody — the status picker (spec 0118 D-3, spec 0154 D-10)', () => {
  it('renders the Stato control on create, optional', () => {
    renderForm({ type: 'create' })
    openInlineEditor(label('tasks.form.status'))

    expect(screen.getByRole('combobox', { name: label('tasks.form.status') })).toBeInTheDocument()
  })
})

/** Spec 0118 D-1: `requester_id` is required now, and the actor is the requester most of the time. */
describe('TaskFormBody — requester prefill on create (spec 0118 D-1)', () => {
  it('prefills the Richiedente with the connected actor, shown closed and still editable', () => {
    renderForm({ type: 'create' })
    openInlineEditor(label('tasks.detail.requester'))

    const requester = picker('tasks.form.requester')
    expect(requester).toHaveTextContent('Utente Corrente')
    expect(requester).toBeEnabled()
  })
})

/**
 * Spec 0118 D-7/AC-027: the task does not exist yet on create, so staging is
 * the only place to collect files before the first save; on a persisted task
 * the same job belongs to the Documenti tab of the detail (`DocumentsSection`).
 */
describe('TaskFormBody — attachment staging mounts on create only (spec 0118 D-7/AC-027)', () => {
  it('renders the staging section on create', () => {
    renderForm({ type: 'create' })

    expect(screen.getByText(label('tasks.form.attachments.title'))).toBeInTheDocument()
    expect(
      screen.getByLabelText(label('tasks.form.attachments.add'), { exact: false }),
    ).toBeInTheDocument()
  })
})

/**
 * Spec 0121 D-7/AC-017: the two closure flags live in the form, and
 * `closure_feedback` is no longer one of its fields — it moved to the
 * completion pop-up entirely (the detail's own editors: `task-detail-editors.test.tsx`).
 */
describe('TaskFormBody — closure section shows both flags, no feedback field (AC-017)', () => {
  it('renders both switches on create, each in its own row', () => {
    renderForm({ type: 'create' })

    openInlineEditor(label('tasks.detail.requiresClosureFeedback'))
    expect(screen.getByRole('switch', { name: label('tasks.form.requiresClosureFeedback') })).toBeInTheDocument()
    openInlineEditor(label('tasks.detail.requiresValidation'))
    expect(screen.getByRole('switch', { name: label('tasks.form.requiresValidation') })).toBeInTheDocument()
    expect(screen.queryByRole('textbox', { name: label('tasks.form.closureFeedback') })).not.toBeInTheDocument()
  })
})

// The recurrence section's own suite moved to `task-form-recurrence-section.test.tsx`
// (engineering.md §6, file-size split).
