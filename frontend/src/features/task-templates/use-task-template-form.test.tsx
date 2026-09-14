import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { createTaskTemplate, updateTaskTemplate } from '@/features/task-templates/api'
import { uploadAttachment } from '@/features/attachments/api'
import { DOCUMENTS_COLLECTION } from '@/features/attachments/types'
import { TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS } from '@/features/task-templates/types'
import { useTaskTemplateForm } from '@/features/task-templates/use-task-template-form'
import type { TaskTemplateDetail } from '@/features/task-templates/types'

/**
 * Orchestration-level suite for `useTaskTemplateForm` (spec 0124): row
 * add/edit/remove/reorder + the payload the submit actually sends (AC-024),
 * and the positional staged-attachment upload after create (AC-026). Mirrors
 * `features/tasks/use-task-form-attachments.test.tsx`'s structure.
 */

vi.mock('@/features/task-templates/api', () => ({
  createTaskTemplate: vi.fn(),
  updateTaskTemplate: vi.fn(),
}))

vi.mock('@/features/attachments/api', () => ({ uploadAttachment: vi.fn() }))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function taskTemplate(overrides: Partial<TaskTemplateDetail> = {}): TaskTemplateDetail {
  return {
    id: 1,
    name: 'Standard onboarding',
    description: null,
    is_active: true,
    items_count: 0,
    items: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(createTaskTemplate).mockReset()
  vi.mocked(updateTaskTemplate).mockReset()
  vi.mocked(uploadAttachment).mockReset()
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('useTaskTemplateForm — row add/edit/remove/reorder (spec 0124 AC-024)', () => {
  it('adds a new empty row, patches it and removes it', () => {
    const { result } = renderHook(
      () => useTaskTemplateForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => result.current.addItemRow())
    expect(result.current.itemRows).toHaveLength(1)
    const rowId = result.current.itemRows[0].id

    act(() => result.current.updateItemRow(rowId, { title: 'Kickoff call' }))
    expect(result.current.itemRows[0].title).toBe('Kickoff call')

    act(() => result.current.removeItemRow(rowId))
    expect(result.current.itemRows).toHaveLength(0)
  })

  it('reorders rows by the ordered id list SortableList reports', () => {
    const { result } = renderHook(
      () => useTaskTemplateForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.addItemRow()
      result.current.addItemRow()
    })
    const [firstId, secondId] = result.current.itemRows.map((row) => row.id)
    act(() => {
      result.current.updateItemRow(firstId, { title: 'First' })
      result.current.updateItemRow(secondId, { title: 'Second' })
    })

    act(() => result.current.reorderItemRows([secondId, firstId]))

    expect(result.current.itemRows.map((row) => row.title)).toEqual(['Second', 'First'])
  })

  it('sends the payload in the DISPLAYED order, existing rows keeping their id', async () => {
    vi.mocked(updateTaskTemplate).mockResolvedValueOnce(taskTemplate())

    const existing = taskTemplate({
      items_count: 2,
      items: [
        {
          id: 10,
          title: 'A',
          description: null,
          estimated_minutes: null,
          task_status_id: null,
          task_status: null,
          due_offset_days: 0,
          sort_order: 0,
          attachments: [],
        },
        {
          id: 11,
          title: 'B',
          description: null,
          estimated_minutes: null,
          task_status_id: null,
          task_status: null,
          due_offset_days: 1,
          sort_order: 1,
          attachments: [],
        },
      ],
    })

    const { result } = renderHook(
      () =>
        useTaskTemplateForm({
          mode: {
            type: 'edit',
            taskTemplate: { ...existing, permissions: { resource: {} as never, fields: {}, actions: {} } },
          },
          onSuccess: () => undefined,
        }),
      { wrapper: wrapper() },
    )

    const [rowA, rowB] = result.current.itemRows
    act(() => result.current.reorderItemRows([rowB.id, rowA.id]))

    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(updateTaskTemplate).toHaveBeenCalledWith(
      existing.id,
      expect.objectContaining({
        items: [
          expect.objectContaining({ id: 11, title: 'B' }),
          expect.objectContaining({ id: 10, title: 'A' }),
        ],
      }),
    )
  })
})

describe('useTaskTemplateForm — staged attachments uploaded positionally after create (spec 0124 D-9/AC-026)', () => {
  it('uploads each row’s staged files against the id the server returned at the SAME position', async () => {
    const created = taskTemplate({
      items_count: 2,
      items: [
        {
          id: 20,
          title: 'First',
          description: null,
          estimated_minutes: null,
          task_status_id: null,
          task_status: null,
          due_offset_days: 0,
          sort_order: 0,
          attachments: [],
        },
        {
          id: 21,
          title: 'Second',
          description: null,
          estimated_minutes: null,
          task_status_id: null,
          task_status: null,
          due_offset_days: 0,
          sort_order: 1,
          attachments: [],
        },
      ],
    })
    vi.mocked(createTaskTemplate).mockResolvedValueOnce(created)
    vi.mocked(uploadAttachment).mockResolvedValue({} as never)

    const { result } = renderHook(
      () => useTaskTemplateForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('name', 'Standard onboarding')
      result.current.addItemRow()
      result.current.addItemRow()
    })
    const [firstRowId, secondRowId] = result.current.itemRows.map((row) => row.id)
    const fileForSecond = new File(['b'], 'foto.png', { type: 'image/png' })

    act(() => {
      result.current.updateItemRow(firstRowId, { title: 'First' })
      result.current.updateItemRow(secondRowId, { title: 'Second' })
      result.current.addStagedRowFiles(secondRowId, [fileForSecond])
    })

    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(createTaskTemplate).toHaveBeenCalledTimes(1)
    expect(uploadAttachment).toHaveBeenCalledTimes(1)
    expect(uploadAttachment).toHaveBeenCalledWith({
      resource: TASK_TEMPLATE_ITEM_ATTACHABLE_ALIAS,
      id: 21,
      collection: DOCUMENTS_COLLECTION,
      file: fileForSecond,
    })
  })

  it('never calls uploadAttachment when no row has staged files', async () => {
    vi.mocked(createTaskTemplate).mockResolvedValueOnce(taskTemplate({ items_count: 0, items: [] }))

    const { result } = renderHook(
      () => useTaskTemplateForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('name', 'Standard onboarding')
      result.current.addItemRow()
    })
    const rowId = result.current.itemRows[0].id
    act(() => result.current.updateItemRow(rowId, { title: 'Only row' }))

    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(uploadAttachment).not.toHaveBeenCalled()
  })
})
