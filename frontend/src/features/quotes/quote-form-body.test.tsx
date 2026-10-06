import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteFormBody } from '@/features/quotes/quote-form-body'
import { createQuote, fetchQuoteFormContext } from '@/features/quotes/api'
import { clickCreateSave, openRow, pressRowButton, queryPencil, rowValue } from '@/features/quotes/quote-test-helpers'
import type { ApplicableAttributeSummary } from '@/features/quotes/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * The create form as a replica of the quote detail (spec 0197 D-6): closed
 * rows that open on their pencil, Done/Revert on the draft, the Offerta/Costi
 * grids open with the summary always visible (spec 0065 AC-070), a field
 * marked non-editable by permissions offering no pencil (AC-077), the `code`
 * prefilled (AC-082), and Save validating the whole draft.
 */

vi.mock('@/features/quotes/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/quotes/api')>('@/features/quotes/api')
  return {
    ...actual,
    createQuote: vi.fn(),
    updateQuote: vi.fn(),
    fetchQuoteNextCode: vi.fn(),
    fetchQuoteFormContext: vi.fn(),
  }
})

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// `commercial_id`/`reporter_id`/`supervisor_id` resolve to resources with a
// registered quick-create entry (referents/users): `RelationSelectField`
// renders a `<Can>`-gated "+" button for them, which needs `AuthProvider`.
// Stubbed the same way `quotes-table.test.tsx` already does, so this suite
// stays scoped to `QuoteFormBody` itself rather than the auth stack.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const READONLY_FIELD: FieldPermission = {
  visible: true,
  hidden: false,
  editable: false,
  readonly: true,
  required: false,
  disabled: true,
}

/** One applicable Attribute, NOT required: nothing on it may block the save. */
function attributeFixture(type: string, code: string): ApplicableAttributeSummary {
  return {
    id: 1,
    code,
    name: code,
    type,
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
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
  // Azzerato anche questo: senza, le chiamate si accumulano tra i test del
  // file e un'asserzione "non e' stato chiamato" fallisce per colpa del test
  // precedente, non del codice sotto esame.
  vi.mocked(fetchQuoteFormContext).mockReset()
  // Idem per la mutation: senza azzerarla, un `toHaveBeenCalledTimes(1)` passa
  // per merito della chiamata del test precedente e l'asserzione non verifica
  // piu' nulla.
  vi.mocked(createQuote).mockReset()
})

function renderCreate(permissions: ResourcePermissions = FULL_ACCESS_PERMISSIONS, initialCode = 'QUO-0007') {
  const onSuccess = vi.fn()
  render(
    <ResourcePermissionsProvider permissions={permissions}>
      <QuoteFormBody mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} initialCode={initialCode} />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
  return { onSuccess }
}

describe('QuoteFormBody — the detail replica (spec 0197)', () => {
  it('shows the Offer/Costs strip with its grids open and the summary visible on both tabs (AC-070)', () => {
    renderCreate()

    expect(screen.getByRole('tab', { name: 'Offer' })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Costs' })).toBeInTheDocument()
    // The grid is an editor of its own: open, on its single empty row (directive 2026-09-01).
    expect(screen.getByLabelText('Row 1 quantity')).toBeInTheDocument()
    expect(screen.getByText('Expected revenue')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('tab', { name: 'Costs' }))

    expect(screen.getByText('Expected revenue')).toBeInTheDocument()
  })

  it('opens every field row closed, the code prefilled with the suggestion (AC-082)', () => {
    renderCreate()

    expect(rowValue('Code')).toBe('QUO-0007')
    expect(screen.queryByRole('combobox', { name: 'Commercial' })).not.toBeInTheDocument()

    openRow('Code')

    const codeInput = screen.getByLabelText('Code') as HTMLInputElement
    expect(codeInput.value).toBe('QUO-0007')
    expect(codeInput).not.toBeDisabled()
  })

  it('offers no pencil on a field the permissions mark non-editable (AC-077)', () => {
    renderCreate({ ...FULL_ACCESS_PERMISSIONS, fields: { commercial_id: READONLY_FIELD } })

    expect(queryPencil('Commercial')).not.toBeInTheDocument()
    expect(queryPencil('Reporter')).toBeInTheDocument()
  })

  it('keeps a value on Done and restores the draft on Revert, without saving anything', () => {
    renderCreate()

    openRow('Title')
    fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'Fornitura annuale' } })
    pressRowButton('Done')
    expect(rowValue('Title')).toBe('Fornitura annuale')

    openRow('Title')
    fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'Altro titolo' } })
    pressRowButton('Revert')
    expect(rowValue('Title')).toBe('Fornitura annuale')
    expect(vi.mocked(createQuote)).not.toHaveBeenCalled()
  })

  it('validates the whole draft on Save: no request, the error under its closed row', async () => {
    renderCreate()

    clickCreateSave()

    expect(await screen.findByText('Opportunity is required.')).toBeInTheDocument()
    expect(vi.mocked(createQuote)).not.toHaveBeenCalled()
  })

  // Spec 0084 D-5 (direttiva utente 2026-08-06): l'innesco della sezione e' la
  // SCELTA DEL PRODOTTO. Senza prodotto non c'e' categoria, quindi nessun
  // attributo: la sezione non deve esistere, e nemmeno l'endpoint viene
  // interrogato (AC-034).
  it('does not render the additional-information section until a product is picked (AC-034)', () => {
    vi.mocked(fetchQuoteFormContext).mockResolvedValue({
      applicable_attributes: [attributeFixture('text', 'colour')],
      attribute_layout: null,
    })

    renderCreate()

    expect(screen.queryByText('Additional information')).not.toBeInTheDocument()
    expect(vi.mocked(fetchQuoteFormContext)).not.toHaveBeenCalled()
  })
})

