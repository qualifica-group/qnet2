import { describe, expect, it, vi } from 'vitest'
import type { ICellRendererParams } from 'ag-grid-community'
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

  // User directive 2026-10-06: the group column stands in for the hidden
  // title, so it keeps the title's own renderer (the complete toggle).
  it("renders the group value with the tree group column's own renderer, when the domain has one", () => {
    const titleRenderer = vi.fn(() => 'rendered')
    const options = buildTreeDataGridOptions(true, 'title', columns, t, { title: titleRenderer })
    const params = { value: 'Alfa' } as ICellRendererParams

    expect(options.autoGroupColumnDef?.cellRendererParams.innerRenderer(params)).toBe('rendered')
    expect(titleRenderer).toHaveBeenCalledWith(params)
    expect(buildTreeDataGridOptions(true, 'title', columns, t).autoGroupColumnDef?.cellRendererParams.innerRenderer).toBeUndefined()
  })
})
