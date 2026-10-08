import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { fetchQuoteFormContext, updateQuote } from '@/features/quotes/api'
import { QuoteDetailView } from '@/features/quotes/quote-detail'
import { quoteLineFixture, WORKFLOW_STATUS_OPEN, WORKFLOW_STATUS_REQUIRES_NOTE } from '@/features/quotes/quote-fixtures'
import { openRow, pressRowButton, queryPencil, rowValue } from '@/features/quotes/quote-test-helpers'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { ApplicableAttributeSummary, QuoteDetailWithPermissions } from '@/features/quotes/types'

/**
 * Spec 0197 (the Commesse model of spec 0196): no edit page, a pencil per
 * editable row — the flexible fields included, one row each, and the two
 * line sets — Save PATCHes that field alone, Cancel restores it without any
 * request.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/quotes/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/quotes/api')>('@/features/quotes/api')
  return { ...actual, updateQuote: vi.fn(), fetchQuoteFormContext: vi.fn() }
})

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: () =>
      Promise.resolve({ items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }),
  }
})

const READ_ONLY_FIELD: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: false,
}

/** As the backend ceiling reports a persisted offer (AC-025/AC-069). */
const PERSISTED_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: { code: READ_ONLY_FIELD, opportunity_id: READ_ONLY_FIELD },
  actions: { generate_document: true },
}

const SITE_ACCESS: ApplicableAttributeSummary = {
  id: 1,
  code: 'site_access',
  name: 'Site access',
  type: 'text',
  description: null,
  help_text: null,
  placeholder: null,
  icon: null,
  config: null,
  relation_target: null,
  is_required: false,
  sort_order: 0,
  options: [],
}

function quote(overrides: Partial<QuoteDetailWithPermissions> = {}): QuoteDetailWithPermissions {
  return {
    id: 9,
    code: 'QUO-0009',
    title: 'Fornitura annuale',
    opportunity_id: 55,
    opportunity: { id: 55, name: 'OPP_55' },
    quote_workflow_status_id: 1,
    quote_workflow_status: WORKFLOW_STATUS_OPEN,
    quote_workflow_statuses: [WORKFLOW_STATUS_OPEN, WORKFLOW_STATUS_REQUIRES_NOTE],
    applicable_attributes: [SITE_ACCESS],
    attribute_layout: null,
    attribute_values: { site_access: 'Gate 3' },
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    company_id: null,
    company: null,
    company_site_id: null,
    company_site: null,
    operational_site_id: null,
    operational_site: null,
    layout_id: 41,
    layout: { id: 41, name: 'Layout disattivato' },
    payment_method_id: 7,
    payment_method: { id: 7, name: 'Bonifico 30gg' },
    internal_notes: null,
    offer_lines: [quoteLineFixture({ id: 3, product: { ...quoteLineFixture().product, name: 'Consulenza' } })],
    cost_lines: [],
    summary: {
      revenue: { net: '10.00', vat: '0.00', gross: '10.00' },
      cost: { net: '0.00', vat: '0.00', gross: '0.00' },
      margin: { net: '10.00' },
      product_typologies: [],
    },
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: PERSISTED_PERMISSIONS,
    ...overrides,
  }
}

function renderDetail(record: QuoteDetailWithPermissions) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const onChanged = vi.fn()
  render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <ConfirmDialogProvider>
          <QuoteDetailView quote={record} onChanged={onChanged} />
        </ConfirmDialogProvider>
      </QueryClientProvider>
    </MemoryRouter>,
  )
  return { onChanged }
}

function editTitle(value: string) {
  openRow('Title')
  fireEvent.change(screen.getByLabelText('Title'), { target: { value } })
  pressRowButton('Save')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateQuote).mockReset()
  vi.mocked(fetchQuoteFormContext).mockReset()
})

