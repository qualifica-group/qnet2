import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { formatEuro } from '@/features/invoices/invoice-format'
import { makeMonthlyTrend } from '@/features/management-control/trend-fixtures'
import type { MonthlyTrend } from '@/features/management-control/trend-types'
import ManagementControlTrendPage from '@/pages/management-control-trend-page'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const abilities = vi.hoisted(() => ({ granted: new Set<string>() }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => abilities.granted.has(permission), isLoading: false }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ title }: { title?: string }) => <h1>{title}</h1>,
}))

// recharts has no layout in jsdom: the chart is covered by its own test, here only its props matter.
vi.mock('@/components/ui/trend-line-chart', () => ({
  TrendLineChart: ({ title, months, series }: {
    title: string
    months: string[]
    series: { key: string; label: string; values: (number | null)[] }[]
  }) => (
    <figure aria-label={title}>
      <figcaption>{months.join(', ')}</figcaption>
      <ul>
        {series.map((line) => (
          <li key={line.key}>{`${line.label}: ${line.values.join(' | ')}`}</li>
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

function mockApi(trend: MonthlyTrend = makeMonthlyTrend()) {
  getMock.mockImplementation((url: string) =>
    url.endsWith('/monthly-trend')
      ? Promise.resolve({ data: { success: true, message: 'OK', data: trend } })
      : Promise.resolve({ data: EMPTY_FOR_SELECT }),
  )
}

function trendCalls() {
  return getMock.mock.calls.filter(([url]) => String(url).endsWith('/monthly-trend'))
}

function renderPage() {
  // One client per test: a per-render client would reset the cache on every render.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ManagementControlTrendPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
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

describe('ManagementControlTrendPage', () => {
  it('AC-015 shows the three tiles with the previous year and the change, n/a when the previous year is zero', async () => {
    mockApi()
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Totals of the period' })
    expect(within(totals).getAllByRole('term')).toHaveLength(3)
    const revenue = within(totals).getByText('Revenue').closest('div') as HTMLElement
    expect(revenue.textContent).toContain(formatEuro(6200))
    expect(revenue.textContent).toContain(`Previous year: ${formatEuro(2100)}`)
    expect(revenue).toHaveTextContent('+195.2% vs previous year')
    expect(within(totals).getByText('Margin').closest('div')).toHaveTextContent('+109.5% vs previous year')
    const costs = within(totals).getByText('Costs').closest('div') as HTMLElement
    expect(costs.textContent).toContain(formatEuro(1800))
    expect(costs).toHaveTextContent('n/a vs previous year')
  })

  it('AC-015 shows functions by month with translated structure and unattributed rows and a total column', async () => {
    mockApi()
    renderPage()

    const table = await screen.findByRole('region', { name: 'Revenue by business function and month' })
    expect(within(table).getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Business function', 'Aug 26', 'Sep 26', 'Oct 26', 'Total',
    ])
    expect(within(table).getByRole('row', { name: /^Engineering/ })).toBeInTheDocument()
    expect(within(table).getByRole('row', { name: /^Structure/ })).toBeInTheDocument()
    expect(within(table).getByRole('row', { name: /^Unattributed/ })).toBeInTheDocument()
    expect(cellTexts(within(table).getByRole('row', { name: /^Engineering/ }))).toEqual(
      [1000, 2000, 3000, 6000].map((value) => formatEuro(value)),
    )
  })

  it('AC-015 closes the table with the totals, the previous year and the change per month', async () => {
    mockApi()
    renderPage()

    const table = await screen.findByRole('region', { name: 'Revenue by business function and month' })
    expect(cellTexts(within(table).getByRole('row', { name: /^Total/ }))).toEqual(
      [1200, 2000, 3000, 6200].map((value) => formatEuro(value)),
    )
    expect(cellTexts(within(table).getByRole('row', { name: /^Previous year/ }))).toEqual(
      [600, 0, 1500, 2100].map((value) => formatEuro(value)),
    )
    expect(cellTexts(within(table).getByRole('row', { name: /^Change %/ }))).toEqual(['+100.0%', 'n/a', '+100.0%', '+195.2%'])
  })

  it('AC-015 the measure selector switches the table and the charts', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Revenue by business function and month' })
    expect(screen.getByRole('radio', { name: 'Revenue' })).toBeChecked()

    fireEvent.click(screen.getByRole('radio', { name: 'Costs' }))

    const table = screen.getByRole('region', { name: 'Costs by business function and month' })
    expect(cellTexts(within(table).getByRole('row', { name: /^Engineering/ }))).toEqual(
      [400, 500, 600, 1500].map((value) => formatEuro(value)),
    )
    expect(screen.getByRole('figure', { name: 'Costs by business function' })).toBeInTheDocument()
  })

  it('AC-015 draws one line per function and the totals against the previous year, with translated months', async () => {
    mockApi()
    renderPage()

    const lines = await screen.findByRole('figure', { name: 'Revenue by business function' })
    expect(within(lines).getByText('Aug 26, Sep 26, Oct 26')).toBeInTheDocument()
    expect(within(lines).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Engineering: 1000 | 2000 | 3000',
      'Structure: 0 | 0 | 0',
      'Unattributed: 200 | 0 | 0',
    ])
    const comparison = screen.getByRole('figure', { name: 'Revenue: total and previous year' })
    expect(within(comparison).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Selected period: 1200 | 2000 | 3000',
      'Previous year: 600 | 0 | 1500',
    ])
  })

  it('AC-015 shows the costs by nature with their share of revenue, n/a where there is none', async () => {
    mockApi(makeMonthlyTrend({
      by_nature: [
        { nature: 'direct', cost: [300, 400, 500], share_of_revenue: [25, 20, null] },
        { nature: null, cost: [200, 200, 200], share_of_revenue: [16.7, 10, 6.7] },
      ],
    }))
    renderPage()

    const table = await screen.findByRole('region', { name: 'Costs by nature and month, with share of revenue' })
    const direct = within(table).getByRole('row', { name: /^Direct/ })
    expect(direct).toHaveTextContent('25.0%')
    expect(direct).toHaveTextContent('20.0%')
    expect(direct).toHaveTextContent('n/a')
    expect(direct).toHaveTextContent('19.4%')
    expect(within(table).getByRole('row', { name: /^No cost type/ })).toHaveTextContent('16.7%')
    expect(cellTexts(within(table).getByRole('row', { name: /^Total costs/ }))).toEqual(
      [500, 600, 700, 1800].map((value) => formatEuro(value)),
    )
    expect(cellTexts(within(table).getByRole('row', { name: /^Revenue/ }))).toEqual(
      [1200, 2000, 3000, 6200].map((value) => formatEuro(value)),
    )
  })

  it('AC-015 labels months and rows in the active language', async () => {
    await i18n.changeLanguage('it')
    try {
      mockApi()
      renderPage()

      const table = await screen.findByRole('region', { name: 'Fatturato per funzione aziendale e mese' })
      expect(within(table).getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
        'Funzione aziendale', 'ago 26', 'set 26', 'ott 26', 'Totale',
      ])
      expect(within(table).getByRole('row', { name: /^Struttura/ })).toBeInTheDocument()
      expect(within(table).getByRole('row', { name: /^Non attribuito/ })).toBeInTheDocument()
    } finally {
      // Unmount before switching back, so the language change does not re-render a mounted tree.
      cleanup()
      await i18n.changeLanguage('en')
    }
  })

  it('AC-015 requests a 12-month window by default, then a period change refetches', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Totals of the period' })

    const [url, config] = trendCalls()[0] ?? []
    expect(url).toBe('/management-control/monthly-trend')
    const params = (config as { params: { date_from: string; date_to: string } }).params
    expect(params.date_from).toMatch(/^\d{4}-\d{2}-01$/)
    expect(params.date_to).toMatch(/^\d{4}-\d{2}-\d{2}$/)

    fireEvent.change(screen.getByLabelText('Documents from'), { target: { value: '2026-01-01' } })

    await waitFor(() =>
      expect(trendCalls().some(([, c]) => (c as { params: { date_from?: string } }).params.date_from === '2026-01-01')).toBe(true),
    )
  })

  it('AC-015 does not call the server while a date of the period is missing', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Totals of the period' })
    const before = trendCalls().length

    fireEvent.change(screen.getByLabelText('to'), { target: { value: '' } })

    await waitFor(() => expect(screen.queryByRole('status')).not.toBeInTheDocument())
    expect(trendCalls().length).toBe(before)
  })

  it('AC-015 renders the empty state when there is no revenue and no cost', async () => {
    const zeros = [0, 0, 0]
    mockApi(makeMonthlyTrend({
      functions: [],
      totals: {
        revenue: zeros, cost: zeros, margin: zeros,
        previous_year: { revenue: [100, 0, 0], cost: zeros, margin: [100, 0, 0] },
      },
      by_nature: [],
    }))
    renderPage()

    expect(await screen.findByText('No revenue or costs in the selected period with these filters.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Totals of the period' })).not.toBeInTheDocument()
  })

  it('AC-015 renders the loading state, then an error with retry that refetches', async () => {
    getMock.mockImplementation((url: string) =>
      url.endsWith('/monthly-trend') ? Promise.reject(new Error('boom')) : Promise.resolve({ data: EMPTY_FOR_SELECT }),
    )
    renderPage()

    expect(screen.getByRole('status', { name: 'Loading the monthly trend' })).toBeInTheDocument()
    const alert = await screen.findByText('Unable to load the monthly trend.')
    const before = trendCalls().length

    fireEvent.click(within(alert.closest('[role="alert"]') as HTMLElement).getByRole('button', { name: 'Retry' }))

    await waitFor(() => expect(trendCalls().length).toBeGreaterThan(before))
  })

  it('AC-015 without the permission shows the notice and calls no API', () => {
    abilities.granted = new Set()
    renderPage()

    expect(screen.getByText('You do not have permission to view management control.')).toBeInTheDocument()
    expect(getMock).not.toHaveBeenCalled()
  })
})
