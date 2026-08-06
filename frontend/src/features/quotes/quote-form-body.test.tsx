import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { WORKFLOW_STATUS_OPEN } from '@/features/quotes/quote-fixtures'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteFormBody } from '@/features/quotes/quote-form-body'
import { fetchQuoteFormContext, updateQuote } from '@/features/quotes/api'
import type { ApplicableAttributeSummary, QuoteDetailWithPermissions, QuoteLine } from '@/features/quotes/types'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0065: AC-070 (tabs + always-visible summary), AC-077 (a field marked
 * non-editable by permissions renders disabled via `MetaField`), AC-082 (the
 * `code` field is prefilled in create and read-only in edit).
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

function quoteFixture(): QuoteDetailWithPermissions {
  return {
    id: 9,
    code: 'QUO-0009',
    title: 'Sample quote',
    opportunity_id: 55,
    opportunity: { id: 55, name: 'OPP_55' },
    quote_workflow_status_id: 1,
    quote_workflow_status: WORKFLOW_STATUS_OPEN,
    quote_workflow_statuses: [WORKFLOW_STATUS_OPEN],
    applicable_attributes: [],
    attribute_layout: null,
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
    layout_id: null,
    layout: null,
    payment_method_id: null,
    payment_method: null,
    internal_notes: null,
    attribute_values: {},
    offer_lines: [],
    cost_lines: [],
    summary: {
      revenue: { net: '0.00', vat: '0.00', gross: '0.00' },
      cost: { net: '0.00', vat: '0.00', gross: '0.00' },
      margin: { net: '0.00' },
    },
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
  }
}

/** A persisted offer line carrying a product: the trigger of the dynamic-fields resolution (spec 0084 D-5). */
function offerLineFixture(): QuoteLine {
  return {
    id: 1,
    product_id: 7,
    product: { id: 7, name: 'Product 7', code: 'P7', category: null, business_function: null },
    quantity: '1.00',
    unit_price: '100.00',
    vat_rate_id: null,
    vat_rate: null,
    net_amount: '100.00',
    vat_amount: '0.00',
    total_amount: '100.00',
    sort_order: 0,
  }
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
  vi.mocked(updateQuote).mockReset()
})