describe('QuoteDetailView — in-place editing (spec 0197)', () => {
  it('offers no Edit button: the record edits in place (AC-001)', () => {
    renderDetail(quote())

    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument()
    expect(queryPencil('Title')).toBeInTheDocument()
  })

  it('saves the edited field alone and reports the change (AC-002)', async () => {
    const record = quote()
    vi.mocked(updateQuote).mockResolvedValueOnce({ ...record, title: 'Nuovo titolo' })
    const { onChanged } = renderDetail(record)

    editTitle('Nuovo titolo')

    await waitFor(() => expect(vi.mocked(updateQuote)).toHaveBeenCalledWith(9, { title: 'Nuovo titolo' }))
    await waitFor(() => expect(onChanged).toHaveBeenCalledTimes(1))
    expect(screen.queryByLabelText('Title')).not.toBeInTheDocument()
  })

  it('restores the field on Cancel without any request (AC-002)', () => {
    renderDetail(quote())

    openRow('Title')
    fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'Scartato' } })
    pressRowButton('Cancel')

    expect(rowValue('Title')).toBe('Fornitura annuale')
    expect(vi.mocked(updateQuote)).not.toHaveBeenCalled()
  })

  it('shows no pencil on the fields fixed at creation (code, opportunity)', () => {
    renderDetail(quote())

    expect(rowValue('Code')).toBe('QUO-0009')
    expect(queryPencil('Code')).not.toBeInTheDocument()
    expect(queryPencil('Opportunity')).not.toBeInTheDocument()
  })

  it('edits each flexible field as a row of its own and PATCHes the map (AC-003)', async () => {
    const record = quote()
    vi.mocked(updateQuote).mockResolvedValueOnce(record)
    renderDetail(record)

    expect(rowValue('Site access')).toBe('Gate 3')
    openRow('Site access')
    fireEvent.change(screen.getByRole('textbox', { name: 'Site access' }), { target: { value: 'Gate 4' } })
    pressRowButton('Save')

    await waitFor(() =>
      expect(vi.mocked(updateQuote)).toHaveBeenCalledWith(9, { attribute_values: { site_access: 'Gate 4' } }),
    )
    // The persisted products resolve the set themselves: no form-context round trip.
    expect(vi.mocked(fetchQuoteFormContext)).not.toHaveBeenCalled()
  })

  it('offers no pencil on the flexible fields without the attribute_values permission (AC-003)', () => {
    renderDetail(
      quote({
        permissions: {
          ...PERSISTED_PERMISSIONS,
          fields: { ...PERSISTED_PERMISSIONS.fields, attribute_values: READ_ONLY_FIELD },
        },
      }),
    )

    expect(rowValue('Site access')).toBe('Gate 3')
    expect(queryPencil('Site access')).not.toBeInTheDocument()
  })

  // Regressione 2026-08-06: un'offerta senza valori dinamici arriva con
  // `attribute_values` serializzato come ARRAY vuoto; lo Zod `z.object` lo
  // rifiuterebbe su un campo che nessun editor mostra e il salvataggio non
  // partirebbe mai.
  it('saves another field when the stored attribute map arrives as an empty array', async () => {
    const record = quote({ attribute_values: [] as unknown as QuoteDetailWithPermissions['attribute_values'] })
    vi.mocked(updateQuote).mockResolvedValueOnce(record)
    renderDetail(record)

    editTitle('Nuovo titolo')

    await waitFor(() => expect(vi.mocked(updateQuote)).toHaveBeenCalledTimes(1))
  })

  it('opens the status on its own row, the persisted pill as its value', () => {
    renderDetail(quote())

    expect(rowValue('Status')).toContain(WORKFLOW_STATUS_OPEN.name)
    openRow('Status')

    expect(screen.getByRole('combobox', { name: 'Status' })).toBeInTheDocument()
  })

  it('shows the persisted document layout and payment method, even a deactivated layout (spec 0070 AC-312)', () => {
    renderDetail(quote())

    expect(rowValue('Layout')).toBe('Layout disattivato')
    expect(rowValue('Payment method')).toBe('Bonifico 30gg')
  })

  it('swaps the offer rows for their grid and the summary for the live one (AC-005)', () => {
    renderDetail(quote())

    expect(screen.getByText('Consulenza')).toBeInTheDocument()
    openRow('Offer rows')

    expect(screen.getByLabelText('Row 1 quantity')).toHaveValue(1)
    expect(screen.getByText('Expected revenue')).toBeInTheDocument()

    pressRowButton('Cancel')
    expect(screen.queryByLabelText('Row 1 quantity')).not.toBeInTheDocument()
    expect(vi.mocked(updateQuote)).not.toHaveBeenCalled()
  })

  // Spec 0087 D-6/AC-004/AC-005: a G.A. not yet on the Opportunity is refused
  // with a 422 on `manager_slots`; the save asks before widening the
  // Opportunity's team and retries with the flag only on an explicit confirm.
  it('asks before promoting a manager and retries with the flag when accepted', async () => {
    const record = quote()
    vi.mocked(updateQuote)
      .mockRejectedValueOnce({
        isAxiosError: true,
        response: { status: 422, data: { errors: { manager_slots: ['Anna Bianchi is not yet an account manager.'] } } },
      })
      .mockResolvedValueOnce(record)
    renderDetail(record)

    editTitle('Nuovo titolo')

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Add and save' }))

    await waitFor(() => expect(vi.mocked(updateQuote)).toHaveBeenCalledTimes(2))
    expect(vi.mocked(updateQuote).mock.calls[1][1]).toMatchObject({ promote_managers_to_opportunity: true })
  })
})
