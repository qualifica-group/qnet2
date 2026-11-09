import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE, removeOtherScopeConfigs, tableKeys, useTableConfig } from '@/features/table/use-table-config'
import { fetchTableConfig } from '@/features/table/api'
import type { TableConfig } from '@/features/table/types'

vi.mock('@/features/table/api', () => ({
  fetchTableConfig: vi.fn(),
}))

const fetchTableConfigMock = vi.mocked(fetchTableConfig)

const CONFIG: TableConfig = {
  resource: 'request-management',
  columns: [],
  filters: [],
  actions: [],
  defaultSort: [],
  defaultPagination: { limit: 25 },
  customized: false,
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeEach(() => {
  fetchTableConfigMock.mockReset()
  fetchTableConfigMock.mockResolvedValue(CONFIG)
})

describe('tableKeys.config', () => {
  it('is scoped by domain only when no productCategoryId is given', () => {
    expect(tableKeys.config('request-management')).toEqual(['table', 'request-management', 'config', null])
  })

  // Spec 0064: switching category tabs must never read the other tab's cache.
  it('isolates the cache per productCategoryId (spec 0064)', () => {
    expect(tableKeys.config('request-management', { productCategoryId: 12 })).toEqual([
      'table',
      'request-management',
      'config',
      12,
    ])
    expect(tableKeys.config('request-management', { productCategoryId: 7 })).not.toEqual(
      tableKeys.config('request-management', { productCategoryId: 12 }),
    )
  })

  it('keeps a parent tab apart from the same category at the all level', () => {
    expect(tableKeys.config('quotes', { productCategoryBranchId: 12 })).not.toEqual(
      tableKeys.config('quotes', { productCategoryId: 12 }),
    )
  })
})

describe('attribute columns from filters scope (spec 0374, AC-015)', () => {
  it('keys the config apart from the one without scope and from a category tab', () => {
    const plain = tableKeys.config('quotes')
    const scoped = tableKeys.config('quotes', ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE)
    expect(scoped).not.toEqual(plain)
    expect(scoped).not.toEqual(tableKeys.config('quotes', { productCategoryId: 12 }))
  })

  it('asks the config with the flag and keeps the entry of its own scope when others are dropped', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const { result } = renderHook(() => useTableConfig('quotes', ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE), {
      wrapper: ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
    })
    await waitFor(() => expect(result.current.data).toEqual(CONFIG))
    expect(fetchTableConfigMock).toHaveBeenCalledWith('quotes', { attributeColumnsFromFilters: true })

    client.setQueryData(tableKeys.config('quotes'), CONFIG)
    removeOtherScopeConfigs(client, 'quotes', ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE)

    expect(client.getQueryData(tableKeys.config('quotes', ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE))).toEqual(CONFIG)
    expect(client.getQueryData(tableKeys.config('quotes'))).toBeUndefined()
  })
})

describe('useTableConfig', () => {
  it('fetches the unscoped config when no scope is given', async () => {
    const { result } = renderHook(() => useTableConfig('users'), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.data).toEqual(CONFIG))
    expect(fetchTableConfigMock).toHaveBeenCalledWith('users', undefined)
  })

  // Spec 0064 AC-020: selecting a category tab queries the config with that
  // category's scope.
  it('fetches the config scoped to productCategoryId when a scope is given', async () => {
    const { result } = renderHook(
      () => useTableConfig('request-management', { productCategoryId: 12 }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.data).toEqual(CONFIG))
    expect(fetchTableConfigMock).toHaveBeenCalledWith('request-management', 12)
  })

  it('asks a parent tab config with its own branch scope', async () => {
    const { result } = renderHook(
      () => useTableConfig('quotes', { productCategoryBranchId: 3 }),
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(result.current.data).toEqual(CONFIG))
    expect(fetchTableConfigMock).toHaveBeenCalledWith('quotes', { branchId: 3 })
  })
})
