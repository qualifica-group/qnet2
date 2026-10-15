import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import { MemoryRouter } from 'react-router-dom'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import i18n from '@/i18n'
import { PurchaseInvoiceDetailView } from '@/features/purchase-invoices/purchase-invoice-detail'
import { makePurchaseInvoiceView } from '@/features/purchase-invoices/purchase-invoice-view-fixtures'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'

const fetchMock = vi.hoisted(() => vi.fn<(id: number) => Promise<PurchaseInvoice>>())
const patchMock = vi.hoisted(() => vi.fn<(id: number, payload: Record<string, unknown>) => Promise<PurchaseInvoice>>())
const canMock = vi.hoisted(() => vi.fn<(permission: string) => boolean>())

vi.mock('@/features/purchase-invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoices/api')>()),
  fetchPurchaseInvoice: (id: number) => fetchMock(id),
  patchPurchaseInvoice: (id: number, payload: Record<string, unknown>) => patchMock(id, payload),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: () => <div role="region" aria-label="Attachments" />,
}))

function renderDetail(invoice: PurchaseInvoice) {
  fetchMock.mockResolvedValue(invoice)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmDialogProvider>
          <PurchaseInvoiceDetailView invoiceId={invoice.id} onDelete={vi.fn()} />
        </ConfirmDialogProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

const LINKED = makePurchaseInvoiceView({
  document_type: 'invoice',
  document_number: 'FT-9',
  total_amount: '1220.00',
  withholding_amount: '115.00',
  enasarco_amount: '85.00',
  payable_amount: '1020.00',
  commission_settlement: { id: 12, code: '3/2026' },
})

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  canMock.mockReturnValue(true)
})

describe('PurchaseInvoiceDetailView withholdings and settlement link (spec 0267 AC-023)', () => {
  it('shows the withholding, ENASARCO and net payable amounts', async () => {
    renderDetail(LINKED)

    await screen.findByRole('heading', { name: 'Invoice FT-9' })
    expect(screen.getByText('Withholding tax').nextElementSibling).toHaveTextContent(/115,00/)
    expect(screen.getByText('ENASARCO').nextElementSibling).toHaveTextContent(/85,00/)
    expect(screen.getByText('Net payable').nextElementSibling).toHaveTextContent(/1020,00/)
  })

  it('links the "From statement" badge to the settlement and hides the edit and delete actions', async () => {
    renderDetail(LINKED)

    const link = await screen.findByRole('link', { name: 'From statement 3/2026' })
    expect(link).toHaveAttribute('href', '/commission-settlements/12')
    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit line 1' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add line' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Document date' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Supplier document number' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit Notes' })).toBeInTheDocument()
  })

  it('shows the badge as plain text without the commission-settlements.view permission', async () => {
    canMock.mockImplementation((permission) => permission !== 'commission-settlements.view')
    renderDetail(LINKED)

    expect(await screen.findByText('From statement 3/2026')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'From statement 3/2026' })).not.toBeInTheDocument()
  })

  it('keeps the actions and shows no badge on a document not linked to a settlement', async () => {
    renderDetail(makePurchaseInvoiceView({ document_number: 'FT-1' }))

    expect(await screen.findByRole('button', { name: 'Delete' })).toBeInTheDocument()
    expect(screen.queryByText(/From statement/)).not.toBeInTheDocument()
  })
})

describe('PurchaseInvoiceDetailView deductions edited in place (spec 0267)', () => {
  it('saves the withholding through the partial PATCH', async () => {
    patchMock.mockResolvedValue(makePurchaseInvoiceView())
    renderDetail(makePurchaseInvoiceView({ document_number: 'FT-1' }))

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Withholding tax' }))
    fireEvent.change(screen.getByLabelText(/^Withholding tax/), { target: { value: '50' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(patchMock).toHaveBeenCalledWith(31, { withholding_amount: 50 }))
  })

  it('puts a 422 on the field', async () => {
    const response = { status: 422, data: { message: 'Invalid', errors: { withholding_amount: ['Net payable must be positive.'] } } }
    patchMock.mockRejectedValue(Object.assign(new AxiosError('Invalid', 'ERR_BAD_REQUEST', undefined, undefined, response as AxiosResponse)))
    renderDetail(makePurchaseInvoiceView({ document_number: 'FT-1' }))

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Withholding tax' }))
    fireEvent.change(screen.getByLabelText(/^Withholding tax/), { target: { value: '9999' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('Net payable must be positive.')).toBeInTheDocument()
  })

  it('is read-only after a payment, when linked to a settlement or without the update ability', async () => {
    const paid = renderDetail(makePurchaseInvoiceView({ has_payments: true }))
    await screen.findByText('ENASARCO')
    expect(screen.queryByRole('button', { name: 'Edit Withholding tax' })).not.toBeInTheDocument()
    paid.unmount()

    const linked = renderDetail(LINKED)
    await screen.findByText('ENASARCO')
    expect(screen.queryByRole('button', { name: 'Edit ENASARCO' })).not.toBeInTheDocument()
    linked.unmount()

    const base = makePurchaseInvoiceView()
    renderDetail({ ...base, permissions: { ...base.permissions, actions: { ...base.permissions.actions, update: false } } })
    await screen.findByText('ENASARCO')
    expect(screen.queryByRole('button', { name: 'Edit ENASARCO' })).not.toBeInTheDocument()
  })
})
