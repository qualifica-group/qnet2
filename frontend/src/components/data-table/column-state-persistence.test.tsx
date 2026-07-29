import { useState, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { GridApi, GridReadyEvent, IServerSideDatasource } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import { DataTable } from '@/components/data-table/data-table'
import { UiScaleContext } from '@/features/appearance/ui-scale-context'
import type { TableColumn } from '@/features/table/types'

/**
 * The user's column layout (manual width, drag-and-drop order, visibility) lives
 * in AG Grid, NOT in React state: it is read back from the grid api and only
 * then persisted. AG Grid re-applies the column definitions whenever the
 * `columnDefs`/`defaultColDef` identity changes — which a plain React re-render
 * can trigger — so the definitions must SEED the layout (`initial*`) instead of
 * asserting it, and `maintainColumnOrder` must be on. Without both, every
 * re-render silently reverted the width and order the user had just set, and the
 * debounced save then persisted the reverted layout.
 */

const SCALE = { scale: 50, factor: 1, setScale: () => {} }

function Providers({ children }: { children: ReactNode }) {
  return (
    <QueryClientProvider client={new QueryClient()}>
      <UiScaleContext.Provider value={SCALE}>{children}</UiScaleContext.Provider>
    </QueryClientProvider>
  )
}

const EMPTY_DATASOURCE: IServerSideDatasource = {
  getRows: (params) => params.success({ rowData: [], rowCount: 0 }),
}

function column(id: string, width: number | null, visible = true): TableColumn {
  return {
    id,
    label: id,
    type: 'text',
    visible,
    width,
    order: 0,
    sortable: true,
    filterable: false,
    filterType: null,
    hasFilterValues: false,
    editable: false,
    options: null,
  } as unknown as TableColumn
}

/** Mounts the grid inside a component that can be re-rendered on demand. */
function renderGrid(columns: TableColumn[]) {
  let api: GridApi | null = null

  function Host() {
    const [, setTick] = useState(0)
    return (
      <>
        <button type="button" onClick={() => setTick((tick) => tick + 1)}>
          rerender
        </button>
        <DataTable
          domain="users"
          columns={columns}
          datasource={EMPTY_DATASOURCE}
          blockSize={25}
          onGridReady={(event: GridReadyEvent) => {
            api = event.api
          }}
        />
      </>
    )
  }

  render(
    <Providers>
      <Host />
    </Providers>,
  )

  return () => api as GridApi
}

/** colId → {width, flex}, in the grid's current display order. */
function layoutOf(grid: GridApi) {
  return grid.getColumnState().map((state) => ({
    colId: state.colId,
    width: state.width,
    flex: state.flex,
  }))
}

describe('DataTable column state', () => {
  it('keeps a manual resize and reorder across a plain re-render', async () => {
    const getGrid = renderGrid([column('name', null), column('email', null), column('role', null)])
    await waitFor(() => expect(getGrid()).not.toBeNull())
    const grid = getGrid()

    grid.setColumnWidths([{ key: 'email', newWidth: 350 }], true, 'uiColumnResized')
    grid.moveColumnByIndex(1, 0)

    fireEvent.click(screen.getByRole('button', { name: 'rerender' }))

    expect(layoutOf(grid)).toEqual([
      // The drag put `email` first and widened it; a re-render must not undo it.
      // `flex: null` is what a manual resize leaves behind — restoring the
      // default `flex: 1` here is what used to snap the column back.
      { colId: 'email', width: 350, flex: null },
      { colId: 'name', width: 200, flex: 1 },
      { colId: 'role', width: 200, flex: 1 },
    ])
  })

  it('still seeds the grid from the backend layout on mount', async () => {
    const getGrid = renderGrid([
      column('name', null),
      column('email', 320),
      column('role', null, false),
    ])
    await waitFor(() => expect(getGrid()).not.toBeNull())
    const grid = getGrid()

    const email = grid.getColumnState().find((state) => state.colId === 'email')
    expect(email?.width).toBe(320)
    // A persisted width opts the column out of the flex layout.
    expect(email?.flex).toBe(0)
    expect(grid.getColumnState().find((state) => state.colId === 'role')?.hide).toBe(true)
  })
})
