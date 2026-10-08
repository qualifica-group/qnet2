import type { ReactNode } from 'react'
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
const ROOT_C = 300
const GROUP_C1 = 301
const LEAF_C1 = 302
const GROUP_C2 = 303
const LEAF_C2 = 304
const DIRECT_LEAF_C = 305

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
      // Intermediate filter (user directive 2026-10-08): two grouping
      // children (Consulenza › ISO style) and a direct leaf, never a filter.
      node({
        id: 300,
        name: 'Root C',
        is_selectable: false,
        children: [
          node({
            id: 301,
            name: 'Group C1',
            parent_id: 300,
            is_selectable: false,
            children: [node({ id: 302, name: 'Leaf C1', parent_id: 301 })],
          }),
          node({ id: 303, name: 'Group C2', parent_id: 300, children: [node({ id: 304, name: 'Leaf C2', parent_id: 303 })] }),
          node({ id: 305, name: 'Direct leaf C', parent_id: 300 }),
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

/**
 * The category quick-create needs the auth context and has its own suites: out
 * of the way here, now that the select double renders its `action` slot (the
 * intermediate filter's remove button lives there).
 */
vi.mock('@/components/form/use-quick-create-action', () => ({
  useQuickCreateAction: () => ({ renderAction: () => null }),
}))

/** Exposes the options it was handed (id + disabled flag), so scoping/pruning is asserted on the real builder's output. */
vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: ({
    value,
    onChange,
    options,
    disabled,
    labels,
    action,
  }: {
    value: number | null
    onChange: (value: number) => void
    options: { id: number; name: string; disabled?: boolean; depth: number }[]
    disabled?: boolean
    labels: { triggerLabel?: string }
    action?: ReactNode
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`}>
      {action}
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

/** Mirrors the real wiring (`OpportunityProductLinesFormField`'s `MetaField`): rows flow through RHF like any other field. */
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

describe('ProductLinesField — intermediate filter (user directive 2026-10-08)', () => {
  const addFilter = (row: number) => screen.queryByRole('button', { name: `Add intermediate filter, row ${row}` })

  it('offers "Filter" only once a root with a grouping child is chosen', () => {
    renderHarness({
      defaultValue: [
        { root_category_id: null, product_category_id: null },
        { root_category_id: ROOT_A, product_category_id: CATEGORY_A1 },
        { root_category_id: ROOT_C, product_category_id: null },
      ],
    })

    expect(addFilter(1)).not.toBeInTheDocument()
    expect(addFilter(2)).not.toBeInTheDocument()
    expect(addFilter(3)).toBeInTheDocument()
    expect(screen.queryByTestId('select-Intermediate filter 3')).not.toBeInTheDocument()
  })

  it('lists the root\'s grouping children and narrows the category to the picked one', async () => {
    renderHarness({ defaultValue: [{ root_category_id: ROOT_C, product_category_id: null }] })

    fireEvent.click(addFilter(1)!)
    expect(screen.getByTestId('options-Intermediate filter 1')).toHaveTextContent(`${GROUP_C1},${GROUP_C2}`)
    expect(screen.getByTestId('options-Intermediate filter 1')).not.toHaveTextContent(String(DIRECT_LEAF_C))
    // Added but still empty: the category keeps listing the whole root.
    expect(screen.getByTestId('options-Product category 1')).toHaveTextContent(String(DIRECT_LEAF_C))

    fireEvent.click(screen.getByRole('button', { name: `select Intermediate filter 1 ${GROUP_C1}` }))

    await waitFor(() => expect(screen.getByTestId('options-Product category 1')).toHaveTextContent(String(LEAF_C1)))
    expect(screen.getByTestId('options-Product category 1')).not.toHaveTextContent(String(LEAF_C2))
    expect(screen.getByTestId('options-Product category 1')).not.toHaveTextContent(String(DIRECT_LEAF_C))
  })

  it('resets a category outside the picked filter, keeping the root derived on load', async () => {
    renderHarness({ defaultValue: [{ root_category_id: null, product_category_id: LEAF_C2 }] })

    fireEvent.click(addFilter(1)!)
    fireEvent.click(screen.getByRole('button', { name: `select Intermediate filter 1 ${GROUP_C1}` }))

    await waitFor(() => expect(screen.getByTestId('value-Product category 1')).toBeEmptyDOMElement())
    expect(screen.getByTestId('value-Parent category 1')).toHaveTextContent(String(ROOT_C))
  })

  it('keeps a category inside the picked filter, and removing the filter keeps it too', async () => {
    renderHarness({ defaultValue: [{ root_category_id: null, product_category_id: LEAF_C2 }] })

    fireEvent.click(addFilter(1)!)
    fireEvent.click(screen.getByRole('button', { name: `select Intermediate filter 1 ${GROUP_C2}` }))
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent(String(LEAF_C2))

    fireEvent.click(screen.getByRole('button', { name: 'Remove intermediate filter, row 1' }))

    await waitFor(() => expect(screen.queryByTestId('select-Intermediate filter 1')).not.toBeInTheDocument())
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent(String(LEAF_C2))
    expect(screen.getByTestId('options-Product category 1')).toHaveTextContent(String(DIRECT_LEAF_C))
  })

  it('drops the filter when the row\'s root changes', async () => {
    renderHarness({ defaultValue: [{ root_category_id: ROOT_C, product_category_id: null }] })

    fireEvent.click(addFilter(1)!)
    fireEvent.click(screen.getByRole('button', { name: `select Parent category 1 ${ROOT_A}` }))

    await waitFor(() => expect(screen.queryByTestId('select-Intermediate filter 1')).not.toBeInTheDocument())
  })

  it('moves a filter with its row when an earlier row is removed', async () => {
    renderHarness({
      defaultValue: [
        { root_category_id: ROOT_A, product_category_id: CATEGORY_A1 },
        { root_category_id: ROOT_C, product_category_id: null },
      ],
    })

    fireEvent.click(addFilter(2)!)
    fireEvent.click(screen.getByRole('button', { name: `select Intermediate filter 2 ${GROUP_C1}` }))
    fireEvent.click(screen.getAllByRole('button', { name: 'Remove product line' })[0])

    await waitFor(() => expect(screen.getByTestId('value-Intermediate filter 1')).toHaveTextContent(String(GROUP_C1)))
  })
})
