import { describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, renderHook, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode, RefObject } from 'react'
import '@/i18n'
import type { TableViewHandle } from '@/features/table/table-view'
import { ContractStatusTabs } from '@/features/contracts/contract-status-tabs'
import { useContractStatusTabs } from '@/features/contracts/use-contract-status-tabs'

const fetchContractStatusesForSelect = vi.fn()
vi.mock('@/features/contract-statuses/for-select-api', () => ({
  fetchContractStatusesForSelect: (...args: unknown[]) => fetchContractStatusesForSelect(...args),
}))

const STATUSES = ['Da validare', 'Programmato', 'Disdetto']

function tableRefWith(setFilterModel: (patch: Record<string, unknown>) => void): RefObject<TableViewHandle | null> {
  return { current: { setFilterModel } as unknown as TableViewHandle }
}

function renderTabsHook(setFilterModel = vi.fn()) {
  fetchContractStatusesForSelect.mockResolvedValue({ items: STATUSES.map((label, index) => ({ id: index + 1, label })) })
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  return renderHook(() => useContractStatusTabs(tableRefWith(setFilterModel)), { wrapper })
}

describe('useContractStatusTabs', () => {
  it('lists the active statuses in the configured order', async () => {
    const { result } = renderTabsHook()

    await waitFor(() => expect(result.current.statuses).toEqual(STATUSES))
    expect(fetchContractStatusesForSelect).toHaveBeenCalledWith({ limit: 100 })
  })

  it('starts on all, follows the grid filter model and selects none for several statuses', async () => {
    const { result } = renderTabsHook()
    await waitFor(() => expect(result.current.statuses).toHaveLength(3))
    expect(result.current.active).toBe('')

    act(() => result.current.onFilterModelChange({ contract_status: { filterType: 'set', values: ['Disdetto'] } }))
    expect(result.current.active).toBe('Disdetto')

    act(() => result.current.onFilterModelChange({ contract_status: { filterType: 'set', values: ['Disdetto', 'Programmato'] } }))
    expect(result.current.active).toBeNull()

    act(() => result.current.onFilterModelChange({}))
    expect(result.current.active).toBe('')
  })

  it('writes the status into the grid filter, and removes it for all', () => {
    const setFilterModel = vi.fn()
    const { result } = renderTabsHook(setFilterModel)

    result.current.select('Programmato')
    expect(setFilterModel).toHaveBeenLastCalledWith({ contract_status: { filterType: 'set', values: ['Programmato'] } })

    result.current.select('')
    expect(setFilterModel).toHaveBeenLastCalledWith({ contract_status: null })
  })

  it('keeps the counts the grid reports', () => {
    const { result } = renderTabsHook()

    act(() => result.current.onAggregatesChange({ Programmato: 4 }))

    expect(result.current.counts).toEqual({ Programmato: 4 })
  })
})

describe('ContractStatusTabs', () => {
  it('offers all first then each status, and reports the pick', () => {
    const onSelect = vi.fn()
    render(<ContractStatusTabs statuses={STATUSES} active="" onSelect={onSelect} />)

    const tabs = screen.getAllByRole('tab')
    expect(tabs.map((tab) => tab.textContent)).toEqual(['Tutti', ...STATUSES])
    expect(tabs[0]).toHaveAttribute('aria-selected', 'true')

    fireEvent.click(tabs[3])
    expect(onSelect).toHaveBeenCalledWith('Disdetto')
  })

  it('shows the count of each status tab, none on all and none when zero', () => {
    render(<ContractStatusTabs statuses={STATUSES} active="Programmato" counts={{ 'Da validare': 1200, Programmato: 3 }} onSelect={vi.fn()} />)

    const [all, toValidate, scheduled, terminated] = screen.getAllByRole('tab')
    expect(all.textContent).toBe('Tutti')
    expect(toValidate.textContent).toMatch(/^Da validare \(1\.?200\)$/)
    expect(scheduled.textContent).toBe('Programmato (3)')
    expect(scheduled).toHaveAttribute('aria-selected', 'true')
    expect(terminated.textContent).toBe('Disdetto')
  })
})
