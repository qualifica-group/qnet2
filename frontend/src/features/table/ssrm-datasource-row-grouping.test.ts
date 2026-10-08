import type { IServerSideGetRowsParams } from 'ag-grid-community'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createSsrmDatasource } from '@/features/table/ssrm-datasource'
import { fetchTableRows } from '@/features/table/api'
import type { TableRow, TableRowsResponse } from '@/features/table/types'

vi.mock('@/features/table/api', () => ({
  fetchTableRows: vi.fn(),
}))

const fetchRowsMock = vi.mocked(fetchTableRows)

const ROW_GROUPING = { maxDepth: 3, aggColumnIds: ['amount', 'residual_amount'] }

function stubParams(
  request: Partial<{
    rowGroupCols: { id: string }[]
    groupKeys: string[]
    sortModel: { colId: string; sort: 'asc' | 'desc' }[]
  }>,
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

const GROUP_RESPONSE: TableRowsResponse = {
  items: [
    {
      group: true,
      column: 'customer',
      key: '42',
      label: 'ACME Spa',
      child_count: 7,
      aggregates: { amount: '1200.00', residual_amount: '1000.00' },
    },
  ] as unknown as TableRow[],
  export_link: null,
  pagination: { total: 1, offset: 0, limit: 25, total_pages: 1 },
  meta: { aggregates: { amount: 1200 } },
}

describe('createSsrmDatasource row grouping (spec 0197)', () => {
  beforeEach(() => {
    fetchRowsMock.mockReset()
  })

  it('sends rowGroupCols and groupKeys and maps group items to group rows', async () => {
    fetchRowsMock.mockResolvedValue(GROUP_RESPONSE)
    const params = stubParams({ rowGroupCols: [{ id: 'customer' }, { id: 'work_order' }] })

    await createSsrmDatasource('invoice-installments', { rowGrouping: ROW_GROUPING }).getRows(params)

    expect(fetchRowsMock.mock.calls[0][1]).toMatchObject({
      rowGroupCols: ['customer', 'work_order'],
      groupKeys: [],
    })
    const success = vi.mocked(params.success).mock.calls[0][0]
    expect(success.rowCount).toBe(1)
    expect(success.rowData[0]).toMatchObject({
      customer: '42',
      __group: true,
      __group_label: 'ACME Spa',
      __child_count: 7,
      amount: 1200,
      residual_amount: 1000,
    })
  })

  it('sends the parent keys for a lower level and passes leaf rows through', async () => {
    const leaf = { id: 5, actions: [], amount: 10 }
    fetchRowsMock.mockResolvedValue({ ...GROUP_RESPONSE, items: [leaf] })
    const params = stubParams({ rowGroupCols: [{ id: 'customer' }], groupKeys: ['42'] })

    await createSsrmDatasource('invoice-installments', { rowGrouping: ROW_GROUPING }).getRows(params)

    expect(fetchRowsMock.mock.calls[0][1]).toMatchObject({ rowGroupCols: ['customer'], groupKeys: ['42'] })
    expect(vi.mocked(params.success).mock.calls[0][0].rowData).toEqual([leaf])
  })

  it('caps the grouped columns at the configured depth', async () => {
    fetchRowsMock.mockResolvedValue(GROUP_RESPONSE)
    const params = stubParams({
      rowGroupCols: [{ id: 'a' }, { id: 'b' }, { id: 'c' }, { id: 'd' }],
    })

    await createSsrmDatasource('invoice-installments', { rowGrouping: ROW_GROUPING }).getRows(params)

    expect(fetchRowsMock.mock.calls[0][1].rowGroupCols).toEqual(['a', 'b', 'c'])
  })

  it('maps the auto group column sort and drops sorts a group level cannot honour', async () => {
    fetchRowsMock.mockResolvedValue(GROUP_RESPONSE)
    const params = stubParams({
      rowGroupCols: [{ id: 'customer' }],
      sortModel: [
        { colId: 'ag-Grid-AutoColumn', sort: 'desc' },
        { colId: 'due_date', sort: 'asc' },
        { colId: 'amount', sort: 'asc' },
      ],
    })

    await createSsrmDatasource('invoice-installments', { rowGrouping: ROW_GROUPING }).getRows(params)

    expect(fetchRowsMock.mock.calls[0][1].sortModel).toEqual([
      { colId: 'customer', sort: 'desc' },
      { colId: 'amount', sort: 'asc' },
    ])
  })

  it('reports the footer aggregates only for the root level', async () => {
    fetchRowsMock.mockResolvedValue(GROUP_RESPONSE)
    const onAggregates = vi.fn()
    const datasource = createSsrmDatasource('invoice-installments', {
      rowGrouping: ROW_GROUPING,
      onAggregates,
    })

    await datasource.getRows(stubParams({ rowGroupCols: [{ id: 'customer' }], groupKeys: ['42'] }))
    expect(onAggregates).not.toHaveBeenCalled()

    await datasource.getRows(stubParams({ rowGroupCols: [{ id: 'customer' }] }))
    expect(onAggregates).toHaveBeenCalledWith({ amount: 1200 })
  })

  it('without row grouping config sends the flat payload untouched (no rowGroupCols/groupKeys)', async () => {
    fetchRowsMock.mockResolvedValue({ ...GROUP_RESPONSE, items: [] })

    await createSsrmDatasource('invoices').getRows(stubParams({ rowGroupCols: [{ id: 'customer' }] }))

    const payload = fetchRowsMock.mock.calls[0][1]
    expect(payload).not.toHaveProperty('rowGroupCols')
    expect(payload).not.toHaveProperty('groupKeys')
  })

  it('keeps tree data working: groupKeys still yield treeParentId and no rowGroupCols', async () => {
    fetchRowsMock.mockResolvedValue({ ...GROUP_RESPONSE, items: [] })

    await createSsrmDatasource('tasks', { treeData: true }).getRows(stubParams({ groupKeys: ['9'] }))

    const payload = fetchRowsMock.mock.calls[0][1]
    expect(payload).toMatchObject({ tree: true, treeParentId: 9 })
    expect(payload).not.toHaveProperty('rowGroupCols')
  })
})
