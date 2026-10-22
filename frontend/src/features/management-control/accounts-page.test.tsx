import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { formatEuro } from '@/features/invoices/invoice-format'
import { makeAccountFlows } from '@/features/management-control/accounts-fixtures'
import type { AccountFlows } from '@/features/management-control/accounts-types'
import ManagementControlAccountsPage from '@/pages/management-control-accounts-page'

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

vi.mock('@/components/ui/stat-column-chart', () => ({
  StatColumnChart: ({ title, items }: { title: string; items: { key: string; label: string; value: number; color: string }[] }) => (
    <figure aria-label={title}>
      <ul>
        {items.map((item) => (
          <li key={item.key}>{`${item.label}: ${item.value} ${item.color}`}</li>
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

function mockApi(flows: AccountFlows = makeAccountFlows()) {
  getMock.mockImplementation((url: string) =>
    url.endsWith('/account-flows')
      ? Promise.resolve({ data: { success: true, message: 'OK', data: flows } })
      : Promise.resolve({ data: EMPTY_FOR_SELECT }),
  )
}

function flowsCalls() {
  return getMock.mock.calls.filter(([url]) => String(url).endsWith('/account-flows'))
}

function renderPage() {
  // One client per test: a per-render client would reset the cache on every render.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ManagementControlAccountsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

/** Compares the text content ignoring the non-breaking spaces that the currency formatter inserts. */
function expectText(element: HTMLElement | null, expected: string) {
  const plain = (value: string | null) => (value ?? '').replace(/\u00a0/g, ' ')
  expect(plain(element?.textContent ?? null)).toContain(plain(expected))
}

function cellTexts(row: HTMLElement) {
  return within(row).getAllByRole('cell').map((cell) => cell.textContent)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
  abilities.granted = new Set(['management-control.view'])
})

describe('ManagementControlAccountsPage', () => {
  it('AC-013 shows the three tiles: collections, payments and the net', async () => {
    mockApi()
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Totals of the period' })
    expect(within(totals).getAllByRole('term').map((term) => term.textContent)).toEqual(['Collections', 'Payments', 'Net'])
    expectText(within(totals).getByText('Collections').closest('div'), formatEuro(5000))
    expectText(within(totals).getByText('Payments').closest('div'), formatEuro(3200))
    expectText(within(totals).getByText('Net').closest('div'), formatEuro(1800))
  })

  it('AC-013 flags a negative net', async () => {
    const base = makeAccountFlows()
    mockApi(makeAccountFlows({ collections: { ...base.collections, total: 1000 } }))
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Totals of the period' })
    expectText(within(totals).getByText('Net').closest('div'), `Negative: ${formatEuro(-2200)}`)
  })

  it('AC-013 shows the collections by account with the translated type and the unattributed row', async () => {
    mockApi()
    renderPage()

    const table = await screen.findByRole('region', { name: 'Collections of the period by financial account' })
    expect(within(table).getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Account', 'Type', 'Amount', 'Installments',
    ])
    expect(cellTexts(within(table).getByRole('row', { name: /^Intesa Main/ }))).toEqual(['Bank account', formatEuro(3500), '2'])
    expect(cellTexts(within(table).getByRole('row', { name: /^Petty cash/ }))).toEqual(['Cash', formatEuro(1000), '1'])
    expect(cellTexts(within(table).getByRole('row', { name: /^Unattributed/ }))).toEqual(['-', formatEuro(500), '1'])
    expect(cellTexts(within(table).getByRole('row', { name: /^Total/ }))).toEqual(['', formatEuro(5000), '4'])
  })

  it('AC-013 shows the payments by account with the card type', async () => {
    mockApi()
    renderPage()

    const table = await screen.findByRole('region', { name: 'Payments of the period by financial account' })
    expect(cellTexts(within(table).getByRole('row', { name: /^Company card/ }))).toEqual(['Card', formatEuro(1200), '2'])
    expect(cellTexts(within(table).getByRole('row', { name: /^Total/ }))).toEqual(['', formatEuro(3200), '3'])
  })

  it('AC-013 draws collections against payments per month and the split by account, one colour per account', async () => {
    mockApi()
    renderPage()

    const months = await screen.findByRole('figure', { name: 'Collections and payments by month' })
    expect(within(months).getByText('Collections / Payments')).toBeInTheDocument()
    expect(within(months).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Aug 26: 1000 | 700',
      'Sep 26: 0 | 1500',
      'Oct 26: 4000 | 1000',
    ])
    const collections = screen.getByRole('figure', { name: 'Collections split by account' })
    expect(within(collections).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Intesa Main: 3500 var(--chart-1)',
      'Petty cash: 1000 var(--chart-2)',
      'Unattributed: 500 var(--chart-5)',
    ])
    expect(screen.getByRole('figure', { name: 'Payments split by account' })).toBeInTheDocument()
  })

  it('AC-013 labels the months and rows in the active language', async () => {
    await i18n.changeLanguage('it')
    try {
      mockApi()
      renderPage()

      const table = await screen.findByRole('region', { name: 'Incassi del periodo per conto finanziario' })
      expect(within(table).getByRole('row', { name: /^Non attribuito/ })).toBeInTheDocument()
      expect(within(table).getByRole('row', { name: /^Intesa Main/ })).toHaveTextContent('Conto bancario')
      const months = screen.getByRole('figure', { name: 'Incassi e pagamenti per mese' })
      expect(within(months).getByText(/^ago 26/)).toBeInTheDocument()
    } finally {
      cleanup()
      await i18n.changeLanguage('en')
    }
  })

  it('AC-013 shows the loading state, then the error with retry', async () => {
    getMock.mockImplementation((url: string) =>
      url.endsWith('/account-flows') ? Promise.reject(new Error('boom')) : Promise.resolve({ data: EMPTY_FOR_SELECT }),
    )
    renderPage()

    expect(screen.getByRole('status', { name: 'Loading accounts' })).toBeInTheDocument()
    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load accounts.')

    mockApi()
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(await screen.findByRole('region', { name: 'Totals of the period' })).toBeInTheDocument()
  })

  it('AC-013 shows the empty state with no collection and no payment', async () => {
    const base = makeAccountFlows()
    mockApi(makeAccountFlows({
      collections: { total: 0, installments: 0, by_account: [] },
      payments: { total: 0, installments: 0, by_account: [] },
      by_month: base.by_month.map((row) => ({ ...row, collected: 0, paid: 0, net: 0 })),
    }))
    renderPage()

    expect(await screen.findByText('No collection and no payment in the chosen period with these filters.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Totals of the period' })).not.toBeInTheDocument()
  })

  it('AC-013 without the permission shows the message and calls nothing', () => {
    abilities.granted = new Set()
    mockApi()
    renderPage()

    expect(screen.getByText('You do not have permission to view management control.')).toBeInTheDocument()
    expect(flowsCalls()).toHaveLength(0)
  })
})
