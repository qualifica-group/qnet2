import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { formatEuro } from '@/features/invoices/invoice-format'
import ManagementControlIncomeStatementPage from '@/pages/management-control-income-statement-page'
import {
  makeFunction,
  makeIncomeStatement,
  makeSegment,
  makeStructureCosts,
  makeTotals,
} from '@/features/management-control/management-control-fixtures'
import type { IncomeStatement } from '@/features/management-control/types'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const abilities = vi.hoisted(() => ({ granted: new Set<string>() }))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => abilities.granted.has(permission), isLoading: false }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ title }: { title?: string }) => <h1>{title}</h1>,
}))

// recharts has no layout in jsdom: the chart is covered by its own test, here only its props matter.
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
          <li key={point.label}>{point.label}</li>
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

function mockApi(statement: IncomeStatement = makeIncomeStatement()) {
  getMock.mockImplementation((url: string) => {
    if (url.endsWith('/income-statement')) {
      return Promise.resolve({ data: { success: true, message: 'OK', data: statement } })
    }
    if (url.endsWith('/structure-costs')) {
      return Promise.resolve({ data: { success: true, message: 'OK', data: makeStructureCosts() } })
    }
    return Promise.resolve({ data: EMPTY_FOR_SELECT })
  })
}

function reportCalls() {
  return getMock.mock.calls.filter(([url]) => String(url).startsWith('/management-control'))
}

function renderPage() {
  // One client per test: a per-render client would reset the cache on every render.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ManagementControlIncomeStatementPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
  abilities.granted = new Set(['management-control.view'])
})

