import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import AccountStatementsPage from '@/pages/account-statements-page'
import type { BankReconciliation, Statement } from '@/features/account-statements/types'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }))
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
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const getMock = vi.mocked(apiClient.get)
const postMock = vi.mocked(apiClient.post)
const deleteMock = vi.mocked(apiClient.delete)

// Spec 0363 - AC-014, AC-015.

const BALANCES = {
  date: '2026-11-05',
  total: '1350.00',
  accounts: [{ id: 5, name: 'Intesa CC', type: 'bank_account', company: { id: 1, name: 'Alfa Srl' }, balance: '1350.00' }],
}
const SAVED: BankReconciliation = {
  id: 9,
  statement_date: '2026-01-31',
  bank_balance: '1140.00',
  reconciled_balance: '1150.00',
  difference: '-10.00',
  adjustment_movement_id: 4,
  note: 'January',
  created_by: { id: 1, name: 'Mario' },
  created_at: '2026-02-01T10:00:00Z',
}
const EMPTY_FOR_SELECT = {
  items: [],
  export_link: null,
  pagination: { total: 0, offset: 0, limit: 25, total_pages: 0, has_more: false },
}

const STATEMENT: Statement = {
  account: { id: 5, name: 'Intesa CC', company: { id: 1, name: 'Alfa Srl' } },
  opening: { date: '2026-01-01', balance: '1000.00' },
  period_opening_balance: '1000.00',
  rows: [
    {
      key: 'collection:1',
      date: '2026-11-02',
      source: 'collection',
      type: 'collection',
      description: 'Incasso rata',
      registry: null,
      document: null,
      work_order: null,
      inflow: '500.00',
      outflow: null,
      balance: '1500.00',
      movement_id: null,
      reconciled: true,
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
      reconciled: false,
    },
  ],
  totals: { inflow: '500.00', outflow: '150.00' },
  closing_balance: '1350.00',
  balance_today: '1350.00',
  filtered: false,
  reconciled_balance: '1500.00',
  unreconciled_count: 1,
}

function envelope(data: unknown) {
  return Promise.resolve({ data: { success: true, message: 'OK', data } })
}

function mockApi(preview = '1150.00') {
  getMock.mockImplementation((url: string) => {
    if (url === '/account-statements/balances') return envelope(BALANCES)
    if (url === '/account-statements/unassigned') return envelope({ count: 0, rows: [] })
    if (url.endsWith('/bank-reconciliations/preview')) {
      return envelope({ statement_date: '2026-01-31', reconciled_balance: preview, unreconciled_count: 1 })
    }
    if (url.endsWith('/bank-reconciliations')) return envelope([SAVED])
    if (url.endsWith('/statement')) return envelope(STATEMENT)
    return Promise.resolve({ data: EMPTY_FOR_SELECT })
  })
  postMock.mockResolvedValue({ data: { success: true, message: 'OK', data: { updated: 1 } } })
  deleteMock.mockResolvedValue({ data: { success: true, message: 'OK' } })
}

function renderPage(entry = '/account-statements?account=5') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[entry]}>
        <ConfirmDialogProvider>
          <AccountStatementsPage />
        </ConfirmDialogProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
  postMock.mockReset()
  deleteMock.mockReset()
  permissions.clear()
  permissions.add('treasury-movements.viewAny')
  permissions.add('treasury-movements.view')
})

