import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useForm, useWatch } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { CompetenceLinesField } from '@/features/product-lines/competence-lines-field'
import type { CompetenceLineRow } from '@/features/product-lines/types'

/**
 * Spec 0129 D-1..D-7, spec 0111 D-5: the "all categories of the function"
 * per-row checkbox (AC-021) and the absence of any `single`-mode row cap
 * (AC-024) — split out of `competence-lines-field.test.tsx` purely to stay
 * under the file-size limit (`engineering.md` §6), same boilerplate shape and
 * fixtures.
 */

const TEST_BUSINESS_FUNCTION_A = 1
const TEST_PRODUCT_CATEGORY_A = 11

const { CATEGORY_TREE } = vi.hoisted(() => {
  const node = (overrides: Record<string, unknown>) => ({
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    ...overrides,
  })

  return {
    CATEGORY_TREE: [
      node({
        id: 100,
        name: 'Formazione',
        business_function_id: 1,
        is_selectable: false,
        children: [
          node({ id: 11, name: 'Consulting', parent_id: 100 }),
          node({ id: 22, name: 'Training', parent_id: 100 }),
        ],
      }),
    ],
  }
})

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({
    data: CATEGORY_TREE,
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
}))

vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: ({
    value,
    onChange,
    options,
    disabled,
    labels,
  }: {
    value: number | null
    onChange: (value: number) => void
    options: { id: number; name: string; disabled?: boolean; depth: number }[]
    disabled?: boolean
    labels: { triggerLabel?: string }
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
      {options
        .filter((option) => !option.disabled)
        .map((option) => (
          <button key={option.id} type="button" onClick={() => onChange(option.id)}>
            {`select ${labels.triggerLabel} ${option.id}`}
          </button>
        ))}
    </div>
  ),
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    disabled,
    labels,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    disabled?: boolean
    labels: { triggerLabel: string }
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`}>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${labels.triggerLabel}`}>{String(Boolean(disabled))}</span>
      <button type="button" onClick={() => onChange(TEST_BUSINESS_FUNCTION_A)}>
        {`select ${labels.triggerLabel} ${TEST_BUSINESS_FUNCTION_A}`}
      </button>
    </div>
  ),
}))

const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>('@/features/for-select/api')
  return {
    ...actual,
    fetchForSelect: (...args: unknown[]) => fetchForSelectMock(...args),
  }
})

const EMPTY_PAGE = { items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }

interface HarnessProps {
  defaultValue?: CompetenceLineRow[]
}

/** Mirrors the real wiring (`user-form-assignment-section.tsx`'s `MetaField`): rows flow through RHF like any other field. */
function Harness({ defaultValue = [] }: HarnessProps) {
  const form = useForm<{ product_lines: CompetenceLineRow[] }>({ defaultValues: { product_lines: defaultValue } })
  const productLines = useWatch({ control: form.control, name: 'product_lines' })

  return (
    <CompetenceLinesField
      value={productLines}
      onChange={(next) => form.setValue('product_lines', next, { shouldDirty: true })}
    />
  )
}

function renderHarness(props: HarnessProps = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

describe('CompetenceLinesField — "all categories" checkbox (spec 0129 AC-021)', () => {
  it('renders a disabled "All categories" checkbox before a function is chosen', () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    expect(screen.getByRole('checkbox')).toBeDisabled()
  })

  it('checking "All categories" disables and clears the category, and writes a null-category row', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_A}` }))
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))

    fireEvent.click(screen.getByRole('checkbox'))

    expect(screen.getByRole('checkbox')).toBeChecked()
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('true')
    expect(screen.getByTestId('value-Product category 1')).toBeEmptyDOMElement()
  })
})

describe('CompetenceLinesField — no `single`-mode row cap (spec 0111 D-5, AC-024)', () => {
  it('on edit: a row LOADED on a category still accepts a second one', async () => {
    renderHarness({
      defaultValue: [{ business_function_id: TEST_BUSINESS_FUNCTION_A, product_category_id: TEST_PRODUCT_CATEGORY_A }],
    })

    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()

    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    await waitFor(() => expect(screen.getAllByRole('button', { name: 'Remove product line' })).toHaveLength(2))
  })
})
