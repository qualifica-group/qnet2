import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { formatEuro } from '@/features/invoices/invoice-format'
import { makeCashFlow } from '@/features/management-control/cash-flow-fixtures'
import type { CashFlow } from '@/features/management-control/cash-flow-types'
import ManagementControlCashFlowPage from '@/pages/management-control-cash-flow-page'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const abilities = vi.hoisted(() => ({ granted: new Set<string>() }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => abilities.granted.has(permission), isLoading: false }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: () => null,
}))

// recharts has no layout in jsdom: the charts are covered by their own tests, here only their props matter.
vi.mock('@/components/ui/comparison-bar-chart', () => ({
  ComparisonBarChart: ({ title, points, seriesLabels }: {
    title: string
    points: { label: string; budget: number; actual: number }[]
    seriesLabels: { budget: string; actual: string }
  }) => (
    <figure aria-label={title}>
      <figcaption>{`${seriesLabels.budget} / ${seriesLabels.actual}`}</figcaption>
      <ul>
        {points.map((point) => (
          <li key={point.label}>{`${point.label}: ${point.budget} / ${point.actual}`}</li>
        ))}
      </ul>
    </figure>
  ),
}))

vi.mock('@/components/ui/stat-chart', () => ({
  StatChart: ({ title, points }: { title: string; points: { label: string; value: number }[] }) => (
    <figure aria-label={title}>
      <ul>
        {points.map((point) => (
          <li key={point.label}>{`${point.label}: ${point.value}`}</li>
        ))}
      </ul>
    </figure>
  ),
}))

const getMock = vi.mocked(apiClient.get)

const EMPTY_FOR_SELECT = {
  items: [],
  export_link: null,
  pagination: { total: 0, offset: 0, limit: 25, total_pages: 0, has_more: false },
}

const ZERO_FLOW = { receivable: 0, payable: 0, committed: 0, net: 0 }

function mockApi(cashFlow: CashFlow = makeCashFlow()) {
  getMock.mockImplementation((url: string) =>
    url.endsWith('/cash-flow')
      ? Promise.resolve({ data: { success: true, message: 'OK', data: cashFlow } })
      : Promise.resolve({ data: EMPTY_FOR_SELECT }),
  )
}

function cashFlowCalls() {
  return getMock.mock.calls.filter(([url]) => String(url).endsWith('/cash-flow'))
}

function renderPage() {
  // One client per test: a per-render client would reset the cache on every render.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ManagementControlCashFlowPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

const TABLE_NAME = 'Cash flow by month: backlog, months of the horizon and beyond'

async function findTable() {
  return screen.findByRole('region', { name: TABLE_NAME })
}

function cellsOf(table: HTMLElement, rowName: RegExp) {
  return within(within(table).getByRole('row', { name: rowName })).getAllByRole('cell')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
  abilities.granted = new Set(['management-control.view'])
})

