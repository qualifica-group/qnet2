import type { IServerSideGetRowsParams } from 'ag-grid-community'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createSsrmDatasource } from '@/features/table/ssrm-datasource'
import { fetchTableRows } from '@/features/table/api'
import type { TableRow } from '@/features/table/types'

vi.mock('@/features/table/api', () => ({
  fetchTableRows: vi.fn(),
}))

const fetchRowsMock = vi.mocked(fetchTableRows)

/** Builds a minimal SSRM `getRows` params stub around the given request. */
function stubParams(
  request: Partial<IServerSideGetRowsParams<TableRow>['request']>,
): IServerSideGetRowsParams<TableRow> {
  return {
    request: {
      startRow: 0,
      endRow: 25,
      rowGroupCols: [],
      valueCols: [],
      pivotCols: [],
      pivotMode: false,
      groupKeys: [],
      filterModel: null,
      sortModel: [],
      ...request,
    },
    success: vi.fn(),
    fail: vi.fn(),
  } as unknown as IServerSideGetRowsParams<TableRow>
}

const EMPTY_RESPONSE = {
  items: [],
  export_link: null,
  pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
}

describe('createSsrmDatasource', () => {
  beforeEach(() => {
    fetchRowsMock.mockReset()
  })

  it('forwards a combined filterModel (including the multi shape) intact to fetchTableRows', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)
    const multiFilterModel = {
      email: {
        filterType: 'multi',
        filterModels: [
          { filterType: 'set', values: ['a@test.com'] },
          { filterType: 'text', type: 'contains', filter: 'a' },
        ],
      },
    }
    const params = stubParams({ filterModel: multiFilterModel })

    await createSsrmDatasource('users').getRows(params)

    expect(fetchRowsMock).toHaveBeenCalledWith('users', {
      startRow: 0,
      endRow: 25,
      sortModel: [],
      filterModel: multiFilterModel,
    })
  })

  it('normalizes a null filterModel to an empty object', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)
    const params = stubParams({ filterModel: null })

    await createSsrmDatasource('users').getRows(params)

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'users',
      expect.objectContaining({ filterModel: {} }),
    )
  })

  it('excludes the Advanced Filter model (array shape) from the request', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)
    // Defensive edge case: an array-shaped filterModel is never forwarded raw.
    const params = stubParams({
      filterModel: [{ filterType: 'join', type: 'AND', conditions: [] }] as never,
    })

    await createSsrmDatasource('users').getRows(params)

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'users',
      expect.objectContaining({ filterModel: {} }),
    )
  })

  it('maps the paginatedResponse envelope to rowData/rowCount on success', async () => {
    const items = [{ id: 1, actions: [] }] as TableRow[]
    fetchRowsMock.mockResolvedValue({ ...EMPTY_RESPONSE, items, pagination: { total: 1, offset: 0, limit: 25, total_pages: 1 } })
    const params = stubParams({})

    await createSsrmDatasource('users').getRows(params)

    expect(params.success).toHaveBeenCalledWith({ rowData: items, rowCount: 1 })
  })

  it('includes the trimmed search term from the getter when non-empty (spec 0009)', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)
    const params = stubParams({})

    await createSsrmDatasource('users', { getSearch: () => '  needle  ' }).getRows(params)

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'users',
      expect.objectContaining({ search: 'needle' }),
    )
  })

  it('omits `search` entirely when the getter returns an empty/blank term', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)

    await createSsrmDatasource('users', { getSearch: () => '   ' }).getRows(stubParams({}))
    // No getter at all behaves the same.
    await createSsrmDatasource('users').getRows(stubParams({}))

    for (const call of fetchRowsMock.mock.calls) {
      expect(call[1]).not.toHaveProperty('search')
    }
  })

  it('includes the applied advanced filters from the getter when non-empty (spec 0032)', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)
    const params = stubParams({})

    await createSsrmDatasource('users', { getAdvancedFilters: () => ({ status: 'active' }) }).getRows(params)

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'users',
      expect.objectContaining({ advancedFilters: { status: 'active' } }),
    )
  })

  it('omits `advancedFilters` entirely when the getter returns an empty map', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)

    await createSsrmDatasource('users', { getAdvancedFilters: () => ({}) }).getRows(stubParams({}))
    // No getter at all behaves the same.
    await createSsrmDatasource('users').getRows(stubParams({}))

    for (const call of fetchRowsMock.mock.calls) {
      expect(call[1]).not.toHaveProperty('advancedFilters')
    }
  })

  // Spec 0158: the active custom filter rides alongside filterModel/search.
  it('includes customFilterRules from the getter when it returns non-null', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)
    const rules = { and: [{ field: 'status', operator: 'equals', value: 'open' }], or: [] }

    await createSsrmDatasource('users', { getCustomFilterRules: () => rules }).getRows(stubParams({}))

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'users',
      expect.objectContaining({ customFilterRules: rules }),
    )
  })

  it('omits customFilterRules entirely when the getter returns null or is absent', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)

    await createSsrmDatasource('users', { getCustomFilterRules: () => null }).getRows(stubParams({}))
    await createSsrmDatasource('users').getRows(stubParams({}))

    for (const call of fetchRowsMock.mock.calls) {
      expect(call[1]).not.toHaveProperty('customFilterRules')
    }
  })

  // Spec 0064 AC-020: the Gestione Richieste category tabs scope the rows
  // request.
  it('includes productCategoryId in the payload when given', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)

    await createSsrmDatasource('request-management', { productCategoryId: 12 }).getRows(stubParams({}))

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'request-management',
      expect.objectContaining({ productCategoryId: 12 }),
    )
  })

  it('omits productCategoryId entirely on the "Tutte" tab (no scope given)', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)

    await createSsrmDatasource('request-management').getRows(stubParams({}))

    expect(fetchRowsMock.mock.calls[0][1]).not.toHaveProperty('productCategoryId')
  })

  // Spec 0067 D-1/AC-030: the Opportunity detail's Quotes panel scopes the
  // rows request the same way productCategoryId does above.
  it('includes opportunityId in the payload when given', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)

    await createSsrmDatasource('quotes', { opportunityId: 7 }).getRows(stubParams({}))

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'quotes',
      expect.objectContaining({ opportunityId: 7 }),
    )
  })

  // AC-072: every existing caller (no rowScope) sends a byte-identical payload.
  it('omits opportunityId entirely when not given', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)

    await createSsrmDatasource('quotes').getRows(stubParams({}))

    expect(fetchRowsMock.mock.calls[0][1]).not.toHaveProperty('opportunityId')
  })

  // Spec 0157 D-1: server-side tree data (Task's "Sintetica" view).
  it('sends tree:true with no treeParentId at the root (empty groupKeys)', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)

    await createSsrmDatasource('tasks', { treeData: true }).getRows(stubParams({ groupKeys: [] }))

    expect(fetchRowsMock).toHaveBeenCalledWith('tasks', expect.objectContaining({ tree: true }))
    expect(fetchRowsMock.mock.calls[0][1]).not.toHaveProperty('treeParentId')
  })

  it('sends treeParentId from the LAST groupKeys entry when expanding a node', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)

    await createSsrmDatasource('tasks', { treeData: true }).getRows(stubParams({ groupKeys: ['42'] }))

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'tasks',
      expect.objectContaining({ tree: true, treeParentId: 42 }),
    )
  })

  it('omits tree/treeParentId entirely when treeData is off (every other domain)', async () => {
    fetchRowsMock.mockResolvedValue(EMPTY_RESPONSE)

    await createSsrmDatasource('users').getRows(stubParams({ groupKeys: ['1'] }))

    expect(fetchRowsMock.mock.calls[0][1]).not.toHaveProperty('tree')
    expect(fetchRowsMock.mock.calls[0][1]).not.toHaveProperty('treeParentId')
  })

  it('calls params.fail() when the request rejects', async () => {
    fetchRowsMock.mockRejectedValue(new Error('network error'))
    const params = stubParams({})

    await createSsrmDatasource('users').getRows(params)

    expect(params.fail).toHaveBeenCalled()
    expect(params.success).not.toHaveBeenCalled()
  })
})

