import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { formatEuro } from '@/features/invoices/invoice-format'
import { makeExposure, makeExposureSide } from '@/features/management-control/exposure-fixtures'
import type { Exposure } from '@/features/management-control/exposure-types'
import ManagementControlExposurePage from '@/pages/management-control-exposure-page'

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

const EMPTY_SIDE = makeExposureSide({
  open_total: 0,
  overdue_total: 0,
  not_due_total: 0,
  invoiced_in_period: 0,
  settled_in_period: 0,
  average_days: null,
  top: [],
})

function mockApi(exposure: Exposure = makeExposure()) {
  getMock.mockImplementation((url: string) =>
    url.endsWith('/exposure')
      ? Promise.resolve({ data: { success: true, message: 'OK', data: exposure } })
      : Promise.resolve({ data: EMPTY_FOR_SELECT }),
  )
}

function exposureCalls() {
  return getMock.mock.calls.filter(([url]) => String(url).endsWith('/exposure'))
}

function renderPage() {
  // One client per test: a per-render client would reset the cache on every render.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ManagementControlExposurePage />
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

describe('ManagementControlExposurePage', () => {
  it('AC-017 shows the eight tiles, with n/a for a missing DSO or DPO', async () => {
    mockApi()
    renderPage()

    const totals = await screen.findByRole('region', { name: 'Schedule totals' })
    for (const label of ['To collect', 'Receivable overdue', 'To pay', 'Payable overdue', 'Committed', 'Net exposure']) {
      expect(within(totals).getByText(label)).toBeInTheDocument()
    }
    expect(within(totals).getByText('To collect').closest('div')?.textContent).toContain(formatEuro(1000))
    expect(within(totals).getByText('DSO (average days to collect)').closest('div')).toHaveTextContent('42 days')
    expect(within(totals).getByText('DPO (average days to pay)').closest('div')).toHaveTextContent('n/a')
    expect(within(totals).getAllByRole('term')).toHaveLength(8)
  })

  it('AC-017 shows the aging table with the seven buckets and the chart', async () => {
    mockApi()
    renderPage()

    const aging = await screen.findByRole('region', { name: 'Open amounts by due bucket' })
    expect(within(aging).getAllByRole('row')).toHaveLength(1 + 7 + 1)
    expect(within(aging).getByRole('row', { name: /Overdue over 90 days/ }).textContent).toContain(formatEuro(100))
    const chart = screen.getByRole('figure', { name: 'To collect and to pay by bucket' })
    expect(within(chart).getByText('To collect / To pay')).toBeInTheDocument()
    expect(within(chart).getAllByRole('listitem')).toHaveLength(7)
  })

  it('AC-017 shows the documents by payment status for both sides', async () => {
    mockApi()
    renderPage()

    const status = await screen.findByRole('region', { name: 'Documents and open amount by payment status' })
    expect(within(status).getByRole('row', { name: /Seriously overdue/ }).textContent).toContain(formatEuro(350))
    expect(within(status).getByRole('row', { name: /Not due/ }).textContent).toContain(formatEuro(500))
  })

  it('AC-017 links customers and suppliers to their ledger and translates the worst bucket', async () => {
    mockApi()
    renderPage()

    const customers = await screen.findByRole('region', { name: 'List of customers with the highest overdue' })
    expect(within(customers).getByRole('link', { name: 'Alfa Srl' })).toHaveAttribute('href', '/ledger?registry=11')
    expect(within(customers).getByRole('row', { name: /Alfa Srl/ })).toHaveTextContent('Overdue 61-90 days')
    const suppliers = screen.getByRole('region', { name: 'List of suppliers with the highest overdue' })
    expect(within(suppliers).getByRole('link', { name: 'Fornitore Uno' })).toHaveAttribute('href', '/ledger?registry=21')
  })

  it('AC-017 shows the commitments by supplier and by cost type with the nature', async () => {
    mockApi()
    renderPage()

    const bySupplier = await screen.findByRole('region', { name: 'Committed by supplier' })
    expect(within(bySupplier).getByRole('row', { name: /Fornitore Due/ })).toHaveTextContent('3')
    const byCostType = screen.getByRole('region', { name: 'Committed by cost type' })
    expect(within(byCostType).getByRole('row', { name: /Consulting/ })).toHaveTextContent('Direct')
    const payable = screen.getByRole('region', { name: 'To pay by cost type' })
    expect(within(payable).getByRole('row', { name: /Rent/ })).toHaveTextContent('General')
    expect(within(payable).getByRole('row', { name: /No cost type/ })).toBeInTheDocument()
  })

  it('AC-017 shows the exposure by dimension with a translated unattributed row and the total', async () => {
    mockApi()
    renderPage()

    const dimension = await screen.findByRole('region', { name: 'Net exposure by Company' })
    expect(within(dimension).getByRole('row', { name: /Acme Spa/ })).toBeInTheDocument()
    expect(within(dimension).getByRole('row', { name: /Unattributed/ })).toBeInTheDocument()
    expect(within(dimension).getByRole('row', { name: /^Total/ }).textContent).toContain('-50,00')
  })

  it('AC-017 shows the concentration with one-decimal percentages', async () => {
    mockApi()
    renderPage()

    const concentration = await screen.findByRole('region', { name: 'Concentration of invoiced and overdue amounts on the top customers' })
    const alfa = within(concentration).getByRole('row', { name: /Alfa Srl/ })
    expect(alfa).toHaveTextContent('40.0%')
    expect(alfa).toHaveTextContent('25.0%')
    expect(screen.getByText('70.0%')).toBeInTheDocument()
  })

  it('AC-017 offers the two buttons towards the installments pages', async () => {
    mockApi()
    renderPage()

    expect(await screen.findByRole('link', { name: 'Open active installments' })).toHaveAttribute('href', '/invoice-installments')
    expect(screen.getByRole('link', { name: 'Open passive installments' })).toHaveAttribute('href', '/purchase-invoice-installments')
  })

  it('AC-017 requests the contract parameters, then a filter change refetches', async () => {
    mockApi()
    renderPage()
    await screen.findByRole('region', { name: 'Schedule totals' })

    const year = new Date().getFullYear()
    const [url, config] = exposureCalls()[0] ?? []
    expect(url).toBe('/management-control/exposure')
    expect(config).toMatchObject({
      params: { date_from: `${year}-01-01`, document_type: 'invoice', group_by: 'company', top: 10 },
    })

    fireEvent.click(screen.getByRole('radio', { name: 'Proforma' }))

    await waitFor(() =>
      expect(
        exposureCalls().some(([, c]) => (c as { params: { document_type?: string } }).params.document_type === 'proforma'),
      ).toBe(true),
    )
  })

  it('AC-017 renders the empty state when there is nothing to show', async () => {
    mockApi(makeExposure({
      receivable: EMPTY_SIDE,
      payable: { ...EMPTY_SIDE, by_cost_type: [] },
      committed: { total: 0, with_work_order: 0, without_work_order: 0, by_supplier: [], by_cost_type: [] },
      concentration: { invoiced_total: 0, overdue_total: 0, top_invoiced_share: 0, top_overdue_share: 0, rows: [] },
    }))
    renderPage()

    expect(await screen.findByText('No schedule, commitment or invoiced data with these filters.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Schedule totals' })).not.toBeInTheDocument()
  })

  it('AC-017 renders the loading state, then an error with retry that refetches', async () => {
    getMock.mockImplementation((url: string) =>
      url.endsWith('/exposure') ? Promise.reject(new Error('boom')) : Promise.resolve({ data: EMPTY_FOR_SELECT }),
    )
    renderPage()

    expect(screen.getByRole('status', { name: 'Loading the schedule' })).toBeInTheDocument()
    const alert = await screen.findByText('Unable to load the schedule.')
    const before = exposureCalls().length

    fireEvent.click(within(alert.closest('[role="alert"]') as HTMLElement).getByRole('button', { name: 'Retry' }))

    await waitFor(() => expect(exposureCalls().length).toBeGreaterThan(before))
  })

  it('AC-018 without the permission shows the notice and calls no API', () => {
    abilities.granted = new Set()
    renderPage()

    expect(screen.getByText('You do not have permission to view management control.')).toBeInTheDocument()
    expect(getMock).not.toHaveBeenCalled()
  })
})
