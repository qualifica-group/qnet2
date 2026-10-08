import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import {
  openInlineEditor,
  queryInlineEditButton,
  renderTaskDetail,
} from '@/features/tasks/task-detail-test-helpers'
import {
  EDITABLE_FIELD,
  FULL_ACCESS_PERMISSIONS,
  taskDetailWithPermissions,
  taskRecurrenceDetail,
} from '@/features/tasks/task-fixtures'

/**
 * The field rules of a PERSISTED task, as the detail's in-place editors apply
 * them (spec 0195 D-3) — moved here from `task-form-body.test.tsx` when the
 * edit form was retired: the editors are the same field components, hydrated
 * from the task the detail shows.
 */

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, updateTask: vi.fn() }
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

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Utente Corrente' } }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/components/rich-text/rich-text-content', () => ({
  RichTextContent: ({ html }: { html: string | null }) => (html ? <span>{html}</span> : null),
}))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

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

describe('Task detail editors — anagrafica scopes the referente (AC-080)', () => {
  it('scopes the referent request to the persisted anagrafica, never unscoped', async () => {
    renderTaskDetail(taskDetailWithPermissions())
    openInlineEditor(label('tasks.detail.referent'))

    fireEvent.click(picker('tasks.form.referent'))

    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledWith('referents', expect.objectContaining({ params: { registry_id: 7 } })),
    )
    const referentCalls = fetchForSelectMock.mock.calls.filter(([resource]) => resource === 'referents')
    for (const [, params] of referentCalls) {
      expect(params).toMatchObject({ params: { registry_id: 7 } })
    }
  })

  it('offers no referent editor while the task has no anagrafica', () => {
    renderTaskDetail(taskDetailWithPermissions({ registry_id: null, registry: null, referent_id: null, referent: null }))

    expect(queryInlineEditButton(label('tasks.detail.referent'))).not.toBeInTheDocument()
  })
})

describe('Task detail editors — the parent picker never offers the task itself (AC-082)', () => {
  it('pushes exclude_id with the task own id', async () => {
    renderTaskDetail(taskDetailWithPermissions())
    openInlineEditor(label('tasks.form.parentTask'))

    fireEvent.click(picker('tasks.form.parentTask'))

    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledWith('tasks', expect.objectContaining({ params: { exclude_id: 90 } })),
    )
  })
})

/**
 * D-5: the ceiling lives server-side (`TasksAuthorization::fieldPermissionCeiling()`);
 * this proves the detail wires it: an assignee's `permissions.fields` must
 * lock a protected field while leaving a free one editable (AC-046).
 */
describe('Task detail editors — protected fields are locked for an assignee (AC-046)', () => {
  it('offers no editor on a protected field while a free one stays editable', () => {
    renderTaskDetail(
      taskDetailWithPermissions({
        permissions: {
          resource: FULL_ACCESS_PERMISSIONS.resource,
          fields: {
            title: { ...EDITABLE_FIELD, editable: false, readonly: true },
            description: { ...EDITABLE_FIELD },
          },
          actions: {},
        },
      }),
    )

    expect(queryInlineEditButton(label('tasks.form.title'))).not.toBeInTheDocument()
    expect(queryInlineEditButton(label('tasks.detail.description'))).toBeInTheDocument()
  })
})

/**
 * `GET /api/work-orders/for-select` is narrowed by `WorkOrderVisibilityScope`,
 * so the option list may omit the linked commessa: the trigger label comes
 * from the task's own detail, which D-9 does not obscure.
 */
