import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { CONTRACT_DATA, PAID_LINE, RECEIVED_LINE } from '@/features/work-order-contract-data/contract-data-fixtures'
import { STATUS_ITEMS, renderSection, unprocessable } from '@/features/work-order-contract-data/contract-data-test-helpers'
import type { BulkUpdateContractLinesPayload, WorkOrderContractData } from '@/features/work-order-contract-data/types'

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/components/detail/record-link', () => ({
  RecordLink: ({ domain, id, children }: { domain: string; id: number; children: ReactNode }) => (
    <a href={`/${domain}/${id}`}>{children}</a>
  ),
}))

const fetchMock = vi.fn<(id: number) => Promise<WorkOrderContractData>>()
const bulkMock = vi.fn<(id: number, payload: BulkUpdateContractLinesPayload) => Promise<WorkOrderContractData>>()
vi.mock('@/features/work-order-contract-data/api', () => ({
  fetchWorkOrderContractData: (id: number) => fetchMock(id),
  updateContractDataLine: vi.fn(),
  bulkUpdateContractDataLines: (id: number, payload: BulkUpdateContractLinesPayload) => bulkMock(id, payload),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params) }
})

/** The header and the narrow-layout "select all" share the name; jsdom renders both. */
const selectAll = () => screen.getAllByRole('checkbox', { name: 'Select all lines' })[0]

async function chooseOption(combobox: string, option: string) {
  fireEvent.keyDown(screen.getByRole('combobox', { name: combobox }), { key: 'Enter' })
  fireEvent.click(await screen.findByRole('option', { name: option }))
}

async function openBulkDialog() {
  renderSection(true)
  await screen.findByRole('table')
  fireEvent.click(selectAll())
  fireEvent.click(screen.getByRole('button', { name: 'Set payment' }))
  return screen.findByRole('dialog', { name: 'Payment of 2 lines' })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchMock.mockReset()
  bulkMock.mockReset()
  vi.mocked(toast.success).mockReset()
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue({
    items: STATUS_ITEMS,
    pagination: { offset: 0, limit: 25, total: null, has_more: false },
    export_link: null,
  })
  fetchMock.mockResolvedValue(CONTRACT_DATA)
})

describe('Contract data bulk payment (spec 0333)', () => {
  it('AC-005: no selection checkboxes nor toolbar without manage_payments', async () => {
    renderSection(false)
    await screen.findByRole('table')

    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('toolbar')).not.toBeInTheDocument()
  })

  it('AC-004: selecting lines shows the toolbar with the count; select all toggles every line', async () => {
    renderSection(true)
    await screen.findByRole('table')
    expect(screen.queryByRole('toolbar')).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Select Consulenza qualita' }))
    expect(within(screen.getByRole('toolbar')).getByText('1 line selected')).toBeInTheDocument()
    expect(selectAll()).toHaveAttribute('data-state', 'indeterminate')

    fireEvent.click(selectAll())
    expect(screen.getByText('2 lines selected')).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'Select Certificazione ente' })).toBeChecked()

    fireEvent.click(selectAll())
    expect(screen.queryByRole('toolbar')).not.toBeInTheDocument()
  })

  it('AC-004: sends only the chosen values for the selected lines, caches the answer and clears the selection', async () => {
    const paidStatus = { id: 3, name: 'Pagato', color: 'green', allows_delivery: true }
    bulkMock.mockResolvedValue({
      ...CONTRACT_DATA,
      lines: [PAID_LINE, RECEIVED_LINE].map((line) => ({ ...line, payment: { ...line.payment, status: paidStatus } })),
    })
    await openBulkDialog()

    await chooseOption('Payment status', 'Pagato')
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() => expect(bulkMock).toHaveBeenCalledWith(4, { quote_line_ids: [11, 12], work_order_payment_status_id: 3 }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(toast.success).toHaveBeenCalledWith('Payment updated on 2 lines.')
    expect(screen.queryByRole('toolbar')).not.toBeInTheDocument()
    expect(await screen.findAllByText('Pagato')).toHaveLength(2)
  })

  it('AC-004: the unpaid flag alone, and "No status" clears the status', async () => {
    bulkMock.mockResolvedValue(CONTRACT_DATA)
    await openBulkDialog()

    await chooseOption('Payment status', 'No status')
    await chooseOption('Unpaid', 'Yes')
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    await waitFor(() =>
      expect(bulkMock).toHaveBeenCalledWith(4, { quote_line_ids: [11, 12], work_order_payment_status_id: null, has_unpaid: true }),
    )
  })

  it('AC-004: with every field on "Keep unchanged" nothing is sent and the error is accessible', async () => {
    await openBulkDialog()

    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(await screen.findByText('Choose at least one value to set.')).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Payment status' })).toHaveAttribute('aria-invalid', 'true')
    expect(bulkMock).not.toHaveBeenCalled()
  })

  it('AC-004: a refused save keeps the dialog open with the server message and the selection', async () => {
    bulkMock.mockRejectedValue(unprocessable({ 'quote_line_ids.1': ['The selected line is not part of the work order.'] }))
    const dialog = await openBulkDialog()

    await chooseOption('Unpaid', 'No')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Apply' }))

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('The selected line is not part of the work order.')
    expect(screen.getByText('2 lines selected')).toBeInTheDocument()
  })
})
