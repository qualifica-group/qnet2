import { beforeAll, beforeEach, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteFormBody } from '@/features/quotes/quote-form-body'
import { createQuote } from '@/features/quotes/api'
import { quoteCreateHref, parseQuoteCreateProductIds } from '@/features/quotes/quote-create-params'
import type { ResourcePermissions } from '@/features/authorization/types'

/**
 * User directive 2026-08-31: an opportunity create refused because the
 * anagrafica already has an open one offers to add the offer THERE instead —
 * the deep link carries the products, and the offer form opens with its rows
 * already filled from them (price and VAT included, exactly like a manual
 * pick).
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

const OPPORTUNITY = {
  id: 25,
  label: 'OPP_25',
  meta: {
    commercial: null,
    reporter: null,
    supervisor: null,
    operational_site: null,
  },
}

const PRODUCTS = [
  {
    id: 4,
    label: 'Fibra 1000',
    meta: { code: 'FIB', price: '120.00', cost: '40.00', vat_rate_id: 7, vat_rate_name: '22%', vat_rate: '22' },
  },
  {
    id: 5,
    label: 'Gas casa',
    meta: { code: 'GAS', price: '80.50', cost: '20.00', vat_rate_id: 7, vat_rate_name: '22%', vat_rate: '22' },
  },
]

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function renderForm(params: Record<string, string | number>) {
  render(
    <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
      <QuoteFormBody mode={{ type: 'create', params }} onSuccess={vi.fn()} onCancel={vi.fn()} initialCode="" />
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
    if (resource === 'opportunities' && params.ids?.includes(25)) {
      return Promise.resolve({ ...EMPTY_PAGE, items: [OPPORTUNITY] })
    }
    if (resource === 'products' && params.ids !== undefined) {
      return Promise.resolve({
        ...EMPTY_PAGE,
        items: PRODUCTS.filter((product) => params.ids?.includes(product.id)),
      })
    }
    return Promise.resolve(EMPTY_PAGE)
  })
})

it('builds a deep link carrying the opportunity and the products', () => {
  expect(quoteCreateHref(25, [4, 5])).toBe('/quotes/new?opportunity_id=25&product_ids=4%2C5')
  // With nothing to seed the link stays the plain "create an offer here" one.
  expect(quoteCreateHref(25, [])).toBe('/quotes/new?opportunity_id=25')
})

it('reads the product ids back, dropping anything unusable', () => {
  expect(parseQuoteCreateProductIds({ product_ids: '4, 5' })).toEqual([4, 5])
  expect(parseQuoteCreateProductIds({ product_ids: '4,x,0,-2' })).toEqual([4])
  expect(parseQuoteCreateProductIds({ opportunity_id: 25 })).toEqual([])
  expect(parseQuoteCreateProductIds()).toEqual([])
})

it('seeds one offer row per product, priced from the product itself', async () => {
  renderForm({ opportunity_id: 25, product_ids: '4,5' })

  await waitFor(() => expect(screen.getByLabelText('Row 1 quantity')).toHaveValue(1))
  expect(screen.getByLabelText('Row 1 unit price')).toHaveValue(120)
  expect(screen.getByLabelText('Row 2 quantity')).toHaveValue(1)
  expect(screen.getByLabelText('Row 2 unit price')).toHaveValue(80.5)
})

it('submits the seeded rows as the offer of the blocking opportunity', async () => {
  renderForm({ opportunity_id: 25, product_ids: '4,5' })

  await waitFor(() => expect(screen.getByLabelText('Row 1 quantity')).toHaveValue(1))
  fireEvent.change(screen.getByLabelText('Code'), { target: { value: 'QUO-0001' } })
  fireEvent.change(screen.getByLabelText('Title'), { target: { value: 'Offer for OPP_25' } })

  fireEvent.click(screen.getByRole('button', { name: 'Save' }))

  await waitFor(() => expect(createQuote).toHaveBeenCalled())
  expect(vi.mocked(createQuote).mock.calls[0][0]).toMatchObject({
    opportunity_id: 25,
    offer_lines: [
      { product_id: 4, quantity: 1, unit_price: 120, vat_rate_id: 7 },
      { product_id: 5, quantity: 1, unit_price: 80.5, vat_rate_id: 7 },
    ],
  })
})

// Direttiva 2026-09-01: senza prodotti nel link la form non resta a griglia
// vuota, apre su UNA riga vuota (nessun prodotto preselezionato) — la riga
// intatta non viaggia nel payload (`toLineInputs`).
it('opens on one empty offer row when the link carries no product', async () => {
  renderForm({ opportunity_id: 25 })

  await waitFor(() =>
    expect(screen.getByRole('combobox', { name: 'Opportunity' })).toHaveTextContent('OPP_25'),
  )
  expect(screen.getByLabelText('Row 1 quantity')).toHaveValue(null)
  expect(screen.queryByLabelText('Row 2 quantity')).not.toBeInTheDocument()
})
