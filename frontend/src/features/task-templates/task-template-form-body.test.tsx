import { beforeAll, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TaskTemplateFormBody } from '@/features/task-templates/task-template-form-body'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'

/**
 * Spec 0128 AC-024: the template header's description field is
 * `RichTextEditor`, opened from its closed row (spec 0227 D-4). Row-level coverage (item description, status filter,
 * error triad) lives in `task-template-items-editor.test.tsx`.
 */

vi.mock('@/features/task-templates/api', () => ({
  createTaskTemplate: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// Tiptap itself is covered end to end by rich-text-editor.test.tsx; only the
// wiring matters here (value/onChange/disabled), same stand-in as the tasks
// module's own `task-form-body.test.tsx`.
vi.mock('@/components/rich-text/rich-text-editor', () => ({
  RichTextEditor: (p: { id?: string; value: string | null; onChange: (html: string | null) => void; disabled?: boolean }) => (
    <textarea
      id={p.id}
      disabled={p.disabled}
      value={p.value ?? ''}
      onChange={(event) => p.onChange(event.target.value === '' ? null : event.target.value)}
    />
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderForm() {
  render(
    <ResourcePermissionsProvider permissions={null}>
      <TaskTemplateFormBody onSuccess={vi.fn()} onCancel={vi.fn()} />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
}

const label = (key: string) => i18n.t(key)

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('TaskTemplateFormBody — header description uses RichTextEditor (AC-024)', () => {
  it('renders the field in its row and emits null when cleared', () => {
    renderForm()
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label('taskTemplates.form.description') }) }))

    const description = screen.getByLabelText(label('taskTemplates.form.description'))
    fireEvent.change(description, { target: { value: '<p>Nota</p>' } })
    expect(description).toHaveValue('<p>Nota</p>')

    fireEvent.change(description, { target: { value: '' } })
    expect(description).toHaveValue('')
  })
})

describe('TaskTemplateFormBody — a replica of the detail (spec 0227 D-4)', () => {
  it('keeps the header rows closed and the board open', () => {
    renderForm()

    expect(screen.queryByLabelText(label('taskTemplates.form.name'))).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: label('taskTemplates.form.items.add') })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: label('taskTemplates.form.stages.add') })).toBeInTheDocument()
  })
})
