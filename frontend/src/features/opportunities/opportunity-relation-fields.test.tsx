import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
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

describe('OpportunityManagersField', () => {
  // AC-043/044: `ManagerSlotsField` itself renders the resolved override
  // (`manager-slots-field.test.tsx`); this asserts the field resolves it
  // LIVE from `product_lines` and forwards it converted to the field's own
  // `Record<number, string>` shape.
  it('AC-043: forwards no `labels` with no product lines picked (Registries-parity default)', () => {
    render(<ManagersHarness />)

    expect(screen.getByTestId('manager-slots-field')).toHaveAttribute('data-labels', '')
    expect(fetchCategoryManagerLabelsMock).not.toHaveBeenCalled()
  })

  it('AC-044: resolves the picked category and converts wire string keys into position-number keys', async () => {
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '1': 'Commercial' })

    render(<ManagersHarness productLines={[{ root_category_id: null, product_category_id: 500 }]} />)

    await waitFor(() =>
      expect(screen.getByTestId('manager-slots-field')).toHaveAttribute(
        'data-labels',
        JSON.stringify({ 1: 'Commercial' }),
      ),
    )
  })

  it('AC-053 (amendment A1): threads a label configured past the 4th position the same as any other', async () => {
    fetchCategoryManagerLabelsMock.mockResolvedValue({ '5': 'Field consultant' })

    render(<ManagersHarness productLines={[{ root_category_id: null, product_category_id: 500 }]} />)

    await waitFor(() =>
      expect(screen.getByTestId('manager-slots-field')).toHaveAttribute(
        'data-labels',
        JSON.stringify({ 5: 'Field consultant' }),
      ),
    )
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