describe('QuoteFormBody — manager promotion dialog (spec 0087 D-6)', () => {
  function membershipError(message: string) {
    return { isAxiosError: true, response: { status: 422, data: { errors: { manager_slots: [message] } } } }
  }

  /** A draft the create schema accepts: an Opportunita' and one priced product row. */
  function fillValidDraft() {
    fetchForSelectMock.mockImplementation((resource: string) =>
      Promise.resolve(
        resource === 'opportunities'
          ? { ...EMPTY_PAGE, items: [{ id: 55, label: 'OPP_55', meta: null }] }
          : resource === 'products'
            ? { ...EMPTY_PAGE, items: [{ id: 7, label: 'Product 7', meta: { code: 'P7', price: '100.00', cost: null, vat_rate_id: null, vat_rate_name: null, vat_rate: null } }] }
            : EMPTY_PAGE,
      ),
    )
  }

  it('asks for confirmation and retries with the promote flag when accepted (AC-005)', async () => {
    fillValidDraft()
    vi.mocked(createQuote)
      .mockRejectedValueOnce(membershipError('Anna Bianchi is not yet an account manager of the opportunity.'))
      .mockResolvedValueOnce({ id: 9 } as never)
    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody
          mode={{ type: 'create', params: { opportunity_id: 55, product_ids: '7' } }}
          onSuccess={vi.fn()}
          onCancel={vi.fn()}
          initialCode="QUO-0007"
        />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )
    await waitFor(() => expect(screen.getByLabelText('Row 1 quantity')).toHaveValue(1))

    clickCreateSave()

    const dialog = await screen.findByRole('alertdialog')
    expect(within(dialog).getByText('Anna Bianchi is not yet an account manager of the opportunity.')).toBeInTheDocument()

    fireEvent.click(within(dialog).getByRole('button', { name: 'Add and save' }))

    await waitFor(() => expect(vi.mocked(createQuote)).toHaveBeenCalledTimes(2))
    expect(vi.mocked(createQuote).mock.calls[1][0]).toMatchObject({ promote_managers_to_opportunity: true })
  })

  it('cancels the save outright on decline, without retrying (user directive 2026-08-31)', async () => {
    fillValidDraft()
    vi.mocked(createQuote).mockRejectedValue(membershipError('Anna Bianchi is not yet an account manager of the opportunity.'))
    const onSuccess = vi.fn()
    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody
          mode={{ type: 'create', params: { opportunity_id: 55, product_ids: '7' } }}
          onSuccess={onSuccess}
          onCancel={vi.fn()}
          initialCode="QUO-0007"
        />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )
    await waitFor(() => expect(screen.getByLabelText('Row 1 quantity')).toHaveValue(1))

    clickCreateSave()

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(vi.mocked(createQuote)).toHaveBeenCalledTimes(1)
    expect(onSuccess).not.toHaveBeenCalled()
  })
})