describe('createSsrmDatasource knownTotal memory (spec 0178)', () => {
  const PAGE_RESPONSE = {
    items: [],
    export_link: null,
    pagination: { total: 60, offset: 0, limit: 25, total_pages: 3 },
    meta: { aggregates: { sum: 1 } },
  }
  const KNOWN_RESPONSE = {
    items: [],
    export_link: null,
    pagination: { total: 60, offset: 25, limit: 25, total_pages: 3 },
  }

  beforeEach(() => {
    fetchRowsMock.mockReset()
  })

  it('AC-005: block 0 has no knownTotal; block 1 with same signature sends it and reuses rowCount', async () => {
    fetchRowsMock.mockResolvedValueOnce(PAGE_RESPONSE)
    fetchRowsMock.mockResolvedValueOnce(KNOWN_RESPONSE)
    const ds = createSsrmDatasource('quotes')
    await ds.getRows(stubParams({}))
    const second = stubParams({ startRow: 25, endRow: 50 })
    await ds.getRows(second)

    expect(fetchRowsMock.mock.calls[0][1]).not.toHaveProperty('knownTotal')
    expect(fetchRowsMock.mock.calls[1][1]).toHaveProperty('knownTotal', 60)
    expect(second.success).toHaveBeenCalledWith({ rowData: [], rowCount: 60 })
  })

  it('AC-005: block 0 never sends knownTotal even when a memo exists', async () => {
    fetchRowsMock.mockResolvedValue(PAGE_RESPONSE)
    const ds = createSsrmDatasource('quotes')
    await ds.getRows(stubParams({}))
    await ds.getRows(stubParams({}))

    expect(fetchRowsMock.mock.calls[1][1]).not.toHaveProperty('knownTotal')
  })

  it('AC-006: changing only sortModel keeps knownTotal', async () => {
    fetchRowsMock.mockResolvedValue(PAGE_RESPONSE)
    const ds = createSsrmDatasource('quotes')
    await ds.getRows(stubParams({}))
    await ds.getRows(stubParams({ startRow: 25, endRow: 50, sortModel: [{ colId: 'id', sort: 'desc' }] }))

    expect(fetchRowsMock.mock.calls[1][1]).toHaveProperty('knownTotal', 60)
  })

  it('AC-005: a first request at startRow > 0 sends none but saves the total for the next block', async () => {
    fetchRowsMock.mockResolvedValueOnce(PAGE_RESPONSE)
    fetchRowsMock.mockResolvedValueOnce(KNOWN_RESPONSE)
    const ds = createSsrmDatasource('quotes')
    await ds.getRows(stubParams({ startRow: 25, endRow: 50 }))
    await ds.getRows(stubParams({ startRow: 50, endRow: 75 }))

    expect(fetchRowsMock.mock.calls[0][1]).not.toHaveProperty('knownTotal')
    expect(fetchRowsMock.mock.calls[1][1]).toHaveProperty('knownTotal', 60)
  })

  it.each([
    ['search', { getSearch: () => 'b' }],
    ['advancedFilters', { getAdvancedFilters: () => ({ status: [1] }) as never }],
    ['customFilterRules', { getCustomFilterRules: () => ({ combinator: 'and', rules: [] }) as never }],
  ] as const)('AC-006: a changed %s drops knownTotal', async (_name, changed) => {
    fetchRowsMock.mockResolvedValue(PAGE_RESPONSE)
    let current: Parameters<typeof createSsrmDatasource>[1] = { getSearch: () => 'a' }
    const holder: NonNullable<Parameters<typeof createSsrmDatasource>[1]> = {
      getSearch: () => (current?.getSearch ?? (() => 'a'))(),
      getAdvancedFilters: () => current?.getAdvancedFilters?.() ?? {},
      getCustomFilterRules: () => current?.getCustomFilterRules?.() ?? null,
    }
    const ds = createSsrmDatasource('quotes', holder)
    await ds.getRows(stubParams({}))
    current = { ...current, ...changed }
    await ds.getRows(stubParams({ startRow: 25, endRow: 50 }))

    expect(fetchRowsMock.mock.calls[1][1]).not.toHaveProperty('knownTotal')
  })

  it('AC-006: a changed filterModel drops knownTotal', async () => {
    fetchRowsMock.mockResolvedValue(PAGE_RESPONSE)
    const ds = createSsrmDatasource('quotes')
    await ds.getRows(stubParams({ filterModel: { a: { filter: 1 } } }))
    await ds.getRows(stubParams({ startRow: 25, endRow: 50, filterModel: { a: { filter: 2 } } }))

    expect(fetchRowsMock.mock.calls[1][1]).not.toHaveProperty('knownTotal')
  })

  it('AC-006: the same filterModel with a different key order keeps knownTotal', async () => {
    fetchRowsMock.mockResolvedValue(PAGE_RESPONSE)
    const ds = createSsrmDatasource('quotes')
    await ds.getRows(stubParams({ filterModel: { a: 1, b: 2 } }))
    await ds.getRows(stubParams({ startRow: 25, endRow: 50, filterModel: { b: 2, a: 1 } }))

    expect(fetchRowsMock.mock.calls[1][1]).toHaveProperty('knownTotal', 60)
  })

  it('AC-006: a changed tree parent drops knownTotal', async () => {
    fetchRowsMock.mockResolvedValue(PAGE_RESPONSE)
    const ds = createSsrmDatasource('tasks', { treeData: true })
    await ds.getRows(stubParams({ groupKeys: ['1'] }))
    await ds.getRows(stubParams({ startRow: 25, endRow: 50, groupKeys: ['2'] }))

    expect(fetchRowsMock.mock.calls[1][1]).not.toHaveProperty('knownTotal')
  })

  it('AC-006: a different scope (separate datasource instance) never shares the memory', async () => {
    fetchRowsMock.mockResolvedValue(PAGE_RESPONSE)
    await createSsrmDatasource('quotes', { productCategoryId: 1 }).getRows(stubParams({}))
    await createSsrmDatasource('quotes', { productCategoryId: 2 }).getRows(
      stubParams({ startRow: 25, endRow: 50 }),
    )

    expect(fetchRowsMock.mock.calls[1][1]).not.toHaveProperty('knownTotal')
  })

  it('AC-007: after resetKnownTotal() the next request with startRow > 0 has no knownTotal', async () => {
    fetchRowsMock.mockResolvedValue(PAGE_RESPONSE)
    const ds = createSsrmDatasource('quotes')
    await ds.getRows(stubParams({}))
    ds.resetKnownTotal()
    await ds.getRows(stubParams({ startRow: 25, endRow: 50 }))

    expect(fetchRowsMock.mock.calls[1][1]).not.toHaveProperty('knownTotal')
  })

  it('AC-008: a response without meta does not call onAggregates; a counted one does', async () => {
    fetchRowsMock.mockResolvedValueOnce(PAGE_RESPONSE)
    fetchRowsMock.mockResolvedValueOnce(KNOWN_RESPONSE)
    const onAggregates = vi.fn()
    const ds = createSsrmDatasource('quotes', { onAggregates })
    await ds.getRows(stubParams({}))
    expect(onAggregates).toHaveBeenCalledTimes(1)
    expect(onAggregates).toHaveBeenLastCalledWith({ sum: 1 })

    await ds.getRows(stubParams({ startRow: 25, endRow: 50 }))
    expect(onAggregates).toHaveBeenCalledTimes(1)
  })
})
