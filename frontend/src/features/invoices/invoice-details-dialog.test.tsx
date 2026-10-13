import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { invoices } from '@/i18n/locales/en-invoices'
import { InvoiceDetailsDialog } from '@/features/invoices/invoice-details-dialog'
import type { InvoiceType, InvoiceWithPermissions } from '@/features/invoices/types'

const getInvoiceMock = vi.fn<(id: number) => Promise<InvoiceWithPermissions>>()

vi.mock('@/features/invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/invoices/api')>()),
  getInvoice: (id: number) => getInvoiceMock(id),
}))
vi.mock('@/features/invoices/invoice-layout-field', () => ({ InvoiceLayoutField: () => null }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function invoiceWith(type: InvoiceType, downgrade: boolean): InvoiceWithPermissions {
  return {
    id: 7,
    type,
    external_number: 'FA-1',
    external_date: '2026-10-01',
    tag: null,
    deviation: null,
    internal_note: null,
    layout: null,
    permissions: { resource: {}, fields: {}, actions: { downgrade } },
  } as unknown as InvoiceWithPermissions
}

async function renderDialog(invoice: InvoiceWithPermissions) {
  getInvoiceMock.mockResolvedValue(invoice)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <InvoiceDetailsDialog invoiceId={invoice.id} onClose={vi.fn()} />
    </QueryClientProvider>,
  )
  await screen.findByLabelText('External invoice number')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
  i18n.addResourceBundle('en', 'translation', { invoices }, true, true)
})

beforeEach(() => {
  getInvoiceMock.mockReset()
})

describe('InvoiceDetailsDialog registered invoice lock (spec 0243 AC-019)', () => {
  it('disables the FiC number and date without downgrade, keeping the other fields editable', async () => {
    await renderDialog(invoiceWith('invoice', false))

    expect(screen.getByLabelText('External invoice number')).toBeDisabled()
    expect(screen.getByLabelText('External invoice date')).toBeDisabled()
    expect(screen.getByLabelText('Deviation')).toBeEnabled()
    expect(screen.getByLabelText('Internal notes')).toBeEnabled()
    expect(screen.getByRole('combobox', { name: 'Tag' })).toBeEnabled()
  })

  it('keeps the FiC number and date editable with downgrade', async () => {
    await renderDialog(invoiceWith('invoice', true))

    expect(screen.getByLabelText('External invoice number')).toBeEnabled()
    expect(screen.getByLabelText('External invoice date')).toBeEnabled()
  })
})
