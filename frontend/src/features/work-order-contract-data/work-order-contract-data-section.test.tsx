import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { WorkOrderContractDataSection } from '@/features/work-order-contract-data/work-order-contract-data-section'
import {
  PAID_LINE,
  CONTRACT_DATA,
  CONTRACT_DATA_HIDDEN_COMMISSIONS,
  RECEIVED_LINE,
} from '@/features/work-order-contract-data/contract-data-fixtures'
import type {
  ContractDataLine,
  UpdateContractLinePayload,
  WorkOrderContractData,
} from '@/features/work-order-contract-data/types'

const fetchMock = vi.fn<(id: number) => Promise<WorkOrderContractData>>()
const updateMock = vi.fn<(id: number, lineId: number, payload: UpdateContractLinePayload) => Promise<ContractDataLine>>()
vi.mock('@/features/work-order-contract-data/api', () => ({
  fetchWorkOrderContractData: (id: number) => fetchMock(id),
  updateContractDataLine: (id: number, lineId: number, payload: UpdateContractLinePayload) =>
    updateMock(id, lineId, payload),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params) }
})

const STATUS_ITEMS = [
  { id: 3, label: 'Pagato', name: 'Pagato', color: 'green', allows_delivery: true },
  { id: 4, label: 'Da pagare', name: 'Da pagare', color: 'red', allows_delivery: false },
]

function renderSection(canManage: boolean) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <WorkOrderContractDataSection workOrderId={4} canManage={canManage} />
    </QueryClientProvider>,
  )
}

function unprocessable(errors: Record<string, string[]>): AxiosError {
  return new AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422,
    data: { message: 'invalid', errors },
  } as AxiosResponse)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchMock.mockReset()
  updateMock.mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue({
    items: STATUS_ITEMS,
    pagination: { offset: 0, limit: 25, total: null, has_more: false },
    export_link: null,
  })
  fetchMock.mockResolvedValue(CONTRACT_DATA)
})

describe('WorkOrderContractDataSection - states', () => {
  it('shows a loading status, then the table', async () => {
    renderSection(false)

    expect(screen.getByRole('status', { name: 'Loading contract data' })).toBeInTheDocument()
    expect(await screen.findByRole('table')).toBeInTheDocument()
  })

  it('shows an error with retry', async () => {
    fetchMock.mockRejectedValueOnce(new Error('boom'))
    renderSection(false)

    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the work order contract data.')
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    expect(await screen.findByRole('table')).toBeInTheDocument()
  })

  it('shows the empty state without a table', async () => {
    fetchMock.mockResolvedValue({ ...CONTRACT_DATA, lines: [] })
    renderSection(false)

    expect(await screen.findByText('The work order has no product lines.')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })
})

describe('WorkOrderContractDataSection - formulas and totals (AC-016)', () => {
  it('renders every column, the readable formulas and the totals', async () => {
    renderSection(false)
    await screen.findByRole('table')

    for (const name of ['Product', 'Net amount', 'Supplier commission', 'Net of commissions', 'Effective revenue', 'Payment']) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument()
    }
    expect(screen.getByText('Net amount 2 × 500.00 = 1,000.00 → revenue 1,000.00 · Supplier commission paid: 15% of 1,000.00 = 150.00 (a cost, it does not reduce the revenue)')).toBeInTheDocument()
    expect(screen.getByText('Supplier commission received: 10% of 2,000.00 = 200.00 → revenue 200.00')).toBeInTheDocument()
    expect(screen.getByText('Net of commissions: 1,000.00 − 250.00 = 750.00')).toBeInTheDocument()

    const totals = screen.getAllByRole('definition').map((node) => node.textContent)
    expect(totals).toEqual([
      '3,000.00',
      'Net amount 1,000.00Revenue 1,000.00',
      'Net amount 2,000.00Revenue 200.00',
      'Net amount 0.00Revenue 0.00',
      '1,200.00',
      '450.00',
      '2,550.00',
    ])
    expect(screen.getByText('Formazione', { selector: 'dt' })).toBeInTheDocument()
    expect(screen.getByText('Total revenue')).toBeInTheDocument()
  })

  it('drops the commission columns and totals when commissions are not visible', async () => {
    fetchMock.mockResolvedValue(CONTRACT_DATA_HIDDEN_COMMISSIONS)
    renderSection(false)
    await screen.findByRole('table')

    expect(screen.queryByRole('columnheader', { name: 'Supplier commission' })).not.toBeInTheDocument()
    expect(screen.queryByRole('columnheader', { name: 'Net of commissions' })).not.toBeInTheDocument()
    expect(screen.queryByText('Commissions')).not.toBeInTheDocument()
    expect(screen.getAllByRole('definition')).toHaveLength(5)
    expect(screen.getByText('Supplier commission received: revenue 200.00')).toBeInTheDocument()
  })

  it('keeps the table inside its own horizontal scroll container', async () => {
    renderSection(false)

    expect((await screen.findByRole('table')).parentElement).toHaveClass('overflow-x-auto')
  })
})

