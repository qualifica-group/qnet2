import { beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { resetTablePreferences, saveTablePreferences } from '@/features/table/api'
import type { TableConfig } from '@/features/table/types'
import { tableKeys } from '@/features/table/use-table-config'
import { useResetTablePreferences, useSaveTablePreferences } from '@/features/table/use-table-preferences'

vi.mock('@/features/table/api', () => ({
  saveTablePreferences: vi.fn(),
  resetTablePreferences: vi.fn(),
}))

const DOMAIN = 'request-management'
const TAB = { productCategoryId: 12 }
const OTHER_TAB = { productCategoryId: 34 }
const OLD_CONFIG = { resource: DOMAIN, columns: [] } as unknown as TableConfig
const SAVED_CONFIG = { resource: DOMAIN, columns: [], customized: true } as unknown as TableConfig
const OTHER_DOMAIN_KEY = tableKeys.config('users')

// The layout is stored once per domain while the config is cached per category
// tab: a save or reset from one tab must stale every other tab's entry, or
// switching tab shows (and the next change re-saves) the previous layout.
describe('table preferences cache', () => {
  let queryClient: QueryClient

  function wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  }

  function isStale(key: readonly unknown[]): boolean | undefined {
    return queryClient.getQueryState(key)?.isInvalidated
  }

  beforeEach(() => {
    queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
    for (const key of [tableKeys.config(DOMAIN), tableKeys.config(DOMAIN, TAB), tableKeys.config(DOMAIN, OTHER_TAB), OTHER_DOMAIN_KEY]) {
      queryClient.setQueryData(key, OLD_CONFIG)
    }
    vi.mocked(saveTablePreferences).mockResolvedValue(SAVED_CONFIG)
    vi.mocked(resetTablePreferences).mockResolvedValue(undefined)
  })

  it('writes the saved config into the tab entry and stales the other tabs', async () => {
    const { result } = renderHook(() => useSaveTablePreferences(DOMAIN, TAB), { wrapper })

    await act(() => result.current.mutateAsync([{ id: 'source', visible: false, order: 0 }]))

    expect(queryClient.getQueryData(tableKeys.config(DOMAIN, TAB))).toEqual(SAVED_CONFIG)
    expect(isStale(tableKeys.config(DOMAIN, TAB))).toBe(false)
    expect(isStale(tableKeys.config(DOMAIN, OTHER_TAB))).toBe(true)
    expect(isStale(tableKeys.config(DOMAIN))).toBe(true)
    expect(isStale(OTHER_DOMAIN_KEY)).toBe(false)
  })

  it('stales every tab of the domain after a reset', async () => {
    const { result } = renderHook(() => useResetTablePreferences(DOMAIN), { wrapper })

    await act(() => result.current.mutateAsync())

    expect(isStale(tableKeys.config(DOMAIN, TAB))).toBe(true)
    expect(isStale(tableKeys.config(DOMAIN, OTHER_TAB))).toBe(true)
    expect(isStale(OTHER_DOMAIN_KEY)).toBe(false)
  })
})
