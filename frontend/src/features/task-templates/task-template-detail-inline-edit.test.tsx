import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import i18n from '@/i18n'
import { updateTaskTemplate } from '@/features/task-templates/api'
import { TaskTemplateDetailView } from '@/features/task-templates/task-template-detail'
import type { TaskTemplateDetailWithPermissions, TaskTemplateItem } from '@/features/task-templates/types'

/**
 * Spec 0227: the task template detail edits in place, one row at a time. A
 * header row PATCHes its own field alone; the "Fasi" board row PATCHes items
 * and stages alone (the full sync of spec 0124 D-1), nothing else.
 */

vi.mock('@/features/task-templates/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/task-templates/api')>()),
  updateTaskTemplate: vi.fn(),
}))
vi.mock('@/features/activity-log/activity-log-section', () => ({ ActivityLogSection: () => null }))
vi.mock('@/features/attachments/documents-section', () => ({ DocumentsSection: () => null }))
vi.mock('@/components/rich-text/rich-text-content', () => ({
  RichTextContent: ({ html }: { html: string | null }) => (html ? <span>{html}</span> : null),
}))
vi.mock('@/components/rich-text/rich-text-editor', () => ({
  RichTextEditor: (p: { id?: string; value: string | null; onChange: (html: string | null) => void }) => (
    <textarea id={p.id} value={p.value ?? ''} onChange={(event) => p.onChange(event.target.value || null)} />
  ),
}))
vi.mock('@/features/task-statuses/for-select-api', () => ({
  useTaskStatusesForSelect: () => ({
    data: { pages: [{ items: [] }] },
    isPending: false,
    isError: false,
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
    fetchNextPage: vi.fn(),
  }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function item(id: number, title: string, sortOrder: number): TaskTemplateItem {
  return {
    id,
    title,
    description: null,
    estimated_minutes: null,
    task_status_id: null,
    task_status: null,
    due_offset_days: sortOrder,
    sort_order: sortOrder,
    task_template_stage_id: null,
    parent_id: null,
    attachments: [],
  }
}

function taskTemplate(overrides: Partial<TaskTemplateDetailWithPermissions> = {}): TaskTemplateDetailWithPermissions {
  return {
    id: 1,
    name: 'Standard onboarding',
    description: null,
    is_active: true,
    items_count: 2,
    stages: [],
    items: [item(10, 'Kickoff', 0), item(11, 'Follow-up', 1)],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

function renderDetail(template: TaskTemplateDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<TaskTemplateDetailView taskTemplate={template} />, {
    wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  })
}

const label = (key: string) => i18n.t(key)
const pencilOf = (key: string) =>
  screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: label(key) }) })
const save = () => fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.save') }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateTaskTemplate).mockReset()
  vi.mocked(updateTaskTemplate).mockImplementation(async () => taskTemplate())
})

describe('TaskTemplateDetailView — header rows (spec 0227 D-2)', () => {
  it('has no Edit button: the rows are closed, each with its pencil', () => {
    renderDetail(taskTemplate())

    expect(screen.queryByRole('button', { name: /^Edit$/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(pencilOf('taskTemplates.form.name')).toBeInTheDocument()
    expect(pencilOf('taskTemplates.form.sections.stages.title')).toBeInTheDocument()
  })

  it('Save sends the edited field alone', async () => {
    renderDetail(taskTemplate())

    fireEvent.click(pencilOf('taskTemplates.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    save()

    await waitFor(() => expect(updateTaskTemplate).toHaveBeenCalledWith(1, { name: 'Renamed' }))
  })

  it('Cancel restores the row without calling the API', () => {
    renderDetail(taskTemplate())

    fireEvent.click(pencilOf('taskTemplates.form.name') as HTMLElement)
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Renamed' } })
    fireEvent.click(screen.getByRole('button', { name: label('common.inlineEdit.cancel') }))

    expect(screen.queryByText('Renamed')).not.toBeInTheDocument()
    expect(updateTaskTemplate).not.toHaveBeenCalled()
  })

  it('offers no pencil without the update permission', () => {
    renderDetail(
      taskTemplate({
        permissions: {
          resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
          fields: {},
          actions: {},
        },
      }),
    )

    expect(pencilOf('taskTemplates.form.name')).not.toBeInTheDocument()
    expect(pencilOf('taskTemplates.form.isActive')).not.toBeInTheDocument()
    expect(pencilOf('taskTemplates.form.sections.stages.title')).not.toBeInTheDocument()
  })
})

describe('TaskTemplateDetailView — the board row (spec 0227 D-3)', () => {
  it('Save sends items and stages only, existing rows keeping their id and order', async () => {
    renderDetail(taskTemplate())

    fireEvent.click(pencilOf('taskTemplates.form.sections.stages.title') as HTMLElement)
    fireEvent.change(screen.getAllByLabelText(label('taskTemplates.form.items.title'))[0], {
      target: { value: 'Kickoff call' },
    })
    save()

    await waitFor(() => expect(updateTaskTemplate).toHaveBeenCalledTimes(1))
    const [id, payload] = vi.mocked(updateTaskTemplate).mock.calls[0]
    expect(id).toBe(1)
    expect(Object.keys(payload).sort()).toEqual(['items', 'stages'])
    expect(payload.items).toEqual([
      expect.objectContaining({ id: 10, title: 'Kickoff call' }),
      expect.objectContaining({ id: 11, title: 'Follow-up' }),
    ])
  })

  it('a row left without title is refused client-side, with no API call', async () => {
    renderDetail(taskTemplate())

    fireEvent.click(pencilOf('taskTemplates.form.sections.stages.title') as HTMLElement)
    fireEvent.change(screen.getAllByLabelText(label('taskTemplates.form.items.title'))[0], { target: { value: '' } })
    save()

    expect(await screen.findByText(label('taskTemplates.form.items.hasErrors'))).toBeInTheDocument()
    expect(updateTaskTemplate).not.toHaveBeenCalled()
  })

  it('a 413 on the board shows on the open row, never as an item error (spec 0128)', async () => {
    const error = new AxiosError('too large')
    error.response = { status: 413, statusText: 'Payload Too Large', data: {}, headers: {}, config: { headers: new AxiosHeaders() } }
    vi.mocked(updateTaskTemplate).mockRejectedValueOnce(error)
    renderDetail(taskTemplate())

    fireEvent.click(pencilOf('taskTemplates.form.sections.stages.title') as HTMLElement)
    save()

    expect(await screen.findByText(label('richText.errors.payloadTooLarge'))).toBeInTheDocument()
    expect(screen.queryByText(label('taskTemplates.form.items.hasErrors'))).not.toBeInTheDocument()
  })
})
