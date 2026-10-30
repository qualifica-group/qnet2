import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { useForm, useWatch, type Control } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteCostsTab } from '@/features/quotes/quote-costs-tab'
import type { QuoteFormValues, QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { ResourcePermissions } from '@/features/authorization/types'

/** Spec 0065 AC-073: the Cost tab's catalogue NEVER sends `category_ids`. */

const costRowsSpy = vi.fn<(rows: QuoteLineFormValues[]) => void>()

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

const EMPTY_PAGE = { items: [] as unknown[], pagination: { offset: 0, limit: 25, total: 0, has_more: false }, export_link: null }

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

const EMPTY_VALUES: QuoteFormValues = {
  code: 'QUO-0001',
  title: '',
  quote_date: '2026-03-15',
  opportunity_id: 55,
  quote_workflow_status_id: null,
  note: null,
  commercial_id: null,
  reporter_id: null,
  supervisor_id: null,
  manager_slots: [],
  company_id: null,
  company_site_id: null,
  operational_site_id: null,
  source_id: null,
  layout_id: null,
  payment_method_id: null,
  internal_notes: null,
  rewards: [],
  attribute_values: {},
  offer_lines: [],
  cost_lines: [],
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

describe('QuoteCostsTab (spec 0065 AC-073, catalogue since spec 0269)', () => {
  const COST_PRODUCT = {
    id: 30,
    label: 'Docenza',
    subtitle: null,
    meta: {
      category_id: 5,
      code: 'DOC-1',
      description: null,
      price: '90.00',
      cost: '40.00',
      vat_rate_id: null,
      vat_rate_name: null,
      vat_rate: null,
      unit_of_measure: null,
      product_typology: null,
    },
  }

  function RowsSpy({ control }: { control: Control<QuoteFormValues> }) {
    costRowsSpy(useWatch({ control, name: 'cost_lines' }))
    return null
  }

  function CatalogueHarness() {
    const form = useForm<QuoteFormValues>({ defaultValues: EMPTY_VALUES })
    return (
      <Form {...form}>
        <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
          <QuoteCostsTab control={form.control} knownLines={[]} vatRatePercentFor={() => null} rememberVatRatePercent={vi.fn()} />
          <RowsSpy control={form.control} />
        </ResourcePermissionsProvider>
      </Form>
    )
  }

  // REQUIREMENT CHANGED (spec 0269): no "Add row" + per-row combobox; the
  // catalogue lists the whole COST catalogue, still never scoped by category.
  it('AC-009: lists the whole COST catalogue, never scoped by category, with no chips', async () => {
    fetchForSelectMock.mockResolvedValue({ ...EMPTY_PAGE, items: [COST_PRODUCT] })
    render(<CatalogueHarness />, { wrapper: wrapper() })

    await screen.findByText('Docenza')
    expect(fetchForSelectMock).toHaveBeenCalledWith('products', expect.objectContaining({ params: { usage: 'COST' } }))
    expect(screen.queryByRole('group', { name: 'Filter by category' })).not.toBeInTheDocument()
    expect(within(screen.getByRole('option', { name: /Docenza/ })).getByText(/40,00/)).toBeInTheDocument()
  })

  it('AC-009: adds the same cost product twice, at its cost price', async () => {
    fetchForSelectMock.mockResolvedValue({ ...EMPTY_PAGE, items: [COST_PRODUCT] })
    render(<CatalogueHarness />, { wrapper: wrapper() })

    for (let pick = 0; pick < 2; pick += 1) {
      fireEvent.click(await screen.findByRole('option', { name: /Docenza/ }))
      fireEvent.click(screen.getByRole('button', { name: 'Add 1 selected' }))
      await waitFor(() => expect(costRowsSpy.mock.lastCall?.[0]).toHaveLength(pick + 1))
    }

    const rows = costRowsSpy.mock.lastCall?.[0] ?? []
    expect(rows.map((row) => [row.product_id, row.unit_price])).toEqual([[30, 40], [30, 40]])
    expect(screen.getByRole('option', { name: /Docenza/ })).not.toHaveAttribute('aria-disabled', 'true')
    // A cost row never carries the revenue-only additional description.
    expect(screen.queryByRole('button', { name: 'Additional description' })).not.toBeInTheDocument()
  })
})

/** Spec 0144 AC-011/AC-012: the "Associated product" column of each cost row. */
describe('QuoteCostsTab — Associated product (spec 0144)', () => {
  function HarnessWithLines({ offerLines, costLines }: { offerLines: QuoteFormValues['offer_lines']; costLines: QuoteFormValues['cost_lines'] }) {
    const form = useForm<QuoteFormValues>({ defaultValues: { ...EMPTY_VALUES, offer_lines: offerLines, cost_lines: costLines } })
    return (
      <Form {...form}>
        <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
          <QuoteCostsTab
            control={form.control}
            knownLines={[]}
            vatRatePercentFor={() => null}
            rememberVatRatePercent={vi.fn()}
            productNameFor={(id) => (id === 7 ? 'Widget Pro' : null)}
          />
        </ResourcePermissionsProvider>
      </Form>
    )
  }

  const OFFER_ROW = { id: 1, client_key: 'line-1', product_id: 7, quantity: 1, unit_price: 10, vat_rate_id: null }
  const COST_ROW = { id: 2, client_key: 'line-2', product_id: 9, quantity: 1, unit_price: 2, vat_rate_id: null, offer_line_key: null }

  it('shows "None" as placeholder and one entry per offer row carrying a product, named after it (AC-011)', () => {
    render(<HarnessWithLines offerLines={[OFFER_ROW]} costLines={[COST_ROW]} />, { wrapper: wrapper() })

    const trigger = screen.getByRole('combobox', { name: 'Row 1 associated product' })
    expect(trigger).toHaveTextContent('None (generic cost)')

    fireEvent.click(trigger)
    expect(screen.getByPlaceholderText('Search product row…')).toBeInTheDocument()
    expect(screen.getAllByRole('option')).toHaveLength(1)
    expect(screen.getByRole('option', { name: 'Widget Pro (row 1)' })).toBeInTheDocument()
  })

  it('the clear button turns an associated cost back into a generic one', () => {
    const associatedCost = { ...COST_ROW, offer_line_key: 'line-1' }
    render(<HarnessWithLines offerLines={[OFFER_ROW]} costLines={[associatedCost]} />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'Clear Widget Pro (row 1)' }))

    expect(screen.getByRole('combobox', { name: 'Row 1 associated product' })).toHaveTextContent('None (generic cost)')
  })

  it('picking an offer row updates the field to its client_key', () => {
    render(<HarnessWithLines offerLines={[OFFER_ROW]} costLines={[COST_ROW]} />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('combobox', { name: 'Row 1 associated product' }))
    fireEvent.click(screen.getByRole('option', { name: 'Widget Pro (row 1)' }))

    expect(screen.getByRole('combobox', { name: 'Row 1 associated product' })).toHaveTextContent('Widget Pro (row 1)')
  })

  it('does not offer a pristine offer row with no product picked yet', () => {
    const pristineOffer = { client_key: 'line-empty', product_id: null, quantity: null, unit_price: null, vat_rate_id: null }
    render(<HarnessWithLines offerLines={[pristineOffer]} costLines={[COST_ROW]} />, { wrapper: wrapper() })

    fireEvent.click(screen.getByRole('combobox', { name: 'Row 1 associated product' }))
    expect(screen.queryAllByRole('option')).toHaveLength(0)
    expect(screen.getByText('No product rows')).toBeInTheDocument()
  })

  it('renders "None" for a cost already pointing at a product row that was removed (AC-012)', () => {
    const staleCost = { ...COST_ROW, offer_line_key: 'line-gone' }
    render(<HarnessWithLines offerLines={[OFFER_ROW]} costLines={[staleCost]} />, { wrapper: wrapper() })

    expect(screen.getByRole('combobox', { name: 'Row 1 associated product' })).toHaveTextContent(
      'None (generic cost)',
    )
  })
})