describe('Task detail editors — commessa label survives the visibility scope', () => {
  it('labels the trigger "{code} — {title}" from the task detail', () => {
    renderTaskDetail(
      taskDetailWithPermissions({ work_order: { id: 9, code: 'COM-0001', title: 'Rifacimento impianto' } }),
    )
    openInlineEditor(label('tasks.detail.workOrder'))

    expect(picker('tasks.form.workOrder')).toHaveTextContent('COM-0001 — Rifacimento impianto')
  })

  it('falls back to the bare code on an empty title, exactly as the backend resource does', () => {
    renderTaskDetail(taskDetailWithPermissions({ work_order: { id: 9, code: 'COM-0001', title: '   ' } }))
    openInlineEditor(label('tasks.detail.workOrder'))

    const trigger = picker('tasks.form.workOrder')
    expect(trigger).toHaveTextContent('COM-0001')
    expect(trigger).not.toHaveTextContent('—')
  })
})

describe('Task detail editors — people', () => {
  const USERS_PAGE = {
    items: [
      { id: 5, label: 'Carol' },
      { id: 6, label: 'Dave' },
    ],
    pagination: { offset: 0, limit: 25, total: 2 },
    export_link: null,
  }

  it('keeps the persisted requester, never the connected actor', () => {
    renderTaskDetail(taskDetailWithPermissions())
    openInlineEditor(label('tasks.detail.requester'))

    expect(picker('tasks.form.requester')).toHaveTextContent('Bruno Bianchi')
  })

  it('offers "do not notify the newly assigned" with the assignees (D-5)', () => {
    renderTaskDetail(taskDetailWithPermissions())
    openInlineEditor(label('tasks.detail.assignees'))

    expect(screen.getByRole('button', { name: label('tasks.form.assignees') })).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: label('tasks.form.suppressNotificationsEdit') })).not.toBeChecked()
  })

  it('never offers the persisted creator as a watcher (spec 0118 D-9/AC-035)', async () => {
    fetchForSelectMock.mockImplementation(async (resource: string) => (resource === 'users' ? USERS_PAGE : EMPTY_PAGE))
    renderTaskDetail(taskDetailWithPermissions({ creator: { id: 6, name: 'Dave' } }))
    openInlineEditor(label('tasks.detail.watchers'))

    fireEvent.click(screen.getByRole('button', { name: label('tasks.form.watchers') }))

    await waitFor(() => expect(screen.getByRole('option', { name: /Carol/ })).toBeInTheDocument())
    expect(screen.queryByRole('option', { name: /Dave/ })).not.toBeInTheDocument()
  })
})

describe('Task detail editors — closure flags (spec 0121 D-7/AC-017)', () => {
  it('seeds the switch from the persisted task', () => {
    renderTaskDetail(taskDetailWithPermissions({ requires_validation: true }))
    openInlineEditor(label('tasks.detail.requiresValidation'))

    expect(screen.getByRole('switch', { name: label('tasks.form.requiresValidation') })).toBeChecked()
    expect(screen.queryByRole('textbox', { name: label('tasks.form.closureFeedback') })).not.toBeInTheDocument()
  })
})

describe('Task detail editors — recurrence (AC-032/AC-034)', () => {
  it('hydrates the editor from the persisted series', () => {
    renderTaskDetail(
      taskDetailWithPermissions({
        recurrence: taskRecurrenceDetail({ frequency: 'weekly', weekdays: [1, 3], ends: 'never' }),
      }),
    )
    openInlineEditor(label('tasks.detail.recurrenceRule'))

    expect(screen.getByRole('switch', { name: label('tasks.form.recurrence.enable') })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: label('tasks.form.recurrence.weekday.mon') })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: label('tasks.form.recurrence.weekday.wed') })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: label('tasks.form.recurrence.weekday.tue') })).not.toBeChecked()
  })

  /** Spec 0120 D-12: the whole rule is ONE protected field. */
  it('offers no editor to an actor without the mandate (D-12)', () => {
    renderTaskDetail(
      taskDetailWithPermissions({
        recurrence: taskRecurrenceDetail(),
        permissions: {
          resource: FULL_ACCESS_PERMISSIONS.resource,
          fields: { recurrence: { ...EDITABLE_FIELD, editable: false, readonly: true } },
          actions: {},
        },
      }),
    )

    expect(queryInlineEditButton(label('tasks.detail.recurrenceRule'))).not.toBeInTheDocument()
  })
})
