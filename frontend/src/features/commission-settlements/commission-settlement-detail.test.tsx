import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { CommissionSettlementDetailView } from '@/features/commission-settlements/commission-settlement-detail'
import type { SettlementDetailWithPermissions } from '@/features/commission-settlements/types'

// The pickers render the quick-create "+" (spec 0028), gated by `<Can>`: no abilities here, so it stays hidden.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const apiMocks = vi.hoisted(() => ({
  fetch: vi.fn(),
  recalculate: vi.fn(),
  issue: vi.fn(),
  cancel: vi.fn(),
  remove: vi.fn(),
  patch: vi.fn(),
  removeAdjustment: vi.fn(),
}))
const toastMocks = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }))

vi.mock('@/features/commission-settlements/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/commission-settlements/api')>()),
  fetchCommissionSettlement: (...args: unknown[]) => apiMocks.fetch(...args),
  recalculateSettlement: (...args: unknown[]) => apiMocks.recalculate(...args),
  issueSettlement: (...args: unknown[]) => apiMocks.issue(...args),
  cancelSettlement: (...args: unknown[]) => apiMocks.cancel(...args),
  deleteSettlement: (...args: unknown[]) => apiMocks.remove(...args),
  updateSettlementNotes: (...args: unknown[]) => apiMocks.patch(...args),
  deleteAdjustment: (...args: unknown[]) => apiMocks.removeAdjustment(...args),
}))
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: () => Promise.resolve({ data: [], meta: { has_more: false } }),
}))
vi.mock('sonner', () => ({ toast: toastMocks }))

const DOCUMENT_NO_ABILITIES = { receive_document: false, remove_document: false, approve: false }
const ALL_ABILITIES = { recalculate: true, issue: true, cancel: false, delete: true, adjust: true, update: true, ...DOCUMENT_NO_ABILITIES }
const NO_ABILITIES = { recalculate: false, issue: false, cancel: false, delete: false, adjust: false, update: false, ...DOCUMENT_NO_ABILITIES }

function settlement(overrides: Partial<SettlementDetailWithPermissions> = {}): SettlementDetailWithPermissions {
  return {
    id: 12,
    code: null,
    status: 'DRAFT',
    period_from: '2026-09-01',
    period_to: '2026-09-30',
    run_id: 4,
    company: { id: 2, name: 'Acme Srl' },
    referent: { id: 5, name: 'Mario Rossi' },
    agent_fiscal_profile_id: 1,
    accruals_amount: '1000.00',
    adjustments_amount: '0.00',
    taxable_amount: '1000.00',
    vat_rate: '22.00',
    vat_amount: '220.00',
    withholding_enabled: true,
    withholding_rate: '23.00',
    withholding_base_percent: '50.00',
    withholding_amount: '115.00',
    enasarco_enabled: true,
    enasarco_mandate_type: 'mono',
    enasarco_base_amount: '1000.00',
    enasarco_agent_amount: '85.00',
    enasarco_principal_amount: '85.00',
    net_payable: '1020.00',
    notes: null,
    issued_at: null,
    issued_by: null,
    cancelled_at: null,
    cancelled_by: null,
    cancellation_reason: null,
    document: null,
    approved_at: null,
    approved_by: null,
    purchase_invoice: null,
    supplier: null,
    created_at: '2026-10-13T08:00:00Z',
    movements: [
      {
        id: 31,
        type: 'ADJUSTMENT',
        recipient_role: null,
        accrued_on: '2026-09-15',
        amount: '10.00',
        base_amount: null,
        notes: 'Rettifica',
        invoice: null,
        quote: null,
        product: null,
        can_edit: true,
      },
    ],
    abilities: ALL_ABILITIES,
    permissions: { resource: {}, fields: {}, actions: {} } as never,
    ...overrides,
  }
}

function conflict(message: string) {
  return new AxiosError('Conflict', '409', undefined, undefined, {
    status: 409,
    data: { success: false, message },
  } as never)
}

