import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { PurchaseInvoiceEditorDialog } from '@/features/purchase-invoices/purchase-invoice-editor-dialog'
import type {
  PurchaseInstallmentPreviewPayload,
  PurchaseInstallmentPreviewRow,
  PurchaseInvoice,
  PurchaseInvoiceWritePayload,
} from '@/features/purchase-invoices/types'

const FOR_SELECT_ITEMS: Record<string, unknown[]> = {
  registries: [{ id: 8, label: 'Acme Supplies' }],
  companies: [{ id: 3, label: 'Qualifica Srl' }],
  'payment-methods': [{ id: 2, label: 'Bonifico 30 gg' }],
  'cost-types': [{ id: 1, label: 'Collaboratori', meta: { color: 'blue' } }],
  'vat-rates': [{ id: 1, label: 'IVA 22%', meta: { rate: '22.00' } }],
}

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn(async (resource: string) => ({
    items: FOR_SELECT_ITEMS[resource] ?? [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 25, total_pages: 1 },
  })),
}))

const createMock = vi.fn<(payload: PurchaseInvoiceWritePayload) => Promise<PurchaseInvoice>>()
const previewMock = vi.fn<(payload: PurchaseInstallmentPreviewPayload) => Promise<PurchaseInstallmentPreviewRow[]>>()

vi.mock('@/features/purchase-invoices/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-invoices/api')>()),
  createPurchaseInvoice: (payload: PurchaseInvoiceWritePayload) => createMock(payload),
  fetchInvoiceableOrders: () => Promise.resolve([]),
  previewPurchaseInvoiceInstallments: (payload: PurchaseInstallmentPreviewPayload) => previewMock(payload),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

async function pick(triggerName: RegExp, optionName: RegExp) {
  fireEvent.click(screen.getByRole('combobox', { name: triggerName }))
  fireEvent.click(await screen.findByRole('option', { name: optionName }))
  await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument())
}

/** Fills a 1000.00 net + 22% VAT (1220.00 total) invoice. */
async function fillInvoice() {
  fireEvent.change(screen.getByLabelText(/Supplier document number/), { target: { value: 'FA-1' } })
  fireEvent.change(screen.getByLabelText(/Document date/), { target: { value: '2026-10-13' } })
  await pick(/^Supplier$/, /Acme/)
  await pick(/^Company$/, /Qualifica/)
  await pick(/^Payment method$/, /Bonifico/)
  await pick(/Default cost type/, /Collaboratori/)
  fireEvent.click(screen.getByRole('button', { name: 'Free' }))
  fireEvent.change(screen.getByLabelText('Free row description'), { target: { value: 'Commissions' } })
  fireEvent.click(screen.getByRole('button', { name: 'Add row' }))
  fireEvent.change(screen.getByLabelText('Unit price 1'), { target: { value: '1000' } })
  await pick(/^VAT 1$/, /IVA 22%/)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  createMock.mockReset().mockResolvedValue({ id: 50 } as PurchaseInvoice)
  previewMock.mockReset().mockResolvedValue([{ sequence: 1, due_date: '2026-11-12', amount: '1020.00', payment_method_code: null }])
})

function renderDialog() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <PurchaseInvoiceEditorDialog open onOpenChange={vi.fn()} onSaved={vi.fn()} />
    </QueryClientProvider>,
  )
}

describe('PurchaseInvoiceEditorDialog withholdings (spec 0267 AC-023)', () => {
  it('shows the net payable, feeds the preview and sends the deductions', async () => {
    renderDialog()
    await fillInvoice()

    fireEvent.change(screen.getByLabelText("Withholding tax"), { target: { value: '115' } })
    fireEvent.change(screen.getByLabelText('ENASARCO charged to the company'), { target: { value: '85' } })

    const totals = screen.getByLabelText('Totals')
    expect(within(totals).getByText('Net payable').nextElementSibling).toHaveTextContent(/1020,00/)
    await waitFor(() =>
      expect(previewMock).toHaveBeenCalledWith(
        expect.objectContaining({ total_amount: 1220, withholding_amount: 115, enasarco_amount: 85 }),
      ),
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))
    await waitFor(() => expect(createMock).toHaveBeenCalledTimes(1))
    expect(createMock.mock.calls[0]?.[0]).toMatchObject({ withholding_amount: 115, enasarco_amount: 85 })
  })

  it('refuses deductions that reach the total, on the withholding field, without calling the server', async () => {
    renderDialog()
    await fillInvoice()

    fireEvent.change(screen.getByLabelText('Withholding tax'), { target: { value: '1220' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    const alert = await screen.findByText('Withholding tax and ENASARCO must be less than the document total.')
    expect(alert).toHaveAttribute('role', 'alert')
    expect(screen.getByLabelText('Withholding tax')).toHaveAttribute('aria-invalid', 'true')
    expect(createMock).not.toHaveBeenCalled()
  })

  it('rejects a negative deduction', async () => {
    renderDialog()
    await fillInvoice()

    fireEvent.change(screen.getByLabelText('ENASARCO charged to the company'), { target: { value: '-5' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(await screen.findByText('The amount cannot be negative.')).toBeInTheDocument()
    expect(createMock).not.toHaveBeenCalled()
  })
})
