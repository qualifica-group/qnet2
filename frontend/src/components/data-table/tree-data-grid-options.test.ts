import { describe, expect, it } from 'vitest'
import type { TFunction } from 'i18next'
import { buildTreeDataGridOptions } from '@/components/data-table/tree-data-grid-options'
import type { TableColumn, TableRow } from '@/features/table/types'

const t = ((key: string) => key) as unknown as TFunction

const columns: TableColumn[] = [
  { id: 'title', label: 'tasks.columns.title', type: 'text', visible: true, width: null, order: 0, sortable: true, filterable: true },
]

describe('buildTreeDataGridOptions', () => {
  it('returns {} (no-op) when treeData is off, for every other domain', () => {
    expect(buildTreeDataGridOptions(undefined, undefined, columns, t)).toEqual({})
    expect(buildTreeDataGridOptions(false, 'title', columns, t)).toEqual({})
  })

  it('enables treeData collapsed by default, keyed on has_subtasks/id', () => {
    const options = buildTreeDataGridOptions(true, 'title', columns, t)

    expect(options.treeData).toBe(true)
    expect(options.groupDefaultExpanded).toBe(0)
    expect(options.isServerSideGroup?.({ id: 1, actions: [], has_subtasks: true } as TableRow)).toBe(true)
    expect(options.isServerSideGroup?.({ id: 1, actions: [], has_subtasks: false } as TableRow)).toBe(false)
    expect(options.getServerSideGroupKey?.({ id: 42, actions: [] } as TableRow)).toBe('42')
  })

  it('builds autoGroupColumnDef off the tree group column label', () => {
    const options = buildTreeDataGridOptions(true, 'title', columns, t)
    expect(options.autoGroupColumnDef?.field).toBe('title')
    expect(options.autoGroupColumnDef?.headerName).toBe('tasks.columns.title')
  })
})
