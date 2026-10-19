import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { EMPTY_INVOICES, INVOICES } from '@/features/work-order-invoices/work-order-invoices-fixtures'
import { WorkOrderInvoicesSection } from '@/features/work-order-invoices/work-order-invoices-section'
import type { WorkOrderInvoices } from '@/features/work-order-invoices/types'

const fetchMock = vi.fn<(id: number) => Promise<WorkOrderInvoices>>()
vi.mock('@/features/work-order-invoices/api', () => ({
  fetchWorkOrderInvoices: (id: number) => fetchMock(id),
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission) }),
}))

vi.mock('@/features/invoices/invoice-detail-sheet', () => ({
  InvoiceDetailSheet: ({ invoiceId, onChanged }: { invoiceId: number | null; onChanged: () => void }) =>
    invoiceId === null ? null : (
      <div>
        <span>active-sheet {invoiceId}</span>
        <button type="button" onClick={onChanged}>changed</button>
      </div>
    ),
}))
vi.mock('@/features/purchase-invoices/purchase-invoice-detail-sheet', () => ({
  PurchaseInvoiceDetailSheet: ({ invoiceId }: { invoiceId: number | null }) =>
    invoiceId === null ? null : <span>passive-sheet {invoiceId}</span>,
}))

function renderSections(kinds: Array<'active' | 'passive'>) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      {kinds.map((kind) => (
        <WorkOrderInvoicesSection key={kind} workOrderId={4} kind={kind} />
      ))}
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchMock.mockReset()
  fetchMock.mockResolvedValue(INVOICES)
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('WorkOrderInvoicesSection active table (spec 0282 AC-008)', () => {
  it('shows one row per document, the badge, the status and the totals row', async () => {
    renderSections(['active'])

    const table = await screen.findByRole('table', { name: /customer proforma and invoices/i })
    const headers = within(table).getAllByRole('columnheader').map((th) => th.textContent)
    expect(headers).toEqual([
      'Type',
      'Number',
      'Date',
      'External number',
      'Customer',
      'Total',
      'Collected',
      'Residual',
      'Collection status',
    ])
    const rows = within(table).getAllByRole('row')
    expect(rows).toHaveLength(4)
    expect(within(rows[1]).getByText('Invoice')).toBeInTheDocument()
    expect(within(rows[1]).getByText('FT-77')).toBeInTheDocument()
    expect(within(rows[1]).getAllByText('Acme Spa')).toHaveLength(1)
    expect(within(rows[1]).getByText('30/09/2026')).toBeInTheDocument()
    expect(within(rows[2]).getByText('Proforma')).toBeInTheDocument()
    expect(within(rows[3]).getByText('Totals')).toBeInTheDocument()
    expect(rows[3].textContent).toContain('1342,00')
    expect(rows[3].textContent).toContain('842,00')
  })

  it('opens the document sheet from the number with invoices.view, and refreshes the tabs on change', async () => {
    renderSections(['active'])

    fireEvent.click(await screen.findByRole('button', { name: '12/2026' }))

    expect(screen.getByText('active-sheet 11')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'changed' }))
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2))
  })

  it('renders the number as plain text without invoices.view and has no row action', async () => {
    canMock.mockReturnValue(false)
    renderSections(['active'])

    expect(await screen.findByText('12/2026')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
  })

  it('shows the empty message with no documents', async () => {
    fetchMock.mockResolvedValue(EMPTY_INVOICES)
    renderSections(['active'])

    expect(await screen.findByText('No active proforma or invoice for this work order.')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })
})

describe('WorkOrderInvoicesSection passive table (spec 0282 AC-008)', () => {
  it('shows the document total, the work order share, paid, residual and the totals row', async () => {
    renderSections(['passive'])

    const table = await screen.findByRole('table', { name: /supplier proforma and invoices/i })
    expect(within(table).getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
      'Type',
      'Number',
      'Date',
      'Supplier',
      'Document total',
      'Work order share',
      'Paid',
      'Residual',
    ])
    const rows = within(table).getAllByRole('row')
    expect(rows).toHaveLength(3)
    expect(within(rows[1]).getByText('Forniture Srl')).toBeInTheDocument()
    expect(rows[1].textContent).toContain('150,00')
    expect(within(rows[2]).getByText('Totals')).toBeInTheDocument()
    expect(rows[2].textContent).toContain('130,00')
  })

  it('opens the passive sheet only with purchase-invoices.view', async () => {
    canMock.mockImplementation((permission) => permission === 'purchase-invoices.view')
    renderSections(['passive'])

    fireEvent.click(await screen.findByRole('button', { name: 'PF-900' }))

    expect(screen.getByText('passive-sheet 21')).toBeInTheDocument()
  })

  it('keeps the number as text when only invoices.view is granted', async () => {
    canMock.mockImplementation((permission) => permission === 'invoices.view')
    renderSections(['passive'])

    expect(await screen.findByText('PF-900')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('shows the empty message with no documents', async () => {
    fetchMock.mockResolvedValue(EMPTY_INVOICES)
    renderSections(['passive'])

    expect(await screen.findByText('No passive proforma or invoice for this work order.')).toBeInTheDocument()
  })
})

describe('WorkOrderInvoicesSection states (spec 0282 AC-010)', () => {
  it('shows a skeleton while loading', () => {
    fetchMock.mockReturnValue(new Promise(() => undefined))
    renderSections(['active'])

    expect(screen.getByRole('status', { name: 'Loading documents' })).toBeInTheDocument()
  })

  it('shows an error alert with retry when the request fails', async () => {
    fetchMock.mockRejectedValueOnce(new Error('boom'))
    renderSections(['active'])

    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the work order proforma and invoices.')
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(await screen.findByRole('table')).toBeInTheDocument()
  })

  it('shares one request between the active and the passive tab', async () => {
    renderSections(['active', 'passive'])

    expect(await screen.findAllByRole('table')).toHaveLength(2)
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })
})
