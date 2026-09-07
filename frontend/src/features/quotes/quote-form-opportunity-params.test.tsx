import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteFormBody } from '@/features/quotes/quote-form-body'
import { createQuote } from '@/features/quotes/api'
import type { OpportunityForSelectItem } from '@/features/opportunities/for-select-api'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * Spec 0067 AC-050/051/052/053: with the Opportunity preset via
 * `mode.params.opportunity_id` (the panel "Crea Offerta" flow, spec 0045
 * `ModuleCreateParams`), the field is precompiled with the Opportunity's
 * NAME, locked read-only, the payload carries the id, and the three
 * commercial roles inherit from its `meta` — the SAME handler exercised (via
 * a real pick) in `quote-form-opportunity-roles.test.tsx`. Renders the real
 * `AsyncPaginatedSelect` (not stubbed) so the label-hydration path is
 * exercised end to end; only the HTTP layer is mocked.
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

const FORCED_OPPORTUNITY: OpportunityForSelectItem = {
  id: 55,
  label: 'OPP_55',
  meta: {
    commercial: { id: 71, name: 'Sara Conti' },
    reporter: { id: 81, name: 'Elio Fabbri' },
    supervisor: { id: 61, name: 'Ivo Bianchi' },
    operational_site: { id: 91, label: 'Via Ereditata 1 - Milano' },
    managers: [],
  },
}

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

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function renderForm(mode: Parameters<typeof QuoteFormBody>[0]['mode']) {
  render(
    <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
      <QuoteFormBody mode={mode} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="" />
    </ResourcePermissionsProvider>,
    { wrapper: wrapper() },
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string, params: { ids?: number[] }) => {
    if (resource === 'opportunities' && params.ids?.includes(55)) {
      return Promise.resolve({ items: [FORCED_OPPORTUNITY], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null })
    }
    if (resource === 'products' && params.ids?.includes(SEEDED_PRODUCT_ITEM.id)) {
      return Promise.resolve({ items: [SEEDED_PRODUCT_ITEM], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null })
    }
    return Promise.resolve(EMPTY_PAGE)
  })
})

describe('QuoteFormBody — Opportunity preset via create params (spec 0067)', () => {
  it('AC-050/051: prefills the Opportunity with its name and locks the field', async () => {
    renderForm({ type: 'create', params: { opportunity_id: 55 } })

    const opportunityField = screen.getByRole('combobox', { name: 'Opportunity' })
    await waitFor(() => expect(opportunityField).toHaveTextContent('OPP_55'))
    expect(opportunityField).toBeDisabled()
  })

  it('AC-051: the create payload carries the forced opportunity_id', async () => {
    renderForm({ type: 'create', params: { opportunity_id: 55, product_ids: String(SEEDED_PRODUCT_ITEM.id) } })

    fireEvent.change(screen.getByLabelText('Code'), { target: { value: 'QUO-0001' } })
    fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'Quote for OPP_55' } })
    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Opportunity' })).toHaveTextContent('OPP_55'),
    )
    // Spec 0102 AC-001: wait for the deep-link seeded row before submitting,
    // or the create schema rejects on an empty `offer_lines` (AC-040).
    await waitFor(() => expect(screen.getByLabelText('Row 1 quantity')).toHaveValue(1))

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createQuote).toHaveBeenCalled())
    expect(vi.mocked(createQuote).mock.calls[0][0]).toMatchObject({ opportunity_id: 55 })
  })

  it('AC-052: the three commercial roles are precompiled from the forced Opportunity meta', async () => {
    renderForm({ type: 'create', params: { opportunity_id: 55 } })

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Commercial' })).toHaveTextContent('Sara Conti'),
    )
    expect(screen.getByRole('combobox', { name: 'Reporter' })).toHaveTextContent('Elio Fabbri')
    expect(screen.getByRole('combobox', { name: 'Supervisor' })).toHaveTextContent('Ivo Bianchi')
  })

  it('AC-053: with no params, the Opportunity field stays empty and enabled', async () => {
    renderForm({ type: 'create' })

    const opportunityField = screen.getByRole('combobox', { name: 'Opportunity' })
    expect(opportunityField).not.toBeDisabled()
    expect(opportunityField).toHaveTextContent('Select…')
    expect(screen.getByRole('combobox', { name: 'Commercial' })).toHaveTextContent('Select…')
  })
})
