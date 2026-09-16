import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useForm, useWatch } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * Spec 0077, MT-7: enforcement of the root category's resolved
 * `management_mode` in the CARD row editor. AC-041 (single: "Add" stops
 * being available) and AC-042 rev.2 (multiple: every row picks its OWN
 * parent category, on any root — user directive 2026-08-31 revoked
 * INV-1/INV-2). The mode is resolved from the category TREE the row's picker
 * renders (user directive 2026-08-03) — never a separate request — for the
 * rows LOADED on an existing record as well as for those picked in this
 * session (user directive 2026-08-05): the opportunity edit form and the
 * request work panel enforce it exactly as the create forms do. Spec 0132
 * moved the row's first step from business function to root category; the
 * cap itself, resolved purely off `product_category_id`, is unaffected.
 */

const SINGLE_ROOT_ID = 900
const SINGLE_CATEGORY_ID = 901
const MULTI_ROOT_ID = 800
const MULTI_CATEGORY_A = 801
const MULTI_CATEGORY_B = 802

/**
 * Two roots: one `single` mode, one `multiple`. `management_mode` is already
 * mirrored on every descendant server-side, so the children carry their
 * root's value. `vi.hoisted`: the `vi.mock` factory below is hoisted above
 * these consts.
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
        id: 900,
        name: 'Single root',
        is_selectable: false,
        management_mode: 'single',
        children: [node({ id: 901, name: 'Single category', parent_id: 900, management_mode: 'single' })],
      }),
      node({
        id: 800,
        name: 'Multi root',
        is_selectable: false,
        children: [
          node({ id: 801, name: 'Multi category A', parent_id: 800 }),
          node({ id: 802, name: 'Multi category B', parent_id: 800 }),
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

/** Exposes the options it was handed, so the tree scoping is asserted on the real builder's output. */
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
}

/** Mirrors the real wiring (`opportunity-product-lines-section.tsx`'s `MetaField`): rows flow through RHF like any other field. */
function Harness({ defaultValue = [] }: HarnessProps) {
  const form = useForm<{ product_lines: ProductLineRow[] }>({ defaultValues: { product_lines: defaultValue } })
  const productLines = useWatch({ control: form.control, name: 'product_lines' })

  return (
    <ProductLinesField
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

describe('ProductLinesField management-mode enforcement (spec 0077 MT-7, spec 0132 contract)', () => {
  it('AC-041: hides "Add" once the picked category resolves to single mode', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(screen.getByRole('button', { name: `select Parent category 1 ${SINGLE_ROOT_ID}` }))
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))

    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()

    fireEvent.click(screen.getByRole('button', { name: `select Product category 1 ${SINGLE_CATEGORY_ID}` }))

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeDisabled())
  })

  it('AC-042 rev.2: the second row opens EMPTY and its root stays editable, once mode resolves to multiple', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(screen.getByRole('button', { name: `select Parent category 1 ${MULTI_ROOT_ID}` }))
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    fireEvent.click(screen.getByRole('button', { name: `select Product category 1 ${MULTI_CATEGORY_A}` }))
    await waitFor(() =>
      expect(screen.getByTestId('value-Product category 1')).toHaveTextContent(String(MULTI_CATEGORY_A)),
    )

    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    // No prefill, no lock: the operator picks this row's own root.
    expect(screen.getByTestId('value-Parent category 2')).toHaveTextContent('')
    expect(screen.getByTestId('disabled-Parent category 2')).toHaveTextContent('false')
    // Its category waits for that pick, as row 1's did.
    expect(screen.getByTestId('disabled-Product category 2')).toHaveTextContent('true')

    fireEvent.click(screen.getByRole('button', { name: `select Parent category 2 ${SINGLE_ROOT_ID}` }))

    // A DIFFERENT root on the second row: nothing bounces it back to the
    // first row's, and row 1 stays untouched.
    await waitFor(() =>
      expect(screen.getByTestId('value-Parent category 2')).toHaveTextContent(String(SINGLE_ROOT_ID)),
    )
    expect(screen.getByTestId('value-Parent category 1')).toHaveTextContent(String(MULTI_ROOT_ID))
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent(String(MULTI_CATEGORY_A))
    // "Add" stays available: multiple mode allows further rows (row 2 has not
    // yet picked its single-mode category).
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()
  })

  it("AC-042 rev.2: changing the FIRST row's root leaves the other rows untouched", async () => {
    renderHarness({
      defaultValue: [
        { root_category_id: MULTI_ROOT_ID, product_category_id: MULTI_CATEGORY_A },
        { root_category_id: MULTI_ROOT_ID, product_category_id: MULTI_CATEGORY_B },
      ],
    })

    fireEvent.click(screen.getByRole('button', { name: `select Parent category 1 ${SINGLE_ROOT_ID}` }))

    await waitFor(() =>
      expect(screen.getByTestId('value-Parent category 1')).toHaveTextContent(String(SINGLE_ROOT_ID)),
    )
    // Only the edited row loses its category (it was scoped by the old root).
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent('')
    expect(screen.getByTestId('value-Parent category 2')).toHaveTextContent(String(MULTI_ROOT_ID))
    expect(screen.getByTestId('value-Product category 2')).toHaveTextContent(String(MULTI_CATEGORY_B))
  })

  it('AC-041 on edit: a row LOADED on a single-mode category disables "Add" without being re-picked', async () => {
    renderHarness({
      defaultValue: [{ root_category_id: null, product_category_id: SINGLE_CATEGORY_ID }],
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeDisabled())
  })

  it('AC-042 rev.2 on edit: rows LOADED on a multiple-mode category keep their root editable, scoped to their OWN root', async () => {
    renderHarness({
      defaultValue: [
        { root_category_id: null, product_category_id: MULTI_CATEGORY_A },
        { root_category_id: null, product_category_id: MULTI_CATEGORY_B },
      ],
    })

    await waitFor(() => expect(screen.getByTestId('disabled-Parent category 2')).toHaveTextContent('false'))
    // Spec 0132: a card row's second select is scoped to its OWN root only —
    // the other root's branch is not even listed as disabled context, unlike
    // the retired business-function scoping (where roots sharing a function
    // stayed mutually visible).
    expect(screen.getByTestId('options-Product category 2')).toHaveTextContent(
      `${MULTI_ROOT_ID}:disabled,${MULTI_CATEGORY_A},${MULTI_CATEGORY_B}`,
    )
    expect(screen.getByTestId('options-Product category 2')).not.toHaveTextContent(String(SINGLE_ROOT_ID))
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()
  })

  it('AC-041 rev.2: one row on a single-mode category caps the whole card, whatever the others resolve to (D-10)', async () => {
    renderHarness({
      defaultValue: [
        { root_category_id: MULTI_ROOT_ID, product_category_id: MULTI_CATEGORY_A },
        { root_category_id: null, product_category_id: SINGLE_CATEGORY_ID },
      ],
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeDisabled())
  })

  it('leaves rows unconstrained while no category has resolved a mode (indeterminate, point 4)', () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(screen.getByRole('button', { name: `select Parent category 1 ${MULTI_ROOT_ID}` }))
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()
    expect(screen.getByTestId('disabled-Parent category 2')).toHaveTextContent('false')
  })
})
