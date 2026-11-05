import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { InvoiceCollectionDialog } from '@/features/invoices/invoice-collection-dialog'
import type { Invoice, InvoiceCollectionPayload, InvoiceInstallment } from '@/features/invoices/types'

const recordMock = vi.fn<(installmentId: number, body: InvoiceCollectionPayload) => Promise<Invoice>>()
let threshold: string | undefined

vi.mock('@/features/invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoices/api')>()),
  recordCollection: (installmentId: number, body: InvoiceCollectionPayload) => recordMock(installmentId, body),
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

const FIRST: InvoiceInstallment = {
  id: 10,
  sequence: 1,
  due_date: '2026-02-15',
  amount: '400.00',
  payment_method_code: null,
  collected_amount: null,
  collected_at: null,
  status: 'unpaid',
}

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <InvoiceCollectionDialog
        installment={FIRST}
        installments={[FIRST]}
        defaultAccount={{ id: 4, name: 'Bank' }}
        onClose={vi.fn()}
      />
    </QueryClientProvider>,
  )
}

const setAmount = (value: string) => fireEvent.change(screen.getByLabelText('Collected amount'), { target: { value } })
const save = () => fireEvent.click(screen.getByRole('button', { name: 'Record' }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  threshold = '5.00'
  recordMock.mockReset().mockResolvedValue({} as Invoice)
})

describe('InvoiceCollectionDialog write-off (spec 0361 AC-012)', () => {
  it('proposes write-off with reason Write-off when the residual is within the threshold', async () => {
    renderDialog()
    setAmount('399.80')

    expect(await screen.findByRole('radio', { name: 'Write-off / bad debt' })).toBeChecked()
    expect(screen.getByRole('radio', { name: 'Write-off' })).toBeChecked()
    expect(screen.getAllByText(/0,20/).length).toBeGreaterThan(1)
    expect(screen.queryByLabelText('New installment date')).not.toBeInTheDocument()

    save()
    await waitFor(() => expect(recordMock).toHaveBeenCalledTimes(1))
    const body = recordMock.mock.calls[0][1]
    expect(body).toMatchObject({
      collected_amount: 399.8,
      residual_mode: 'write_off',
      write_off_reason: 'rounding',
      write_off_note: null,
    })
    expect(body).not.toHaveProperty('write_off_amount')
  })

  it('proposes a new installment above the threshold and lets the user choose bad debt with a note', async () => {
    renderDialog()
    setAmount('300')

    expect(await screen.findByRole('radio', { name: 'New due date for the residual' })).toBeChecked()
    fireEvent.click(screen.getByRole('radio', { name: 'Write-off / bad debt' }))
    fireEvent.click(screen.getByRole('radio', { name: 'Bad debt' }))
    fireEvent.change(screen.getByLabelText('Note (optional)'), { target: { value: 'customer failed' } })
    save()

    await waitFor(() => expect(recordMock).toHaveBeenCalledTimes(1))
    expect(recordMock.mock.calls[0][1]).toMatchObject({
      collected_amount: 300,
      residual_mode: 'write_off',
      write_off_reason: 'bad_debt',
      write_off_note: 'customer failed',
    })
  })

  it('uses the threshold from the config', async () => {
    threshold = '150.00'
    renderDialog()
    setAmount('300')

    expect(await screen.findByRole('radio', { name: 'Write-off / bad debt' })).toBeChecked()
  })

  it('falls back to 5.00 when the config has no treasury block', async () => {
    threshold = undefined
    renderDialog()
    setAmount('395')
    expect(await screen.findByRole('radio', { name: 'Write-off / bad debt' })).toBeChecked()

    setAmount('394.99')
    expect(screen.getByRole('radio', { name: 'New due date for the residual' })).toBeChecked()
  })

  it('rejects a note longer than 255 characters', async () => {
    renderDialog()
    setAmount('399.80')
    fireEvent.change(await screen.findByLabelText('Note (optional)'), { target: { value: 'x'.repeat(256) } })
    save()

    expect(await screen.findByRole('alert')).toHaveTextContent('cannot exceed 255')
    expect(recordMock).not.toHaveBeenCalled()
  })
})

describe('InvoiceCollectionDialog zero amount (spec 0361 AC-013)', () => {
  it('blocks a zero amount with an announced error unless write-off is chosen', async () => {
    renderDialog()
    setAmount('0')
    save()

    expect(await screen.findByRole('alert')).toHaveTextContent('only allowed with Write-off / bad debt')
    const amount = screen.getByLabelText('Collected amount')
    expect(amount).toHaveAttribute('aria-invalid', 'true')
    expect(amount).toHaveAccessibleDescription(/only allowed with Write-off/)
    expect(recordMock).not.toHaveBeenCalled()
  })

  it('hides the account and sends a total write-off with a null account', async () => {
    renderDialog()
    expect(screen.getByRole('button', { name: 'Account' })).toBeInTheDocument()
    setAmount('0')
    fireEvent.click(await screen.findByRole('radio', { name: 'Write-off / bad debt' }))
    fireEvent.click(screen.getByRole('radio', { name: 'Bad debt' }))

    expect(screen.queryByRole('button', { name: 'Account' })).not.toBeInTheDocument()
    save()

    await waitFor(() => expect(recordMock).toHaveBeenCalledTimes(1))
    expect(recordMock.mock.calls[0][1]).toMatchObject({
      collected_amount: 0,
      financial_account_id: null,
      residual_mode: 'write_off',
      write_off_reason: 'bad_debt',
    })
  })
})
