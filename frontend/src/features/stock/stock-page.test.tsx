import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import StockPage from '@/features/stock/stock-page'
import { buildMovement, stockWrapper } from '@/features/stock/stock-fixtures'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableRow } from '@/features/table/types'

const permissions = new Set<string>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => permissions.has(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: React.ReactNode }) => <header>{actions}</header>,
}))
vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/staff-trainings/picker-stub')).PickerStub,
}))
const fetchHistoryMock = vi.fn()
vi.mock('@/features/stock/api', () => ({
  fetchStockHistory: (params: unknown) => fetchHistoryMock(params),
  recordStockMovement: vi.fn(),
}))

/** Backend row of the grid: the server lists every action; the client narrows them per row. */
const ALL_ACTIONS = ['assign', 'return', 'transfer', 'unload', 'adjust', 'history']
const FREE_ROW: TableRow = {
  id: 1,
  product_id: 3,
  operational_site_id: 7,
  user_id: null,
  abilities: { manage: true },
  actions: ALL_ACTIONS,
}
const ASSIGNED_ROW: TableRow = { ...FREE_ROW, id: 2, user_id: 9 }
const READ_ONLY_ROW: TableRow = { ...FREE_ROW, id: 3, abilities: { manage: false } }

let rows: TableRow[] = []
vi.mock('@/features/table/table-view', () => ({
  TableView: ({
    domain,
    onAction,
    decorateRow,
  }: {
    domain: string
    onAction: RowActionHandler
    decorateRow?: (row: TableRow) => TableRow
  }) => (
    <div aria-label={`grid ${domain}`} role="region">
      {rows.map((row) => (
        <div key={row.id} role="group" aria-label={`row ${row.id}`}>
          {(decorateRow ? decorateRow(row) : row).actions.map((key) => (
            <button
              key={key}
              type="button"
              onClick={() => onAction({ key, label: key, icon: key, type: 'action', confirm: false }, row)}
            >
              {`${row.id}:${key}`}
            </button>
          ))}
        </div>
      ))}
    </div>
  ),
}))

const label = (key: string) => i18n.t(key)

function renderPage() {
  const { wrapper } = stockWrapper()
  return render(<StockPage />, { wrapper })
}

const actionKeys = (rowId: number) =>
  screen
    .getAllByRole('button')
    .map((button) => button.textContent ?? '')
    .filter((text) => text.startsWith(`${rowId}:`))
    .map((text) => text.split(':')[1])

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  permissions.clear()
  permissions.add('stock.viewAny')
  rows = [FREE_ROW, ASSIGNED_ROW, READ_ONLY_ROW]
  fetchHistoryMock.mockReset()
  fetchHistoryMock.mockResolvedValue([buildMovement()])
})

describe('StockPage (spec 0307 AC-017)', () => {
  it('shows a forbidden message without stock.viewAny', () => {
    permissions.clear()
    renderPage()

    expect(screen.getByText(label('stock.forbidden'))).toBeInTheDocument()
  })

  it('starts on the Giacenze tab with its grid and switches to the Movimenti grid', () => {
    renderPage()

    expect(screen.getByRole('tab', { name: label('stock.page.views.balances'), selected: true })).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'grid stock-balances' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('tab', { name: label('stock.page.views.movements') }))
    expect(screen.getByRole('region', { name: 'grid stock-movements' })).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'grid stock-balances' })).not.toBeInTheDocument()
  })

  it('without stock.manage hides Carico, Trasferimento and every writing action, but keeps Storico', () => {
    renderPage()

    expect(screen.queryByRole('button', { name: label('stock.page.load') })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('stock.page.transfer') })).not.toBeInTheDocument()
    expect(actionKeys(1)).toEqual(['history'])
  })

  it('with stock.manage offers Assegna only on free rows and Restituisci only on assigned rows, none on a row without abilities.manage', () => {
    permissions.add('stock.manage')
    renderPage()

    expect(actionKeys(1)).toEqual(['assign', 'transfer', 'unload', 'adjust', 'history'])
    expect(actionKeys(2)).toEqual(['return', 'transfer', 'unload', 'adjust', 'history'])
    expect(actionKeys(3)).toEqual(['history'])
  })

  it('opens the form prefilled from the row on a row action', async () => {
    permissions.add('stock.manage')
    renderPage()

    fireEvent.click(screen.getByRole('button', { name: '2:return' }))
    const sheet = await screen.findByRole('dialog', { name: label('stock.form.title') })
    expect(sheet).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: new RegExp(`^${label('stock.form.type')}`) })).toHaveTextContent(
      label('enums.stock_movement_type.RETURN'),
    )
  })

  it.each([
    ['stock.page.load', 'LOAD'],
    ['stock.page.transfer', 'TRANSFER'],
  ])('opens the form from the "%s" page button as a %s', async (key, type) => {
    permissions.add('stock.manage')
    renderPage()

    fireEvent.click(screen.getByRole('button', { name: label(key) }))
    expect(await screen.findByRole('dialog', { name: label('stock.form.title') })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: new RegExp(`^${label('stock.form.type')}`) })).toHaveTextContent(
      label(`enums.stock_movement_type.${type}`),
    )
  })

  it('opens the history of the row with the (product, site, user) of that row, also without stock.manage', async () => {
    renderPage()

    fireEvent.click(screen.getByRole('button', { name: '2:history' }))
    expect(await screen.findByText('Flavio Scognamiglio', { exact: false })).toBeInTheDocument()
    expect(fetchHistoryMock).toHaveBeenCalledWith({ product_id: 3, operational_site_id: 7, user_id: 9 })
  })

  it('asks the history of a free row without user_id', async () => {
    renderPage()

    fireEvent.click(screen.getByRole('button', { name: '1:history' }))
    await screen.findByText('Flavio Scognamiglio', { exact: false })
    expect(fetchHistoryMock).toHaveBeenCalledWith({ product_id: 3, operational_site_id: 7 })
  })
})
