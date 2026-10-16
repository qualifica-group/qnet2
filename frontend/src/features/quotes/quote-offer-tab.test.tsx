import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { useForm, useWatch, type Control } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { fetchQuoteCommissionDefaults } from '@/features/quotes/api'
import { QuoteOfferTab } from '@/features/quotes/quote-offer-tab'
import type { QuoteFormValues, QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'
import type { ForSelectParams } from '@/features/for-select/types'

/**
 * Spec 0065 AC-072 + spec 0269: the Offer tab's catalogue defaults to the
 * SELECTED opportunity's own product-line categories; the "Whole catalogue"
 * chip (confirmed) drops that scope. Spec 0077: a `single` category caps the
 * offer at one row, so the catalogue turns into a single pick. REQUIREMENT
 * CHANGED (spec 0269): rows come from the catalogue, not from "Add row" + a
 * per-row product combobox.
 */

const { categoryTree } = vi.hoisted(() => ({ categoryTree: { nodes: [] as unknown[] } }))

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: categoryTree.nodes, isPending: false, isError: false }),
}))

function treeWithMode(mode: 'single' | 'multiple') {
  const node = (overrides: Record<string, unknown>) => ({
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    is_active: true,
    management_mode: mode,
    ...overrides,
  })

  return [
    node({
      id: 6,
      name: 'Root',
      business_function_id: 1,
      is_selectable: false,
      children: [node({ id: 7, name: 'Widgets', parent_id: 6 }), node({ id: 9, name: 'Gadgets', parent_id: 6 })],
    }),
  ]
}

function product(id: number, label: string) {
  return {
    id,
    label,
    subtitle: null,
    meta: {
      category_id: 7,
      code: `W-${id}`,
      description: null,
      price: '50.00',
      cost: '20.00',
      vat_rate_id: null,
      vat_rate_name: null,
      vat_rate: null,
      unit_of_measure: null,
      product_typology: { id: 3, name: 'Course', color: 'blue' },
    },
  }
}

const CATALOGUE = [product(20, 'Widget Pro'), product(21, 'Widget Lite')]

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return { ...actual, fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params) }
})

const fetchOpportunityMock = vi.fn()
vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>('@/features/opportunities/api')
  return { ...actual, fetchOpportunity: (id: number) => fetchOpportunityMock(id) }
})

vi.mock('@/features/quotes/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/quotes/api')>('@/features/quotes/api')
  return { ...actual, fetchQuoteCommissionDefaults: vi.fn(), fetchQuoteCommissionRecipients: vi.fn() }
})

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function opportunityFixture(): OpportunityDetailWithPermissions {
  return {
    id: 55,
    product_lines: [
      { id: 1, business_function: { id: 1, name: 'Sales' }, product_category: { id: 7, name: 'Widgets' } },
      { id: 2, business_function: { id: 1, name: 'Sales' }, product_category: { id: 9, name: 'Gadgets' } },
    ],
    permissions: FULL_ACCESS_PERMISSIONS,
  } as unknown as OpportunityDetailWithPermissions
}

const EMPTY_VALUES = {
  opportunity_id: 55,
  commercial_id: 4,
  reporter_id: null,
  supervisor_id: null,
  offer_lines: [],
  cost_lines: [],
} as unknown as QuoteFormValues

const rowsSpy = vi.fn<(rows: QuoteLineFormValues[]) => void>()
const rememberProductTypology = vi.fn()
const rememberProductName = vi.fn()

function RowsSpy({ control }: { control: Control<QuoteFormValues> }) {
  rowsSpy(useWatch({ control, name: 'offer_lines' }))
  return null
}

