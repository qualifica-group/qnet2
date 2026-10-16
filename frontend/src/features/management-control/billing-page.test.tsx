import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { formatEuro } from '@/features/invoices/invoice-format'
import { makeBilling } from '@/features/management-control/billing-fixtures'
import type { Billing } from '@/features/management-control/billing-types'
import ManagementControlBillingPage from '@/pages/management-control-billing-page'

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

function mockApi(billing: Billing = makeBilling()) {
  getMock.mockImplementation((url: string) =>
    url.endsWith('/billing')
      ? Promise.resolve({ data: { success: true, message: 'OK', data: billing } })
      : Promise.resolve({ data: EMPTY_FOR_SELECT }),
  )
}

function billingCalls() {
  return getMock.mock.calls.filter(([url]) => String(url).endsWith('/billing'))
}

function renderPage() {
  // One client per test: a per-render client would reset the cache on every render.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ManagementControlBillingPage />
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

describe('ManagementControlBillingPage', () => {
  it('AC-012 shows the four tiles, the over-invoiced amount flagged as negative', async () => {
    mockApi(makeBilling({
      backlog: { ...makeBilling().backlog, contract: 8000, invoiced: 9000, to_invoice: -1000 },
    }))
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Amount to invoice totals' })
    expect(within(totals).getAllByRole('term').map((term) => term.textContent)).toEqual([
      'Open work orders', 'Contract', 'Invoiced', 'To invoice',
    ])
    const toInvoice = within(totals).getByText('To invoice').closest('div') as HTMLElement
    expectText(toInvoice, `Negative: ${formatEuro(-1000)}`)
    expect(within(totals).getByText('Open work orders').closest('div')).toHaveTextContent('5')
  })

  it('AC-012 shows the backlog by dimension with the translated unattributed row and the totals', async () => {
    mockApi()
    renderPage()

    const table = await screen.findByRole('region', { name: 'Contract, invoiced and to invoice by Business function' })
    expect(within(table).getAllByRole('columnheader').map((header) => header.textContent)).toEqual([
      'Business function', 'Work orders', 'Contract', 'Invoiced', 'To invoice',
    ])
    expect(cellTexts(within(table).getByRole('row', { name: /^Engineering/ }))).toEqual(
      ['3', formatEuro(6000), formatEuro(4000), formatEuro(2000)],
    )
    expect(within(table).getByRole('row', { name: /^Unattributed/ })).toBeInTheDocument()
    expectText(within(table).getByRole('row', { name: /^Training/ }), `Negative: ${formatEuro(-500)}`)
    expect(cellTexts(within(table).getByRole('row', { name: /^Total/ }))).toEqual(
      ['5', formatEuro(10000), formatEuro(7500), formatEuro(2500)],
    )
  })

  it('AC-012 draws contract against invoiced for each dimension', async () => {
    mockApi()
    renderPage()

    const chart = await screen.findByRole('figure', { name: 'Contract and invoiced by Business function' })
    expect(within(chart).getByText('Contract / Invoiced')).toBeInTheDocument()
    expect(within(chart).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      'Engineering: 6000 | 4000',
      'Training: 3000 | 3500',
      'Unattributed: 1000 | 0',
    ])
  })

  it('AC-012 shows the work order age bands translated, always four, with the total', async () => {
    mockApi()
    renderPage()

    const table = await screen.findByRole('region', { name: 'Amount to invoice by work order age band' })
    expect(within(table).getAllByRole('row', { name: /months/ }).map((row) => within(row).getByRole('rowheader').textContent)).toEqual([
      '0-3 months', '3-6 months', '6-12 months', 'Over 12 months',
    ])
    expect(cellTexts(within(table).getByRole('row', { name: /^0-3 months/ }))).toEqual(['2', formatEuro(1500)])
    expect(cellTexts(within(table).getByRole('row', { name: /^Total/ }))).toEqual(['5', formatEuro(2500)])
  })

  it('AC-012 shows the proforma tiles with amounts and document counts and the average times', async () => {
    mockApi()
    renderPage()

    const tiles = await screen.findByRole('region', { name: 'Proforma of the period' })
    const issued = within(tiles).getByText('Issued').closest('div') as HTMLElement
    expectText(issued, formatEuro(12000))
    expect(issued).toHaveTextContent('6 documents')
    expect(within(tiles).getByText('Converted to invoice').closest('div')).toHaveTextContent('3 documents')
    expectText(within(tiles).getByText('Still open').closest('div'), formatEuro(7000))

    const times = screen.getByRole('region', { name: 'Average times' })
    expect(within(times).getByText('Average time from proforma to invoice').closest('div')).toHaveTextContent('20 days')
    expect(within(times).getByText('Average time from invoice to collection').closest('div')).toHaveTextContent('13 days')
  })

  it('AC-012 shows n/a for the average times with no data', async () => {
    const base = makeBilling()
    mockApi(makeBilling({
      proforma: {
        ...base.proforma,
        converted: { ...base.proforma.converted, average_days_to_invoice: null },
        average_days_invoice_to_collection: null,
      },
    }))
    renderPage()

    const times = await screen.findByRole('region', { name: 'Average times' })
    expect(within(times).getAllByText('n/a')).toHaveLength(2)
  })

  it('AC-012 shows open proforma by age and the cycle by kind, with the no-request row translated', async () => {
    mockApi()
    renderPage()

    const age = await screen.findByRole('region', { name: 'Open proforma by age' })
    expect(within(age).getAllByRole('rowheader').map((header) => header.textContent)).toEqual([
      '0-30 days', '31-60 days', '61-90 days', 'Over 90 days',
    ])
    expect(cellTexts(within(age).getByRole('row', { name: /^Over 90 days/ }))).toEqual(['1', formatEuro(4000)])

    const kinds = screen.getByRole('region', { name: 'Proforma cycle by request kind' })
    expect(within(kinds).getAllByRole('rowheader').map((header) => header.textContent)).toEqual([
      'Consultancy', 'Institution', 'No request',
    ])
    expect(cellTexts(within(kinds).getByRole('row', { name: /^Consultancy/ }))).toEqual(['3', '2', '1', '15 days'])
    expect(cellTexts(within(kinds).getByRole('row', { name: /^No request/ }))).toEqual(['1', '0', '1', 'n/a'])
  })

  it('AC-012 labels the bands and rows in the active language', async () => {
    await i18n.changeLanguage('it')
    try {
      mockApi()
      renderPage()

      const age = await screen.findByRole('region', { name: 'Da fatturare per fascia di età della commessa' })
      expect(within(age).getByRole('rowheader', { name: '0-3 mesi' })).toBeInTheDocument()
      const kinds = screen.getByRole('region', { name: 'Ciclo delle proforma per tipologia di richiesta' })
      expect(within(kinds).getByRole('rowheader', { name: 'Senza richiesta' })).toBeInTheDocument()
      expect(within(kinds).getByRole('rowheader', { name: 'Consulenza' })).toBeInTheDocument()
    } finally {
      cleanup()
      await i18n.changeLanguage('en')
    }
  })

  it('AC-012 a grouping change refetches with the new group_by', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Amount to invoice totals' })

    fireEvent.keyDown(screen.getByRole('combobox', { name: 'Group the amount to invoice by' }), { key: 'ArrowDown' })
    fireEvent.click(await screen.findByRole('option', { name: 'Company' }))

    await waitFor(() => expect(billingCalls()).toHaveLength(2))
    expect(billingCalls()[1][1]).toMatchObject({ params: { group_by: 'company' } })
  })

  it('AC-012 shows the loading state, then the error with retry', async () => {
    getMock.mockImplementation((url: string) =>
      url.endsWith('/billing') ? Promise.reject(new Error('boom')) : Promise.resolve({ data: EMPTY_FOR_SELECT }),
    )
    renderPage()

    expect(screen.getByRole('status', { name: 'Loading billing' })).toBeInTheDocument()
    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load billing.')

    mockApi()
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(await screen.findByRole('region', { name: 'Amount to invoice totals' })).toBeInTheDocument()
  })

  it('AC-012 shows the empty state with no open work order and no proforma', async () => {
    const base = makeBilling()
    mockApi(makeBilling({
      backlog: { ...base.backlog, work_orders_count: 0, contract: 0, invoiced: 0, to_invoice: 0, by_dimension: [] },
      proforma: {
        ...base.proforma,
        issued: { documents: 0, amount: 0 },
        converted: { documents: 0, amount: 0, average_days_to_invoice: null },
        open: { documents: 0, amount: 0 },
      },
    }))
    renderPage()

    expect(await screen.findByText('No open work orders and no proforma in the period with these filters.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Amount to invoice totals' })).not.toBeInTheDocument()
  })

  it('AC-012 without the permission shows the message and calls nothing', () => {
    abilities.granted = new Set()
    mockApi()
    renderPage()

    expect(screen.getByText('You do not have permission to view management control.')).toBeInTheDocument()
    expect(billingCalls()).toHaveLength(0)
  })
})
