import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { formatEuro } from '@/features/invoices/invoice-format'
import { makeAgents } from '@/features/management-control/agents-fixtures'
import type { Agents } from '@/features/management-control/agents-types'
import ManagementControlAgentsPage from '@/pages/management-control-agents-page'

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
          <li key={point.label}>{`${point.label}: ${point.budget} | ${point.actual}`}</li>
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

function mockApi(agents: Agents = makeAgents()) {
  getMock.mockImplementation((url: string) =>
    url.endsWith('/agents')
      ? Promise.resolve({ data: { success: true, message: 'OK', data: agents } })
      : Promise.resolve({ data: EMPTY_FOR_SELECT }),
  )
}

function agentsCalls() {
  return getMock.mock.calls.filter(([url]) => String(url).endsWith('/agents'))
}

function renderPage() {
  // One client per test: a per-render client would reset the cache on every render.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ManagementControlAgentsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

/** Compares the text content ignoring the non-breaking spaces that the currency formatter inserts. */
function expectText(element: HTMLElement | null, expected: string) {
  const plain = (value: string | null) => (value ?? '').replace(/00a0/g, ' ')
  expect(plain(element?.textContent ?? null)).toContain(plain(expected))
}

function cellTexts(row: HTMLElement) {
  return within(row).getAllByRole('cell').map((cell) => (cell.textContent ?? '').replace(/00a0/g, ' '))
}

const CAPTION = 'Commissions by agent: accrued, to settle, in statement, approved and paid'
const CAPTION_IT = 'Provvigioni per agente: maturato, da liquidare, in estratto conto, approvato e pagato'

function euros(...values: number[]) {
  return values.map((value) => formatEuro(value).replace(/00a0/g, ' '))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
  abilities.granted = new Set(['management-control.view'])
})

