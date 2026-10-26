import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { CommissionSettlementDetailView } from '@/features/commission-settlements/commission-settlement-detail'
import type { ApprovalDraft, SettlementDetailWithPermissions } from '@/features/commission-settlements/types'

// The pickers render the quick-create "+" (spec 0028), gated by `<Can>`: no abilities here, so it stays hidden.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const apiMocks = vi.hoisted(() => ({
  fetch: vi.fn(),
  postDocument: vi.fn(),
  deleteDocument: vi.fn(),
  draft: vi.fn(),
  supplier: vi.fn(),
  approve: vi.fn(),
}))
const toastMocks = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }))

vi.mock('@/features/commission-settlements/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/commission-settlements/api')>()),
  fetchCommissionSettlement: (...args: unknown[]) => apiMocks.fetch(...args),
  postSettlementDocument: (...args: unknown[]) => apiMocks.postDocument(...args),
  deleteSettlementDocument: (...args: unknown[]) => apiMocks.deleteDocument(...args),
  getApprovalDraft: (...args: unknown[]) => apiMocks.draft(...args),
  postSettlementSupplier: (...args: unknown[]) => apiMocks.supplier(...args),
  approveSettlement: (...args: unknown[]) => apiMocks.approve(...args),
}))
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: () => Promise.resolve({ data: [], meta: { has_more: false } }),
}))
vi.mock('@/features/purchase-invoices/purchase-invoice-detail-sheet', () => ({
  PurchaseInvoiceDetailSheet: ({ invoiceId }: { invoiceId: number | null }) =>
    invoiceId === null ? null : <div role="dialog" aria-label="invoice-sheet" />,
}))
vi.mock('@/features/purchase-invoices/use-purchase-invoice-editor-queries', () => ({
  usePurchaseInstallmentPreview: () => ({ data: [], isError: false, isFetching: false, error: null }),
}))
vi.mock('sonner', () => ({ toast: toastMocks }))

const NO_ABILITIES = {
  recalculate: false, issue: false, cancel: false, delete: false, adjust: false, update: false,
  receive_document: false, remove_document: false, approve: false,
}

function settlement(overrides: Partial<SettlementDetailWithPermissions> = {}): SettlementDetailWithPermissions {
  return {
    id: 12, code: '1/2026', status: 'ISSUED', period_from: '2026-09-01', period_to: '2026-09-30', run_id: 4,
    company: { id: 2, name: 'Acme Srl' }, referent: { id: 5, name: 'Mario Rossi' }, agent_fiscal_profile_id: 1,
    accruals_amount: '1000.00', adjustments_amount: '0.00', taxable_amount: '1000.00', vat_rate: '22.00',
    vat_amount: '220.00', withholding_enabled: true, withholding_rate: '23.00', withholding_base_percent: '50.00',
    withholding_amount: '115.00', enasarco_enabled: true, enasarco_mandate_type: 'mono', enasarco_base_amount: '1000.00',
    enasarco_agent_amount: '85.00', enasarco_principal_amount: '85.00', net_payable: '1020.00', notes: null,
    issued_at: '2026-10-01T08:00:00Z', issued_by: null, cancelled_at: null, cancelled_by: null, cancellation_reason: null,
    document: null, approved_at: null, approved_by: null, purchase_invoice: null, supplier: null,
    created_at: '2026-10-13T08:00:00Z', movements: [], abilities: { ...NO_ABILITIES, receive_document: true },
    permissions: { resource: {}, fields: {}, actions: {} } as never,
    ...overrides,
  }
}

const RECEIVED = settlement({
  status: 'DOCUMENT_RECEIVED',
  abilities: { ...NO_ABILITIES, remove_document: true, approve: true },
  document: {
    number: 'F-77', date: '2026-10-05', taxable_amount: '1000.50', vat_amount: '220.00', withholding_amount: '115.00',
    enasarco_amount: '85.00', total_amount: '1220.50', received_at: '2026-10-06T08:00:00Z', received_by: { id: 1, name: 'Admin' },
    differences: { taxable_amount: '0.50', vat_amount: '0.00', withholding_amount: '0.00', enasarco_amount: '0.00', total_amount: '0.50' },
  },
})

