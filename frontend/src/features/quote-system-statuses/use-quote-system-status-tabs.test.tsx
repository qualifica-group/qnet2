import { describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode, RefObject } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import { useQuoteSystemStatusTabs } from '@/features/quote-system-statuses/use-quote-system-status-tabs'

const fetchForSelect = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  fetchForSelect: (...args: unknown[]) => fetchForSelect(...args),
}))

const STATUSES = ['Aperto', 'In attesa', 'Vinto', 'Perso']

function renderTabsHook(setFilterModel = vi.fn()) {
  fetchForSelect.mockResolvedValue({ items: STATUSES.map((label, index) => ({ id: index + 1, label })) })
  const tableRef: RefObject<TableViewHandle | null> = { current: { setFilterModel } as unknown as TableViewHandle }
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  return renderHook(() => useQuoteSystemStatusTabs(tableRef), { wrapper })
}

describe('useQuoteSystemStatusTabs (spec 0409)', () => {
  it('lists the system statuses in the configured order', async () => {
    const { result } = renderTabsHook()

    await waitFor(() => expect(result.current.statuses).toEqual(STATUSES))
    expect(fetchForSelect).toHaveBeenCalledWith('quote-system-statuses', { limit: 100 })
  })

  it('follows the quote_system_status grid filter, none for several statuses', async () => {
    const { result } = renderTabsHook()
    await waitFor(() => expect(result.current.statuses).toHaveLength(4))
    expect(result.current.active).toBe('')

    act(() => result.current.onFilterModelChange({ quote_system_status: { filterType: 'set', values: ['Vinto'] } }))
    expect(result.current.active).toBe('Vinto')

    act(() => result.current.onFilterModelChange({ quote_system_status: { filterType: 'set', values: ['Vinto', 'Perso'] } }))
    expect(result.current.active).toBeNull()
  })

  it('writes the status into the quote_system_status filter, and removes it for all', () => {
    const setFilterModel = vi.fn()
    const { result } = renderTabsHook(setFilterModel)

    result.current.select('Perso')
    expect(setFilterModel).toHaveBeenLastCalledWith({ quote_system_status: { filterType: 'set', values: ['Perso'] } })

    result.current.select('')
    expect(setFilterModel).toHaveBeenLastCalledWith({ quote_system_status: null })
  })

  it('keeps the counts the grid reports', () => {
    const { result } = renderTabsHook()

    act(() => result.current.onAggregatesChange({ Vinto: 4 }))

    expect(result.current.counts).toEqual({ Vinto: 4 })
  })
})