describe('ManagementControlIncomeStatementPage', () => {
  it('AC-017 shows the totals with the work orders count, the by-function table with its total line and the chart', async () => {
    mockApi()
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Totals' })
    expect(within(totals).getByText('Expected margin')).toBeInTheDocument()
    expect(within(totals).getByText('Work orders').closest('div')).toHaveTextContent('3')

    const functions = screen.getByRole('region', { name: 'Statement by business function' })
    const training = within(functions).getByRole('row', { name: /Training/ })
    expect(training.textContent).toContain(formatEuro(1000))
    expect(within(functions).getByRole('row', { name: /Unattributed/ })).toBeInTheDocument()
    expect(within(functions).getByRole('row', { name: /^Total/ })).toBeInTheDocument()
    expect(within(functions).getByRole('figure', { name: 'Expected vs actual margin by business function' })).toBeInTheDocument()
  })

  it('AC-009 shows the labor cost and hours in the tiles and in the by-function columns, with the part not valued', async () => {
    mockApi()
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Totals' })
    expect(within(totals).getByText('Labor cost').closest('div')?.textContent).toContain(formatEuro(120))
    const hours = within(totals).getByText('Hours recorded').closest('div') as HTMLElement
    expect(hours).toHaveTextContent('6:00')
    expect(hours).toHaveTextContent('of which 1:00 not valued')
    expect(within(hours).getByTitle(/no hourly cost/)).toBeInTheDocument()

    const functions = screen.getByRole('region', { name: 'Statement by business function' })
    expect(within(functions).getByRole('columnheader', { name: 'Labor cost' })).toBeInTheDocument()
    expect(within(functions).getByRole('columnheader', { name: 'Hours' })).toBeInTheDocument()
    const training = within(functions).getByRole('row', { name: /Training/ })
    expect(training.textContent).toContain(formatEuro(80))
    expect(training).toHaveTextContent('4:00')
    expect(training).not.toHaveTextContent('not valued')
    expect(within(functions).getByRole('row', { name: /Unattributed/ })).toHaveTextContent('of which 1:00 not valued')
  })

  it('AC-009 gives the totals eleven tiles, the hours one spanning two columns so the grid closes without orphans', async () => {
    mockApi()
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Totals' })
    expect(within(totals).getAllByRole('term')).toHaveLength(11)
    expect(within(totals).getByText('Hours recorded').closest('div')).toHaveClass('col-span-2')
  })

  it('AC-017 renders no work orders table, no detail link and no segment block without segmentation', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Totals' })

    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Work orders' })).not.toBeInTheDocument()
    expect(screen.queryByRole('article')).not.toBeInTheDocument()
  })

  it('AC-017 renders one block per segment with its subtotal and a nested by-function table', async () => {
    mockApi(makeIncomeStatement({
      applied: { ...makeIncomeStatement().applied, segment_by: 'work_order_type' },
      segments: [
        makeSegment({ key: 'project', label: null, work_orders_count: 2, by_business_function: [makeFunction({ label: 'Consulting' })] }),
        makeSegment({ key: 'unattributed', label: null, work_orders_count: 1, by_business_function: [makeFunction({ label: 'Audit' })] }),
      ],
    }))
    renderPage()
    await screen.findByRole('region', { name: 'Totals' })

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Segmentation' }), { key: 'ArrowDown' })
    fireEvent.click(await screen.findByRole('option', { name: 'Work order type' }))

    const project = await screen.findByRole('article', { name: 'Project' })
    expect(within(project).getByText('2 work orders')).toBeInTheDocument()
    expect(within(project).getByRole('row', { name: /Consulting/ })).toBeInTheDocument()
    const unattributed = screen.getByRole('article', { name: 'Unattributed' })
    expect(within(unattributed).getByText('1 work order')).toBeInTheDocument()
    expect(within(unattributed).getByRole('row', { name: /Audit/ })).toBeInTheDocument()
  })

  it('AC-017 requests the contract parameters with the default filters', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Totals' })

    const year = new Date().getFullYear()
    const [url, config] = reportCalls().find(([target]) => String(target).endsWith('/income-statement')) ?? []
    expect(url).toBe('/management-control/income-statement')
    expect(config).toMatchObject({
      params: { date_from: `${year}-01-01`, revenue_basis: 'contract', segment_by: 'none' },
    })
  })

  it('AC-017 a filter change refetches with the new parameters', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Totals' })

    fireEvent.click(screen.getByRole('radio', { name: 'Invoiced' }))

    await waitFor(() =>
      expect(
        reportCalls().some(([, config]) => (config as { params: { revenue_basis?: string } }).params.revenue_basis === 'invoiced'),
      ).toBe(true),
    )
  })

  it('AC-017 shows the structure costs with translated unattributed rows', async () => {
    mockApi()
    renderPage()

    const structure = await screen.findByRole('region', { name: 'Structure costs' })
    expect(await within(structure).findByRole('row', { name: /Rent/ })).toBeInTheDocument()
    expect(within(structure).getByRole('row', { name: /No cost type/ })).toBeInTheDocument()
    expect(within(structure).getByRole('row', { name: /Unattributed/ })).toBeInTheDocument()
  })

  it('AC-017 renders the empty state when no work order matches', async () => {
    mockApi(makeIncomeStatement({ totals: makeTotals({ work_orders_count: 0 }), by_business_function: [] }))
    renderPage()

    expect(await screen.findByText('No work orders start in the selected period with these filters.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Totals' })).not.toBeInTheDocument()
  })

  it('AC-017 renders the loading state, then an error with retry that refetches', async () => {
    getMock.mockImplementation((url: string) =>
      url.endsWith('/income-statement') ? Promise.reject(new Error('boom')) : Promise.resolve({ data: EMPTY_FOR_SELECT }),
    )
    renderPage()

    expect(screen.getByRole('status', { name: 'Loading the income statement' })).toBeInTheDocument()
    const alert = await screen.findByText('Unable to load the income statement.')
    const before = reportCalls().filter(([url]) => String(url).endsWith('/income-statement')).length

    fireEvent.click(within(alert.closest('[role="alert"]') as HTMLElement).getByRole('button', { name: 'Retry' }))

    await waitFor(() =>
      expect(reportCalls().filter(([url]) => String(url).endsWith('/income-statement')).length).toBeGreaterThan(before),
    )
  })

  it('AC-017 marks negative amounts with the minus sign and an accessible prefix', async () => {
    mockApi()
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Totals' })
    expect(within(totals).getAllByText('Negative:').length).toBeGreaterThan(0)
    expect(within(totals).getByText(/-240,00/)).toBeInTheDocument()
  })

  it('AC-018 without the permission shows the notice and calls no API', () => {
    abilities.granted = new Set()
    renderPage()

    expect(screen.getByText('You do not have permission to view management control.')).toBeInTheDocument()
    expect(getMock).not.toHaveBeenCalled()
  })
})
