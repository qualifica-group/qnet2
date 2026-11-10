import { useEffect, type ReactNode } from 'react'
import { act, render, screen, waitFor } from '@testing-library/react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { UiScaleContext } from '@/features/appearance/ui-scale-context'
import { TableView } from '@/features/table/table-view'
import { fetchTableConfig } from '@/features/table/api'
import { ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE, tableKeys } from '@/features/table/use-table-config'
import type { TableConfig } from '@/features/table/types'

/**
 * Spec 0374 AC-016: the grid is remounted when the categories whose `attr.*` columns the config carries change,
 * and only then. `DataTable` is stubbed with a mount counter (jsdom has no AG Grid runtime).
 */
const mountSpy = vi.fn()

vi.mock('@/components/data-table/data-table', () => ({
  ACTIONS_COLUMN_ID: '__actions',
  DataTable: () => {
    useEffect(() => {
      mountSpy()
    }, [])
    return <div role="grid" />
  },
}))

vi.mock('@/features/exports/export-dialog', () => ({ ExportDialog: () => null }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/table/api', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/features/table/api')>()
  return { ...actual, fetchTableConfig: vi.fn() }
})

const BASE_CONFIG: TableConfig = {
  resource: 'quotes',
  columns: [
    { id: 'code', label: 'quotes.columns.code', type: 'text', visible: true, width: null, order: 0, sortable: true, filterable: true },
  ],
  filters: [],
  actions: [],
  defaultSort: [],
  defaultPagination: { limit: 25 },
  customized: false,
  attributeCategoryIds: [],
}

const CONFIG_KEY = tableKeys.config('quotes', ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE)

function withProviders(client: QueryClient, node: ReactNode) {
  return (
    <QueryClientProvider client={client}>
      <UiScaleContext.Provider value={{ scale: 40, factor: 1, setScale: vi.fn() }}>
        <TooltipProvider>
          <ConfirmDialogProvider>{node}</ConfirmDialogProvider>
        </TooltipProvider>
      </UiScaleContext.Provider>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  mountSpy.mockReset()
  vi.mocked(fetchTableConfig).mockReset().mockResolvedValue(BASE_CONFIG)
})

describe('TableView - remount on attribute categories (AC-016)', () => {
  it('remounts the grid when attributeCategoryIds changes, not when only the filters do', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(withProviders(client, <TableView domain="quotes" scope={ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE} onAction={vi.fn()} />))
    await screen.findByRole('grid')
    expect(mountSpy).toHaveBeenCalledTimes(1)

    act(() => {
      client.setQueryData(CONFIG_KEY, { ...BASE_CONFIG, attributeCategoryIds: [4], filterState: {} })
    })
    await waitFor(() => expect(mountSpy).toHaveBeenCalledTimes(2))

    act(() => {
      client.setQueryData(CONFIG_KEY, {
        ...BASE_CONFIG,
        attributeCategoryIds: [4],
        filterState: { code: { filterType: 'text', type: 'contains', filter: 'a' } },
      })
    })
    expect(mountSpy).toHaveBeenCalledTimes(2)

    act(() => {
      client.setQueryData(CONFIG_KEY, { ...BASE_CONFIG, attributeCategoryIds: [4, 9] })
    })
    await waitFor(() => expect(mountSpy).toHaveBeenCalledTimes(3))
  })
})
