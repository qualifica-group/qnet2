import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildColDefs } from '@/components/data-table/column-def-builder'
import { ACTIONS_COLUMN_ID } from '@/components/data-table/data-table-overlays'

const t = ((key: string) => key) as unknown as TFunction

function actionsWidth(options: { actionsColumnHasOverflow?: boolean; actionsColumnWidth?: number }) {
  const defs = buildColDefs({
    domain: 'notifications',
    columns: [],
    renderRowActions: () => null,
    t,
    ...options,
  })
  return defs.find((def) => def.colId === ACTIONS_COLUMN_ID)?.width
}

describe('buildColDefs actions column width', () => {
  it('keeps the narrow default, widened only for the overflow button', () => {
    expect(actionsWidth({})).toBe(100)
    expect(actionsWidth({ actionsColumnHasOverflow: true })).toBe(120)
  })

  it('uses an explicit width (labeled row actions, spec 0150 D-8) over the default', () => {
    expect(actionsWidth({ actionsColumnWidth: 190, actionsColumnHasOverflow: true })).toBe(190)
  })
})

// Spec 0157 D-1: tree data forces the tree/expand column's own flat ColDef
// hidden, so it never shows twice (once inside AG Grid's `autoGroupColumnDef`,
// once as its own column).
describe('buildColDefs tree data', () => {
  const columns = [
    { id: 'title', label: 'tasks.columns.title', type: 'text' as const, visible: true, width: null, order: 0, sortable: true, filterable: true },
    { id: 'status', label: 'tasks.columns.status', type: 'text' as const, visible: true, width: null, order: 1, sortable: true, filterable: true },
  ]

  it('force-hides only the designated tree group column', () => {
    const defs = buildColDefs({ domain: 'tasks', columns, treeGroupColumnId: 'title', t })
    expect(defs.find((def) => def.colId === 'title')?.initialHide).toBe(true)
    expect(defs.find((def) => def.colId === 'status')?.initialHide).toBe(false)
  })

  it('leaves every column visible as usual when no tree group column is given', () => {
    const defs = buildColDefs({ domain: 'tasks', columns, t })
    expect(defs.find((def) => def.colId === 'title')?.initialHide).toBe(false)
  })
})
