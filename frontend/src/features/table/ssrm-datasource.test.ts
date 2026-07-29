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

describe('createSsrmDatasource', () => {
  beforeEach(() => {
    fetchRowsMock.mockReset()
  })

  it('forwards a combined filterModel (including the multi shape) intact to fetchTableRows', async () => {
    fetchRowsMock.mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
    })
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
    fetchRowsMock.mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
    })
    const params = stubParams({ filterModel: null })

    await createSsrmDatasource('users').getRows(params)

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'users',
      expect.objectContaining({ filterModel: {} }),
    )
  })

  it('excludes the Advanced Filter model (array shape) from the request', async () => {
    fetchRowsMock.mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
    })
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
    fetchRowsMock.mockResolvedValue({
      items,
      export_link: null,
      pagination: { total: 1, offset: 0, limit: 25, total_pages: 1 },
    })
    const params = stubParams({})

    await createSsrmDatasource('users').getRows(params)

    expect(params.success).toHaveBeenCalledWith({ rowData: items, rowCount: 1 })
  })

  it('includes the trimmed search term from the getter when non-empty (spec 0009)', async () => {
    fetchRowsMock.mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
    })
    const params = stubParams({})

    await createSsrmDatasource('users', () => '  needle  ').getRows(params)

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'users',
      expect.objectContaining({ search: 'needle' }),
    )
  })

  it('omits `search` entirely when the getter returns an empty/blank term', async () => {
    fetchRowsMock.mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
    })

    await createSsrmDatasource('users', () => '   ').getRows(stubParams({}))
    // No getter at all behaves the same.
    await createSsrmDatasource('users').getRows(stubParams({}))

    for (const call of fetchRowsMock.mock.calls) {
      expect(call[1]).not.toHaveProperty('search')
    }
  })

  it('includes the applied advanced filters from the getter when non-empty (spec 0032)', async () => {
    fetchRowsMock.mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
    })
    const params = stubParams({})

    await createSsrmDatasource('users', undefined, () => ({ status: 'active' })).getRows(params)

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'users',
      expect.objectContaining({ advancedFilters: { status: 'active' } }),
    )
  })

  it('omits `advancedFilters` entirely when the getter returns an empty map', async () => {
    fetchRowsMock.mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
    })

    await createSsrmDatasource('users', undefined, () => ({})).getRows(stubParams({}))
    // No getter at all behaves the same.
    await createSsrmDatasource('users').getRows(stubParams({}))

    for (const call of fetchRowsMock.mock.calls) {
      expect(call[1]).not.toHaveProperty('advancedFilters')
    }
  })

  // Spec 0064 AC-020: the Gestione Richieste category tabs scope the rows
  // request.
  it('includes productCategoryId in the payload when given', async () => {
    fetchRowsMock.mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
    })

    await createSsrmDatasource('request-management', undefined, undefined, 12).getRows(stubParams({}))

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'request-management',
      expect.objectContaining({ productCategoryId: 12 }),
    )
  })

  it('omits productCategoryId entirely on the "Tutte" tab (no scope given)', async () => {
    fetchRowsMock.mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
    })

    await createSsrmDatasource('request-management').getRows(stubParams({}))

    expect(fetchRowsMock.mock.calls[0][1]).not.toHaveProperty('productCategoryId')
  })

  // Spec 0067 D-1/AC-030: the Opportunity detail's Quotes panel scopes the
  // rows request the same way productCategoryId does above.
  it('includes opportunityId in the payload when given', async () => {
    fetchRowsMock.mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
    })

    await createSsrmDatasource('quotes', undefined, undefined, undefined, 7).getRows(stubParams({}))

    expect(fetchRowsMock).toHaveBeenCalledWith(
      'quotes',
      expect.objectContaining({ opportunityId: 7 }),
    )
  })

  // AC-072: every existing caller (no rowScope) sends a byte-identical payload.
  it('omits opportunityId entirely when not given', async () => {
    fetchRowsMock.mockResolvedValue({
      items: [],
      export_link: null,
      pagination: { total: 0, offset: 0, limit: 25, total_pages: 0 },
    })

    await createSsrmDatasource('quotes').getRows(stubParams({}))

    expect(fetchRowsMock.mock.calls[0][1]).not.toHaveProperty('opportunityId')
  })

  it('calls params.fail() when the request rejects', async () => {
    fetchRowsMock.mockRejectedValue(new Error('network error'))
    const params = stubParams({})

    await createSsrmDatasource('users').getRows(params)

    expect(params.fail).toHaveBeenCalled()
    expect(params.success).not.toHaveBeenCalled()
  })
})
