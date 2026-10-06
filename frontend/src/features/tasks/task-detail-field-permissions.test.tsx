import { beforeAll, describe, expect, it, vi } from 'vitest'
import { screen } from '@testing-library/react'
import i18n from '@/i18n'
import { queryInlineEditButton, renderTaskDetail } from '@/features/tasks/task-detail-test-helpers'
import { FULL_ACCESS_PERMISSIONS, taskDetailWithPermissions } from '@/features/tasks/task-fixtures'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0195: the detail honours the role's field matrix everywhere it shows a
 * field — the identity band and the KPI strip too, not only the section rows —
 * and never opens an editor whose cascade would write a locked field.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Utente Corrente' } }),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/rich-text/rich-text-content', () => ({
  RichTextContent: ({ html }: { html: string | null }) => (html ? <span>{html}</span> : null),
}))

const label = (key: string) => i18n.t(key)

const HIDDEN_FIELD: FieldPermission = {
  visible: false,
  hidden: true,
  editable: false,
  readonly: false,
  required: false,
  disabled: false,
}

const READ_ONLY_FIELD: FieldPermission = { ...HIDDEN_FIELD, visible: true, hidden: false, readonly: true }

function withFields(fields: Record<string, FieldPermission>): ResourcePermissions {
  return { ...FULL_ACCESS_PERMISSIONS, fields }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('TaskDetailView — field permissions (spec 0195)', () => {
  it('keeps a hidden lookup out of the identity band', () => {
    renderTaskDetail(
      taskDetailWithPermissions({
        task_type: { id: 2, name: 'Chiamata riservata', color: 'blue', icon: null },
        permissions: withFields({ task_type_id: HIDDEN_FIELD }),
      }),
    )

    expect(screen.queryByText('Chiamata riservata')).not.toBeInTheDocument()
  })

  it('keeps a hidden date and estimate out of the KPI strip as well as their rows', () => {
    renderTaskDetail(
      taskDetailWithPermissions({
        permissions: withFields({ start_date: HIDDEN_FIELD, estimated_minutes: HIDDEN_FIELD }),
      }),
    )

    expect(screen.queryByText(label('tasks.detail.startDate'))).not.toBeInTheDocument()
    expect(screen.queryByText(label('tasks.detail.estimatedMinutes'))).not.toBeInTheDocument()
    expect(screen.getAllByText(label('tasks.detail.endDate')).length).toBeGreaterThan(0)
  })

  it('hides the read-only completion date and closure feedback rows when the role hides them', () => {
    renderTaskDetail(
      taskDetailWithPermissions({
        closure_feedback: 'Esito riservato',
        permissions: withFields({ completion_date: HIDDEN_FIELD, closure_feedback: HIDDEN_FIELD }),
      }),
    )

    expect(screen.queryByText(label('tasks.detail.completionDate'))).not.toBeInTheDocument()
    expect(screen.queryByText('Esito riservato')).not.toBeInTheDocument()
  })

  it('offers no anagrafica editor when its cascade would clear a locked referent', () => {
    renderTaskDetail(taskDetailWithPermissions({ permissions: withFields({ referent_id: READ_ONLY_FIELD }) }))

    expect(queryInlineEditButton(label('tasks.detail.registry'))).not.toBeInTheDocument()
    expect(queryInlineEditButton(label('tasks.form.title'))).toBeInTheDocument()
  })

  it('offers no commessa editor when the anagrafica it sets is locked', () => {
    renderTaskDetail(taskDetailWithPermissions({ permissions: withFields({ registry_id: READ_ONLY_FIELD }) }))

    expect(queryInlineEditButton(label('tasks.detail.workOrder'))).not.toBeInTheDocument()
    expect(queryInlineEditButton(label('tasks.detail.opportunity'))).toBeInTheDocument()
  })
})
