import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useForm, useWatch } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * Spec 0077, MT-7: enforcement of the root category's resolved
 * `management_mode` in the shared row editor. AC-041 (single: "Add" stops
 * being available) and AC-042 rev.2 (multiple: every row picks its OWN
 * business function, on any root — user directive 2026-08-31 revoked
 * INV-1/INV-2). The mode is resolved from the category TREE the row's picker
 * renders (user directive 2026-08-03) — never a separate request — for the
 * rows LOADED on an existing record as well as for those picked in this
 * session (user directive 2026-08-05): the opportunity edit form and the
 * request work panel enforce it exactly as the create forms do.
 */

const BUSINESS_FUNCTION_A = 1
const BUSINESS_FUNCTION_B = 2
const SINGLE_ROOT_ID = 900
const SINGLE_CATEGORY_ID = 901
const MULTI_ROOT_ID = 800
const MULTI_CATEGORY_A = 801
const MULTI_CATEGORY_B = 802

/**
 * The mode now travels with the category TREE the row's picker reads (user
 * directive 2026-08-03), not with a for-select item's `meta`: two roots under
 * the SAME business function, one `single` and one `multiple`, so a pick on
 * either resolves the card's policy. `management_mode` is already mirrored on
 * every descendant server-side, so the children carry their root's value.
 * `vi.hoisted`: the `vi.mock` factory below is hoisted above these consts.
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
        business_function_id: 1,
        is_selectable: false,
        management_mode: 'single',
        single_quote_per_opportunity: false,
        generates_contract: true,
        children: [node({ id: 901, name: 'Single category', parent_id: 900, management_mode: 'single' })],
      }),
      node({
        id: 800,
        name: 'Multi root',
        business_function_id: 1,
        is_selectable: false,
        children: [
          node({ id: 801, name: 'Multi category A', parent_id: 800 }),
          node({ id: 802, name: 'Multi category B', parent_id: 800 }),
        ],
      }),
    ],
  }
})

const SELECT_IDS: Record<string, number[]> = {
  'Business function 1': [BUSINESS_FUNCTION_A, BUSINESS_FUNCTION_B],
  'Business function 2': [BUSINESS_FUNCTION_A, BUSINESS_FUNCTION_B],
}

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({
    data: CATEGORY_TREE,
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
}))

/** Exposes the options it was handed, so the root scoping (INV-1) is asserted on the real builder's output. */
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
      {(SELECT_IDS[labels.triggerLabel] ?? []).map((id) => (
        <button key={id} type="button" onClick={() => onChange(id)}>
          {`select ${labels.triggerLabel} ${id}`}
        </button>
      ))}
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

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockResolvedValue(EMPTY_PAGE)
})