describe('QuoteFormBody (spec 0065)', () => {
  it('shows the three tabs and keeps the economic summary visible below them regardless of the active tab (AC-070)', () => {
    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="" />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('tab', { name: 'Offer' })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Costs' })).toBeInTheDocument()
    // Renamed by the 2026-07-30 directive: the tab now also hosts the payment
    // method picker, so it is "Notes and payments"/"Note e pagamenti".
    expect(screen.getByRole('tab', { name: 'Notes and payments' })).toBeInTheDocument()
    expect(screen.getByText('Expected revenue')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('tab', { name: 'Costs' }))

    expect(screen.getByText('Expected revenue')).toBeInTheDocument()
  })

  it('prefills the code field with the suggested sequential code in create mode, editable (AC-082)', () => {
    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="QUO-0007" />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    const codeInput = screen.getByLabelText('Code') as HTMLInputElement
    expect(codeInput.value).toBe('QUO-0007')
    expect(codeInput).not.toBeDisabled()
  })

  it('shows the persisted code read-only in edit mode, without requesting a next-code (AC-082)', () => {
    const quote = quoteFixture()
    const editPermissions: ResourcePermissions = {
      ...FULL_ACCESS_PERMISSIONS,
      fields: { code: READONLY_FIELD },
    }

    render(
      <ResourcePermissionsProvider permissions={editPermissions}>
        <QuoteFormBody mode={{ type: 'edit', quote: { ...quote, permissions: editPermissions } }} onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    const codeInput = screen.getByLabelText('Code') as HTMLInputElement
    expect(codeInput.value).toBe('QUO-0009')
    expect(codeInput).toBeDisabled()
  })

  it('disables a field the permissions mark non-editable, via MetaField (AC-077)', () => {
    const permissions: ResourcePermissions = {
      ...FULL_ACCESS_PERMISSIONS,
      fields: { commercial_id: READONLY_FIELD },
    }

    render(
      <ResourcePermissionsProvider permissions={permissions}>
        <QuoteFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="" />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    expect(screen.getByRole('combobox', { name: 'Commercial' })).toBeDisabled()
  })

  // Spec 0084 D-5 regression: the applicable set arrives AFTER the form is
  // built, so a quote saved before an Attribute was configured (or simply left
  // blank) has no key for it in `attribute_values`. Without seeding one per
  // applicable `code`, the rebuilt Zod object rejects the missing keys and
  // `handleSubmit` aborts with errors on fields the operator never touched —
  // the save button visibly does nothing.
  it('saves in edit mode when the resolved attributes have no stored value yet', async () => {
    const quote = quoteFixture()
    quote.offer_lines = [offerLineFixture()]
    vi.mocked(fetchQuoteFormContext).mockResolvedValue({
      applicable_attributes: [attributeFixture('text', 'colour'), attributeFixture('boolean', 'urgent')],
      attribute_layout: null,
    })
    vi.mocked(updateQuote).mockResolvedValue(quote)

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'edit', quote }} onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    await screen.findByLabelText('colour')

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(vi.mocked(updateQuote)).toHaveBeenCalledTimes(1))
  })

  // Regressione 2026-08-06: un'offerta senza alcun valore dinamico salvato
  // arriva con `attribute_values` serializzato come ARRAY vuoto (`[]`, la
  // resa JSON di un array PHP vuoto), non come mappa. Lo Zod `z.object` lo
  // rifiuta, l'errore cade su `attribute_values` — che nessun input rende — e
  // `handleSubmit` aborta: il pulsante Salva non fa nulla. La seed non lo
  // ripara, perche' con set applicabile vuoto produce `{}` e
  // `setValue(name, {})` di RHF non ha chiavi su cui ricorrere.
  it('saves in edit mode when the stored attribute map arrives as an empty array', async () => {
    const quote = quoteFixture()
    quote.offer_lines = [offerLineFixture()]
    quote.attribute_values = [] as unknown as typeof quote.attribute_values
    vi.mocked(fetchQuoteFormContext).mockResolvedValue({
      applicable_attributes: [],
      attribute_layout: null,
    })
    vi.mocked(updateQuote).mockResolvedValue(quote)

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'edit', quote }} onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'Nuovo titolo' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(vi.mocked(updateQuote)).toHaveBeenCalledTimes(1))
  })

  // Spec 0084 D-5 (direttiva utente 2026-08-06): l'innesco della sezione e' la
  // SCELTA DEL PRODOTTO. Senza prodotto non c'e' categoria, quindi nessun
  // attributo: la sezione non deve esistere affatto. Un placeholder "nessun
  // campo aggiuntivo" sotto i totali di un'offerta appena aperta si legge come
  // un difetto, non come un'informazione.
  it('does not render the additional-information section until a product is picked (AC-034)', async () => {
    const quote = quoteFixture()
    quote.offer_lines = []
    vi.mocked(fetchQuoteFormContext).mockResolvedValue({
      applicable_attributes: [attributeFixture('text', 'colour')],
      attribute_layout: null,
    })

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'edit', quote }} onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    await screen.findByRole('button', { name: 'Save' })

    expect(
      screen.queryByText(/Informazioni aggiuntive|Additional information/),
    ).not.toBeInTheDocument()
    // E nemmeno l'endpoint viene interrogato: senza prodotti non c'e' nulla da
    // risolvere, e chiamarlo costerebbe un round trip per un set vuoto.
    expect(vi.mocked(fetchQuoteFormContext)).not.toHaveBeenCalled()
  })

  // AC-035: con un prodotto scelto la catena prodotto -> categoria -> attributi
  // si risolve e la sezione compare, SENZA che l'offerta sia salvata.
  it('resolves and shows the section once an offer line carries a product (AC-035)', async () => {
    const quote = quoteFixture()
    quote.offer_lines = [offerLineFixture()]
    vi.mocked(fetchQuoteFormContext).mockResolvedValue({
      applicable_attributes: [attributeFixture('text', 'colour')],
      attribute_layout: null,
    })

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'edit', quote }} onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    expect(await screen.findByLabelText('colour')).toBeInTheDocument()
    expect(vi.mocked(fetchQuoteFormContext)).toHaveBeenCalledWith([offerLineFixture().product_id])
  })

  // AC-036: la sezione sta SOTTO il blocco dei tab righe (quindi sotto Costi) e
  // SOPRA il riepilogo economico. Verificato sull'ordine nel DOM, non
  // sull'aspetto: e' l'unica proprieta' oggettiva della richiesta.
  it('renders the section below the line tabs and above the economic summary (AC-036)', async () => {
    const quote = quoteFixture()
    quote.offer_lines = [offerLineFixture()]
    vi.mocked(fetchQuoteFormContext).mockResolvedValue({
      applicable_attributes: [attributeFixture('text', 'colour')],
      attribute_layout: null,
    })

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'edit', quote }} onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    const section = await screen.findByText(/Informazioni aggiuntive|Additional information/)
    const costsTab = screen.getByRole('tab', { name: /Costi|Costs/ })
    const summary = screen.getByText(/Ricavi attesi|Expected revenue/)

    expect(costsTab.compareDocumentPosition(section) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    expect(section.compareDocumentPosition(summary) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })
})