function renderDetail(onDeleted = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <CommissionSettlementDetailView settlementId={12} onChanged={vi.fn()} onDeleted={onDeleted} />
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

describe('CommissionSettlementDetailView - content (spec 0213 AC-021)', () => {
  it('shows the D-2 breakdown, the movements and the abilities-gated actions', async () => {
    apiMocks.fetch.mockResolvedValue(settlement())
    renderDetail()

    const list = await screen.findByLabelText('Totals')
    expect(within(list).getByText('VAT (22%)')).toBeInTheDocument()
    expect(within(list).getByText('Withholding tax (23% on 50%)')).toBeInTheDocument()
    expect(within(list).getByText('ENASARCO borne by the principal (informative)')).toBeInTheDocument()
    expect(within(list).getByText('Net payable')).toBeInTheDocument()
    expect(screen.getByRole('table', { name: 'Movements' })).toBeInTheDocument()
    for (const name of ['Recalculate', 'New adjustment', 'Issue', 'Delete draft']) {
      expect(screen.getByRole('button', { name })).toBeInTheDocument()
    }
    expect(screen.queryByRole('button', { name: 'Cancel statement' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Edit adjustment' })).toBeInTheDocument()
  })

  it('omits the hidden amounts and every action without ability', async () => {
    const hidden = settlement({ abilities: NO_ABILITIES })
    delete hidden.enasarco_base_amount
    delete hidden.accruals_amount
    delete hidden.adjustments_amount
    delete hidden.taxable_amount
    delete hidden.vat_amount
    delete hidden.withholding_amount
    delete hidden.enasarco_agent_amount
    delete hidden.enasarco_principal_amount
    delete hidden.net_payable
    apiMocks.fetch.mockResolvedValue(hidden)
    renderDetail()

    const list = await screen.findByLabelText('Totals')
    expect(within(list).queryByText('Net payable')).not.toBeInTheDocument()
    expect(within(list).queryByText('VAT (22%)')).not.toBeInTheDocument()
    expect(within(list).queryByText('Taxable amount')).not.toBeInTheDocument()
    expect(within(list).queryByText('Accruals')).not.toBeInTheDocument()
    expect(within(list).queryByText('Adjustments')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Issue' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit adjustment' })).not.toBeInTheDocument()
  })
})

describe('CommissionSettlementDetailView - actions', () => {
  it('issues only after the confirmation', async () => {
    apiMocks.fetch.mockResolvedValue(settlement())
    apiMocks.issue.mockResolvedValue(settlement({ status: 'ISSUED', code: '1/2026', abilities: NO_ABILITIES }))
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Issue' }))
    expect(apiMocks.issue).not.toHaveBeenCalled()
    fireEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Issue' }))

    await waitFor(() => expect(apiMocks.issue).toHaveBeenCalledWith(12))
    expect(await screen.findByText('Statement 1/2026')).toBeInTheDocument()
  })

  it('shows a 409 as a toast with the server message', async () => {
    apiMocks.fetch.mockResolvedValue(settlement())
    apiMocks.recalculate.mockRejectedValue(conflict('The statement is no longer a draft.'))
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Recalculate' }))

    await waitFor(() => expect(toastMocks.error).toHaveBeenCalledWith('The statement is no longer a draft.'))
  })

  it('translates the machine reason of a 422 on issue instead of showing the raw code', async () => {
    apiMocks.fetch.mockResolvedValue(settlement())
    apiMocks.issue.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Invalid', errors: { reason: ['missing_profile'] } },
      } as never),
    )
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Issue' }))
    fireEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Issue' }))

    await waitFor(() => expect(toastMocks.error).toHaveBeenCalledWith('No valid fiscal profile at the end date'))
  })

  it('requires a reason to cancel an issued statement', async () => {
    apiMocks.fetch.mockResolvedValue(
      settlement({ status: 'ISSUED', code: '1/2026', abilities: { ...NO_ABILITIES, cancel: true }, movements: [] }),
    )
    apiMocks.cancel.mockResolvedValue(settlement({ status: 'CANCELLED', abilities: NO_ABILITIES, movements: [] }))
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'Cancel statement' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel statement' }))
    expect(await within(dialog).findByText('The reason is required.')).toHaveAttribute('role', 'alert')
    expect(apiMocks.cancel).not.toHaveBeenCalled()

    fireEvent.change(within(dialog).getByLabelText(/^Reason/), { target: { value: 'Wrong amounts' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel statement' }))

    await waitFor(() => expect(apiMocks.cancel).toHaveBeenCalledWith(12, { reason: 'Wrong amounts' }))
  })

  it('deletes the draft after the confirmation and notifies the caller', async () => {
    apiMocks.fetch.mockResolvedValue(settlement())
    apiMocks.remove.mockResolvedValue(undefined)
    const onDeleted = vi.fn()
    renderDetail(onDeleted)

    fireEvent.click(await screen.findByRole('button', { name: 'Delete draft' }))
    fireEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Delete' }))

    await waitFor(() => expect(apiMocks.remove).toHaveBeenCalledWith(12))
    await waitFor(() => expect(onDeleted).toHaveBeenCalled())
  })

  it('opens the adjustment form with agent and company fixed by the draft', async () => {
    apiMocks.fetch.mockResolvedValue(settlement())
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: 'New adjustment' }))

    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByText('Mario Rossi')).toBeInTheDocument()
    expect(within(dialog).getByText('Acme Srl')).toBeInTheDocument()
    expect(within(dialog).queryByRole('combobox')).not.toBeInTheDocument()
  })

  // REQUIREMENT CHANGED (spec 0265 D-1): the notes are a row edited in place, no "Save notes" button.
  it('saves the notes in place with a PATCH while the statement is a draft', async () => {
    apiMocks.fetch.mockResolvedValue(settlement())
    apiMocks.patch.mockResolvedValue(settlement({ notes: 'ok' }))
    renderDetail()

    fireEvent.click(await screen.findByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) }))
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'ok' } })
    fireEvent.click(screen.getByRole('button', { name: i18n.t('common.inlineEdit.save') }))

    await waitFor(() => expect(apiMocks.patch).toHaveBeenCalledWith(12, { notes: 'ok' }))
  })

  it('offers no pencil on the notes once the statement is issued', async () => {
    apiMocks.fetch.mockResolvedValue(settlement({ status: 'ISSUED' }))
    renderDetail()

    expect((await screen.findAllByText('Notes')).length).toBeGreaterThan(0)
    expect(screen.queryByRole('button', { name: i18n.t('common.inlineEdit.edit', { field: 'Notes' }) })).not.toBeInTheDocument()
  })
})
