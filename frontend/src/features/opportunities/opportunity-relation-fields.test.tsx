import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { MAX_MANAGER_SLOTS } from '@/components/form/manager-slots-limits'
import { OpportunityManagersField } from '@/features/opportunities/opportunity-relation-fields'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * Spec 0080: `OpportunityManagersField` resolves its slot labels LIVE from the
 * form's own `product_lines` (`useOpportunityManagerLabels`), identically on
 * the create form and the detail (spec 0198) — this suite only asserts the
 * wiring into `ManagerSlotsField` (its own rendering is
 * `manager-slots-field.test.tsx`; the resolution rule itself is
 * `use-opportunity-manager-labels.test.tsx`), plus the spec 0087 sync hint.
 * Ported from the removed `OpportunityTeamSection` suite.
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

vi.mock('@/components/form/manager-slots-field', () => ({
  ManagerSlotsField: ({ labels }: { labels?: Record<number, string> }) => (
    <div data-testid="manager-slots-field" data-labels={labels ? JSON.stringify(labels) : ''} />
  ),
}))

function ManagersHarness({
  synchronized = false,
  productLines = [],
}: {
  synchronized?: boolean
  productLines?: ProductLineRow[]
}) {
  const form = useForm<OpportunityFormValues>({
    defaultValues: { product_lines: productLines, manager_slots: [] },
  })
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return (
    <QueryClientProvider client={queryClient}>
      <Form {...form}>
        <OpportunityManagersField control={form.control} selected={[]} synchronized={synchronized} />
      </Form>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchCategoryManagerLabelsMock.mockReset()
})

/** The labels the field forwarded, keyed by position, as `ManagerSlotsField` received them. */
function forwardedLabels(): Record<string, string> {
  return JSON.parse(screen.getByTestId('manager-slots-field').getAttribute('data-labels') || '{}')
}

describe('OpportunityManagersField', () => {
  // AC-043/044: `ManagerSlotsField` itself renders the resolved override
  // (`manager-slots-field.test.tsx`); this asserts the field resolves it
  // LIVE from `product_lines` and forwards EVERY slot's label — the override
  // where configured, the shared default elsewhere — so the editor always
  // takes its named layout, as Commesse does (user request 2026-10-07).
  it('AC-043: forwards the shared default label for every slot with no product lines picked', () => {
    render(<ManagersHarness />)

    const labels = forwardedLabels()
    expect(Object.keys(labels)).toHaveLength(MAX_MANAGER_SLOTS)
    expect(labels[1]).toBe('Account manager 1')
    expect(labels[MAX_MANAGER_SLOTS]).toBe(`Account manager ${MAX_MANAGER_SLOTS}`)
    expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalled()
  })

  it('AC-044: resolves the picked category and puts its label on the matching position', async () => {
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '1': 'Commercial' })

    render(<ManagersHarness productLines={[{ root_category_id: null, product_category_id: 500 }]} />)

    await waitFor(() => expect(forwardedLabels()[1]).toBe('Commercial'))
    expect(forwardedLabels()[2]).toBe('Account manager 2')
  })

  it('AC-053 (amendment A1): threads a label configured past the 4th position the same as any other', async () => {
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '5': 'Field consultant' })

    render(<ManagersHarness productLines={[{ root_category_id: null, product_category_id: 500 }]} />)

    await waitFor(() => expect(forwardedLabels()[5]).toBe('Field consultant'))
    expect(forwardedLabels()[1]).toBe('Account manager 1')
  })

  // Spec 0087 (D-7): on a persisted opportunity the G.A. are kept identical
  // to one of its quotes', and the editor says so; a create has nothing to sync.
  it('shows the sync hint only when the managers are synchronized with a quote', () => {
    const synced = render(<ManagersHarness synchronized />)
    expect(screen.getByText(/Synced with its quote/)).toBeInTheDocument()
    synced.unmount()

    render(<ManagersHarness />)
    expect(screen.queryByText(/Synced with its quote/)).not.toBeInTheDocument()
  })
})
