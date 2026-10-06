import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { updateTask } from '@/features/tasks/api'
import { queryInlineEditButton, renderTaskDetail } from '@/features/tasks/task-detail-test-helpers'
import { FULL_ACCESS_PERMISSIONS, taskDetailWithPermissions } from '@/features/tasks/task-fixtures'
import type { FieldPermission } from '@/features/authorization/types'

/**
 * Spec 0195: the task detail edits its fields in place — no "Modifica"
 * button, a pencil per editable row, Confirm PATCHes that field alone,
 * Cancel/Esc restores it without any request.
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

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/components/rich-text/rich-text-content', () => ({
  RichTextContent: ({ html }: { html: string | null }) => (html ? <span>{html}</span> : null),
}))

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, updateTask: vi.fn() }
})

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)

const READ_ONLY_FIELD: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateTask).mockReset()
})

describe('TaskDetailView — in-place editing (spec 0195)', () => {
  it('offers no separate "Edit" action any more (AC-001)', () => {
    renderTaskDetail(taskDetailWithPermissions())

    expect(screen.queryByRole('button', { name: label('common.edit') })).not.toBeInTheDocument()
  })

  it('saves the edited field alone and reports the change (AC-002)', async () => {
    const task = taskDetailWithPermissions()
    vi.mocked(updateTask).mockResolvedValueOnce({ ...task, title: 'Nuovo titolo' })
    const { onChanged } = renderTaskDetail(task)

    fireEvent.click(queryInlineEditButton(label('tasks.form.title'))!)
    fireEvent.change(screen.getByRole('textbox', { name: label('tasks.form.title') }), {
      target: { value: 'Nuovo titolo' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.detail.inlineEdit.save') }))

    await waitFor(() => expect(updateTask).toHaveBeenCalledWith(task.id, { title: 'Nuovo titolo' }))
    await waitFor(() => expect(onChanged).toHaveBeenCalledOnce())
    expect(screen.queryByRole('button', { name: label('tasks.detail.inlineEdit.save') })).not.toBeInTheDocument()
  })

  it('restores the persisted value on cancel, without any request (AC-003)', () => {
    const task = taskDetailWithPermissions()
    renderTaskDetail(task)

    fireEvent.click(queryInlineEditButton(label('tasks.form.title'))!)
    fireEvent.change(screen.getByRole('textbox', { name: label('tasks.form.title') }), {
      target: { value: 'Da scartare' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.detail.inlineEdit.cancel') }))

    expect(updateTask).not.toHaveBeenCalled()
    expect(screen.queryByRole('textbox', { name: label('tasks.form.title') })).not.toBeInTheDocument()
    expect(screen.getAllByText(task.title).length).toBeGreaterThan(0)
  })

  it('never carries a cancelled edit into the next field save (AC-003)', async () => {
    const task = taskDetailWithPermissions()
    vi.mocked(updateTask).mockResolvedValueOnce({ ...task, start_time: '09:30' })
    renderTaskDetail(task)

    fireEvent.click(queryInlineEditButton(label('tasks.form.title'))!)
    fireEvent.change(screen.getByRole('textbox', { name: label('tasks.form.title') }), {
      target: { value: 'Da scartare' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.detail.inlineEdit.cancel') }))

    fireEvent.click(queryInlineEditButton(label('tasks.detail.startTime'))!)
    fireEvent.change(screen.getByLabelText(label('tasks.form.startTime')), { target: { value: '09:30' } })
    fireEvent.click(screen.getByRole('button', { name: label('tasks.detail.inlineEdit.save') }))

    await waitFor(() => expect(updateTask).toHaveBeenCalledWith(task.id, { start_time: '09:30' }))
  })

  it('closes the editor on Escape (AC-003)', () => {
    renderTaskDetail(taskDetailWithPermissions())

    fireEvent.click(queryInlineEditButton(label('tasks.form.title'))!)
    fireEvent.keyDown(screen.getByRole('textbox', { name: label('tasks.form.title') }), { key: 'Escape' })

    expect(screen.queryByRole('textbox', { name: label('tasks.form.title') })).not.toBeInTheDocument()
    expect(updateTask).not.toHaveBeenCalled()
  })

  it('closes the editor on a press outside the row, discarding the edit without any request', () => {
    const task = taskDetailWithPermissions()
    renderTaskDetail(task)

    fireEvent.click(queryInlineEditButton(label('tasks.form.title'))!)
    const input = screen.getByRole('textbox', { name: label('tasks.form.title') })
    fireEvent.change(input, { target: { value: 'Da scartare' } })
    // A press inside the open row keeps it open.
    fireEvent.pointerDown(input)
    expect(screen.getByRole('textbox', { name: label('tasks.form.title') })).toBeInTheDocument()

    fireEvent.pointerDown(document.body)

    expect(screen.queryByRole('textbox', { name: label('tasks.form.title') })).not.toBeInTheDocument()
    expect(screen.queryByText('Da scartare')).not.toBeInTheDocument()
    expect(updateTask).not.toHaveBeenCalled()
  })

  it('shows no affordance on a field the actor may not edit (AC-004)', () => {
    renderTaskDetail(
      taskDetailWithPermissions({
        permissions: { ...FULL_ACCESS_PERMISSIONS, fields: { title: READ_ONLY_FIELD } },
      }),
    )

    expect(queryInlineEditButton(label('tasks.form.title'))).not.toBeInTheDocument()
    expect(queryInlineEditButton(label('tasks.detail.startTime'))).toBeInTheDocument()
  })

  it('keeps the status read-only when the task may not change status (spec 0126 D-4)', () => {
    renderTaskDetail(taskDetailWithPermissions({ permissions: { ...FULL_ACCESS_PERMISSIONS, actions: { change_status: false } } }))

    expect(queryInlineEditButton(label('tasks.form.status'))).not.toBeInTheDocument()
  })
})
