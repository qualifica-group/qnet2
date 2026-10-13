import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { purchaseInvoices } from '@/i18n/locales/en-purchase-invoices'
import { PurchaseInvoiceDetailsDialog } from '@/features/purchase-invoices/purchase-invoice-details-dialog'
import type { PurchaseInvoice, PurchaseInvoiceDetailsPayload, PurchaseInvoiceType } from '@/features/purchase-invoices/types'

const patchMock = vi.fn<(id: number, payload: PurchaseInvoiceDetailsPayload) => Promise<PurchaseInvoice>>()

vi.mock('@/features/purchase-invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoices/api')>()),
  updatePurchaseInvoiceDetails: (id: number, payload: PurchaseInvoiceDetailsPayload) => patchMock(id, payload),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function invoiceWith(type: PurchaseInvoiceType, downgrade: boolean, number: string | null): PurchaseInvoice {
  return {
    id: 7,
    document_type: type,
    document_number: number,
    notes: null,
    permissions: { resource: {}, fields: {}, actions: { downgrade } },
  } as unknown as PurchaseInvoice
}

function renderDialog(invoice: PurchaseInvoice) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <PurchaseInvoiceDetailsDialog invoice={invoice} open onOpenChange={vi.fn()} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { purchaseInvoices }, true, true)
})

beforeEach(() => {
  patchMock.mockReset().mockResolvedValue({ id: 7 } as PurchaseInvoice)
})

describe('PurchaseInvoiceDetailsDialog (spec 0250 D-2)', () => {
  it('locks the number of a registered invoice without the downgrade permission, keeping notes editable', () => {
    renderDialog(invoiceWith('invoice', false, 'FA-1'))

    expect(screen.getByLabelText('Supplier document number')).toBeDisabled()
    expect(screen.getByLabelText('Notes')).toBeEnabled()
    expect(screen.getByText(/downgrade permission is required/)).toBeInTheDocument()
  })

  it('lets a downgrade holder clear the number, sending null', async () => {
    renderDialog(invoiceWith('invoice', true, 'FA-1'))

    fireEvent.change(screen.getByLabelText('Supplier document number'), { target: { value: '' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(patchMock).toHaveBeenCalledWith(7, { document_number: null, notes: null }))
  })

  it('turns a proforma into an invoice by sending the supplier number', async () => {
    renderDialog(invoiceWith('proforma', false, null))

    const input = screen.getByLabelText('Supplier document number')
    expect(input).toBeEnabled()
    fireEvent.change(input, { target: { value: ' FA-2 ' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(patchMock).toHaveBeenCalledWith(7, { document_number: 'FA-2', notes: null }))
  })
})
