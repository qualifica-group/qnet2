import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { formatEuro } from '@/features/invoices/invoice-format'
import TreasuryOverviewPage from '@/pages/treasury-overview-page'
import type { TreasuryOverview } from '@/features/treasury-overview/types'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))
const permissions = new Set<string>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (name: string) => permissions.has(name),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))
vi.mock('@/components/page-header', () => ({ PageHeader: () => null }))

const getMock = vi.mocked(apiClient.get)

// Spec 0362 — AC-010..AC-013.

const COMPANY = { id: 1, name: 'Alfa Srl' }
const BALANCES = {
  date: '2026-11-15',
  total: '1500.00',
  accounts: [{ id: 5, name: 'Intesa CC', type: 'bank_account', company: COMPANY, balance: '1500.00' }],
}
const OVERVIEW: TreasuryOverview = {
  today: '2026-11-15',
  company_id: null,
  liquidity: {
    total: '1500.00',
    by_type: { bank_account: '1350.00', card: '0.00', cash: '150.00' },
    accounts: [
      { id: 5, name: 'Intesa CC', type: 'bank_account', company: COMPANY, balance: '1350.00' },
      { id: 6, name: 'Cassa', type: 'cash', company: null, balance: '150.00' },
    ],
  },
  receivable: {
    open_total: '900.00',
    not_due_total: '600.00',
    overdue_total: '300.00',
    overdue: { overdue_1_30: '100.00', overdue_31_60: '0.00', overdue_61_90: '0.00', overdue_over_90: '200.00' },
  },
  payable: {
    open_total: '400.00',
    not_due_total: '400.00',
    overdue_total: '0.00',
    overdue: { overdue_1_30: '0.00', overdue_31_60: '0.00', overdue_61_90: '0.00', overdue_over_90: '0.00' },
  },
  net_position: '2000.00',
  forecast: [
    { days: 30, until: '2026-12-15', receivable: '250.00', payable: '120.00', expected_balance: '1630.00' },
    { days: 60, until: '2027-01-14', receivable: '400.00', payable: '300.00', expected_balance: '1600.00' },
    { days: 90, until: '2027-02-13', receivable: '600.00', payable: '400.00', expected_balance: '1700.00' },
  ],
  to_verify: {
    unassigned: { count: 2, collections: '100.00', payments: '50.00' },
    adjustments: { month: '2026-11', count: 1, inflow: '12.50', outflow: '0.00' },
  },
}

/** `getByText` collapses the non-breaking space of the euro format. */
function euroText(value: string) {
  return formatEuro(value).replace(/\s+/g, ' ')
}

function envelope(data: unknown) {
  return Promise.resolve({ data: { success: true, message: 'OK', data } })
}

function renderPage() {
  getMock.mockImplementation((url: string) =>
    url === '/account-statements/balances' ? envelope(BALANCES) : envelope(OVERVIEW),
  )
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/treasury-overview']}>
        <TreasuryOverviewPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function overviewCalls() {
  return getMock.mock.calls.filter(([url]) => url === '/treasury-overview')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
  permissions.clear()
  permissions.add('treasury-movements.view')
})

describe('TreasuryOverviewPage (spec 0362)', () => {
  it('AC-010: shows liquidity, receivables, payables, net position, forecast and to verify', async () => {
    renderPage()

    const liquidity = await screen.findByRole('region', { name: 'Liquidity' })
    expect(within(liquidity).getByRole('link', { name: formatEuro('1500.00') })).toBeInTheDocument()
    expect(within(liquidity).getByText('Intesa CC · Bank')).toBeInTheDocument()

    const receivable = screen.getByRole('region', { name: 'Customer receivables' })
    expect(within(receivable).getByText('Overdue over 90 days')).toBeInTheDocument()
    expect(within(receivable).getByRole('link', { name: formatEuro('200.00') })).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Supplier payables' })).toBeInTheDocument()

    const net = screen.getByRole('region', { name: 'Net position' })
    expect(within(net).getByText(euroText('2000.00'))).toBeInTheDocument()

    const forecast = screen.getByRole('region', { name: 'Forecast' })
    expect(within(forecast).getAllByRole('row')).toHaveLength(4)
    expect(within(forecast).getByText('30 days (until 2026-12-15)')).toBeInTheDocument()
    expect(within(forecast).getByText(euroText('1630.00'))).toBeInTheDocument()

    const toVerify = screen.getByRole('region', { name: 'To verify' })
    expect(within(toVerify).getByText('2 collections/payments without account')).toBeInTheDocument()
    expect(within(toVerify).getByText('1 balance adjustment in 2026-11')).toBeInTheDocument()
  })

  it('AC-011: changing company refetches with company_id, All refetches without it', async () => {
    renderPage()
    await screen.findByRole('region', { name: 'Liquidity' })
    expect(overviewCalls()[0][1]).toEqual({ params: {} })

    const select = screen.getByLabelText('Company')
    await screen.findByRole('option', { name: 'Alfa Srl' })
    fireEvent.change(select, { target: { value: '1' } })
    await waitFor(() => expect(overviewCalls().at(-1)?.[1]).toEqual({ params: { company_id: 1 } }))

    fireEvent.change(select, { target: { value: '' } })
    await waitFor(() => expect(overviewCalls().at(-1)?.[1]).toEqual({ params: {} }))
  })

  it('AC-012: each figure links to the list it comes from', async () => {
    renderPage()
    const liquidity = await screen.findByRole('region', { name: 'Liquidity' })

    expect(within(liquidity).getByRole('link', { name: formatEuro('1500.00') })).toHaveAttribute('href', '/account-statements')
    expect(within(liquidity).getByRole('link', { name: formatEuro('1350.00') })).toHaveAttribute('href', '/account-statements?account=5')
    expect(
      within(screen.getByRole('region', { name: 'Customer receivables' })).getByRole('link', { name: formatEuro('900.00') }),
    ).toHaveAttribute('href', '/invoice-installments')
    expect(
      within(screen.getByRole('region', { name: 'Supplier payables' })).getAllByRole('link', { name: formatEuro('400.00') })[0],
    ).toHaveAttribute('href', '/purchase-invoice-installments')
    const toVerify = screen.getByRole('region', { name: 'To verify' })
    expect(within(toVerify).getByRole('link', { name: '2 collections/payments without account' })).toHaveAttribute(
      'href',
      '/account-statements',
    )
    expect(within(toVerify).getByRole('link', { name: '1 balance adjustment in 2026-11' })).toHaveAttribute(
      'href',
      '/treasury-movements',
    )
  })

  it('AC-013: without the permission it shows the forbidden message and calls no API', () => {
    permissions.clear()
    renderPage()

    expect(screen.getByText('You do not have permission to view the treasury overview.')).toBeInTheDocument()
    expect(getMock).not.toHaveBeenCalled()
  })
})
