import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PurchaseInstallmentBulkPaymentDialog } from '@/features/purchase-invoice-installments/purchase-installment-bulk-payment-dialog'
import type { BulkPaymentTarget } from '@/features/purchase-invoice-installments/purchase-installment-bulk-payment'
import { axiosError } from '@/features/purchase-invoice-installments/purchase-installment-fixtures'
import type { BulkPaymentPayload, BulkPaymentResult } from '@/features/purchase-invoice-installments/types'

const bulkMock = vi.fn<(payload: BulkPaymentPayload) => Promise<BulkPaymentResult>>()
const toastError = vi.fn()

vi.mock('@/features/purchase-invoice-installments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoice-installments/api')>()),
  bulkPayInstallments: (payload: BulkPaymentPayload) => bulkMock(payload),
}))
vi.mock('@/features/purchase-invoice-installments/purchase-financial-account-select', () => ({
  PurchaseFinancialAccountSelect: (props: { onChange: (value: number | null) => void; label: string }) => (
    <button type="button" aria-label={props.label} onClick={() => props.onChange(9)}>
      pick
    </button>
  ),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: (message: string) => toastError(message) } }))

const TARGETS: BulkPaymentTarget[] = [
  { id: 1, documentLabel: 'F-12', sequence: 1, dueDate: '2026-10-31', amount: 1000 },
  { id: 2, documentLabel: 'F-15', sequence: 1, dueDate: '2026-11-30', amount: 500 },
]

function renderDialog() {
  const onSaved = vi.fn()
  const onClose = vi.fn()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <PurchaseInstallmentBulkPaymentDialog targets={TARGETS} onClose={onClose} onSaved={onSaved} />
    </QueryClientProvider>,
  )
  return { onSaved, onClose }
}

const amountInputs = () => screen.getAllByRole('spinbutton', { name: 'Paid amount' })
const saveButton = () => screen.getByRole('button', { name: 'Save' })
const typeAmount = (index: number, value: string) => fireEvent.change(amountInputs()[index], { target: { value } })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  bulkMock.mockReset().mockResolvedValue({ paid_count: 2, residual_count: 1 })
  toastError.mockReset()
})

describe('PurchaseInstallmentBulkPaymentDialog (spec 0211 AC-017)', () => {
  it('starts with empty amounts, a zero total and Save disabled', () => {
    renderDialog()

    expect(amountInputs().map((input) => (input as HTMLInputElement).value)).toEqual(['', ''])
    expect(screen.getByText('Total paid').nextSibling?.textContent).toMatch(/0,00/)
    expect(saveButton()).toBeDisabled()
  })

  it('sums the typed amounts and sends the contract payload with date, account and only the filled rows', async () => {
    const { onSaved, onClose } = renderDialog()

    fireEvent.change(screen.getByLabelText('Payment date'), { target: { value: '2026-11-02' } })
    fireEvent.click(screen.getByRole('button', { name: 'Payment account' }))
    typeAmount(0, '600')
    typeAmount(1, '500')
    await waitFor(() => expect(screen.getByText('Total paid').nextSibling?.textContent).toMatch(/1\.?100,00/))

    typeAmount(1, '')
    await waitFor(() => expect(saveButton()).toBeEnabled())
    fireEvent.click(saveButton())

    await waitFor(() => expect(onSaved).toHaveBeenCalled())
    expect(bulkMock).toHaveBeenCalledWith({
      paid_at: '2026-11-02',
      financial_account_id: 9,
      items: [{ installment_id: 1, paid_amount: 600 }],
    })
    expect(onClose).toHaveBeenCalled()
  })

  it('an amount above the installment shows the error and disables Save', async () => {
    renderDialog()

    typeAmount(0, '100')
    typeAmount(1, '500.01')

    expect(await screen.findByText(/cannot exceed the installment/)).toBeInTheDocument()
    expect(saveButton()).toBeDisabled()
  })

  it('maps a server 422 on a sent item back onto its dialog row', async () => {
    bulkMock.mockRejectedValue(
      axiosError(422, { errors: { 'items.0.installment_id': ['This installment is already paid.'] } }),
    )
    renderDialog()

    typeAmount(1, '200')
    await waitFor(() => expect(saveButton()).toBeEnabled())
    fireEvent.click(saveButton())

    expect(await screen.findByText('This installment has already been paid.')).toBeInTheDocument()
  })

  it('maps a 422 on the items to the one-supplier message and on the account to the company message', async () => {
    bulkMock.mockRejectedValueOnce(
      axiosError(422, { errors: { items: ['The installments must belong to the same supplier.'] } }),
    )
    renderDialog()

    typeAmount(0, '200')
    await waitFor(() => expect(saveButton()).toBeEnabled())
    fireEvent.click(saveButton())
    await waitFor(() => expect(toastError).toHaveBeenCalledWith('The installments must belong to the same supplier.'))

    bulkMock.mockRejectedValueOnce(
      axiosError(422, { errors: { financial_account_id: ['The account must belong to the document company.'] } }),
    )
    fireEvent.click(saveButton())
    expect(await screen.findByText('The account must belong to the company of every selected document.')).toBeInTheDocument()
  })
})
