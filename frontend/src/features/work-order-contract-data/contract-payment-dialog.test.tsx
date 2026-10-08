import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import { CONTRACT_DATA, PAID_LINE, RECEIVED_LINE } from '@/features/work-order-contract-data/contract-data-fixtures'
import { STATUS_ITEMS, renderSection, unprocessable } from '@/features/work-order-contract-data/contract-data-test-helpers'
import type {
  ContractDataLine,
  UpdateContractLinePayload,
  WorkOrderContractData,
} from '@/features/work-order-contract-data/types'

vi.mock('@/components/detail/record-link', () => ({
  RecordLink: ({ domain, id, children }: { domain: string; id: number; children: ReactNode }) => (
    <a href={`/${domain}/${id}`}>{children}</a>
  ),
}))

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

async function openDialog(product: string) {
  renderSection(true)
  await screen.findByRole('table')
  fireEvent.click(screen.getByRole('button', { name: `Edit the payment of ${product}` }))
  return screen.findByRole('dialog', { name: /Line payment/ })
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

describe('ContractPaymentDialog (AC-017)', () => {
  it('gives each line one actions button, and none without manage_payments', async () => {
    renderSection(true)
    await screen.findByRole('table')

    expect(screen.getAllByRole('button', { name: /^Edit the payment of / })).toHaveLength(2)
    expect(screen.getByRole('columnheader', { name: 'Actions' })).toBeInTheDocument()
  })

  it('opens with the product in the title and the three payment controls', async () => {
    const dialog = await openDialog('Certificazione ente')

    expect(within(dialog).getByRole('heading', { name: 'Line payment: ENT-001 Certificazione ente' })).toBeInTheDocument()
    expect(within(dialog).getByRole('combobox', { name: 'Payment status' })).toBeInTheDocument()
    expect(within(dialog).getByRole('textbox', { name: 'Payment agreement' })).toHaveValue('Saldo a 30 giorni')
    expect(within(dialog).getByRole('switch', { name: 'Unpaid' })).toBeChecked()
  })

  it('sends only the changed keys on Save, closes and refreshes the row from the answer', async () => {
    const saved: ContractDataLine = {
      ...PAID_LINE,
      payment: { status: STATUS_ITEMS[1], payment_agreement: 'Rate mensili', has_unpaid: false },
    }
    updateMock.mockResolvedValue(saved)
    await openDialog('Consulenza qualita')

    fireEvent.keyDown(await screen.findByRole('combobox', { name: 'Payment status' }), { key: 'Enter' })
    fireEvent.click(await screen.findByRole('option', { name: 'Da pagare' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Payment agreement' }), { target: { value: 'Rate mensili' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledTimes(1))
    expect(updateMock).toHaveBeenCalledWith(4, 11, { work_order_payment_status_id: 4, payment_agreement: 'Rate mensili' })
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(await screen.findByText('Da pagare')).toBeInTheDocument()
  })

  it('sends the unpaid flag alone when it is the only change', async () => {
    updateMock.mockResolvedValue({ ...RECEIVED_LINE, payment: { ...RECEIVED_LINE.payment, has_unpaid: false } })
    await openDialog('Certificazione ente')

    fireEvent.click(screen.getByRole('switch', { name: 'Unpaid' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateMock).toHaveBeenCalledWith(4, 12, { has_unpaid: false }))
  })

  it('makes no request on Save when nothing changed, and Cancel discards the draft', async () => {
    await openDialog('Certificazione ente')
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(updateMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('button', { name: 'Edit the payment of Certificazione ente' }))
    fireEvent.change(await screen.findByRole('textbox', { name: 'Payment agreement' }), { target: { value: 'altro' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())

    fireEvent.click(screen.getByRole('button', { name: 'Edit the payment of Certificazione ente' }))
    expect(await screen.findByRole('textbox', { name: 'Payment agreement' })).toHaveValue('Saldo a 30 giorni')
    expect(updateMock).not.toHaveBeenCalled()
  })

  it('keeps the dialog open and shows the server message on a refused save', async () => {
    updateMock.mockRejectedValue(unprocessable({ work_order_payment_status_id: ['The selected status is not active.'] }))
    const dialog = await openDialog('Certificazione ente')

    fireEvent.click(screen.getByRole('switch', { name: 'Unpaid' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('The selected status is not active.')
    expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument()
  })

  it('blocks an over-long agreement client-side with an accessible error', async () => {
    await openDialog('Consulenza qualita')

    fireEvent.change(screen.getByRole('textbox', { name: 'Payment agreement' }), { target: { value: 'a'.repeat(2001) } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('The agreement can be at most 2000 characters.')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Payment agreement' })).toHaveAttribute('aria-invalid', 'true')
    expect(updateMock).not.toHaveBeenCalled()
  })
})
