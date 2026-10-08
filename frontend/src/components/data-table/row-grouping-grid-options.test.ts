import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import {
  buildRowGroupingGridOptions,
  resolveGroupingColumnProps,
  resolveRowId,
} from '@/components/data-table/row-grouping-grid-options'
import { buildSideBar } from '@/components/data-table/data-table-overlays'
import type { TableColumn, TableRow } from '@/features/table/types'

const t = ((key: string) => key) as unknown as TFunction
const CONFIG = { enabled: true, max_depth: 3, columns: ['customer'] }

function column(overrides: Partial<TableColumn>): TableColumn {
  return {
    id: 'customer',
    label: 'x',
    type: 'text',
    visible: true,
    width: null,
    order: 0,
    sortable: true,
    filterable: true,
    ...overrides,
  }
}

describe('row grouping grid options (spec 0197)', () => {
  it('is a no-op without an enabled row_grouping config', () => {
    expect(buildRowGroupingGridOptions(undefined, t)).toEqual({})
    expect(buildRowGroupingGridOptions({ ...CONFIG, enabled: false }, t)).toEqual({})
    expect(resolveGroupingColumnProps(column({ groupable: true }), undefined)).toEqual({})
  })

  it('shows the group panel and counts children from the group row', () => {
    const options = buildRowGroupingGridOptions(CONFIG, t)
    expect(options.rowGroupPanelShow).toBe('never')
    expect(options.getChildCount?.({ __child_count: 7 } as unknown as TableRow)).toBe(7)
  })

  it('makes only groupable columns draggable and seeds the aggregate function', () => {
    expect(resolveGroupingColumnProps(column({ groupable: true }), CONFIG)).toMatchObject({ enableRowGroup: true })
    expect(resolveGroupingColumnProps(column({ groupable: false }), CONFIG)).toMatchObject({ enableRowGroup: false })
    expect(resolveGroupingColumnProps(column({ id: 'amount', aggFunc: 'sum' }), CONFIG)).toMatchObject({
      initialAggFunc: 'sum',
    })
  })

  it('offers the row group section in the side bar only when grouping is on', () => {
    const params = (on: boolean) =>
      (buildSideBar(on).toolPanels?.[0] as { toolPanelParams: { suppressRowGroups: boolean } }).toolPanelParams
    expect(params(false).suppressRowGroups).toBe(true)
    expect(params(true).suppressRowGroups).toBe(false)
  })

  it('gives group rows an id from the key path and keeps leaf ids', () => {
    const leaf = { id: 5, actions: [] } as TableRow
    const group = { id: '42', actions: [], __group: true, __group_key: '42' } as TableRow
    expect(resolveRowId({ data: leaf, parentKeys: ['42'], level: 1 } as never)).toBe('5')
    expect(resolveRowId({ data: group, parentKeys: ['7'], level: 1 } as never)).toBe('group:7/42')
  })

  it('renders the localized unassigned label for the missing-value group', () => {
    const options = buildRowGroupingGridOptions(CONFIG, t)
    const innerRenderer = (options.autoGroupColumnDef?.cellRendererParams as {
      innerRenderer: (params: unknown) => string
    }).innerRenderer
    const group = (key: string, label: string | null) => ({ node: { data: { __group_key: key, __group_label: label } } })

    expect(innerRenderer(group('__null__', null))).toBe('table.grouping.unassigned')
    expect(innerRenderer(group('42', 'ACME Spa'))).toBe('ACME Spa')
  })
})
