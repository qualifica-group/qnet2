import { beforeAll, describe, expect, it, vi } from 'vitest'
import { screen } from '@testing-library/react'
import i18n from '@/i18n'
import { renderTaskDetail } from '@/features/tasks/task-detail-test-helpers'
import { taskDetailWithPermissions } from '@/features/tasks/task-fixtures'

/** Spec 0323 AC-021: the task detail shows the work order status the task applies when it closes, read-only. */

vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/auth/use-auth', () => ({ useAuth: () => ({ user: { id: 99, name: 'Utente Corrente' } }) }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/rich-text/rich-text-content', () => ({
  RichTextContent: ({ html }: { html: string | null }) => (html ? <span>{html}</span> : null),
}))

const LABEL = () => i18n.t('tasks.detail.workOrderWorkflowStatus')

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('TaskDetailView — linked work order status (spec 0323)', () => {
  it('shows the status and its advancement, with no edit affordance', () => {
    renderTaskDetail(
      taskDetailWithPermissions({
        work_order_workflow_status: { id: 7, name: 'In collaudo', color: null, progress_percentage: 60 },
      }),
    )

    expect(screen.getByText(LABEL())).toBeInTheDocument()
    expect(screen.getByText('In collaudo')).toBeInTheDocument()
    expect(screen.getByText('60%')).toBeInTheDocument()
    expect(
      screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: LABEL() }) }),
    ).not.toBeInTheDocument()
  })

  it('shows nothing when the task carries no status', () => {
    renderTaskDetail(taskDetailWithPermissions({ work_order_workflow_status: null }))

    expect(screen.queryByText(LABEL())).not.toBeInTheDocument()
  })
})
