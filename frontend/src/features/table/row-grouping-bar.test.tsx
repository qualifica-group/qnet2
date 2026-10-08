import { beforeAll, describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, screen } from '@testing-library/react'
import { I18nextProvider } from 'react-i18next'
import type { GridApi } from 'ag-grid-community'
import i18n from '@/i18n'
import { RowGroupingBar } from '@/features/table/row-grouping-bar'
import { toggleRowGroupColumn } from '@/features/table/use-row-group-columns'
import type { TableColumn } from '@/features/table/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const ROW_GROUPING = { enabled: true, max_depth: 2, columns: ['customer', 'work_order', 'company'] }

function column(id: string, groupable = true): TableColumn {
  return { id, label: id, type: 'text', visible: true, width: null, order: 0, sortable: true, filterable: true, groupable }
}

const COLUMNS = [column('customer'), column('work_order'), column('company'), column('amount', false)]

/** Minimal grid double: keeps the row group columns and fires `columnRowGroupChanged` like AG Grid. */
function fakeGridApi(initial: string[] = []) {
  let grouped = initial
  const listeners = new Set<() => void>()
  const api = {
    isDestroyed: () => false,
    getRowGroupColumns: () => grouped.map((id) => ({ getColId: () => id })),
    setRowGroupColumns: vi.fn((ids: string[]) => {
      grouped = ids
      listeners.forEach((listener) => listener())
    }),
    addEventListener: (_: string, listener: () => void) => listeners.add(listener),
    removeEventListener: (_: string, listener: () => void) => listeners.delete(listener),
  }
  return api as typeof api & GridApi
}

/** Radix opens on pointerdown; a bare click leaves the menu closed in jsdom. */
function openMenu() {
  fireEvent.pointerDown(screen.getByRole('button', { name: 'Group by' }), { button: 0, ctrlKey: false })
}

function renderBar(api: GridApi) {
  return render(
    <I18nextProvider i18n={i18n}>
      <RowGroupingBar gridApi={api} columns={COLUMNS} rowGrouping={ROW_GROUPING} />
    </I18nextProvider>,
  )
}

describe('toggleRowGroupColumn', () => {
  it('appends a new level, removes an existing one and stops at the max depth', () => {
    expect(toggleRowGroupColumn([], 'customer', 2)).toEqual(['customer'])
    expect(toggleRowGroupColumn(['customer'], 'company', 2)).toEqual(['customer', 'company'])
    expect(toggleRowGroupColumn(['customer', 'company'], 'work_order', 2)).toEqual(['customer', 'company'])
    expect(toggleRowGroupColumn(['customer', 'company'], 'customer', 2)).toEqual(['company'])
  })
})

describe('RowGroupingBar (spec 0197)', () => {
  it('groups by the picked columns in selection order, listing only groupable ones', () => {
    const api = fakeGridApi()
    renderBar(api)

    expect(screen.getByText('No grouping')).toBeInTheDocument()
    openMenu()
    expect(screen.queryByRole('menuitemcheckbox', { name: 'amount' })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('menuitemcheckbox', { name: 'work_order' }))
    fireEvent.click(screen.getByRole('menuitemcheckbox', { name: 'customer' }))

    expect(api.setRowGroupColumns).toHaveBeenLastCalledWith(['work_order', 'customer'])
    expect(screen.getByRole('menuitemcheckbox', { name: 'company' })).toHaveAttribute('aria-disabled', 'true')
  })

  it('shows the active levels as removable chips and clears them', () => {
    const api = fakeGridApi(['customer', 'company'])
    renderBar(api)

    const levels = screen.getByRole('list', { name: 'Grouping levels' })
    expect(levels).toHaveTextContent('1.customer')
    expect(levels).toHaveTextContent('2.company')

    fireEvent.click(screen.getByRole('button', { name: 'Remove customer from grouping' }))
    expect(api.setRowGroupColumns).toHaveBeenLastCalledWith(['company'])

    fireEvent.click(screen.getByRole('button', { name: 'Clear grouping' }))
    expect(api.setRowGroupColumns).toHaveBeenLastCalledWith([])
    expect(screen.getByText('No grouping')).toBeInTheDocument()
  })

  it('follows levels changed elsewhere on the grid (columns tool panel)', () => {
    const api = fakeGridApi()
    renderBar(api)

    act(() => {
      api.setRowGroupColumns(['company'])
    })

    expect(screen.getByRole('list', { name: 'Grouping levels' })).toHaveTextContent('1.company')
  })
})