describe('ProductLinesField management-mode enforcement (spec 0077 MT-7)', () => {
  it('AC-041: hides "Add" once the picked category resolves to single mode', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(screen.getByRole('button', { name: `select Business function 1 ${BUSINESS_FUNCTION_A}` }))
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))

    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()

    fireEvent.click(screen.getByRole('button', { name: `select Product category 1 ${SINGLE_CATEGORY_ID}` }))

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeDisabled())
  })

  it('AC-042 rev.2: the second row opens EMPTY and its function stays editable, once mode resolves to multiple', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(screen.getByRole('button', { name: `select Business function 1 ${BUSINESS_FUNCTION_A}` }))
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    fireEvent.click(screen.getByRole('button', { name: `select Product category 1 ${MULTI_CATEGORY_A}` }))
    await waitFor(() =>
      expect(screen.getByTestId('value-Product category 1')).toHaveTextContent(String(MULTI_CATEGORY_A)),
    )

    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    // No prefill, no lock: the operator picks this row's own function.
    expect(screen.getByTestId('value-Business function 2')).toHaveTextContent('')
    expect(screen.getByTestId('disabled-Business function 2')).toHaveTextContent('false')
    // Its category waits for that pick, as row 1's did.
    expect(screen.getByTestId('disabled-Product category 2')).toHaveTextContent('true')

    fireEvent.click(screen.getByRole('button', { name: `select Business function 2 ${BUSINESS_FUNCTION_B}` }))

    // A DIFFERENT function on the second row: nothing bounces it back to the
    // first row's, and the whole tree stays available to it.
    await waitFor(() => expect(screen.getByTestId('value-Business function 2')).toHaveTextContent(String(BUSINESS_FUNCTION_B)))
    expect(screen.getByTestId('value-Business function 1')).toHaveTextContent(String(BUSINESS_FUNCTION_A))
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent(String(MULTI_CATEGORY_A))
    // "Add" stays available: multiple mode allows further rows.
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()
  })

  it('AC-042 rev.2: changing the FIRST row\'s function leaves the other rows untouched', async () => {
    renderHarness({
      defaultValue: [
        { business_function_id: BUSINESS_FUNCTION_A, product_category_id: MULTI_CATEGORY_A },
        { business_function_id: BUSINESS_FUNCTION_B, product_category_id: MULTI_CATEGORY_B },
      ],
    })

    fireEvent.click(screen.getByRole('button', { name: `select Business function 1 ${BUSINESS_FUNCTION_B}` }))

    await waitFor(() =>
      expect(screen.getByTestId('value-Business function 1')).toHaveTextContent(String(BUSINESS_FUNCTION_B)),
    )
    // Only the edited row loses its category (it was scoped by the old function).
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent('')
    expect(screen.getByTestId('value-Business function 2')).toHaveTextContent(String(BUSINESS_FUNCTION_B))
    expect(screen.getByTestId('value-Product category 2')).toHaveTextContent(String(MULTI_CATEGORY_B))
  })

  it('AC-041 on edit: a row LOADED on a single-mode category disables "Add" without being re-picked', async () => {
    renderHarness({
      defaultValue: [{ business_function_id: BUSINESS_FUNCTION_A, product_category_id: SINGLE_CATEGORY_ID }],
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeDisabled())
  })

  it('AC-042 rev.2 on edit: rows LOADED on a multiple-mode category keep their function editable, whole tree in scope', async () => {
    renderHarness({
      defaultValue: [
        { business_function_id: BUSINESS_FUNCTION_A, product_category_id: MULTI_CATEGORY_A },
        { business_function_id: BUSINESS_FUNCTION_A, product_category_id: MULTI_CATEGORY_B },
      ],
    })

    await waitFor(() => expect(screen.getByTestId('disabled-Business function 2')).toHaveTextContent('false'))
    // No root confinement left: the single-mode branch is listed too, for
    // whatever the row's own function makes pickable there.
    expect(screen.getByTestId('options-Product category 2')).toHaveTextContent(
      `${SINGLE_ROOT_ID}:disabled,${SINGLE_CATEGORY_ID},${MULTI_ROOT_ID}:disabled,${MULTI_CATEGORY_A},${MULTI_CATEGORY_B}`,
    )
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()
  })

  it('AC-041 rev.2: one row on a single-mode category caps the whole card, whatever the others resolve to (D-10)', async () => {
    renderHarness({
      defaultValue: [
        { business_function_id: BUSINESS_FUNCTION_A, product_category_id: MULTI_CATEGORY_A },
        { business_function_id: BUSINESS_FUNCTION_A, product_category_id: SINGLE_CATEGORY_ID },
      ],
    })

    await waitFor(() => expect(screen.getByRole('button', { name: 'Add product line' })).toBeDisabled())
  })

  it('leaves rows unconstrained while no category has resolved a mode (indeterminate, point 4)', () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(screen.getByRole('button', { name: `select Business function 1 ${BUSINESS_FUNCTION_A}` }))
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()
    expect(screen.getByTestId('disabled-Business function 2')).toHaveTextContent('false')
  })
})
