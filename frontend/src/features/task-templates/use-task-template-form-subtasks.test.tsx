import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { createTaskTemplate, updateTaskTemplate } from '@/features/task-templates/api'
import { useTaskTemplateForm } from '@/features/task-templates/use-task-template-form'

/**
 * Spec 0172 D-1/AC-018: sub-task rows nest depth-first, a row's own subtree
 * is removed with it, and the create/update payload carries every row's
 * `key`/`parent_key`. Split out of `use-task-template-form.test.tsx` (its own
 * 500-line hard limit, `engineering.md` §6) — same rendering setup.
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(createTaskTemplate).mockReset()
  vi.mocked(updateTaskTemplate).mockReset()
})

describe('useTaskTemplateForm — sub-tasks (spec 0172 D-1/AC-018)', () => {
  it('adds a sub-task under a row, nested right after it, and removing the parent removes it too', () => {
    const { result } = renderHook(
      () => useTaskTemplateForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => result.current.addItemRow())
    const parentId = result.current.itemRows[0].id
    act(() => result.current.addSubtaskItemRow(parentId))

    expect(result.current.itemRows).toHaveLength(2)
    expect(result.current.itemRows[1].parent_key).toBe(parentId)

    act(() => result.current.removeItemRow(parentId))
    expect(result.current.itemRows).toHaveLength(0)
  })

  it('removing a sub-task alone leaves its parent and siblings untouched', () => {
    const { result } = renderHook(
      () => useTaskTemplateForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => result.current.addItemRow())
    const parentId = result.current.itemRows[0].id
    act(() => result.current.addSubtaskItemRow(parentId))
    const childId = result.current.itemRows[1].id

    act(() => result.current.removeItemRow(childId))

    expect(result.current.itemRows.map((row) => row.id)).toEqual([parentId])
  })

  it('a grandchild nests after its own parent, keeping the tree depth-first', () => {
    const { result } = renderHook(
      () => useTaskTemplateForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => result.current.addItemRow())
    const rootId = result.current.itemRows[0].id
    act(() => result.current.addSubtaskItemRow(rootId))
    const childId = result.current.itemRows[1].id
    act(() => result.current.addSubtaskItemRow(childId))

    expect(result.current.itemRows.map((row) => row.id)).toEqual([rootId, childId, result.current.itemRows[2].id])
    expect(result.current.itemRows[2].parent_key).toBe(childId)
  })

  it('sends each row own key/parent_key on create, root before its sub-task', async () => {
    vi.mocked(createTaskTemplate).mockResolvedValueOnce({
      id: 1,
      name: 'Standard onboarding',
      description: null,
      is_active: true,
      items_count: 0,
      stages: [],
      items: [],
      created_at: '2026-01-01T00:00:00Z',
      updated_at: '2026-01-01T00:00:00Z',
    })

    const { result } = renderHook(
      () => useTaskTemplateForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('name', 'Standard onboarding')
      result.current.addItemRow()
    })
    const rootId = result.current.itemRows[0].id
    act(() => {
      result.current.updateItemRow(rootId, { title: 'Root' })
      result.current.addSubtaskItemRow(rootId)
    })
    const childId = result.current.itemRows[1].id
    act(() => result.current.updateItemRow(childId, { title: 'Sub-task' }))

    await act(async () => {
      await result.current.form.handleSubmit(result.current.onSubmit)()
    })

    expect(createTaskTemplate).toHaveBeenCalledWith(
      expect.objectContaining({
        items: [
          expect.objectContaining({ title: 'Root', key: rootId, parent_key: null }),
          expect.objectContaining({ title: 'Sub-task', key: childId, parent_key: rootId }),
        ],
      }),
    )
  })
})
