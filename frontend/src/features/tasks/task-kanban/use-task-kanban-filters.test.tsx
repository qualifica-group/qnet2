import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useTaskKanbanFilters } from '@/features/tasks/task-kanban/use-task-kanban-filters'
import { fetchTableConfig } from '@/features/table/api'
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

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

beforeEach(() => {
  vi.mocked(fetchTableConfig).mockReset().mockResolvedValue(CONFIG)
})

describe('useTaskKanbanFilters (spec 0164: the board shared filter slice, no longer fetching rows itself)', () => {
  it('exposes the SAME persisted column filterState, sort and applied advanced filters as the list', async () => {
    const { result } = renderHook(() => useTaskKanbanFilters(), { wrapper })

    await waitFor(() => expect(result.current.isReady).toBe(true))

    expect(result.current.sortModel).toEqual([{ colId: 'updated_at', sort: 'desc' }])
    expect(result.current.filterModel).toEqual({ status: { values: ['open'] } })
    expect(result.current.advancedFilters.activeValues).toEqual({ status: 'open' })
  })

  it('trims the search term', async () => {
    const { result } = renderHook(() => useTaskKanbanFilters(), { wrapper })
    await waitFor(() => expect(result.current.isReady).toBe(true))

    act(() => result.current.setSearch('  needle  '))

    expect(result.current.trimmedSearch).toBe('needle')
  })

  it('is not ready before the config resolves', () => {
    const { result } = renderHook(() => useTaskKanbanFilters(), { wrapper })

    expect(result.current.isReady).toBe(false)
    expect(result.current.isPending).toBe(true)
  })
})