describe('ManagementControlCashFlowPage', () => {
  it('AC-014 shows the six tiles with the net backlog, the horizon figures and the balance with scenario', async () => {
    mockApi()
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Cash flow totals' })
    expect(within(totals).getAllByRole('term')).toHaveLength(6)
    const expected: [string, number][] = [
      ['Net backlog', 200],
      ['Receipts in the horizon', 1600],
      ['Payments in the horizon', 800],
      ['Commitments in the horizon', 100],
      ['Net balance in the horizon', 700],
      ['Net balance with scenario', 900],
    ]
    for (const [label, value] of expected) {
      expect(within(totals).getByText(label).closest('div')?.textContent).toContain(formatEuro(value))
    }
  })

  it('AC-014 shows the table with backlog, translated months and beyond, and the five rows', async () => {
    mockApi()
    renderPage()

    const table = await findTable()
    expect(within(table).getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Item', 'Backlog', 'Oct 26', 'Nov 26', 'Dec 26', 'Beyond',
    ])
    const rows = within(table).getAllByRole('row').slice(1)
    expect(rows.map((row) => within(row).getByRole('rowheader').textContent)).toEqual([
      'Receipts', 'Payments', 'Commitments', 'Net', 'Cumulative',
    ])
    expect(cellsOf(table, /^Receipts/).map((cell) => cell.textContent)).toEqual(
      [500, 1000, 0, 600, 200].map((value) => formatEuro(value)),
    )
    expect(cellsOf(table, /^Cumulative/)[3]?.textContent).toBe(formatEuro(700))
  })

  it('AC-014 the scenario switch moves the backlog onto the first month and changes the cumulative', async () => {
    mockApi()
    renderPage()
    const table = await findTable()
    const scenario = screen.getByRole('switch', { name: 'Backlog collected and paid within 30 days' })
    expect(scenario).not.toBeChecked()

    fireEvent.click(scenario)

    expect(scenario).toBeChecked()
    expect(cellsOf(table, /^Receipts/).map((cell) => cell.textContent)).toEqual(
      [0, 1500, 0, 600, 200].map((value) => formatEuro(value)),
    )
    expect(cellsOf(table, /^Net/)[1]?.textContent).toBe(formatEuro(700))
    expect(cellsOf(table, /^Cumulative/).slice(1, 4).map((cell) => cell.textContent)).toEqual(
      [700, 400, 900].map((value) => formatEuro(value)),
    )
    const totals = screen.getByRole('region', { name: 'Cash flow totals' })
    expect(within(totals).getByText('Net backlog').closest('div')?.textContent).toContain(formatEuro(200))
  })

  it('AC-014 shows the chart of receipts against payments and the cumulative chart', async () => {
    mockApi()
    renderPage()

    const bars = await screen.findByRole('figure', { name: 'Receipts and payments by month' })
    expect(within(bars).getByText('Receipts / Payments and commitments')).toBeInTheDocument()
    expect(within(bars).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Backlog: 500 / 300',
      'Oct 26: 1000 / 500',
      'Nov 26: 0 / 300',
      'Dec 26: 600 / 100',
      'Beyond: 200 / 50',
    ])
    const line = screen.getByRole('figure', { name: 'Cumulative net by month' })
    expect(within(line).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Oct 26: 500',
      'Nov 26: 200',
      'Dec 26: 700',
    ])
  })

  it('AC-014 shows the net by company with a translated unattributed row and the total line', async () => {
    mockApi()
    renderPage()

    const byCompany = await screen.findByRole('region', { name: 'Net cash flow by company and month' })
    const acme = within(byCompany).getByRole('row', { name: /Acme Spa/ })
    expect(within(acme).getAllByRole('cell').at(-1)?.textContent).toBe(formatEuro(850))
    expect(within(byCompany).getByRole('row', { name: /Unattributed/ })).toBeInTheDocument()
    const total = within(byCompany).getByRole('row', { name: /^Total/ })
    expect(within(total).getAllByRole('cell').map((cell) => cell.textContent)).toEqual(
      [200, 500, -300, 500, 150, 1050].map((value) => (value < 0 ? `Negative: ${formatEuro(value)}` : formatEuro(value))),
    )
  })

  it('AC-014 requests the contract parameters, then a filter change refetches', async () => {
    mockApi()
    renderPage()
    await findTable()

    const [url, config] = cashFlowCalls()[0] ?? []
    expect(url).toBe('/management-control/cash-flow')
    expect(config).toMatchObject({ params: { document_type: 'all', horizon_months: 6 } })

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Document type' }), { key: 'ArrowDown' })
    fireEvent.click(await screen.findByRole('option', { name: 'Invoices' }))

    await waitFor(() =>
      expect(
        cashFlowCalls().some(([, c]) => (c as { params: { document_type?: string } }).params.document_type === 'invoice'),
      ).toBe(true),
    )
  })

  it('AC-014 renders the empty state when nothing is open or committed', async () => {
    mockApi(makeCashFlow({
      backlog: ZERO_FLOW,
      months: makeCashFlow().months.map((month) => ({ ...month, ...ZERO_FLOW, cumulative: 0 })),
      beyond: ZERO_FLOW,
      by_company: [],
    }))
    renderPage()

    expect(await screen.findByText('No open installments or commitments with these filters.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Cash flow totals' })).not.toBeInTheDocument()
  })

  it('AC-014 renders the loading state, then an error with retry that refetches', async () => {
    getMock.mockImplementation((url: string) =>
      url.endsWith('/cash-flow') ? Promise.reject(new Error('boom')) : Promise.resolve({ data: EMPTY_FOR_SELECT }),
    )
    renderPage()

    expect(screen.getByRole('status', { name: 'Loading the cash flow' })).toBeInTheDocument()
    const alert = await screen.findByText('Unable to load the cash flow.')
    const before = cashFlowCalls().length

    fireEvent.click(within(alert.closest('[role="alert"]') as HTMLElement).getByRole('button', { name: 'Retry' }))

    await waitFor(() => expect(cashFlowCalls().length).toBeGreaterThan(before))
  })

  it('AC-014 without the permission shows the notice and calls no API', () => {
    abilities.granted = new Set()
    renderPage()

    expect(screen.getByText('You do not have permission to view management control.')).toBeInTheDocument()
    expect(getMock).not.toHaveBeenCalled()
  })
})