describe('ManagementControlAgentsPage', () => {
  it('AC-010 shows the six tiles, the in-statement one with the documents awaited', async () => {
    mockApi()
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Totals of the period' })
    expect(within(totals).getAllByRole('term').map((term) => term.textContent)).toEqual([
      'Accrued', 'To settle', 'In statement', 'Approved, unpaid', 'Paid', 'Agents',
    ])
    expectText(within(totals).getByText('Accrued').closest('div'), formatEuro(9000))
    expectText(within(totals).getByText('To settle').closest('div'), formatEuro(4000))
    const inStatement = within(totals).getByText('In statement').closest('div')
    expectText(inStatement, formatEuro(3000))
    expectText(inStatement, '3 awaiting document')
    expectText(within(totals).getByText('Approved, unpaid').closest('div'), formatEuro(1500))
    expectText(within(totals).getByText('Paid').closest('div'), formatEuro(2500))
    expect(within(totals).getByText('Agents', { selector: 'dt' }).closest('div')).toHaveTextContent('4')
  })

  it('AC-010 shows the agents, the others row and the total, with the pending documents', async () => {
    mockApi()
    renderPage()

    const table = await screen.findByRole('region', { name: CAPTION })
    expect(within(table).getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Agent', 'To settle', 'Accrued', 'In statement', 'Awaiting document', 'Approved, unpaid', 'Paid',
    ])
    expect(cellTexts(within(table).getByRole('row', { name: /^Mario Rossi/ }))).toEqual([...euros(2500, 4000, 1800), '1', ...euros(500, 1000)])
    expect(cellTexts(within(table).getByRole('row', { name: /^Anna Bianchi/ }))).toEqual([...euros(1000, 2000, 1200), '1', ...euros(700, 500)])
    expect(cellTexts(within(table).getByRole('row', { name: /^Others \(2 agents\)/ }))).toEqual([...euros(500, 3000, 0), '1', ...euros(300, 1000)])
    expect(cellTexts(within(table).getByRole('row', { name: /^Total/ }))).toEqual([...euros(4000, 9000, 3000), '3', ...euros(1500, 2500)])
  })

  it('AC-010 keeps the first column sticky and the table in a focusable region', async () => {
    mockApi()
    renderPage()

    const table = await screen.findByRole('region', { name: CAPTION })
    expect(table).toHaveAttribute('tabindex', '0')
    expect(within(table).getByRole('rowheader', { name: 'Mario Rossi' })).toHaveClass('sticky', 'left-0')
  })

  it('AC-010 omits the others row when no agent is left out', async () => {
    mockApi(makeAgents({ others: null }))
    renderPage()

    const table = await screen.findByRole('region', { name: CAPTION })
    expect(within(table).queryByRole('row', { name: /^Others/ })).not.toBeInTheDocument()
  })

  it('AC-010 flags a negative accrual with the minus sign and the accessible prefix', async () => {
    const base = makeAgents()
    mockApi(makeAgents({ totals: { ...base.totals, accrued: -250 } }))
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Totals of the period' })
    expectText(within(totals).getByText('Accrued').closest('div'), `Negative: ${formatEuro(-250)}`)
  })

  it('AC-010 draws accrued against paid per agent and per month', async () => {
    mockApi()
    renderPage()

    const agents = await screen.findByRole('figure', { name: 'Accrued and paid by agent' })
    expect(within(agents).getByText('Accrued / Paid')).toBeInTheDocument()
    expect(within(agents).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Mario Rossi: 4000 | 1000',
      'Anna Bianchi: 2000 | 500',
      'Others (2 agents): 3000 | 1000',
    ])
    const months = screen.getByRole('figure', { name: 'Accrued and paid by month' })
    expect(within(months).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Aug 26: 3000 | 500',
      'Sep 26: 2000 | 1000',
      'Oct 26: 4000 | 1000',
    ])
  })

  it('AC-010 requests the contract parameters with the default filters and reads data.data', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Totals of the period' })

    const year = new Date().getFullYear()
    const [url, config] = agentsCalls()[0] ?? []
    expect(url).toBe('/management-control/agents')
    expect(config).toMatchObject({ params: { date_from: `${year}-01-01`, top: 20 } })
  })

  it('AC-010 exposes the period, the company and the top by accessible role, and a change refetches', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Totals of the period' })

    expect(screen.getByLabelText('Accruals and payments from')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Company' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Agents shown' })).toHaveTextContent('Top 20')

    fireEvent.change(screen.getByLabelText('Accruals and payments from'), { target: { value: '2026-02-01' } })

    await waitFor(() =>
      expect(agentsCalls().some(([, c]) => (c as { params: { date_from?: string } }).params.date_from === '2026-02-01')).toBe(true),
    )
  })

  it('AC-010 choosing a different top refetches with it', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Totals of the period' })

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Agents shown' }), { key: 'ArrowDown' })
    fireEvent.click(await screen.findByRole('option', { name: 'Top 50' }))

    await waitFor(() =>
      expect(agentsCalls().some(([, c]) => (c as { params: { top?: number } }).params.top === 50)).toBe(true),
    )
  })

  it('AC-010 does not call the server while a date of the period is missing', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Totals of the period' })
    const before = agentsCalls().length

    fireEvent.change(screen.getByLabelText('to'), { target: { value: '' } })

    await waitFor(() => expect(screen.queryByRole('status')).not.toBeInTheDocument())
    expect(agentsCalls().length).toBe(before)
  })

  it('AC-010 labels months and rows in the active language', async () => {
    await i18n.changeLanguage('it')
    try {
      mockApi()
      renderPage()

      const table = await screen.findByRole('region', { name: CAPTION_IT })
      expect(within(table).getByRole('row', { name: /^Altri \(2 agenti\)/ })).toBeInTheDocument()
      const months = screen.getByRole('figure', { name: 'Maturato e pagato per mese' })
      expect(within(months).getByText(/^ago 26/)).toBeInTheDocument()
    } finally {
      cleanup()
      await i18n.changeLanguage('en')
    }
  })

  it('AC-010 shows the loading state, then the error with retry', async () => {
    getMock.mockImplementation((url: string) =>
      url.endsWith('/agents') ? Promise.reject(new Error('boom')) : Promise.resolve({ data: EMPTY_FOR_SELECT }),
    )
    renderPage()

    expect(screen.getByRole('status', { name: 'Loading the agents' })).toBeInTheDocument()
    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the agents.')

    mockApi()
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(await screen.findByRole('region', { name: 'Totals of the period' })).toBeInTheDocument()
  })

  it('AC-010 shows the empty state with no agent', async () => {
    const base = makeAgents()
    mockApi(makeAgents({
      agents: [],
      others: null,
      totals: { ...base.totals, accrued: 0, to_settle: 0, in_statement: 0, approved_unpaid: 0, paid: 0, agents_count: 0, pending_document_count: 0 },
    }))
    renderPage()

    expect(
      await screen.findByText('No commission accrued, in a statement or paid in the selected period with these filters.'),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Totals of the period' })).not.toBeInTheDocument()
  })

  it('AC-010 without the permission shows the message and calls nothing', () => {
    abilities.granted = new Set()
    mockApi()
    renderPage()

    expect(screen.getByText('You do not have permission to view management control.')).toBeInTheDocument()
    expect(agentsCalls()).toHaveLength(0)
  })
})
