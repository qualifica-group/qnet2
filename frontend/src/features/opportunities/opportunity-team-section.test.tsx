import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { OpportunityTeamSection } from '@/features/opportunities/opportunity-team-section'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * Spec 0080: `OpportunityTeamSection` resolves its slot labels LIVE from the
 * form's own `product_lines` (`useOpportunityManagerLabels`), identically in
 * create and edit — this suite only asserts the wiring into
 * `ManagerSlotsField` (its own rendering is `manager-slots-field.test.tsx`;
 * the resolution rule itself is `use-opportunity-manager-labels.test.tsx`).
 */

const fetchCategoryManagerLabelsMock = vi.fn()
vi.mock('@/features/opportunities/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/api')>(
    '@/features/opportunities/api',
  )
  return {
    ...actual,
    fetchCategoryManagerLabels: (categoryId: number) => fetchCategoryManagerLabelsMock(categoryId),
  }
})

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: () => <div />,
}))

vi.mock('@/components/form/manager-slots-field', () => ({
  ManagerSlotsField: ({ labels }: { labels?: Record<number, string> }) => (
    <div data-testid="manager-slots-field" data-labels={labels ? JSON.stringify(labels) : ''} />
  ),
}))

const EMPTY_SELECTED_ITEMS = {
  registry: null,
  opportunityStatus: null,
  referent: null,
  commercial: null,
  reporter: null,
  source: null,
  operationalSite: null,
  state: null,
  supervisor: null,
  managers: [],
}

function TeamSectionHarness({
  supervisorRequired,
  productLines = [],
}: {
  supervisorRequired: boolean
  productLines?: ProductLineRow[]
}) {
  const form = useForm<OpportunityFormValues>({
    defaultValues: {
      registry_id: null,
      opportunity_status_id: null,
      referent_id: null,
      commercial_id: null,
      reporter_id: null,
      supervisor_id: null,
      source_id: null,
      product_lines: productLines,
      manager_slots: [],
      start_date: null,
      expected_close_date: null,
      estimated_value: null,
      success_probability: 0,
    },
  })
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return (
    <QueryClientProvider client={queryClient}>
      <Form {...form}>
        <OpportunityTeamSection
          control={form.control}
          selectedItems={EMPTY_SELECTED_ITEMS}
          supervisorRequired={supervisorRequired}
        />
      </Form>
    </QueryClientProvider>
  )
}

function supervisorLabel(): HTMLElement {
  return screen.getByText(
    (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith('Supervisor') === true,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchCategoryManagerLabelsMock.mockReset()
})

describe('OpportunityTeamSection', () => {
  it('marks supervisor as required in create mode', () => {
    render(<TeamSectionHarness supervisorRequired />)

    expect(supervisorLabel()).toHaveTextContent('Supervisor*')
  })

  it('does not mark supervisor as required in edit mode', () => {
    render(<TeamSectionHarness supervisorRequired={false} />)

    expect(supervisorLabel()).toHaveTextContent('Supervisor')
    expect(supervisorLabel()).not.toHaveTextContent('*')
  })

  // AC-043/044: `ManagerSlotsField` itself renders the resolved override
  // (`manager-slots-field.test.tsx`); this asserts the section resolves it
  // LIVE from `product_lines` and forwards it converted to the field's own
  // `Record<number, string>` shape.
  it('AC-043: forwards no `labels` with no product lines picked (Registries-parity default)', () => {
    render(<TeamSectionHarness supervisorRequired={false} />)

    expect(screen.getByTestId('manager-slots-field')).toHaveAttribute('data-labels', '')
    expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalled()
  })

  it('AC-044: resolves the picked category and converts wire string keys into position-number keys', async () => {
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '1': 'Commercial' })

    render(
      <TeamSectionHarness
        supervisorRequired={false}
        productLines={[{ business_function_id: 40, product_category_id: 500 }]}
      />,
    )

    await waitFor(() =>
      expect(screen.getByTestId('manager-slots-field')).toHaveAttribute(
        'data-labels',
        JSON.stringify({ 1: 'Commercial' }),
      ),
    )
  })
})
