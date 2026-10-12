import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PurchasePaymentDialog } from '@/features/purchase-invoice-installments/purchase-payment-dialog'
import { axiosError, purchaseDetail } from '@/features/purchase-invoice-installments/purchase-installment-fixtures'
import { hasLaterUnpaidInstallments } from '@/features/purchase-invoice-installments/purchase-installment-payment'
import type { PurchaseInstallmentDetail, PurchasePaymentPayload } from '@/features/purchase-invoice-installments/types'

const recordMock = vi.fn<(id: number, payload: PurchasePaymentPayload) => Promise<PurchaseInstallmentDetail>>()
const selectProps = vi.fn<(props: { companyId?: number; value: number | null }) => void>()

vi.mock('@/features/purchase-invoice-installments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoice-installments/api')>()),
  recordPurchasePayment: (id: number, payload: PurchasePaymentPayload) => recordMock(id, payload),
}))
// The shared async select is tested on its own: here only the props the dialog feeds it matter.
vi.mock('@/features/purchase-invoice-installments/purchase-financial-account-select', () => ({
  PurchaseFinancialAccountSelect: (props: {
    companyId?: number
    value: number | null
    onChange: (value: number | null) => void
    label: string
  }) => {
    selectProps({ companyId: props.companyId, value: props.value })
    return (
      <button type="button" aria-label={props.label} onClick={() => props.onChange(11)}>
        {`account ${props.value ?? 'none'}`}
      </button>
    )
  },
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderDialog(detail: PurchaseInstallmentDetail = purchaseDetail()) {
  const onSaved = vi.fn()
  const onClose = vi.fn()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <PurchasePaymentDialog detail={detail} onClose={onClose} onSaved={onSaved} />
    </QueryClientProvider>,
  )
  return { onSaved, onClose }
}

const amountInput = () => screen.getByRole('spinbutton', { name: 'Paid amount' })
const save = () => fireEvent.click(screen.getByRole('button', { name: 'Record' }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  recordMock.mockReset().mockResolvedValue(purchaseDetail())
  selectProps.mockReset()
})

describe('PurchasePaymentDialog (spec 0211 AC-016)', () => {
  it('proposes the document account, scopes the picker to its company and sends a full payment', async () => {
    const { onSaved, onClose } = renderDialog()

    expect(screen.getByRole('button', { name: 'Payment account' })).toHaveTextContent('account 9')
    expect(selectProps).toHaveBeenLastCalledWith({ companyId: 5, value: 9 })
    expect(amountInput()).toHaveValue(400)
    expect(screen.queryByRole('group', { name: 'Residual' })).not.toBeInTheDocument()

    fireEvent.change(screen.getByLabelText('Payment date'), { target: { value: '2026-11-20' } })
    save()

    await waitFor(() =>
      expect(recordMock).toHaveBeenCalledWith(7, { paid_amount: 400, paid_at: '2026-11-20', financial_account_id: 9 }),
    )
    await waitFor(() => expect(onSaved).toHaveBeenCalled())
    expect(onClose).toHaveBeenCalled()
  })

  it('sends the account the user picks instead of the default', async () => {
    renderDialog()

    fireEvent.click(screen.getByRole('button', { name: 'Payment account' }))
    save()

    await waitFor(() => expect(recordMock).toHaveBeenCalled())
    expect(recordMock.mock.calls[0][1]).toMatchObject({ financial_account_id: 11 })
  })

  it('a partial payment asks how to handle the residual and defaults to spread when later installments are open', async () => {
    renderDialog()

    fireEvent.change(amountInput(), { target: { value: '150' } })

    expect(await screen.findByText(/250,00/)).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Spread over the later installments' })).toBeChecked()
    save()

    await waitFor(() => expect(recordMock).toHaveBeenCalled())
    expect(recordMock.mock.calls[0][1]).toMatchObject({ paid_amount: 150, residual_mode: 'spread' })
    expect(recordMock.mock.calls[0][1]).not.toHaveProperty('residual_due_date')
  })

  it('disables spread without later open installments and sends the new installment date', async () => {
    renderDialog(purchaseDetail({ id: 8, sequence: 3, due_date: '2026-12-30' }))

    fireEvent.change(amountInput(), { target: { value: '100' } })

    const spread = await screen.findByRole('radio', { name: 'Spread over the later installments' })
    expect(spread).toBeDisabled()
    expect(screen.getByRole('radio', { name: 'New due date for the residual' })).toBeChecked()
    expect(screen.getByLabelText('New installment date')).toHaveValue('2027-01-29')
    save()

    await waitFor(() => expect(recordMock).toHaveBeenCalled())
    expect(recordMock.mock.calls[0][1]).toMatchObject({
      paid_amount: 100,
      residual_mode: 'new_installment',
      residual_due_date: '2027-01-29',
    })
  })

  it('knows from the document installments whether a residual can be spread', () => {
    const { installments } = purchaseDetail().purchase_invoice
    expect(hasLaterUnpaidInstallments(1, installments)).toBe(true)
    expect(hasLaterUnpaidInstallments(2, installments)).toBe(true)
    expect(hasLaterUnpaidInstallments(3, installments)).toBe(false)
  })

  it('rejects an amount above the installment without calling the server', async () => {
    renderDialog()

    fireEvent.change(amountInput(), { target: { value: '400.01' } })
    save()

    expect(await screen.findByRole('alert')).toHaveTextContent('cannot exceed the installment amount')
    expect(amountInput()).toHaveAttribute('aria-invalid', 'true')
    expect(recordMock).not.toHaveBeenCalled()
  })

  it('maps a 422 on the account to the localized company message', async () => {
    recordMock.mockRejectedValue(
      axiosError(422, { errors: { financial_account_id: ['The account must belong to the document company.'] } }),
    )
    renderDialog()

    save()

    expect(await screen.findByText('The account must belong to the document company.')).toBeInTheDocument()
  })
})
