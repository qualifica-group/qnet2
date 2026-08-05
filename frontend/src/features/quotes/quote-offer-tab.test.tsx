import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { QuoteOfferTab } from '@/features/quotes/quote-offer-tab'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { ResourcePermissions } from '@/features/authorization/types'
import type { OpportunityDetailWithPermissions } from '@/features/opportunities/types'

/**
 * Spec 0065 AC-072: the Offer tab's product picker defaults to the SELECTED
 * opportunity's own product-line categories; unlocking (confirmed) drops it.
 */

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

const fetchOpportunityMock = vi.fn()
vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return {
    ...actual,
    fetchOpportunity: (id: number) => fetchOpportunityMock(id),
  }
})

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function opportunityFixture(): OpportunityDetailWithPermissions {
  return {
    id: 55,
    name: 'OPP_55',
    registry_id: 1,
    registry: { id: 1, name: 'ACME' },
    referent_id: null,
    referent: null,
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    supervisor_id: null,
    supervisor: null,
    source_id: null,
    source: null,
    product_lines: [
      { id: 1, business_function: { id: 1, name: 'Sales' }, product_category: { id: 7, name: 'Widgets' } },
      { id: 2, business_function: { id: 1, name: 'Sales' }, product_category: { id: 9, name: 'Gadgets' } },
    ],
    lead_id: null,
    lead: null,
    managers: [],
    start_date: null,
    expected_close_date: null,
    estimated_value: null,
    success_probability: 0,
    general_notes: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
  } as unknown as OpportunityDetailWithPermissions
}

const EMPTY_VALUES: QuoteFormValues = {
  code: 'QUO-0001',
  title: '',
  opportunity_id: 55,
  quote_status_id: null,
  commercial_id: null,
  reporter_id: null,
  supervisor_id: null,
  company_id: null,
  company_site_id: null,
  operational_site_id: null,
  layout_id: null,
  payment_method_id: null,
  internal_notes: null,
  offer_lines: [],
  cost_lines: [],
}

function Harness() {
  const form = useForm<QuoteFormValues>({ defaultValues: EMPTY_VALUES })
  return (
    <Form {...form}>
      <ResourcePermissionsProvider permissions={FULL_ACCESS_PERMISSIONS}>
        <QuoteOfferTab
          control={form.control}
          knownLines={[]}
          vatRatePercentFor={() => null}
          rememberVatRatePercent={vi.fn()}
        />
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

async function addRowAndOpenProductPicker() {
  fireEvent.click(screen.getByRole('button', { name: 'Add row' }))
  fireEvent.click(screen.getByRole('combobox', { name: 'Row 1 product' }))
  await waitFor(() => expect(fetchForSelectMock).toHaveBeenCalledWith('products', expect.anything()))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
  fetchOpportunityMock.mockReset()
  fetchOpportunityMock.mockResolvedValue(opportunityFixture())
})

describe('QuoteOfferTab (spec 0065 AC-072)', () => {
  it("scopes the product picker to the opportunity's product-line categories by default", async () => {
    render(<Harness />, { wrapper: wrapper() })

    // Wait for the resolved categories to actually reach the row (not just
    // for the fetch to have fired): until then the picker is locked-without-
    // scope (AC-072's OWN empty-categories branch) and disabled.
    await screen.findByText("Products limited to the linked opportunity's categories.")
    await addRowAndOpenProductPicker()

    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'products',
      expect.objectContaining({ params: { category_ids: [7, 9] } }),
    )
  })

  it('stops sending category_ids once the operator confirms the unlock', async () => {
    render(<Harness />, { wrapper: wrapper() })

    await screen.findByText("Products limited to the linked opportunity's categories.")

    fireEvent.click(screen.getByRole('button', { name: 'Show all products' }))
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Show all products' }))

    await waitFor(() =>
      expect(screen.getByRole('button', { name: "Limit to the opportunity's categories" })).toBeInTheDocument(),
    )

    fetchForSelectMock.mockClear()
    await addRowAndOpenProductPicker()

    expect(fetchForSelectMock).toHaveBeenCalledWith(
      'products',
      expect.not.objectContaining({ params: expect.anything() }),
    )
  })
})
