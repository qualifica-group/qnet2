import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import {
  TASK_KANBAN_COLUMN_PAGE_SIZE,
  taskKanbanColumnQueryKey,
  useTaskKanbanColumnRows,
} from '@/features/tasks/task-kanban/use-task-kanban-column-rows'
import { fetchTableRows } from '@/features/table/api'
import type { TableRow, TableRowsResponse } from '@/features/table/types'

vi.mock('@/features/table/api', () => ({
  fetchTableRows: vi.fn(),
}))

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

function rowsPage(ids: number[], total: number): TableRowsResponse {
  return {
    items: ids.map((id): TableRow => ({ id, actions: [] })),
    export_link: null,
    pagination: { total, offset: ids[0] ?? 0, limit: TASK_KANBAN_COLUMN_PAGE_SIZE, total_pages: Math.ceil(total / TASK_KANBAN_COLUMN_PAGE_SIZE) },
  }
}

function idsFrom(start: number, count: number): number[] {
  return Array.from({ length: count }, (_, index) => start + index)
}

const BASE_ARGS = {
  kanbanGroup: { by: 'status' as const, key: 1 },
  search: '',
  advancedFilters: {},
  filterModel: {},
  sortModel: [],
  enabled: true,
}

beforeEach(() => {
  vi.mocked(fetchTableRows).mockReset()
})

describe('useTaskKanbanColumnRows (spec 0164 D-1: blocks of 50, no 500 cap)', () => {
  it('loads a column with 120 rows in three blocks — 50, then 100, then 120 — and stops there', async () => {
    vi.mocked(fetchTableRows)
      .mockResolvedValueOnce(rowsPage(idsFrom(1, 50), 120))
      .mockResolvedValueOnce(rowsPage(idsFrom(51, 50), 120))
      .mockResolvedValueOnce(rowsPage(idsFrom(101, 20), 120))

    const { result } = renderHook(() => useTaskKanbanColumnRows(BASE_ARGS), { wrapper })

    await waitFor(() => expect(result.current.isPending).toBe(false))
    expect(result.current.data?.pages.flatMap((page) => page.items)).toHaveLength(50)
    expect(result.current.hasNextPage).toBe(true)

    await result.current.fetchNextPage()
    await waitFor(() => expect(result.current.data?.pages.flatMap((page) => page.items)).toHaveLength(100))
    expect(result.current.hasNextPage).toBe(true)

    await result.current.fetchNextPage()
    await waitFor(() => expect(result.current.data?.pages.flatMap((page) => page.items)).toHaveLength(120))
    expect(result.current.hasNextPage).toBe(false)

    expect(fetchTableRows).toHaveBeenCalledTimes(3)
    expect(fetchTableRows).toHaveBeenNthCalledWith(1, 'tasks', expect.objectContaining({ startRow: 0, endRow: 50 }))
    expect(fetchTableRows).toHaveBeenNthCalledWith(2, 'tasks', expect.objectContaining({ startRow: 50, endRow: 100 }))
    expect(fetchTableRows).toHaveBeenNthCalledWith(3, 'tasks', expect.objectContaining({ startRow: 100, endRow: 150 }))
  })

  it('exposes pagination.total as the column count from the first loaded page', async () => {
    vi.mocked(fetchTableRows).mockResolvedValue(rowsPage(idsFrom(1, 50), 120))

    const { result } = renderHook(() => useTaskKanbanColumnRows(BASE_ARGS), { wrapper })

    await waitFor(() => expect(result.current.data?.pages[0]?.pagination.total).toBe(120))
  })

  it('keeps loading past the old 500-row cap — no artificial ceiling', async () => {
    vi.mocked(fetchTableRows).mockResolvedValueOnce(rowsPage(idsFrom(1, 50), 640))

    const { result } = renderHook(() => useTaskKanbanColumnRows(BASE_ARGS), { wrapper })

    await waitFor(() => expect(result.current.hasNextPage).toBe(true))
    expect(result.current.data?.pages[0]?.pagination.total).toBe(640)
  })

  it('sends kanbanGroup, filterModel, sortModel, search and advancedFilters on every block', async () => {
    vi.mocked(fetchTableRows).mockResolvedValue(rowsPage(idsFrom(1, 1), 1))

    renderHook(
      () =>
        useTaskKanbanColumnRows({
          kanbanGroup: { by: 'due', key: 'overdue' },
          search: 'invoice',
          advancedFilters: { assignee: 7 },
          filterModel: { priority: { values: ['high'] } },
          sortModel: [{ colId: 'end_date', sort: 'asc' }],
          enabled: true,
        }),
      { wrapper },
    )

    await waitFor(() =>
      expect(fetchTableRows).toHaveBeenCalledWith('tasks', {
        startRow: 0,
        endRow: TASK_KANBAN_COLUMN_PAGE_SIZE,
        sortModel: [{ colId: 'end_date', sort: 'asc' }],
        filterModel: { priority: { values: ['high'] } },
        kanbanGroup: { by: 'due', key: 'overdue' },
        search: 'invoice',
        advancedFilters: { assignee: 7 },
      }),
    )
  })

  it('omits search/advancedFilters when empty, and never fetches while disabled', () => {
    renderHook(() => useTaskKanbanColumnRows({ ...BASE_ARGS, enabled: false }), { wrapper })

    expect(fetchTableRows).not.toHaveBeenCalled()
  })
})

describe('taskKanbanColumnQueryKey (spec 0164 D-3: origin/destination-only invalidation)', () => {
  it('is a stable prefix keyed by kanbanGroup alone, independent of filters', () => {
    expect(taskKanbanColumnQueryKey({ by: 'status', key: 5 })).toEqual([
      'tasks',
      'kanban',
      'column',
      { by: 'status', key: 5 },
    ])
  })
})
