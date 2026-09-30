import { beforeAll, describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { TableSummaryCell } from '@/components/data-table/table-summary-cell'
import { resolveCellRenderer } from '@/components/data-table/column-defaults'
import type { TableFieldConfig } from '@/features/custom-fields/types'
import type { TableColumn } from '@/features/table/types'

const TABLE: TableFieldConfig = {
  columns: [
    { key: 'inspection_date', label: 'Inspection date', type: 'date' },
    { key: 'outcome', label: 'Outcome', type: 'enum', options: [{ value: 'ok', label: 'Compliant' }] },
    { key: 'certified', label: 'Certified', type: 'boolean' },
    { key: 'note', label: 'Note', type: 'text' },
  ],
  selectable: { key: 'is_last', label: 'Last inspection' },
}

const ROWS = [
  { id: 'a', inspection_date: '2026-10-12', outcome: 'ok', certified: true, note: null, is_last: true },
  { id: 'b', inspection_date: '2026-01-05', outcome: null, certified: false, note: 'x', is_last: false },
]

function renderCell(value: unknown, table?: TableFieldConfig) {
  return render(<TableSummaryCell {...({ value } as unknown as ICellRendererParams)} table={table} />)
}

function visibleText(container: HTMLElement) {
  return container.querySelector('[aria-hidden="true"]')?.textContent
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('TableSummaryCell (spec 0180, AC-022)', () => {
  it('shows the selected row inline: local date, enum label, labelled boolean, empty cells omitted', () => {
    const { container } = renderCell({ rows: ROWS, summary: '2026-10-12' }, TABLE)

    expect(visibleText(container)).toBe('12/10/2026 · Compliant · Certified: Yes')
  })

  it('falls back to summary + count when no row is selected', () => {
    const rows = ROWS.map((row) => ({ ...row, is_last: false }))
    const { container } = renderCell({ rows, summary: '2026-10-12' }, TABLE)

    expect(visibleText(container)).toBe('12/10/2026 · 2')
  })

  it('shows only the row count when there is no summary and no selection', () => {
    const { container } = renderCell({ rows: [{ id: 'a' }, { id: 'b' }], summary: null })

    expect(visibleText(container)).toBe('2 rows')
  })

  it('degrades to summary + count without a tooltip when the definition is missing', () => {
    const { container } = renderCell({ rows: ROWS, summary: 'Passed' })

    expect(visibleText(container)).toBe('Passed · 2')
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
    expect(container.querySelector('[tabindex]')).toBeNull()
  })

  it('is empty for a null value and for zero rows', () => {
    expect(renderCell(null, TABLE).container).toBeEmptyDOMElement()
    expect(renderCell({ rows: [], summary: null }, TABLE).container).toBeEmptyDOMElement()
  })

  it('opens a read-only table of every row on keyboard focus', async () => {
    const { container } = renderCell({ rows: ROWS, summary: '2026-10-12' }, TABLE)

    fireEvent.focus(container.querySelector('[tabindex="0"]') as HTMLElement)

    const tooltip = await screen.findByRole('tooltip')
    expect(tooltip).toHaveTextContent('Inspection date')
    expect(tooltip).toHaveTextContent('05/01/2026')
    expect(tooltip).toHaveTextContent('Last inspection')
  })

  it('is the renderer picked for a dynamic table column only, carrying its definition', () => {
    const base = { id: 'custom.checks', label: 'c', visible: true, width: null, order: 0, sortable: true, filterable: true }
    const custom = { ...base, type: 'table', source: 'custom', table: TABLE } as TableColumn
    const native = { ...base, type: 'table' } as TableColumn

    expect(resolveCellRenderer(custom)).toBeTypeOf('function')
    expect(resolveCellRenderer(native)).toBeUndefined()
  })
})