describe('Account statement ticking (AC-014)', () => {
  it('posts the selected keys on "Tick" and shows the reconciled column and balances', async () => {
    permissions.add('treasury-movements.reconcile')
    mockApi()
    renderPage()

    fireEvent.click(await screen.findByRole('checkbox', { name: 'Select Cancelleria' }))
    fireEvent.click(screen.getByRole('button', { name: 'Tick' }))

    await waitFor(() =>
      expect(postMock).toHaveBeenCalledWith('/financial-accounts/5/reconciliation-marks', {
        keys: ['movement:2'],
        reconciled: true,
      }),
    )
    expect(screen.getAllByText('Reconciled balance')[0].nextElementSibling).toHaveTextContent(/1.?500,00/)
    expect(screen.getByText('Rows not ticked').nextElementSibling).toHaveTextContent('1')
    expect(screen.getByText('Reconciled')).toBeInTheDocument()
  })

  it('has no checkboxes or tick actions without treasury-movements.reconcile', async () => {
    mockApi()
    renderPage()

    await screen.findByText('Cancelleria')
    expect(screen.queryByRole('checkbox', { name: 'Select Cancelleria' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Tick' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'New reconciliation' })).not.toBeInTheDocument()
  })

  it('opens pre-filtered on ?reconciled=no', async () => {
    mockApi()
    renderPage('/account-statements?account=5&reconciled=no')

    await screen.findByText('Cancelleria')
    const call = getMock.mock.calls.find(([url]) => url === '/financial-accounts/5/statement')
    expect(call?.[1]).toMatchObject({ params: { reconciled: 'no' } })
  })
})

describe('Bank reconciliations (AC-015)', () => {
  it('shows the ticked balance and the difference, then saves without adjustment when they match', async () => {
    permissions.add('treasury-movements.reconcile')
    mockApi()
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: 'New reconciliation' }))
    const dialog = await screen.findByRole('dialog', { name: 'New reconciliation' })
    fireEvent.change(within(dialog).getByLabelText(/Statement date/), { target: { value: '2026-01-31' } })
    fireEvent.change(within(dialog).getByLabelText(/Bank balance/), { target: { value: '1150' } })

    const comparison = await within(dialog).findByRole('region', { name: 'Comparison' })
    await waitFor(() => expect(within(comparison).getByText('Difference (bank - reconciled)').nextElementSibling).toHaveTextContent(/0,00/))
    expect(within(dialog).queryByRole('checkbox')).not.toBeInTheDocument()

    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))
    await waitFor(() =>
      expect(postMock).toHaveBeenCalledWith('/financial-accounts/5/bank-reconciliations', {
        statement_date: '2026-01-31',
        bank_balance: '1150',
        note: null,
        create_adjustment: false,
      }),
    )
  })

  it('offers the adjustment only with a difference and create permission, and requires the sub-account', async () => {
    permissions.add('treasury-movements.reconcile')
    permissions.add('treasury-movements.create')
    mockApi()
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: 'New reconciliation' }))
    const dialog = await screen.findByRole('dialog', { name: 'New reconciliation' })
    fireEvent.change(within(dialog).getByLabelText(/Statement date/), { target: { value: '2026-01-31' } })
    fireEvent.change(within(dialog).getByLabelText(/Bank balance/), { target: { value: '1140' } })

    const comparison = await within(dialog).findByRole('region', { name: 'Comparison' })
    await waitFor(() => expect(within(comparison).getByText('Difference (bank - reconciled)').nextElementSibling).toHaveTextContent(/-10,00/))
    fireEvent.click(within(dialog).getByRole('checkbox', { name: /Create a balance adjustment/ }))
    expect(await within(dialog).findByText(/Financial sub-account of the adjustment/)).toBeInTheDocument()

    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }))
    expect(await within(dialog).findByRole('alert')).toHaveTextContent('Select the financial sub-account of the adjustment.')
    expect(postMock).not.toHaveBeenCalled()
  })

  it('hides the adjustment option without treasury-movements.create', async () => {
    permissions.add('treasury-movements.reconcile')
    mockApi()
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: 'New reconciliation' }))
    const dialog = await screen.findByRole('dialog', { name: 'New reconciliation' })
    fireEvent.change(within(dialog).getByLabelText(/Statement date/), { target: { value: '2026-01-31' } })
    fireEvent.change(within(dialog).getByLabelText(/Bank balance/), { target: { value: '1140' } })

    await within(dialog).findByText(/-10,00/)
    expect(within(dialog).queryByRole('checkbox')).not.toBeInTheDocument()
  })

  it('lists the history and deletes after confirmation', async () => {
    permissions.add('treasury-movements.reconcile')
    mockApi()
    renderPage()

    const history = await screen.findByRole('region', { name: 'Bank reconciliations' })
    expect(await within(history).findByText('2026-01-31')).toBeInTheDocument()
    expect(within(history).getByText('January')).toBeInTheDocument()

    fireEvent.click(within(history).getByRole('button', { name: 'Delete the reconciliation of 2026-01-31' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith('/bank-reconciliations/9'))
  })

  it('shows the history read-only (no delete) to a viewer', async () => {
    mockApi()
    renderPage()

    const history = await screen.findByRole('region', { name: 'Bank reconciliations' })
    expect(await within(history).findByText('2026-01-31')).toBeInTheDocument()
    expect(within(history).queryByRole('button')).not.toBeInTheDocument()
  })
})
