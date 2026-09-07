import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { WORKFLOW_STATUS_OPEN } from '@/features/quotes/quote-fixtures'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteFormBody } from '@/features/quotes/quote-form-body'
import { createQuote, updateQuote } from '@/features/quotes/api'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

/**
 * Directive 2026-07-30: the "Note" tab became "Note e pagamenti" and hosts a
 * payment method picker fed by the `payment-methods` for-select, persisted on
 * the quote as `payment_method_id`. Renders the real `AsyncPaginatedSelect`
 * (not stubbed), only the HTTP layer is mocked — mirrors
 * `quote-layout-section.test.tsx`.
 */

vi.mock('@/features/quotes/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/quotes/api')>('@/features/quotes/api')
  return { ...actual, createQuote: vi.fn(), updateQuote: vi.fn(), fetchQuoteNextCode: vi.fn() }
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

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const PAYMENT_METHOD_ITEM = { id: 7, label: 'Bonifico 30gg', meta: { payment_days: 30 } }

/**
 * Spec 0102 AC-001: creation now requires at least one real offer line.
 * Seeded via the deep-link `product_ids` mechanism (`quote-form-body.tsx`,
 * user directive 2026-08-31, already exercised by
 * `quote-form-seeded-products.test.tsx`) rather than driving the
 * product-picker UI, which this file isn't testing.
 */
const SEEDED_PRODUCT_ITEM = {
  id: 42,
  label: 'Widget Pro',
  meta: {
    code: 'WGT',
    price: '10.00',
    cost: '5.00',
    vat_rate_id: null,
    vat_rate_name: null,
    vat_rate: null,
    unit_of_measure: null,
    product_typology: null,
  },
}

/** Serves the payment-methods and seeded-product for-selects; every other resource stays empty. */
function servePaymentMethods() {
  fetchForSelectMock.mockImplementation((resource: string, params: { ids?: number[] }) => {
    if (resource === 'payment-methods') {
      return Promise.resolve({ items: [PAYMENT_METHOD_ITEM], pagination: { offset: 0, limit: 25, total: 1 }, export_link: null })
    }
    if (resource === 'products' && params.ids?.includes(SEEDED_PRODUCT_ITEM.id)) {
      return Promise.resolve({ items: [SEEDED_PRODUCT_ITEM], pagination: { offset: 0, limit: 25, total: 1 }, export_link: null })
    }
    return Promise.resolve(EMPTY_PAGE)
  })
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function quoteFixture(overrides: Partial<QuoteDetailWithPermissions> = {}): QuoteDetailWithPermissions {
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
    product_typologies: [],
    },
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

/**
 * Opens the renamed tab; the picker lives inside its (lazily rendered)
 * content. Radix activates a tab on mousedown, not click.
 */
function openNotesTab() {
  fireEvent.mouseDown(screen.getByRole('tab', { name: 'Notes and payments' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
  // Reset the submit spies too: every assertion below reads `mock.calls[0]`,
  // which would otherwise be the previous test's submit.
  vi.mocked(createQuote).mockReset()
  vi.mocked(updateQuote).mockReset()
})

describe('QuoteNotesTab — create mode', () => {
  it('renames the tab to cover payments and exposes the payment method picker', () => {
    servePaymentMethods()

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="" />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    openNotesTab()

    expect(screen.getByRole('combobox', { name: 'Payment method' })).toBeInTheDocument()
    expect(screen.getByLabelText('Internal notes')).toBeInTheDocument()
  })

  it('sends the picked payment_method_id in the create payload', async () => {
    servePaymentMethods()

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody
          mode={{ type: 'create', params: { opportunity_id: 55, product_ids: String(SEEDED_PRODUCT_ITEM.id) } }}
          onSuccess={vi.fn()}
          onCancel={vi.fn()}
          initialCode=""
        />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    // Spec 0102 AC-001: wait for the deep-link seeded row (visible on the
    // default-active Offer tab) before switching away, or the create schema
    // rejects on an empty `offer_lines` (AC-040).
    await waitFor(() => expect(screen.getByLabelText('Row 1 quantity')).toHaveValue(1))

    openNotesTab()
    fireEvent.click(screen.getByRole('combobox', { name: 'Payment method' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Bonifico 30gg' }))

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Payment method' })).toHaveTextContent('Bonifico 30gg'),
    )

    fireEvent.change(screen.getByLabelText('Code'), { target: { value: 'QUO-0001' } })
    fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'Quote with payment' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createQuote).toHaveBeenCalled())
    expect(vi.mocked(createQuote).mock.calls[0][0]).toMatchObject({ payment_method_id: 7 })
  })

  it('leaves payment_method_id null when the user picks nothing', async () => {
    servePaymentMethods()

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody
          mode={{ type: 'create', params: { opportunity_id: 55, product_ids: String(SEEDED_PRODUCT_ITEM.id) } }}
          onSuccess={vi.fn()}
          onCancel={vi.fn()}
          initialCode=""
        />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    // Spec 0102 AC-001: wait for the deep-link seeded row before submitting,
    // or the create schema rejects on an empty `offer_lines` (AC-040).
    await waitFor(() => expect(screen.getByLabelText('Row 1 quantity')).toHaveValue(1))

    fireEvent.change(screen.getByLabelText('Code'), { target: { value: 'QUO-0002' } })
    fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'Quote without payment' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createQuote).toHaveBeenCalled())
    expect(vi.mocked(createQuote).mock.calls[0][0]).toMatchObject({ payment_method_id: null })
  })
})

describe('QuoteNotesTab — edit mode', () => {
  it('shows the persisted payment method straight off the loaded quote', () => {
    const quote = quoteFixture({ payment_method_id: 7, payment_method: { id: 7, name: 'Bonifico 30gg' } })

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'edit', quote }} onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    openNotesTab()

    expect(screen.getByRole('combobox', { name: 'Payment method' })).toHaveTextContent('Bonifico 30gg')
  })

  it('omits payment_method_id from the PATCH payload when it did not change', async () => {
    servePaymentMethods()
    const quote = quoteFixture({ payment_method_id: 7, payment_method: { id: 7, name: 'Bonifico 30gg' } })

    render(
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteFormBody mode={{ type: 'edit', quote }} onSuccess={vi.fn()} onCancel={vi.fn()} />
      </ResourcePermissionsProvider>,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'Renamed quote' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateQuote).toHaveBeenCalled())
    expect(vi.mocked(updateQuote).mock.calls[0][1]).not.toHaveProperty('payment_method_id')
  })
})
