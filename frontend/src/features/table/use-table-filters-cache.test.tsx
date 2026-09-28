import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { toast } from 'sonner'
import { resetTableFilters, saveTableFilters } from '@/features/table/api'
import type { TableConfig } from '@/features/table/types'
import { tableKeys } from '@/features/table/use-table-config'
import { useResetTableFilters, useSaveTableFilters } from '@/features/table/use-table-filters'

vi.mock('@/features/table/api', () => ({
  saveTableFilters: vi.fn(),
  resetTableFilters: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { error: vi.fn() } }))

const DOMAIN = 'request-management'
const TAB = { productCategoryId: 12 }
const OTHER_TAB = { productCategoryId: 34 }
const OLD_CONFIG = { resource: DOMAIN, columns: [] } as unknown as TableConfig
const SAVED_CONFIG = {
  resource: DOMAIN,
  columns: [],
  appliedAdvancedFilters: { status: ['open'] },
} as unknown as TableConfig
const OTHER_DOMAIN_KEY = tableKeys.config('users')

// The filters are stored once per domain while the config is cached per
// category tab: a save or reset from one tab must DROP every other tab's entry.
// A merely stale entry is still served on the next mount, and the grid would
// query with the previous filters before the fresh config arrives.
describe('table filters cache', () => {
  let queryClient: QueryClient

  function wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  }

  function isCached(key: readonly unknown[]): boolean {
    return queryClient.getQueryData(key) !== undefined
  }

  beforeEach(() => {
    queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
    for (const key of [tableKeys.config(DOMAIN), tableKeys.config(DOMAIN, TAB), tableKeys.config(DOMAIN, OTHER_TAB), OTHER_DOMAIN_KEY]) {
      queryClient.setQueryData(key, OLD_CONFIG)
    }
    vi.mocked(saveTableFilters).mockReset().mockResolvedValue(SAVED_CONFIG)
    vi.mocked(resetTableFilters).mockReset().mockResolvedValue(undefined)
    vi.mocked(toast.error).mockReset()
  })

  it('saves with the tab scope, writes the tab entry and drops the other tabs', async () => {
    const { result } = renderHook(() => useSaveTableFilters(DOMAIN, TAB), { wrapper })

    await act(() => result.current.mutateAsync({ advancedFilters: { status: ['open'] } }))

    expect(saveTableFilters).toHaveBeenCalledWith(DOMAIN, { advancedFilters: { status: ['open'] } }, 12)
    expect(queryClient.getQueryData(tableKeys.config(DOMAIN, TAB))).toEqual(SAVED_CONFIG)
    expect(isCached(tableKeys.config(DOMAIN, OTHER_TAB))).toBe(false)
    expect(isCached(tableKeys.config(DOMAIN))).toBe(false)
    expect(isCached(OTHER_DOMAIN_KEY)).toBe(true)
  })

  it('keeps the unscoped entry when saving from the "Tutte" tab', async () => {
    const { result } = renderHook(() => useSaveTableFilters(DOMAIN), { wrapper })

    await act(() => result.current.mutateAsync({ filterModel: {} }))

    expect(queryClient.getQueryData(tableKeys.config(DOMAIN))).toEqual(SAVED_CONFIG)
    expect(isCached(tableKeys.config(DOMAIN, TAB))).toBe(false)
    expect(isCached(tableKeys.config(DOMAIN, OTHER_TAB))).toBe(false)
  })

  it('drops every other tab of the domain after a reset, keeping the caller tab', async () => {
    const { result } = renderHook(() => useResetTableFilters(DOMAIN, TAB), { wrapper })

    await act(() => result.current.mutateAsync())

    expect(isCached(tableKeys.config(DOMAIN, TAB))).toBe(true)
    expect(isCached(tableKeys.config(DOMAIN, OTHER_TAB))).toBe(false)
    expect(isCached(tableKeys.config(DOMAIN))).toBe(false)
    expect(isCached(OTHER_DOMAIN_KEY)).toBe(true)
  })

  it('reports a rejected save instead of dropping it silently', async () => {
    vi.mocked(saveTableFilters).mockRejectedValue(new Error('422'))
    const { result } = renderHook(() => useSaveTableFilters(DOMAIN, TAB), { wrapper })

    await act(async () => {
      await result.current.mutateAsync({ filterModel: {} }).catch(() => undefined)
    })

    expect(toast.error).toHaveBeenCalledTimes(1)
    expect(queryClient.getQueryData(tableKeys.config(DOMAIN, TAB))).toEqual(OLD_CONFIG)
  })
})
