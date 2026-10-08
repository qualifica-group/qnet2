import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { invoices } from '@/i18n/locales/en-invoices'
import { InvoiceCollectionDialog } from '@/features/invoices/invoice-collection-dialog'
import type { Invoice, InvoiceCollectionPayload, InvoiceInstallment } from '@/features/invoices/types'

const recordMock = vi.fn<(installmentId: number, body: InvoiceCollectionPayload) => Promise<Invoice>>()

vi.mock('@/features/invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoices/api')>()),
  recordCollection: (installmentId: number, body: InvoiceCollectionPayload) => recordMock(installmentId, body),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function installment(sequence: number, overrides: Partial<InvoiceInstallment> = {}): InvoiceInstallment {
  return {
    id: sequence * 10,
    sequence,
    due_date: `2026-0${sequence + 1}-15`,
    amount: '400.00',
    payment_method_code: null,
    collected_amount: null,
    collected_at: null,
    status: 'unpaid',
    ...overrides,
  }
}

const FIRST = installment(1)
const PLAN = [FIRST, installment(2), installment(3)]

function renderDialog(target: InvoiceInstallment, installments: InvoiceInstallment[]) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <InvoiceCollectionDialog installment={target} installments={installments} onClose={vi.fn()} />
    </QueryClientProvider>,
  )
}

function setAmount(value: string) {
  fireEvent.change(screen.getByLabelText('Collected amount'), { target: { value } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { invoices }, true, true)
})

beforeEach(() => {
  recordMock.mockReset().mockResolvedValue({} as Invoice)
})

describe('InvoiceCollectionDialog residual choice (spec 0196 AC-022)', () => {
  it('shows no residual choice on a full collection and sends no residual fields', async () => {
    renderDialog(FIRST, PLAN)

    expect(screen.queryByRole('radio')).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Record' }))

    await waitFor(() => expect(recordMock).toHaveBeenCalledTimes(1))
    const body = recordMock.mock.calls[0][1]
    expect(body.collected_amount).toBe(400)
    expect(body).not.toHaveProperty('residual_mode')
    expect(body).not.toHaveProperty('residual_due_date')
  })

  it('offers spread (default) and a new installment on a partial collection and sends spread', async () => {
    renderDialog(FIRST, PLAN)
    setAmount('200')

    const spread = await screen.findByRole('radio', { name: 'Spread over the later installments' })
    expect(spread).toBeChecked()
    expect(spread).toBeEnabled()
    expect(screen.getByRole('radio', { name: 'New due date for the residual' })).toBeEnabled()
    expect(screen.getByText(/200,00/, { selector: 'p' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Record' }))
    await waitFor(() => expect(recordMock).toHaveBeenCalledTimes(1))
    const body = recordMock.mock.calls[0][1]
    expect(body).toMatchObject({ collected_amount: 200, residual_mode: 'spread' })
    expect(body).not.toHaveProperty('residual_due_date')
  })

  it('disables spread with a reason when no later open installment exists and defaults to a new installment', async () => {
    const last = installment(3)
    renderDialog(last, [installment(1, { collected_amount: '400.00', status: 'paid' }), installment(2), last])
    setAmount('100')

    const spread = await screen.findByRole('radio', { name: 'Spread over the later installments' })
    expect(spread).toBeDisabled()
    expect(spread).toHaveAccessibleDescription('There are no later open installments.')
    expect(screen.getByRole('radio', { name: 'New due date for the residual' })).toBeChecked()
  })

  it('proposes due date + 30 days, lets the user change it and sends it', async () => {
    renderDialog(FIRST, PLAN)
    setAmount('200')
    fireEvent.click(await screen.findByRole('radio', { name: 'New due date for the residual' }))

    const date = screen.getByLabelText('New installment date')
    expect(date).toHaveValue('2026-03-17')

    fireEvent.change(date, { target: { value: '2026-04-01' } })
    fireEvent.click(screen.getByRole('button', { name: 'Record' }))

    await waitFor(() => expect(recordMock).toHaveBeenCalledTimes(1))
    expect(recordMock.mock.calls[0][1]).toMatchObject({
      collected_amount: 200,
      residual_mode: 'new_installment',
      residual_due_date: '2026-04-01',
    })
  })

  it('blocks the submit with an accessible error when the new installment date is empty', async () => {
    renderDialog(FIRST, PLAN)
    setAmount('200')
    fireEvent.click(await screen.findByRole('radio', { name: 'New due date for the residual' }))
    fireEvent.change(screen.getByLabelText('New installment date'), { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: 'Record' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('The new installment date is required.')
    expect(recordMock).not.toHaveBeenCalled()
  })
})
