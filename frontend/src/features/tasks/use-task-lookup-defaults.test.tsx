import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import { useTaskLookupDefaults } from '@/features/tasks/use-task-lookup-defaults'
import type { ForSelectItem, PaginatedResponse } from '@/features/for-select/types'

/** Builds a well-formed for-select page, `meta` cast in since the base `ForSelectItem` carries none. */
function page(items: Array<ForSelectItem & { meta: Record<string, unknown> }>): PaginatedResponse<ForSelectItem> {
  return {
    items,
    export_link: null,
    pagination: { offset: 0, limit: 100, total: items.length, total_pages: 1 },
  }
}

/** Spec 0154 D-8: resolves each catalog's `meta.is_default` row, so a new task form can precompile it. */

vi.mock('@/features/for-select/api', () => ({ fetchForSelect: vi.fn() }))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  vi.mocked(fetchForSelect).mockReset()
})

describe('useTaskLookupDefaults', () => {
  it('does not query any catalog while disabled', () => {
    renderHook(() => useTaskLookupDefaults(false), { wrapper: wrapper() })

    expect(fetchForSelect).not.toHaveBeenCalled()
  })

  it('resolves the default row id of each of the three catalogs', async () => {
    vi.mocked(fetchForSelect).mockImplementation(async (resource) => {
      const rows: Record<string, { id: number; is_default: boolean }[]> = {
        'task-types': [
          { id: 1, is_default: false },
          { id: 2, is_default: true },
        ],
        'task-priorities': [{ id: 4, is_default: true }],
        'task-importances': [
          { id: 5, is_default: true },
          { id: 6, is_default: false },
        ],
      }
      return page(
        rows[resource].map((row) => ({ id: row.id, label: resource, meta: { is_default: row.is_default } })),
      )
    })

    const { result } = renderHook(() => useTaskLookupDefaults(true), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.taskTypeId).toBe(2))
    expect(result.current.taskPriorityId).toBe(4)
    expect(result.current.taskImportanceId).toBe(5)
  })

  it('resolves null when a catalog carries no default row', async () => {
    vi.mocked(fetchForSelect).mockResolvedValue(page([{ id: 1, label: 'x', meta: { is_default: false } }]))

    const { result } = renderHook(() => useTaskLookupDefaults(true), { wrapper: wrapper() })

    await waitFor(() => expect(fetchForSelect).toHaveBeenCalledTimes(3))
    expect(result.current.taskTypeId).toBeNull()
    expect(result.current.taskPriorityId).toBeNull()
    expect(result.current.taskImportanceId).toBeNull()
  })
})
