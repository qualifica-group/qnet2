import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useForm, useWatch } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * Spec 0132: the CARD row editor picks a ROOT category first, then one of its
 * `is_selectable` descendants — no business-function select anywhere in this
 * field (D-3: the server derives and persists the function). Covers
 * AC-013..AC-017. The `single`-mode row cap (AC-041/042) is unaffected by the
 * contract change and gets its own suite, `product-lines-field-management-mode.test.tsx`.
 */

const ROOT_A = 100
const ROOT_B = 200
const CATEGORY_A1 = 11
const CATEGORY_A2 = 22
const CONTAINER_B = 201
const LEAF_B = 202
const DEAD_BRANCH_B = 203

/**
 * `vi.hoisted` because the `vi.mock` factory below is hoisted above this
 * module's consts. Root B exercises AC-015: a disabled container context
 * (`Container B`) around the only pickable leaf, and a dead branch
 * (`Dead branch`, no selectable descendant at all) pruned outright.
 */
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
        name: 'Root A',
        is_selectable: false,
        children: [
          node({ id: 11, name: 'Category A1', parent_id: 100 }),
          node({ id: 22, name: 'Category A2', parent_id: 100 }),
        ],
      }),
      node({
        id: 200,
        name: 'Root B',
        is_selectable: false,
        children: [
          node({
            id: 201,
            name: 'Container B',
            parent_id: 200,
            is_selectable: false,
            children: [node({ id: 202, name: 'Leaf B', parent_id: 201 })],
          }),
          node({ id: 203, name: 'Dead branch', parent_id: 200, is_selectable: false }),
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

/** Exposes the options it was handed (id + disabled flag), so scoping/pruning is asserted on the real builder's output. */
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
      <span data-testid={`options-${labels.triggerLabel}`}>
        {options.map((option) => `${option.id}${option.disabled ? ':disabled' : ''}`).join(',')}
      </span>
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

interface HarnessProps {
  defaultValue?: ProductLineRow[]
  disabled?: boolean
}

/** Mirrors the real wiring (`opportunity-product-lines-section.tsx`'s `MetaField`): rows flow through RHF like any other field. */
function Harness({ defaultValue = [], disabled }: HarnessProps) {
  const form = useForm<{ product_lines: ProductLineRow[] }>({ defaultValues: { product_lines: defaultValue } })
  const productLines = useWatch({ control: form.control, name: 'product_lines' })

  return (
    <ProductLinesField
      value={productLines}
      onChange={(next) => form.setValue('product_lines', next, { shouldDirty: true })}
      disabled={disabled}
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

describe('ProductLinesField (spec 0132)', () => {
  it('AC-013: renders "Parent category" and "Product category", no business-function select at all', () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    expect(screen.getByTestId('select-Parent category 1')).toBeInTheDocument()
    expect(screen.getByTestId('select-Product category 1')).toBeInTheDocument()
    expect(screen.queryByTestId('select-Business function 1')).not.toBeInTheDocument()
  })

  it('AC-014: the category select is disabled until a parent category is chosen', () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('true')
  })

  it('AC-015: lists the selectable descendants of the chosen root; containers disabled, dead branches pruned', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(screen.getByRole('button', { name: `select Parent category 1 ${ROOT_B}` }))

    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    // Root B and its Container are context — listed disabled — around the
    // one pickable leaf; the dead branch offers nothing and is gone entirely.
    expect(screen.getByTestId('options-Product category 1')).toHaveTextContent(
      `${ROOT_B}:disabled,${CONTAINER_B}:disabled,${LEAF_B}`,
    )
    expect(screen.getByTestId('options-Product category 1')).not.toHaveTextContent(String(DEAD_BRANCH_B))
  })

  it('AC-016: changing the parent category resets only that row\'s category, other rows untouched', async () => {
    renderHarness({
      defaultValue: [
        { root_category_id: ROOT_A, product_category_id: CATEGORY_A1 },
        { root_category_id: ROOT_B, product_category_id: LEAF_B },
      ],
    })

    fireEvent.click(screen.getByRole('button', { name: `select Parent category 1 ${ROOT_B}` }))

    await waitFor(() => expect(screen.getByTestId('value-Product category 1')).toBeEmptyDOMElement())
    expect(screen.getByTestId('value-Product category 2')).toHaveTextContent(String(LEAF_B))
  })

  it('AC-017: preselects the parent category by walking the tree from the persisted category, no extra fetch', () => {
    renderHarness({ defaultValue: [{ root_category_id: null, product_category_id: CATEGORY_A2 }] })

    expect(screen.getByTestId('value-Parent category 1')).toHaveTextContent(String(ROOT_A))
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false')
  })

  it('removes a row', () => {
    renderHarness({ defaultValue: [{ root_category_id: ROOT_A, product_category_id: CATEGORY_A1 }] })

    expect(screen.getByTestId('select-Parent category 1')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Remove product line' }))

    expect(screen.queryByTestId('select-Parent category 1')).not.toBeInTheDocument()
  })

  it('adds a second row independently of the first', async () => {
    renderHarness({ defaultValue: [{ root_category_id: ROOT_A, product_category_id: CATEGORY_A1 }] })

    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    expect(screen.getByTestId('disabled-Product category 2')).toHaveTextContent('true')

    fireEvent.click(screen.getByRole('button', { name: `select Parent category 2 ${ROOT_B}` }))
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 2')).toHaveTextContent('false'))
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent(String(CATEGORY_A1))
  })

  it('disables every row control and the "Add" button when `disabled` is set', () => {
    renderHarness({
      defaultValue: [{ root_category_id: ROOT_A, product_category_id: CATEGORY_A1 }],
      disabled: true,
    })

    expect(screen.getByTestId('disabled-Parent category 1')).toHaveTextContent('true')
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('true')
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Remove product line' })).toBeDisabled()
  })

  it('non-regression: renders no "All categories" checkbox — that is a CompetenceLinesField affordance', () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
  })
})
