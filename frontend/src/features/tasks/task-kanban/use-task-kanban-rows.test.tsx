import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useTaskKanbanRows, TASK_KANBAN_ROW_LIMIT } from '@/features/tasks/task-kanban/use-task-kanban-rows'
import { fetchTableConfig, fetchTableRows } from '@/features/table/api'
import type { TableConfig } from '@/features/table/types'

vi.mock('@/features/table/api', () => ({
  fetchTableConfig: vi.fn(),
  fetchTableRows: vi.fn(),
  saveTableFilters: vi.fn(),
}))

const CONFIG: TableConfig = {
  resource: 'tasks',
  columns: [],
  filters: [],
  actions: [],
  defaultSort: [{ columnId: 'updated_at', direction: 'desc' }],
  defaultPagination: { limit: 25 },
  customized: false,
  filterState: { status: { values: ['open'] } },
  filtersCustomized: false,
  searchable: ['title'],
  advancedFilters: [
    {
      name: 'status',
      label: 'tasks.advancedFilters.status',
      type: 'text',
      order: 0,
      required: false,
      visible: true,
      width: 'sm',
      multiple: false,
      defaultValue: null,
    },
  ],
  appliedAdvancedFilters: { status: 'open' },
}

const EMPTY_ROWS = { items: [], export_link: null, pagination: { total: 0, offset: 0, limit: 500, total_pages: 0 } }

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

beforeEach(() => {
  vi.mocked(fetchTableConfig).mockReset().mockResolvedValue(CONFIG)
  vi.mocked(fetchTableRows).mockReset().mockResolvedValue(EMPTY_ROWS)
})

describe('useTaskKanbanRows (spec 0157 D-4)', () => {
  it('loads endRow 500 with the SAME persisted column filterState, sort and applied advanced filters as the list', async () => {
    const { result } = renderHook(() => useTaskKanbanRows(), { wrapper })

    await waitFor(() => expect(result.current.isPending).toBe(false))

    expect(fetchTableRows).toHaveBeenCalledWith('tasks', {
      startRow: 0,
      endRow: TASK_KANBAN_ROW_LIMIT,
      sortModel: [{ colId: 'updated_at', sort: 'desc' }],
      filterModel: { status: { values: ['open'] } },
      advancedFilters: { status: 'open' },
    })
  })

  it('adds the trimmed search term once set, refetching automatically', async () => {
    const { result } = renderHook(() => useTaskKanbanRows(), { wrapper })
    await waitFor(() => expect(result.current.isPending).toBe(false))

    act(() => result.current.setSearch('  needle  '))

    await waitFor(() =>
      expect(fetchTableRows).toHaveBeenLastCalledWith(
        'tasks',
        expect.objectContaining({ search: 'needle' }),
      ),
    )
  })

  it('flags exceededLimit once pagination.total exceeds the 500 cap', async () => {
    vi.mocked(fetchTableRows).mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 640, offset: 0, limit: 500, total_pages: 2 },
    })

    const { result } = renderHook(() => useTaskKanbanRows(), { wrapper })

    await waitFor(() => expect(result.current.total).toBe(640))
    expect(result.current.exceededLimit).toBe(true)
  })

  it('does not flag exceededLimit at or under the cap', async () => {
    vi.mocked(fetchTableRows).mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 500, offset: 0, limit: 500, total_pages: 1 },
    })

    const { result } = renderHook(() => useTaskKanbanRows(), { wrapper })

    await waitFor(() => expect(result.current.total).toBe(500))
    expect(result.current.exceededLimit).toBe(false)
  })
})
