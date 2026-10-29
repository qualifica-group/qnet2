import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import type { ReactElement } from 'react'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { LedgerPanel } from '@/features/ledger/ledger-panel'
import { makeDocument, makeInstallment, makeLedger, makeSection } from '@/features/ledger/ledger-fixtures'
import type { Ledger } from '@/features/ledger/types'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn() } }))

const getMock = vi.mocked(apiClient.get)

const EMPTY_FOR_SELECT = {
  items: [],
  export_link: null,
  pagination: { total: 0, offset: 0, limit: 25, total_pages: 0, has_more: false },
}

function mockLedger(ledger: Ledger) {
  getMock.mockImplementation((url: string) =>
    Promise.resolve({
      data: url.endsWith('/ledger') ? { success: true, message: 'OK', data: ledger } : EMPTY_FOR_SELECT,
    }),
  )
}

function ledgerCalls() {
  return getMock.mock.calls.filter(([url]) => String(url).endsWith('/ledger'))
}

function renderPanel(ui: ReactElement) {
  // One client per test: a per-render client would reset the cache on every render.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
})

describe('LedgerPanel', () => {
  it('AC-017 requests the ledger with the contract parameters and default filters', async () => {
    mockLedger(makeLedger())
    renderPanel(<LedgerPanel registryId={7} />)

    await screen.findByRole('heading', { name: 'Customer' })
    const [url, config] = ledgerCalls()[0]
    const year = new Date().getFullYear()
    expect(url).toBe('/registries/7/ledger')
    expect(config).toMatchObject({ params: { document_type: 'invoice', date_from: `${year}-01-01` } })
    expect(screen.getByRole('radio', { name: 'Invoices' })).toBeChecked()
  })

  it('AC-017 a filter change refetches with the new parameters', async () => {
    mockLedger(makeLedger())
    renderPanel(<LedgerPanel registryId={7} />)
    await screen.findByRole('heading', { name: 'Customer' })

    fireEvent.click(screen.getByRole('radio', { name: 'Proforma' }))

    await waitFor(() => expect(ledgerCalls()).toHaveLength(2))
    expect(ledgerCalls()[1][1]).toMatchObject({ params: { document_type: 'proforma' } })
  })

  it('AC-016 renders the columns, the opening balance row and the expandable installments', async () => {
    mockLedger(makeLedger())
    renderPanel(<LedgerPanel registryId={7} />)
    await screen.findByRole('heading', { name: 'Customer' })

    for (const name of ['Date', 'Document', 'Debit', 'Credit', 'Balance', 'Status']) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument()
    }
    expect(screen.getByRole('row', { name: /Opening balance/ })).toBeInTheDocument()

    const toggle = screen.getByRole('button', { name: 'Show the installments of document 12/2026' })
    expect(toggle).toHaveAttribute('aria-expanded', 'false')
    expect(screen.queryByRole('columnheader', { name: 'Days late' })).not.toBeInTheDocument()

    fireEvent.click(toggle)

    expect(toggle).toHaveAttribute('aria-expanded', 'true')
    for (const name of ['Due date', 'Collected on', 'Collected amount', 'Outstanding', 'Days late']) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument()
    }
  })

  it('AC-016 hides the opening balance row without a start date', async () => {
    mockLedger(makeLedger())
    renderPanel(<LedgerPanel registryId={7} />)
    await screen.findByRole('heading', { name: 'Customer' })

    fireEvent.change(screen.getByLabelText('From'), { target: { value: '' } })

    await waitFor(() => expect(screen.queryByRole('row', { name: /Opening balance/ })).not.toBeInTheDocument())
    expect(ledgerCalls().at(-1)?.[1]).not.toHaveProperty('params.date_from')
  })

  it('AC-016 puts the supplier document in Credit and labels settlement as paid', async () => {
    const supplierDocument = makeDocument({
      id: 20,
      number_label: 'F-9',
      debit: 300,
      credit: 800,
      settled_amount: 300,
      residual_amount: 500,
      running_balance: 500,
      status: 'not_due',
      installments: [makeInstallment({ id: 5, settled_amount: 300, settled_at: '2026-04-02', residual_amount: 0, days_late: 0 })],
    })
    mockLedger(makeLedger({ customer: null, supplier: makeSection({ documents: [supplierDocument] }) }))
    renderPanel(<LedgerPanel registryId={7} />)

    const section = await screen.findByRole('region', { name: 'Supplier summary' })
    expect(within(section).getByText('Paid')).toBeInTheDocument()
    const row = within(section).getByRole('row', { name: /F-9/ })
    const cells = within(row).getAllByRole('cell')
    expect(cells[2]).toHaveTextContent(/300/)
    expect(cells[3]).toHaveTextContent(/800/)

    fireEvent.click(within(section).getByRole('button', { name: /Show the installments/ }))
    expect(within(section).getByRole('columnheader', { name: 'Paid on' })).toBeInTheDocument()
  })

  it('AC-017 shows the status as text, not only as colour', async () => {
    mockLedger(
      makeLedger({
        customer: makeSection({
          documents: [
            makeDocument({ id: 1, number_label: '1/2026', status: 'paid' }),
            makeDocument({ id: 2, number_label: '2/2026', status: 'not_due' }),
            makeDocument({ id: 3, number_label: '3/2026', status: 'overdue' }),
            makeDocument({ id: 4, number_label: '4/2026', status: 'seriously_overdue' }),
          ],
        }),
      }),
    )
    renderPanel(<LedgerPanel registryId={7} />)
    await screen.findByRole('heading', { name: 'Customer' })

    const table = screen.getByRole('table', { name: 'Customer ledger documents' })
    for (const text of ['Closed', 'Open', 'Overdue', 'Seriously overdue']) {
      expect(within(table).getByText(text)).toBeInTheDocument()
    }
  })

  it('AC-019 shows only the non-null sections, customer above supplier', async () => {
    mockLedger(makeLedger({ supplier: makeSection() }))
    const { unmount } = renderPanel(<LedgerPanel registryId={7} />)

    const headings = (await screen.findAllByRole('heading', { level: 3 })).map((heading) => heading.textContent)
    expect(headings).toEqual(['Customer', 'Supplier'])
    expect(screen.getAllByText('Outstanding').length).toBeGreaterThanOrEqual(2)
    unmount()

    mockLedger(makeLedger({ customer: null }))
    renderPanel(<LedgerPanel registryId={7} />)
    await waitFor(() => expect(screen.queryByRole('status')).not.toBeInTheDocument())
    expect(screen.queryByRole('heading', { name: 'Customer' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Supplier' })).not.toBeInTheDocument()
  })

  it('shows the specific permission sentence on a 403 and the generic text on a 500', async () => {
    const httpError = (status: number, data: unknown) =>
      new AxiosError('failed', undefined, undefined, undefined, { status, data } as AxiosResponse)
    getMock.mockRejectedValue(
      httpError(403, {
        success: false,
        message: 'You do not have the «ledger.viewAny» permission.',
        authorization: { reason: 'missing_permission', permission: 'ledger.viewAny' },
      }),
    )
    const { unmount } = renderPanel(<LedgerPanel registryId={7} />)

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'You do not have the «View list» permission on the «Ledger» module.',
    )
    unmount()

    getMock.mockRejectedValue(httpError(500, { success: false, message: 'Server error' }))
    renderPanel(<LedgerPanel registryId={7} />)

    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the ledger.')
  })
})
