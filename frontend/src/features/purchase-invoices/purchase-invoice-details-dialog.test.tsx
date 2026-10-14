import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
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

function invoiceWith(
  type: PurchaseInvoiceType,
  downgrade: boolean,
  number: string | null,
  purchaseOrders: PurchaseInvoice['purchase_orders'] = [],
): PurchaseInvoice {
  return {
    id: 7,
    document_type: type,
    document_number: number,
    notes: null,
    purchase_orders: purchaseOrders,
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

describe('PurchaseInvoiceDetailsDialog receipt notice (spec 0254 D-12)', () => {
  const ORDERS = [{ id: 5, code: 'ODA-5' }] as unknown as PurchaseInvoice['purchase_orders']
  const NOTICE = /the receipt of the quantities not yet received/

  it('warns that a proforma linked to ODAs registers the receipt when it becomes an invoice', () => {
    renderDialog(invoiceWith('proforma', false, null, ORDERS))
    expect(screen.getByRole('note')).toHaveTextContent(NOTICE)
  })

  it('shows no notice for a proforma without ODAs or for an already registered invoice', () => {
    renderDialog(invoiceWith('proforma', false, null))
    expect(screen.queryByRole('note')).not.toBeInTheDocument()
  })

  it('shows no notice for a registered invoice linked to ODAs', () => {
    renderDialog(invoiceWith('invoice', true, 'FA-1', ORDERS))
    expect(screen.queryByRole('note')).not.toBeInTheDocument()
  })
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

  it('spec 0254 AC-008: shows the 422 document_number message on the field with the accessible triad', async () => {
    const message = "La riga ODA supera il ricevuto (disponibile 1.000)"
    const response = { status: 422, data: { message: 'Invalid', errors: { document_number: [message] } } } as AxiosResponse
    patchMock.mockRejectedValue(new AxiosError('Invalid', 'ERR_BAD_REQUEST', undefined, undefined, response))
    renderDialog(invoiceWith('proforma', false, null))

    fireEvent.change(screen.getByLabelText('Supplier document number'), { target: { value: 'FA-2' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const alert = await screen.findByText(message)
    const input = screen.getByLabelText('Supplier document number')
    expect(alert).toHaveAttribute('role', 'alert')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input.getAttribute('aria-describedby')).toContain(alert.id)
  })
})
