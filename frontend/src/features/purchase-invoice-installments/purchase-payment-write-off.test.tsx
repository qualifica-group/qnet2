import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PurchasePaymentDialog } from '@/features/purchase-invoice-installments/purchase-payment-dialog'
import { purchaseDetail } from '@/features/purchase-invoice-installments/purchase-installment-fixtures'
import type { PurchaseInstallmentDetail, PurchasePaymentPayload } from '@/features/purchase-invoice-installments/types'

const recordMock = vi.fn<(id: number, payload: PurchasePaymentPayload) => Promise<PurchaseInstallmentDetail>>()
let threshold: string | undefined

vi.mock('@/features/purchase-invoice-installments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoice-installments/api')>()),
  recordPurchasePayment: (id: number, payload: PurchasePaymentPayload) => recordMock(id, payload),
}))
vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({
    data: { enums: {}, localization: {}, ...(threshold ? { treasury: { write_off_suggest_threshold: threshold } } : {}) },
  }),
}))
vi.mock('@/features/purchase-invoice-installments/purchase-financial-account-select', () => ({
  PurchaseFinancialAccountSelect: (props: { label: string }) => <button type="button" aria-label={props.label} />,
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <PurchasePaymentDialog detail={purchaseDetail()} onClose={vi.fn()} onSaved={vi.fn()} />
    </QueryClientProvider>,
  )
}

const amountInput = () => screen.getByRole('spinbutton', { name: 'Paid amount' })
const setAmount = (value: string) => fireEvent.change(amountInput(), { target: { value } })
const save = () => fireEvent.click(screen.getByRole('button', { name: 'Record' }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  threshold = '5.00'
  recordMock.mockReset().mockResolvedValue(purchaseDetail())
})

describe('PurchasePaymentDialog write-off (spec 0361 AC-012)', () => {
  it('proposes write-off within the threshold and sends reason and note, not the amount', async () => {
    renderDialog()
    setAmount('399.80')

    expect(await screen.findByRole('radio', { name: 'Write-off / bad debt' })).toBeChecked()
    expect(screen.getByRole('radio', { name: 'Write-off' })).toBeChecked()
    fireEvent.change(screen.getByLabelText('Note (optional)'), { target: { value: 'rounding' } })
    save()

    await waitFor(() => expect(recordMock).toHaveBeenCalledTimes(1))
    const body = recordMock.mock.calls[0][1]
    expect(body).toMatchObject({
      paid_amount: 399.8,
      residual_mode: 'write_off',
      write_off_reason: 'rounding',
      write_off_note: 'rounding',
    })
    expect(body).not.toHaveProperty('write_off_amount')
  })

  it('proposes a new installment above the threshold', async () => {
    renderDialog()
    setAmount('300')

    expect(await screen.findByRole('radio', { name: 'New due date for the residual' })).toBeChecked()
    expect(screen.getByRole('radio', { name: 'Write-off / bad debt' })).not.toBeChecked()
  })
})

describe('PurchasePaymentDialog zero amount (spec 0361 AC-013)', () => {
  it('blocks a zero amount with an announced error unless write-off is chosen', async () => {
    renderDialog()
    setAmount('0')
    save()

    expect(await screen.findByRole('alert')).toHaveTextContent('only allowed with Write-off / bad debt')
    expect(amountInput()).toHaveAttribute('aria-invalid', 'true')
    expect(recordMock).not.toHaveBeenCalled()
  })

  it('sends a total write-off with a null account', async () => {
    renderDialog()
    setAmount('0')
    fireEvent.click(await screen.findByRole('radio', { name: 'Write-off / bad debt' }))
    fireEvent.click(screen.getByRole('radio', { name: 'Bad debt' }))

    expect(screen.queryByRole('button', { name: 'Payment account' })).not.toBeInTheDocument()
    save()

    await waitFor(() => expect(recordMock).toHaveBeenCalledTimes(1))
    expect(recordMock.mock.calls[0][1]).toMatchObject({
      paid_amount: 0,
      financial_account_id: null,
      residual_mode: 'write_off',
      write_off_reason: 'bad_debt',
      write_off_note: null,
    })
  })
})
