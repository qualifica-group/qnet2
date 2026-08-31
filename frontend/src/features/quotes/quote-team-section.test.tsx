import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { QuoteTeamSection } from '@/features/quotes/quote-team-section'
import { WORKFLOW_STATUS_OPEN } from '@/features/quotes/quote-fixtures'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteDetail } from '@/features/quotes/types'

/**
 * Spec 0087 D-11: `QuoteTeamSection` mounts the shared `ManagerSlotsField`
 * (own rendering asserted in `manager-slots-field.test.tsx`), wires it to the
 * live `useQuoteManagerLabels` resolution (own rules asserted in
 * `use-quote-manager-labels.test.tsx`), hydrates the trigger labels from the
 * loaded quote's `managers`, and shows the D-7 sync banner.
 */

const fetchCategoryManagerLabelsMock = vi.fn()
const fetchOpportunityMock = vi.fn()
vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return {
    ...actual,
    fetchCategoryManagerLabels: (categoryId: number) => fetchCategoryManagerLabelsMock(categoryId),
    fetchOpportunity: (id: number) => fetchOpportunityMock(id),
  }
})

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

vi.mock('@/components/form/manager-slots-field', () => ({
  ManagerSlotsField: ({
    labels,
    selectedItems,
  }: {
    labels?: Record<number, string>
    selectedItems: { id: number; label: string }[]
  }) => (
    <div
      data-testid="manager-slots-field"
      data-labels={labels ? JSON.stringify(labels) : ''}
      data-selected={JSON.stringify(selectedItems)}
    />
  ),
}))

function baseValues(overrides: Partial<QuoteFormValues> = {}): QuoteFormValues {
  return {
    code: 'QUO-0001',
    title: 'Offerta',
    opportunity_id: null,
    quote_workflow_status_id: null,
    note: null,
    commercial_id: null,
    reporter_id: null,
    supervisor_id: null,
    manager_slots: [],
    company_id: null,
    company_site_id: null,
    operational_site_id: null,
    layout_id: null,
    payment_method_id: null,
    internal_notes: null,
    rewards: [],
    attribute_values: {},
    offer_lines: [],
    cost_lines: [],
    ...overrides,
  }
}

function quoteDetail(overrides: Partial<QuoteDetail> = {}): QuoteDetail {
  return {
    id: 9,
    code: 'QUO-0009',
    title: 'Offerta',
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
    rewards: [],
    attribute_values: {},
    offer_lines: [],
    cost_lines: [],
    summary: {
      revenue: { net: '0.00', vat: '0.00', gross: '0.00' },
      cost: { net: '0.00', vat: '0.00', gross: '0.00' },
      margin: { net: '0.00' },
    },
    created_at: '2026-08-31T00:00:00Z',
    updated_at: '2026-08-31T00:00:00Z',
    ...overrides,
  }
}

function TeamSectionHarness({
  values,
  original = null,
}: {
  values: QuoteFormValues
  original?: QuoteDetail | null
}) {
  const form = useForm<QuoteFormValues>({ defaultValues: values })
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return (
    <QueryClientProvider client={queryClient}>
      <Form {...form}>
        <QuoteTeamSection control={form.control} original={original} />
      </Form>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchCategoryManagerLabelsMock.mockReset()
  fetchOpportunityMock.mockReset()
  fetchForSelectMock.mockReset()
})

describe('QuoteTeamSection', () => {
  it('forwards no labels and no selection with nothing picked yet', () => {
    render(<TeamSectionHarness values={baseValues()} />)

    const field = screen.getByTestId('manager-slots-field')
    expect(field).toHaveAttribute('data-labels', '')
    expect(field).toHaveAttribute('data-selected', '[]')
    expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalled()
  })

  it('D-8 fallback: resolves labels from the linked Opportunity while there are no revenue rows yet', async () => {
    fetchOpportunityMock.mockResolvedValue({
      id: 55,
      product_lines: [{ id: 1, business_function: { id: 40, name: 'BF' }, product_category: { id: 500, name: 'Cat' } }],
      managers: [],
    })
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '1': 'Commercial' })

    render(<TeamSectionHarness values={baseValues({ opportunity_id: 55 })} />)

    await waitFor(() =>
      expect(screen.getByTestId('manager-slots-field')).toHaveAttribute(
        'data-labels',
        JSON.stringify({ 1: 'Commercial' }),
      ),
    )
  })

  it("hydrates the field's selectedItems from the loaded quote's managers", () => {
    const original = quoteDetail({ managers: [{ id: 12, name: 'Mario Rossi', position: 1 }] })

    render(<TeamSectionHarness values={baseValues({ manager_slots: [12] })} original={original} />)

    expect(screen.getByTestId('manager-slots-field')).toHaveAttribute(
      'data-selected',
      JSON.stringify([{ id: 12, label: 'Mario Rossi' }]),
    )
  })

  it('D-7: shows the sync banner only when the loaded quote is managers_synchronized', () => {
    const synced = quoteDetail({ managers_synchronized: true })
    render(<TeamSectionHarness values={baseValues()} original={synced} />)
    expect(
      screen.getByText('Synced with the opportunity: a change here updates its account managers too, and vice versa.'),
    ).toBeInTheDocument()
  })

  it('D-7: no banner when the loaded quote is not synchronized', () => {
    const notSynced = quoteDetail({ managers_synchronized: false })
    render(<TeamSectionHarness values={baseValues()} original={notSynced} />)
    expect(
      screen.queryByText('Synced with the opportunity: a change here updates its account managers too, and vice versa.'),
    ).not.toBeInTheDocument()
  })
})
