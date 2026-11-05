import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { apiClient } from '@/api/client'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { CompensationDialog } from '@/features/compensations/compensation-dialog'
import { LedgerCompensations } from '@/features/ledger/ledger-compensations'
import type { InstallmentCompensation } from '@/features/compensations/types'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }))
const abilities = vi.hoisted(() => ({ granted: new Set<string>() }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => abilities.granted.has(permission) }),
}))

const getMock = vi.mocked(apiClient.get)
const postMock = vi.mocked(apiClient.post)
const deleteMock = vi.mocked(apiClient.delete)

const CANDIDATES = {
  receivables: [
    { id: 11, sequence: 1, due_date: '2026-11-30', residual: '500.00', invoice: { id: 1, number_label: '5/2026', date: '2026-10-01' } },
  ],
  payables: [
    { id: 21, sequence: 1, due_date: '2026-11-20', residual: '300.00', purchase_invoice: { id: 2, number_label: 'FA-9', date: '2026-10-02' } },
  ],
}

const COMPENSATION: InstallmentCompensation = {
  id: 5,
  company: { id: 3, name: 'Acme' },
  compensated_at: '2026-11-04',
  amount: '300.00',
  note: null,
  invoice_installment: { id: 11, sequence: 1, invoice: { id: 1, number_label: '5/2026' } },
  purchase_invoice_installment: { id: 21, sequence: 1, purchase_invoice: { id: 2, number_label: 'FA-9' } },
  created_by: null,
  created_at: '2026-11-04T10:00:00Z',
  can_delete: true,
}

function mockApi(list: InstallmentCompensation[]) {
  getMock.mockImplementation((url: string) =>
    Promise.resolve({
      data: { success: true, message: 'OK', data: url.endsWith('/compensation-candidates') ? CANDIDATES : list },
    }),
  )
}

function renderWithProviders(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{ui}</ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getMock.mockReset()
  postMock.mockReset()
  deleteMock.mockReset()
  abilities.granted = new Set()
})

describe('CompensationDialog', () => {
  it('AC-011 computes the minor residual, asks for the residual choice and sends the contract', async () => {
    mockApi([])
    postMock.mockResolvedValue({ data: { success: true, message: 'OK', data: COMPENSATION } })
    renderWithProviders(<CompensationDialog target={{ registryId: 7, companyId: 3 }} onClose={() => {}} />)

    await screen.findByRole('option', { name: /5\/2026/ })
    expect(getMock).toHaveBeenCalledWith('/registries/7/compensation-candidates', { params: { company_id: 3 } })
    fireEvent.change(screen.getByLabelText('Receivable installment (to collect)'), { target: { value: '11' } })
    fireEvent.change(screen.getByLabelText('Payable installment (to pay)'), { target: { value: '21' } })

    expect(screen.getByRole('status', { name: 'Compensated amount' }).textContent).toMatch(/300/)
    expect(screen.getByRole('group', { name: 'Residual' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Compensate' }))
    expect(await screen.findByText('Choose what to do with the residual')).toBeInTheDocument()
    expect(postMock).not.toHaveBeenCalled()

    fireEvent.click(screen.getByRole('radio', { name: 'Create a new installment' }))
    fireEvent.change(screen.getByLabelText('Due date of the new installment'), { target: { value: '2026-12-31' } })
    fireEvent.click(screen.getByRole('button', { name: 'Compensate' }))

    await waitFor(() => expect(postMock).toHaveBeenCalledTimes(1))
    expect(postMock.mock.calls[0][0]).toBe('/installment-compensations')
    expect(postMock.mock.calls[0][1]).toMatchObject({
      invoice_installment_id: 11,
      purchase_invoice_installment_id: 21,
      residual_mode: 'new_installment',
      residual_due_date: '2026-12-31',
      note: null,
    })
  })

  it('AC-011 hides the residual choice when the residuals are equal', async () => {
    mockApi([])
    getMock.mockImplementation(() =>
      Promise.resolve({
        data: {
          success: true,
          message: 'OK',
          data: { ...CANDIDATES, payables: [{ ...CANDIDATES.payables[0], residual: '500.00' }] },
        },
      }),
    )
    renderWithProviders(<CompensationDialog target={{ registryId: 7, companyId: 3 }} onClose={() => {}} />)

    await screen.findByRole('option', { name: /5\/2026/ })
    fireEvent.change(screen.getByLabelText('Receivable installment (to collect)'), { target: { value: '11' } })
    fireEvent.change(screen.getByLabelText('Payable installment (to pay)'), { target: { value: '21' } })

    expect(screen.queryByRole('group', { name: 'Residual' })).not.toBeInTheDocument()
  })

  it('AC-012 opens with the installment already chosen', async () => {
    mockApi([])
    renderWithProviders(
      <CompensationDialog target={{ registryId: 7, companyId: 3, receivableId: 11 }} onClose={() => {}} />,
    )

    await screen.findByRole('option', { name: /5\/2026/ })
    expect(screen.getByLabelText('Receivable installment (to collect)')).toHaveValue('11')
  })
})

describe('LedgerCompensations', () => {
  it('AC-011 shows Compensate only with both collect and pay abilities', async () => {
    mockApi([])
    abilities.granted = new Set(['invoices.collect'])
    const { unmount } = renderWithProviders(<LedgerCompensations registryId={7} />)
    await screen.findByText('No compensations.')
    expect(screen.queryByRole('button', { name: 'Compensate' })).not.toBeInTheDocument()
    unmount()

    abilities.granted = new Set(['invoices.collect', 'purchase-invoices.pay'])
    renderWithProviders(<LedgerCompensations registryId={7} />)
    fireEvent.click(await screen.findByRole('button', { name: 'Compensate' }))
    expect(await screen.findByRole('dialog', { name: 'Compensate' })).toBeInTheDocument()
  })

  it('AC-013 lists the compensations and undoes one after confirmation', async () => {
    mockApi([COMPENSATION])
    deleteMock.mockResolvedValue({ data: { success: true, message: 'OK' } })
    renderWithProviders(<LedgerCompensations registryId={7} />)

    const row = (await screen.findByRole('row', { name: /Acme/ })) as HTMLElement
    expect(within(row).getByText('5/2026 / 1')).toBeInTheDocument()
    fireEvent.click(within(row).getByRole('button', { name: 'Undo' }))

    const dialog = await screen.findByRole('alertdialog')
    expect(deleteMock).not.toHaveBeenCalled()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Undo' }))

    await waitFor(() => expect(deleteMock).toHaveBeenCalledWith('/installment-compensations/5'))
  })

  it('AC-013 hides Undo when the server says the row cannot be deleted', async () => {
    mockApi([{ ...COMPENSATION, can_delete: false }])
    renderWithProviders(<LedgerCompensations registryId={7} />)

    await screen.findByRole('row', { name: /Acme/ })
    expect(screen.queryByRole('button', { name: 'Undo' })).not.toBeInTheDocument()
  })
})
