import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useTaskForm } from '@/features/tasks/use-task-form'
import { fetchForSelect } from '@/features/for-select/api'
import { DEFAULT_MODULE_OPEN_PREFERENCES } from '@/features/modules/types'
import type { ForSelectItem, PaginatedResponse } from '@/features/for-select/types'
import type { User } from '@/features/auth/types'

/** Builds a well-formed for-select page, `meta` cast in since the base `ForSelectItem` carries none. */
function page(items: Array<ForSelectItem & { meta: Record<string, unknown> }>): PaginatedResponse<ForSelectItem> {
  return {
    items,
    export_link: null,
    pagination: { offset: 0, limit: 100, total: items.length, total_pages: 1 },
  }
}

/** A single for-select item carrying `meta`, cast in the same way as `page()`'s own items. */
function itemWithMeta(id: number, label: string, meta: Record<string, unknown>): ForSelectItem & { meta: Record<string, unknown> } {
  return { id, label, meta }
}

/**
 * Spec 0154 D-11: registry/commessa/opportunita'/lead cross-clearing, split
 * off `use-task-form.test.tsx` for file size (mirrors the existing split of
 * `use-task-form-server-errors.test.tsx`/`use-task-form-attachments.test.tsx`).
 */

vi.mock('@/features/tasks/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/tasks/api')>('@/features/tasks/api')
  return { ...actual, createTask: vi.fn(), updateTask: vi.fn() }
})

vi.mock('@/features/for-select/api', () => ({ fetchForSelect: vi.fn() }))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: currentUser() }),
}))

function currentUser(): User {
  return {
    id: 99,
    name: 'Utente Corrente',
    email: 'utente@example.com',
    locale: 'en',
    roles: [],
    avatar_url: null,
    personal_data: null,
    created_at: null,
    module_open_preferences: DEFAULT_MODULE_OPEN_PREFERENCES,
    ui_scale: 40,
    date_format: 'dmy',
    time_format: '24h',
    color_preset: 'default',
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  vi.mocked(fetchForSelect).mockReset()
  vi.mocked(fetchForSelect).mockResolvedValue(page([]))
})

describe('useTaskForm — registry change cascade (spec 0154 D-11)', () => {
  it('always clears referent, opportunity and lead', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('referent_id', 11)
      result.current.form.setValue('opportunity_id', 22)
      result.current.form.setValue('lead_id', 33)
      result.current.handleRegistryChange(8)
    })

    expect(result.current.form.getValues('referent_id')).toBeNull()
    expect(result.current.form.getValues('opportunity_id')).toBeNull()
    expect(result.current.form.getValues('lead_id')).toBeNull()
  })

  it('clears the commessa when it belongs to a DIFFERENT registry', async () => {
    vi.mocked(fetchForSelect).mockResolvedValue(page([{ id: 5, label: 'WO-5', meta: { registry_id: 7 } }]))

    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('work_order_id', 5)
    })

    // `useTaskWorkOrderRegistryId` resolves asynchronously: retry the cascade
    // until the hook's closure has picked up the resolved registry (7).
    await waitFor(() => {
      act(() => {
        result.current.handleRegistryChange(8)
      })
      expect(result.current.form.getValues('work_order_id')).toBeNull()
    })
    expect(result.current.form.getValues('work_order_stage_id')).toBeNull()
  })

  it('keeps the commessa when it belongs to the SAME registry', async () => {
    vi.mocked(fetchForSelect).mockResolvedValue(page([{ id: 5, label: 'WO-5', meta: { registry_id: 7 } }]))

    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('work_order_id', 5)
    })
    await waitFor(() => expect(fetchForSelect).toHaveBeenCalledWith('work-orders', { ids: [5], limit: 1 }))
    // Let the resolved registry id (7) commit into the hook's state before asserting.
    await act(async () => {
      await Promise.resolve()
      await Promise.resolve()
    })

    act(() => {
      result.current.handleRegistryChange(7)
    })

    expect(result.current.form.getValues('work_order_id')).toBe(5)
  })
})

describe('useTaskForm — commessa/opportunita\' mutual exclusion (spec 0154 D-11)', () => {
  it('picking a commessa clears the opportunita\' and sets the registry from its own meta', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('opportunity_id', 22)
      result.current.handleWorkOrderItemChange(itemWithMeta(5, 'WO-5', { registry_id: 7 }))
    })

    expect(result.current.form.getValues('opportunity_id')).toBeNull()
    expect(result.current.form.getValues('registry_id')).toBe(7)
  })

  it('clearing the commessa picker (null item) touches nothing else', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('opportunity_id', 22)
      result.current.form.setValue('registry_id', 3)
      result.current.handleWorkOrderItemChange(null)
    })

    expect(result.current.form.getValues('opportunity_id')).toBe(22)
    expect(result.current.form.getValues('registry_id')).toBe(3)
  })

  it('picking an opportunita\' clears the commessa and its fase', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('work_order_id', 5)
      result.current.form.setValue('work_order_stage_id', 2)
      result.current.handleOpportunityChange(22)
    })

    expect(result.current.form.getValues('work_order_id')).toBeNull()
    expect(result.current.form.getValues('work_order_stage_id')).toBeNull()
  })

  it('clearing the opportunita\' picker (null id) touches nothing else', () => {
    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('work_order_id', 5)
      result.current.handleOpportunityChange(null)
    })

    expect(result.current.form.getValues('work_order_id')).toBe(5)
  })
})

/** Spec 0154 D-8: the three lookups precompile from each catalog's default row on create. */
describe('useTaskForm — lookup defaults precompile (spec 0154 D-8)', () => {
  it('fills type/priority/importance from the is_default row once resolved', async () => {
    vi.mocked(fetchForSelect).mockImplementation(async (resource) => {
      if (resource === 'task-types') {
        return page([itemWithMeta(2, 'Attivita', { is_default: true })])
      }
      if (resource === 'task-priorities') {
        return page([itemWithMeta(4, 'Media', { is_default: true })])
      }
      if (resource === 'task-importances') {
        return page([itemWithMeta(5, 'Media', { is_default: true })])
      }
      return page([])
    })

    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.form.getValues('task_type_id')).toBe(2))
    expect(result.current.form.getValues('task_priority_id')).toBe(4)
    expect(result.current.form.getValues('task_importance_id')).toBe(5)
  })

  it('never overwrites a value the user already picked', async () => {
    vi.mocked(fetchForSelect).mockImplementation(async (resource) => {
      if (resource === 'task-types') {
        return page([itemWithMeta(2, 'Attivita', { is_default: true })])
      }
      return page([])
    })

    const { result } = renderHook(
      () => useTaskForm({ mode: { type: 'create' }, onSuccess: () => undefined }),
      { wrapper: wrapper() },
    )

    act(() => {
      result.current.form.setValue('task_type_id', 9)
    })

    await waitFor(() => expect(fetchForSelect).toHaveBeenCalledWith('task-types', { limit: 100 }))
    expect(result.current.form.getValues('task_type_id')).toBe(9)
  })
})