function Harness({ permissions = FULL_ACCESS_PERMISSIONS, values = EMPTY_VALUES }: { permissions?: ResourcePermissions; values?: QuoteFormValues }) {
  const form = useForm<QuoteFormValues>({ defaultValues: values })
  return (
    <Form {...form}>
      <ResourcePermissionsProvider permissions={permissions}>
        <QuoteOfferTab
          control={form.control}
          knownLines={[]}
          vatRatePercentFor={() => null}
          rememberVatRatePercent={vi.fn()}
          rememberProductTypology={rememberProductTypology}
          rememberProductName={rememberProductName}
          quoteId={9}
        />
        <RowsSpy control={form.control} />
      </ResourcePermissionsProvider>
    </Form>
  )
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

/** The last catalogue request (not an ids-keyed label lookup). */
function lastCatalogParams() {
  const calls = fetchForSelectMock.mock.calls.filter(([resource, params]) => resource === 'products' && !(params as ForSelectParams).ids?.length)
  return (calls[calls.length - 1]?.[1] as ForSelectParams | undefined)?.params
}

const chips = () => within(screen.getByRole('group', { name: 'Filter by category' }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
  fetchForSelectMock.mockImplementation((resource: string, params: ForSelectParams = {}) => {
    // `ids[]` hydration answers the asked products, as ProductService::appendHydratedIds does.
    const items = resource !== 'products' ? [] : params.ids?.length ? CATALOGUE.filter((item) => params.ids?.includes(item.id)) : CATALOGUE
    return Promise.resolve({ items, pagination: { offset: 0, limit: 25, total: items.length, has_more: false }, export_link: null })
  })
  fetchOpportunityMock.mockResolvedValue(opportunityFixture())
  vi.mocked(fetchQuoteCommissionDefaults).mockReset().mockResolvedValue({ commissions: [], supplier_commission_direction: null })
  categoryTree.nodes = treeWithMode('multiple')
})

describe('QuoteOfferTab — catalogo (spec 0269)', () => {
  it("AC-002: scopes the catalogue to the opportunity's categories, with the chips", async () => {
    render(<Harness />, { wrapper: wrapper() })

    await screen.findByText('Widget Pro')
    expect(lastCatalogParams()).toEqual({ usage: 'SALE', category_ids: [7, 9] })
    expect(chips().getAllByRole('button').map((chip) => chip.textContent)).toEqual(['All', 'Widgets', 'Gadgets', 'Whole catalogue'])
  })

  it('AC-002: locks the catalogue until an opportunity is chosen', () => {
    render(<Harness values={{ ...EMPTY_VALUES, opportunity_id: null } as unknown as QuoteFormValues} />, { wrapper: wrapper() })

    expect(screen.getByText('Select an opportunity first.')).toBeInTheDocument()
    expect(screen.getByRole('searchbox')).toBeDisabled()
  })

  it('AC-003: the whole-catalogue chip asks first, then drops the scope until a category chip is picked again', async () => {
    render(<Harness />, { wrapper: wrapper() })
    await screen.findByText('Widget Pro')

    fireEvent.click(chips().getByRole('button', { name: 'Whole catalogue' }))
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Show all products' }))

    await waitFor(() => expect(lastCatalogParams()).toEqual({ usage: 'SALE' }))
    expect(chips().getByRole('button', { name: 'Whole catalogue' })).toHaveAttribute('aria-pressed', 'true')

    fireEvent.click(chips().getByRole('button', { name: 'All' }))
    await waitFor(() => expect(lastCatalogParams()).toEqual({ usage: 'SALE', category_ids: [7, 9] }))
  })

  it('AC-003: a cancelled confirmation keeps the scope', async () => {
    render(<Harness />, { wrapper: wrapper() })
    await screen.findByText('Widget Pro')

    fireEvent.click(chips().getByRole('button', { name: 'Whole catalogue' }))
    fireEvent.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(chips().getByRole('button', { name: 'Whole catalogue' })).toHaveAttribute('aria-pressed', 'false')
    expect(lastCatalogParams()).toEqual({ usage: 'SALE', category_ids: [7, 9] })
  })

  it('AC-006: a single-managed opportunity turns the catalogue into a single pick', async () => {
    categoryTree.nodes = treeWithMode('single')
    render(<Harness />, { wrapper: wrapper() })
    await screen.findByText('Widget Pro')

    expect(screen.queryByRole('button', { name: /^Add \d/ })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('option', { name: /Widget Pro/ }))

    await waitFor(() => expect(rowsSpy).toHaveBeenLastCalledWith([expect.objectContaining({ product_id: 20 })]))
  })
})

describe('QuoteOfferTab — righe e provvigioni (spec 0269)', () => {
  it('AC-004: adds every selected product with its commission defaults and feeds the summary caches', async () => {
    vi.mocked(fetchQuoteCommissionDefaults).mockResolvedValue({ commissions: [], supplier_commission_direction: 'RECEIVED' })
    render(<Harness />, { wrapper: wrapper() })
    await screen.findByText('Widget Pro')

    fireEvent.click(screen.getByRole('option', { name: /Widget Pro/ }))
    fireEvent.click(screen.getByRole('option', { name: /Widget Lite/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Add 2 selected' }))

    await waitFor(() => expect(rowsSpy.mock.lastCall?.[0]).toHaveLength(2))
    const rows = rowsSpy.mock.lastCall?.[0] ?? []
    expect(fetchQuoteCommissionDefaults).toHaveBeenCalledWith(expect.objectContaining({ quote_id: 9, product_id: 20, line_net_amount: 50, commercial_id: 4 }))
    expect(rows[0]).toMatchObject({ product_id: 20, quantity: 1, unit_price: 50, supplier_commission_direction: 'RECEIVED' })
    expect(rows[0].client_key).toEqual(expect.any(String))
    expect(rows[1].client_key).not.toBe(rows[0].client_key)
    expect(rememberProductTypology).toHaveBeenCalledWith(20, 3)
    expect(rememberProductName).toHaveBeenCalledWith(21, 'Widget Lite')
  })

  it('AC-005: a failed default adds nothing and keeps the selection', async () => {
    vi.mocked(fetchQuoteCommissionDefaults).mockRejectedValue(new Error('down'))
    render(<Harness />, { wrapper: wrapper() })
    await screen.findByText('Widget Pro')

    fireEvent.click(screen.getByRole('option', { name: /Widget Pro/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Add 1 selected' }))

    await waitFor(() => expect(fetchQuoteCommissionDefaults).toHaveBeenCalled())
    await waitFor(() => expect(screen.getByRole('button', { name: 'Add 1 selected' })).toBeEnabled())
    expect(rowsSpy).toHaveBeenLastCalledWith([])
    expect(screen.getByRole('option', { name: /Widget Pro/ })).toHaveAttribute('aria-selected', 'true')
  })

  it('AC-006/AC-007: a row card blocks its product in the catalogue and carries commissions and the additional description', async () => {
    const row = { product_id: 20, quantity: 1, unit_price: 50, vat_rate_id: null, commissions: [], client_key: 'k1' }
    render(<Harness values={{ ...EMPTY_VALUES, offer_lines: [row] } as unknown as QuoteFormValues} />, { wrapper: wrapper() })
    await screen.findByText('Widget Lite')

    expect(screen.getByRole('option', { name: /Widget Pro/ })).toHaveAttribute('aria-disabled', 'true')
    expect(screen.getByRole('button', { name: 'Additional description' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Commissions for line 1' }))
    expect(await screen.findByRole('dialog', { name: /Widget Pro.*1/ })).toBeInTheDocument()
  })

  it('AC-007: hides the commissions control when the commissions field is not visible', async () => {
    const row = { product_id: 20, quantity: 1, unit_price: 50, vat_rate_id: null, commissions: [], client_key: 'k1' }
    const hidden = { visible: false, hidden: true, editable: false, readonly: true, required: false, disabled: true }
    render(
      <Harness
        permissions={{ ...FULL_ACCESS_PERMISSIONS, fields: { commissions: hidden } }}
        values={{ ...EMPTY_VALUES, offer_lines: [row] } as unknown as QuoteFormValues}
      />,
      { wrapper: wrapper() },
    )
    await screen.findByText('Widget Lite')

    expect(screen.queryByRole('button', { name: 'Commissions for line 1' })).not.toBeInTheDocument()
  })
})
