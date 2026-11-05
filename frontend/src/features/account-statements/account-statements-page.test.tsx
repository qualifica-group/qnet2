import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { saveBlob } from '@/lib/download'
import AccountStatementsPage from '@/pages/account-statements-page'
import type { Statement } from '@/features/account-statements/types'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))
vi.mock('@/lib/download', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/download')>()),
  saveBlob: vi.fn(),
}))
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

const COMPANY = { id: 1, name: 'Alfa Srl' }
const BALANCES = {
  date: '2026-11-05',
  total: '1500.00',
  accounts: [
    { id: 5, name: 'Intesa CC', type: 'bank_account', company: COMPANY, balance: '1350.00' },
    { id: 6, name: 'Cassa', type: 'cash', company: null, balance: '150.00' },
  ],
}
const UNASSIGNED = {
  count: 2,
  rows: [
    {
      source: 'collection',
      installment_id: 1,
      document: { kind: 'invoice', id: 10, number: 'F-10' },
      registry: { id: 3, name: 'Rossi' },
      date: '2026-02-01',
      amount: '100.00',
    },
    {
      source: 'payment',
      installment_id: 2,
      document: { kind: 'purchase_invoice', id: 11, number: 'P-11' },
      registry: null,
      date: '2026-02-02',
      amount: '50.00',
    },
  ],
}
const EMPTY_FOR_SELECT = {
  items: [],
  export_link: null,
  pagination: { total: 0, offset: 0, limit: 25, total_pages: 0, has_more: false },
}

function statement(filtered: boolean): Statement {
  return {
    account: { id: 5, name: 'Intesa CC', company: COMPANY },
    opening: { date: '2026-01-01', balance: '1000.00' },
    period_opening_balance: '1000.00',
    rows: [
      {
        key: 'collection:1',
        date: '2026-11-02',
        source: 'collection',
        type: 'collection',
        description: 'Incasso rata',
        registry: { id: 3, name: 'Rossi' },
        document: { kind: 'invoice', id: 10, number: 'F-10' },
        work_order: null,
        inflow: '500.00',
        outflow: null,
        balance: '1500.00',
        movement_id: null,
      },
      {
        key: 'movement:2',
        date: '2026-11-03',
        source: 'movement',
        type: 'manual_expense',
        description: 'Cancelleria',
        registry: null,
        document: null,
        work_order: null,
        inflow: null,
        outflow: '150.00',
        balance: '1350.00',
        movement_id: 2,
      },
    ],
    totals: { inflow: '500.00', outflow: '150.00' },
    closing_balance: '1350.00',
    balance_today: '1350.00',
    filtered,
  }
}

function envelope(data: unknown) {
  return Promise.resolve({ data: { success: true, message: 'OK', data } })
}

function mockApi(stmt: Statement) {
  getMock.mockImplementation((url: string) => {
    if (url === '/account-statements/balances') return envelope(BALANCES)
    if (url === '/account-statements/unassigned') return envelope(UNASSIGNED)
    if (url.endsWith('/statement/export')) {
      return Promise.resolve({
        data: new Blob(['x']),
        headers: { 'content-disposition': 'attachment; filename="estratto.xlsx"' },
      })
    }
    if (url.endsWith('/statement')) return envelope(stmt)
    return Promise.resolve({ data: EMPTY_FOR_SELECT })
  })
}

function renderPage(entry: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[entry]}>
        <AccountStatementsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
  vi.mocked(saveBlob).mockReset()
  permissions.clear()
  permissions.add('treasury-movements.viewAny')
})

describe('AccountStatementsPage (AC-021)', () => {
  it('preselects the account from ?account= and shows summary, rows and totals', async () => {
    mockApi(statement(false))
    renderPage('/account-statements?account=5')

    const closing = await screen.findByText('Closing balance')
    expect(screen.getByRole('combobox', { name: 'Account' })).toHaveValue('5')
    expect(screen.getByText('Period opening balance').nextElementSibling).toHaveTextContent(/1.?000,00/)
    expect(screen.getByText('Total inflow').nextElementSibling).toHaveTextContent(/500,00/)
    expect(screen.getByText('Total outflow').nextElementSibling).toHaveTextContent(/150,00/)
    expect(closing.nextElementSibling).toHaveTextContent(/1.?350,00/)

    const table = screen.getByRole('table')
    expect(within(table).getByText('Incasso rata')).toBeInTheDocument()
    expect(within(table).getByText('Invoice F-10')).toBeInTheDocument()
    expect(within(table).getAllByRole('row')).toHaveLength(3)
    expect(screen.queryByText(/running balance is the real/)).not.toBeInTheDocument()

    const statementCall = getMock.mock.calls.find(([url]) => url === '/financial-accounts/5/statement')
    expect(statementCall?.[1]).toMatchObject({
      params: { date_from: expect.stringMatching(/-01$/), date_to: expect.any(String) },
    })
  })

  it('groups accounts by company with the balance and offers the "All" company entry', async () => {
    mockApi(statement(false))
    renderPage('/account-statements')

    expect(await screen.findByRole('option', { name: /Intesa CC - .*1.?350,00/ })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'All' })).toBeInTheDocument()
    expect(screen.getByRole('group', { name: 'Alfa Srl' })).toBeInTheDocument()
    expect(screen.getByRole('group', { name: 'No company' })).toBeInTheDocument()
    expect(screen.getByText('Select an account to see its statement.')).toBeInTheDocument()
  })

  it('warns that the running balance is the real one when filtered is true', async () => {
    mockApi(statement(true))
    renderPage('/account-statements?account=5')

    expect(await screen.findByRole('status')).toHaveTextContent(/running balance is the real account balance/)
  })

  it('shows the to-verify count and the expandable list', async () => {
    mockApi(statement(false))
    renderPage('/account-statements?account=5')

    expect(await screen.findByText(/2 collections or payments without an account/)).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Show list' }))
    expect(screen.getByText('Purchase invoice P-11')).toBeInTheDocument()
  })

  it('hides Export without treasury-movements.export and downloads through the client with it', async () => {
    mockApi(statement(false))
    const first = renderPage('/account-statements?account=5')
    await screen.findByText('Closing balance')
    expect(screen.queryByRole('button', { name: 'Export' })).not.toBeInTheDocument()
    first.unmount()

    permissions.add('treasury-movements.export')
    renderPage('/account-statements?account=5')
    fireEvent.click(await screen.findByRole('button', { name: 'Export' }))

    await waitFor(() => expect(saveBlob).toHaveBeenCalledWith(expect.any(Blob), 'estratto.xlsx'))
    const exportCall = getMock.mock.calls.find(([url]) => url === '/financial-accounts/5/statement/export')
    expect(exportCall?.[1]).toMatchObject({ responseType: 'blob' })
  })

  it('asks to narrow the period on a 422', async () => {
    getMock.mockImplementation((url: string) => {
      if (url.endsWith('/statement')) {
        return Promise.reject(Object.assign(new Error('too many'), { response: { status: 422 } }))
      }
      if (url === '/account-statements/balances') return envelope(BALANCES)
      return envelope({ count: 0, rows: [] })
    })
    renderPage('/account-statements?account=5')

    expect(await screen.findByRole('alert')).toHaveTextContent('narrow the period')
  })
})