describe('WorkOrderContractDataSection - warnings (AC-018)', () => {
  it('shows each warning as text, not only as colour', async () => {
    fetchMock.mockResolvedValue({
      ...CONTRACT_DATA,
      lines: [
        { ...RECEIVED_LINE, supplier_commission: null, effective_revenue: '0.00', warnings: ['missing_supplier_commission'] },
        { ...PAID_LINE, warnings: ['stale_commission_base'] },
      ],
    })
    renderSection(false)
    await screen.findByRole('table')

    expect(screen.getByText('Supplier commission received missing: the revenue is 0.00.')).toBeInTheDocument()
    expect(screen.getByText('Amount calculated on a previous base: save the quote again to update it.')).toBeInTheDocument()
  })
})

describe('WorkOrderContractDataSection - payment display and editor (AC-017)', () => {
  it('shows status, agreement and unpaid flag, and no pencil without manage_payments', async () => {
    renderSection(false)
    await screen.findByRole('table')

    expect(screen.getByText('Pagato')).toBeInTheDocument()
    expect(screen.getByText('Saldo a 30 giorni')).toBeInTheDocument()
    expect(screen.getAllByText(/^Unpaid:/).map((node) => node.textContent)).toEqual(['Unpaid: No', 'Unpaid: Yes'])
    expect(screen.queryByRole('button', { name: /Edit the payment/ })).not.toBeInTheDocument()
  })

  it('sends only the changed keys on Done and updates the row from the answer', async () => {
    const saved: ContractDataLine = {
      ...PAID_LINE,
      payment: { status: STATUS_ITEMS[1], payment_agreement: 'Rate mensili', has_unpaid: false },
    }
    updateMock.mockResolvedValue(saved)
    renderSection(true)
    await screen.findByRole('table')

    fireEvent.click(screen.getByRole('button', { name: 'Edit the payment of Consulenza qualita' }))
    fireEvent.keyDown(await screen.findByRole('combobox', { name: 'Payment status' }), { key: 'Enter' })
    fireEvent.click(await screen.findByRole('option', { name: 'Da pagare' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Payment agreement' }), { target: { value: 'Rate mensili' } })
    fireEvent.click(screen.getByRole('button', { name: 'Done' }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledTimes(1))
    expect(updateMock).toHaveBeenCalledWith(4, 11, { work_order_payment_status_id: 4, payment_agreement: 'Rate mensili' })
    expect(await screen.findByText('Rate mensili')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Done' })).not.toBeInTheDocument()
  })

  it('sends the unpaid flag alone when it is the only change', async () => {
    updateMock.mockResolvedValue({ ...RECEIVED_LINE, payment: { ...RECEIVED_LINE.payment, has_unpaid: false } })
    renderSection(true)
    await screen.findByRole('table')

    fireEvent.click(screen.getByRole('button', { name: 'Edit the payment of Certificazione ente' }))
    fireEvent.click(screen.getByRole('switch', { name: 'Unpaid' }))
    fireEvent.click(screen.getByRole('button', { name: 'Done' }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(4, 12, { has_unpaid: false }))
  })

  it('makes no request on Done when nothing changed, and Reset discards the draft', async () => {
    renderSection(true)
    await screen.findByRole('table')

    fireEvent.click(screen.getByRole('button', { name: 'Edit the payment of Certificazione ente' }))
    fireEvent.click(screen.getByRole('button', { name: 'Done' }))
    await waitFor(() => expect(screen.queryByRole('button', { name: 'Done' })).not.toBeInTheDocument())
    expect(updateMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'Edit the payment of Certificazione ente' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Payment agreement' }), { target: { value: 'altro' } })
    fireEvent.click(screen.getByRole('button', { name: 'Reset' }))

    expect(screen.queryByRole('textbox', { name: 'Payment agreement' })).not.toBeInTheDocument()
    expect(screen.getByText('Saldo a 30 giorni')).toBeInTheDocument()
    expect(updateMock).not.toHaveBeenCalled()
  })

  it('keeps the editor open and shows the server message on a refused save', async () => {
    updateMock.mockRejectedValue(unprocessable({ work_order_payment_status_id: ['The selected status is not active.'] }))
    renderSection(true)
    await screen.findByRole('table')

    fireEvent.click(screen.getByRole('button', { name: 'Edit the payment of Certificazione ente' }))
    fireEvent.click(screen.getByRole('switch', { name: 'Unpaid' }))
    fireEvent.click(screen.getByRole('button', { name: 'Done' }))

    const editor = screen.getByRole('textbox', { name: 'Payment agreement' }).closest('td') as HTMLElement
    expect(await within(editor).findByRole('alert')).toHaveTextContent('The selected status is not active.')
    expect(screen.getByRole('button', { name: 'Done' })).toBeInTheDocument()
  })

  it('blocks an over-long agreement client-side with an accessible error', async () => {
    renderSection(true)
    await screen.findByRole('table')

    fireEvent.click(screen.getByRole('button', { name: 'Edit the payment of Consulenza qualita' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Payment agreement' }), { target: { value: 'a'.repeat(2001) } })
    fireEvent.click(screen.getByRole('button', { name: 'Done' }))

    expect(await screen.findByText('The agreement can be at most 2000 characters.')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Payment agreement' })).toHaveAttribute('aria-invalid', 'true')
    expect(updateMock).not.toHaveBeenCalled()
  })
})
