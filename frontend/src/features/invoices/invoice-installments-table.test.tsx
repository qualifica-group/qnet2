import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { invoices } from '@/i18n/locales/en-invoices'
import { InvoiceClearCollectionDialog } from '@/features/invoices/invoice-clear-collection-dialog'
import { InvoiceInstallmentsTable } from '@/features/invoices/invoice-installments-table'
import { useInvoiceCollectionActions } from '@/features/invoices/use-invoice-collection-actions'
import type { Invoice, InvoiceInstallment } from '@/features/invoices/types'

const clearMock = vi.fn<(installmentId: number) => Promise<Invoice>>()

vi.mock('@/features/invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoices/api')>()),
  clearCollection: (installmentId: number) => clearMock(installmentId),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const COLLECTED: InvoiceInstallment = {
  id: 1,
  sequence: 1,
  due_date: '2026-11-05',
  amount: '200.00',
  payment_method_code: null,
  collected_amount: '200.00',
  collected_at: '2026-11-06',
  status: 'paid',
}
const OPEN: InvoiceInstallment = {
  ...COLLECTED,
  id: 2,
  sequence: 2,
  collected_amount: null,
  collected_at: null,
  status: 'unpaid',
}

function Harness() {
  const actions = useInvoiceCollectionActions()
  return (
    <>
      <InvoiceInstallmentsTable
        installments={[COLLECTED, OPEN]}
        canCollect
        busyInstallmentId={actions.busyInstallmentId}
        onRecord={actions.openCollect}
        onClear={actions.requestClear}
      />
      <InvoiceClearCollectionDialog
        target={actions.clearTarget}
        isPending={actions.isClearing}
        onClose={actions.closeClear}
        onConfirm={actions.confirmClear}
      />
    </>
  )
}

function conflict(): AxiosError {
  const error = new AxiosError('conflict', 'ERR_BAD_REQUEST')
  error.response = { status: 409, data: { message: 'Clear the later collections first.' } } as AxiosResponse
  return error
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { invoices }, true, true)
})

beforeEach(() => {
  clearMock.mockReset()
  vi.mocked(toast.error).mockReset()
})

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <Harness />
    </QueryClientProvider>,
  )
}

describe('InvoiceInstallmentsTable collection actions (spec 0196 AC-023)', () => {
  it('offers "Record collection" only on open installments and "Clear collection" only on collected ones', () => {
    renderTable()
    const [, collectedRow, openRow] = screen.getAllByRole('row')

    expect(within(collectedRow).queryByRole('button', { name: 'Record collection' })).not.toBeInTheDocument()
    expect(within(collectedRow).getByRole('button', { name: 'Clear collection' })).toBeInTheDocument()
    expect(within(openRow).getByRole('button', { name: 'Record collection' })).toBeInTheDocument()
    expect(within(openRow).queryByRole('button', { name: 'Clear collection' })).not.toBeInTheDocument()
  })

  it('asks for confirmation before clearing and does nothing when the user goes back', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Clear collection' }))
    const dialog = await screen.findByRole('alertdialog', { name: 'Clear the collection?' })
    expect(within(dialog).getByText(/installment 1 will be cleared/)).toBeInTheDocument()

    fireEvent.click(within(dialog).getByRole('button', { name: 'Back' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(clearMock).not.toHaveBeenCalled()
  })

  it('clears the collection once confirmed', async () => {
    clearMock.mockResolvedValue({} as Invoice)
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Clear collection' }))
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Clear collection' }))

    await waitFor(() => expect(clearMock).toHaveBeenCalledWith(1))
  })

  it('shows the "clear the later collections first" message on a 409 of the clear', async () => {
    clearMock.mockRejectedValue(conflict())
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Clear collection' }))
    fireEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Clear collection' }))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith('Unable to clear: clear the later collections first.'),
    )
  })
})