const DRAFT: ApprovalDraft = {
  supplier: null,
  supplier_candidates: [{ id: 9, name: 'Rossi Mario', tax_code: 'RSSMRA80A01H501U', vat_number: null, is_supplier: true },
    { id: 10, name: 'Rossi M. Snc', tax_code: null, vat_number: '01234567890', is_supplier: false },
  ],
  can_create_supplier: true,
  payment_method_id: 3, cost_type_id: 4, vat_rate_id: 5,
  line_description: 'Provvigioni 01/09-30/09',
}

function unprocessable(errors: Record<string, string[]>) {
  return new AxiosError('Unprocessable', '422', undefined, undefined, {
    status: 422, data: { success: false, message: 'Invalid', errors },
  } as never)
}

function renderDetail() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <CommissionSettlementDetailView settlementId={12} onChanged={vi.fn()} onDeleted={vi.fn()} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  Object.values(apiMocks).forEach((mock) => mock.mockReset())
  toastMocks.success.mockReset()
  toastMocks.error.mockReset()
})

describe('settlement detail - document and passive invoice sections (spec 0267 AC-019)', () => {
  it('shows the document with signed differences and only the buttons the abilities allow', async () => {
    apiMocks.fetch.mockResolvedValue(RECEIVED)
    renderDetail()

    const table = await screen.findByRole('table', { name: 'Document amounts and differences from the statement' })
    const taxableRow = within(table).getByRole('row', { name: /Taxable amount/ })
    expect(within(taxableRow).getByText(/\+.*0,50/)).toBeInTheDocument()
    expect(screen.getByText('F-77')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Remove document' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Approve' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Register document' })).not.toBeInTheDocument()
  })

  it('shows the passive invoice with its payment status and opens it', async () => {
    apiMocks.fetch.mockResolvedValue(
      settlement({
        status: 'APPROVED',
        abilities: NO_ABILITIES,
        purchase_invoice: {
          id: 40, document_number: 'F-77', document_date: '2026-10-05', payable_amount: '1020.00',
          paid_amount: '0.00', residual_amount: '1020.00', payment_status: 'not_due',
        },
      }),
    )
    renderDetail()

    expect(await screen.findByText('Not due')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Open invoice F-77' }))
    expect(await screen.findByRole('dialog', { name: 'invoice-sheet' })).toBeInTheDocument()
  })

  it('removes the document after the confirmation and surfaces a 409 as a toast', async () => {
    apiMocks.fetch.mockResolvedValue(RECEIVED)
    apiMocks.deleteDocument.mockRejectedValue(
      new AxiosError('Conflict', '409', undefined, undefined, { status: 409, data: { message: 'Not received.' } } as never),
    )
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Remove document' }))
    fireEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Remove' }))

    await waitFor(() => expect(apiMocks.deleteDocument).toHaveBeenCalledWith(12))
    await waitFor(() => expect(toastMocks.error).toHaveBeenCalledWith('Not received.'))
  })
})

describe('Register document dialog (spec 0267 AC-020)', () => {
  it('is prefilled, validates the total and sends the contract body', async () => {
    apiMocks.fetch.mockResolvedValue(settlement())
    apiMocks.postDocument.mockResolvedValue(RECEIVED)
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Register document' }))
    const dialog = await screen.findByRole('dialog')
    const total = within(dialog).getByLabelText(/^Document total/)
    expect(within(dialog).getByLabelText(/^Taxable amount/)).toHaveValue(1000)

    fireEvent.click(within(dialog).getByRole('button', { name: 'Register document' }))
    expect(await within(dialog).findByText('The document number is required.')).toHaveAttribute('role', 'alert')

    fireEvent.change(within(dialog).getByLabelText(/^Document number/), { target: { value: 'F-77' } })
    fireEvent.change(total, { target: { value: '1300.00' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Register document' }))
    expect(await within(dialog).findByText('The total must equal taxable amount + VAT.')).toBeInTheDocument()
    expect(apiMocks.postDocument).not.toHaveBeenCalled()

    fireEvent.change(total, { target: { value: '1220.00' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Register document' }))

    await waitFor(() =>
      expect(apiMocks.postDocument).toHaveBeenCalledWith(12, expect.objectContaining({
        document_number: 'F-77',
        document_taxable_amount: 1000,
        document_vat_amount: 220,
        document_withholding_amount: 115,
        document_enasarco_amount: 85,
        document_total_amount: 1220,
      })),
    )
  })

  it('maps a 422 tolerance error onto the field', async () => {
    apiMocks.fetch.mockResolvedValue(settlement())
    apiMocks.postDocument.mockRejectedValue(unprocessable({ document_taxable_amount: ['Difference over the tolerance.'] }))
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Register document' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.change(within(dialog).getByLabelText(/^Document number/), { target: { value: 'F-1' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Register document' }))

    expect(await within(dialog).findByText('Difference over the tolerance.')).toHaveAttribute('role', 'alert')
  })
})

describe('Approve dialog (spec 0267 AC-021)', () => {
  it('requires a supplier choice, then links the supplier and approves', async () => {
    apiMocks.fetch.mockResolvedValue(RECEIVED)
    apiMocks.draft.mockResolvedValue(DRAFT)
    apiMocks.supplier.mockResolvedValue(undefined)
    apiMocks.approve.mockResolvedValue(settlement({ status: 'APPROVED', abilities: NO_ABILITIES }))
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Approve' }))
    const dialog = await screen.findByRole('dialog')
    expect(await within(dialog).findByRole('radio', { name: /Rossi Mario/ })).toBeInTheDocument()
    expect(within(dialog).getByRole('radio', { name: 'Create from the referent' })).toBeInTheDocument()
    expect(within(dialog).getByRole('radio', { name: /Rossi M\. Snc/ })).toBeDisabled()
    expect(within(dialog).getByText('Not a supplier')).toBeInTheDocument()
    expect(within(dialog).getByRole('radio', { name: /Rossi Mario/ })).toBeEnabled()

    fireEvent.click(within(dialog).getByRole('button', { name: 'Approve' }))
    expect(await within(dialog).findByText('Choose the supplier registry.')).toBeInTheDocument()
    expect(apiMocks.supplier).not.toHaveBeenCalled()

    fireEvent.click(within(dialog).getByRole('radio', { name: 'Create from the referent' }))
    fireEvent.click(within(dialog).getByRole('button', { name: 'Approve' }))

    await waitFor(() => expect(apiMocks.supplier).toHaveBeenCalledWith(12, { registry_id: null }))
    await waitFor(() =>
      expect(apiMocks.approve).toHaveBeenCalledWith(12, {
        payment_method_id: 3, cost_type_id: 4, vat_rate_id: 5, financial_account_id: null,
      }),
    )
  })

  it('shows the linked supplier read-only and skips the supplier call', async () => {
    apiMocks.fetch.mockResolvedValue(RECEIVED)
    apiMocks.draft.mockResolvedValue({ ...DRAFT, supplier: { id: 9, name: 'Rossi Mario' }, supplier_candidates: [] })
    apiMocks.approve.mockResolvedValue(settlement({ status: 'APPROVED', abilities: NO_ABILITIES }))
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Approve' }))
    const dialog = await screen.findByRole('dialog')
    expect(await within(dialog).findByText('Rossi Mario')).toBeInTheDocument()
    expect(within(dialog).queryByRole('radio')).not.toBeInTheDocument()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Approve' }))

    await waitFor(() => expect(apiMocks.approve).toHaveBeenCalled())
    expect(apiMocks.supplier).not.toHaveBeenCalled()
  })

  it('maps a 422 on the VAT rate onto its field', async () => {
    apiMocks.fetch.mockResolvedValue(RECEIVED)
    apiMocks.draft.mockResolvedValue({ ...DRAFT, supplier: { id: 9, name: 'Rossi Mario' }, supplier_candidates: [] })
    apiMocks.approve.mockRejectedValue(unprocessable({ vat_rate_id: ['VAT out of tolerance.'] }))
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Approve' }))
    const dialog = await screen.findByRole('dialog')
    await within(dialog).findByText('Rossi Mario')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Approve' }))

    expect(await within(dialog).findByText('VAT out of tolerance.')).toHaveAttribute('role', 'alert')
  })
})
