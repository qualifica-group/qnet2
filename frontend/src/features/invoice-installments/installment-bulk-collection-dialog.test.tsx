import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { InstallmentBulkCollectionDialog } from '@/features/invoice-installments/installment-bulk-collection-dialog'
import type { BulkCollectionTarget } from '@/features/invoice-installments/installment-bulk-collection'
import type { BulkCollectionPayload, BulkCollectionResult } from '@/features/invoice-installments/types'

const bulkMock = vi.fn<(payload: BulkCollectionPayload) => Promise<BulkCollectionResult>>()

vi.mock('@/features/invoice-installments/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoice-installments/api')>()),
  bulkCollectInstallments: (payload: BulkCollectionPayload) => bulkMock(payload),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const TARGETS: BulkCollectionTarget[] = [
  { id: 1, invoiceLabel: '12/2026', sequence: 1, dueDate: '2026-10-31', amount: 1000 },
  { id: 2, invoiceLabel: '15/2026', sequence: 1, dueDate: '2026-11-30', amount: 500 },
]

function renderDialog() {
  const onSaved = vi.fn()
  const onClose = vi.fn()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <InstallmentBulkCollectionDialog targets={TARGETS} onClose={onClose} onSaved={onSaved} />
    </QueryClientProvider>,
  )
  return { onSaved, onClose }
}

const amountInputs = () => screen.getAllByRole('spinbutton', { name: 'Collected amount' })
const saveButton = () => screen.getByRole('button', { name: 'Save' })
const typeAmount = (index: number, value: string) => fireEvent.change(amountInputs()[index], { target: { value } })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  bulkMock.mockReset().mockResolvedValue({ collected_count: 2, residual_count: 1 })
})

describe('InstallmentBulkCollectionDialog (spec 0198 AC-010)', () => {
  it('starts with empty amounts, a zero total and Save disabled', () => {
    renderDialog()

    expect(amountInputs().map((input) => (input as HTMLInputElement).value)).toEqual(['', ''])
    expect(screen.getByText('Total collected').nextSibling?.textContent).toMatch(/0,00/)
    expect(saveButton()).toBeDisabled()
  })

  it('sums the typed amounts in the footer and sends only the filled rows', async () => {
    const { onSaved, onClose } = renderDialog()

    typeAmount(0, '600')
    typeAmount(1, '500')
    await waitFor(() => expect(screen.getByText('Total collected').nextSibling?.textContent).toMatch(/1\.?100,00/))

    typeAmount(1, '')
    await waitFor(() => expect(saveButton()).toBeEnabled())
    fireEvent.click(saveButton())

    await waitFor(() => expect(onSaved).toHaveBeenCalled())
    expect(bulkMock.mock.calls[0][0]).toMatchObject({ items: [{ installment_id: 1, collected_amount: 600 }] })
    expect(onClose).toHaveBeenCalled()
  })

  it('D-4: an amount above the installment shows the error and disables Save', async () => {
    renderDialog()

    typeAmount(0, '100')
    typeAmount(1, '500.01')

    expect(await screen.findByText(/cannot exceed the installment/)).toBeInTheDocument()
    expect(saveButton()).toBeDisabled()
  })

  it('maps a server 422 on a sent item back onto its dialog row', async () => {
    bulkMock.mockRejectedValue(
      new AxiosError('failed', '422', undefined, undefined, {
        status: 422,
        data: { errors: { 'items.0.installment_id': ['This installment is already collected.'] } },
      } as AxiosResponse),
    )
    renderDialog()

    typeAmount(1, '200')
    await waitFor(() => expect(saveButton()).toBeEnabled())
    fireEvent.click(saveButton())

    expect(await screen.findByText('This installment has already been collected.')).toBeInTheDocument()
  })
})
