import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useForm, useWatch } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { ProductLine, ProductLineRow } from '@/features/product-lines/types'

/**
 * Spec 0057 (generalized from the opportunity form's original
 * `OpportunityProductLinesField`, spec 0040 amendment rev.3, AC-106): the
 * inline business-function + product-category row editor, styled/interacted
 * like `ManagerSlotsField` ("Add" appends an empty row, edited in place,
 * removed with a trash icon). Covers adding an empty row, category options
 * scoped by the row's own function, removal, and label resolution from
 * already-known rows (no redundant fetch). The former name auto-fill
 * (AC-107) was removed along with the opportunity form's `name` input (spec
 * 0057 D-5) and is not part of this shared component.
 */

const TEST_BUSINESS_FUNCTION_A = 1
const TEST_BUSINESS_FUNCTION_B = 2
const TEST_PRODUCT_CATEGORY_A = 11
const TEST_PRODUCT_CATEGORY_B = 22

const SELECT_IDS: Record<string, number[]> = {
  'Business function 1': [TEST_BUSINESS_FUNCTION_A, TEST_BUSINESS_FUNCTION_B],
  'Business function 2': [TEST_BUSINESS_FUNCTION_A, TEST_BUSINESS_FUNCTION_B],
}

/**
 * The category select now reads the structural TREE (user directive
 * 2026-08-03), so the fixture IS a tree: an unselectable root that owns the
 * business function, its two pickable children, and a second branch under the
 * other function. `vi.hoisted` because the `vi.mock` factory below is hoisted
 * above this module's consts.
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
      node({ id: 200, name: 'Marketing area', business_function_id: 2 }),
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

/** Exposes the options it was handed (id + disabled flag), so the scoping under test is asserted on the real builder's output. */
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
      {(SELECT_IDS[labels.triggerLabel] ?? [1]).map((id) => (
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
  knownLines?: ProductLine[]
  disabled?: boolean
}

/** Mirrors the real wiring (`opportunity-product-lines-section.tsx`'s `MetaField`): rows flow through RHF like any other field. */
function Harness({ defaultValue = [], knownLines, disabled }: HarnessProps) {
  const form = useForm<{ product_lines: ProductLineRow[] }>({ defaultValues: { product_lines: defaultValue } })
  const productLines = useWatch({ control: form.control, name: 'product_lines' })

  return (
    <ProductLinesField
      value={productLines}
      onChange={(next) => form.setValue('product_lines', next, { shouldDirty: true })}
      knownLines={knownLines}
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

beforeEach(() => {
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation(async (resource: string, params: { ids?: number[] }) => {
    if (resource === 'business-functions' && params?.ids?.includes(TEST_BUSINESS_FUNCTION_A)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_BUSINESS_FUNCTION_A, label: 'Sales' }] }
    }
    if (resource === 'business-functions' && params?.ids?.includes(TEST_BUSINESS_FUNCTION_B)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_BUSINESS_FUNCTION_B, label: 'Marketing' }] }
    }
    if (resource === 'product-categories' && params?.ids?.includes(TEST_PRODUCT_CATEGORY_A)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_PRODUCT_CATEGORY_A, label: 'Consulting' }] }
    }
    if (resource === 'product-categories' && params?.ids?.includes(TEST_PRODUCT_CATEGORY_B)) {
      return { ...EMPTY_PAGE, items: [{ id: TEST_PRODUCT_CATEGORY_B, label: 'Training' }] }
    }
    return EMPTY_PAGE
  })
})

describe('ProductLinesField (spec 0057, AC-106)', () => {
  it('renders no row and an enabled "Add" button when empty', () => {
    renderHarness()
    expect(screen.queryByTestId('select-Business function 1')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeEnabled()
  })

  it('adds an empty row on "Add", with the category disabled until a function is chosen', () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    expect(screen.getByTestId('select-Business function 1')).toBeInTheDocument()
    expect(screen.getByTestId('value-Business function 1')).toHaveTextContent('')
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('true')
  })

  it('scopes the row category by the row own business function (AC-104)', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))

    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_A}` }).click()

    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    // The other function's branch is absent; the root that owns THIS function
    // is listed but disabled (it is not `is_selectable`), which is the whole
    // point of reading the tree: the children show WHERE they live.
    expect(screen.getByTestId('options-Product category 1')).toHaveTextContent(
      `100:disabled,${TEST_PRODUCT_CATEGORY_A},${TEST_PRODUCT_CATEGORY_B}`,
    )
  })

  it('swaps the offered branch when the row function changes (user directive 2026-08-03)', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_A}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))

    fireEvent.click(screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_B}` }))

    await waitFor(() => expect(screen.getByTestId('options-Product category 1')).toHaveTextContent('200'))
    expect(screen.getByTestId('options-Product category 1')).not.toHaveTextContent('100')
  })

  it('resets the category when the row function changes', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_A}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    screen.getByRole('button', { name: `select Product category 1 ${TEST_PRODUCT_CATEGORY_A}` }).click()
    await waitFor(() =>
      expect(screen.getByTestId('value-Product category 1')).toHaveTextContent(String(TEST_PRODUCT_CATEGORY_A)),
    )

    fireEvent.click(screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_B}` }))

    await waitFor(() => expect(screen.getByTestId('value-Product category 1')).toBeEmptyDOMElement())
    // The new function (B) is still set, so the category select stays enabled — just cleared.
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false')
  })

  it('removes a row', () => {
    renderHarness({
      knownLines: [
        {
          id: 1,
          business_function: { id: TEST_BUSINESS_FUNCTION_A, name: 'Sales' },
          product_category: { id: TEST_PRODUCT_CATEGORY_A, name: 'Consulting' },
        },
      ],
      defaultValue: [{ business_function_id: TEST_BUSINESS_FUNCTION_A, product_category_id: TEST_PRODUCT_CATEGORY_A }],
    })

    expect(screen.getByTestId('select-Business function 1')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Remove product line' }))

    expect(screen.queryByTestId('select-Business function 1')).not.toBeInTheDocument()
  })

  it('resolves row labels from knownLines without a fetch (pre-fill from lead/edit, AC-103)', () => {
    renderHarness({
      knownLines: [
        { id: 1, business_function: { id: 40, name: 'Sales' }, product_category: { id: 50, name: 'Consulting' } },
      ],
      defaultValue: [{ business_function_id: 40, product_category_id: 50 }],
    })

    expect(fetchForSelectMock).not.toHaveBeenCalled()
    expect(screen.getByTestId('value-Business function 1')).toHaveTextContent('40')
  })

  it('adds a second row independently of the first', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    screen.getByRole('button', { name: `select Business function 1 ${TEST_BUSINESS_FUNCTION_A}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('false'))
    screen.getByRole('button', { name: `select Product category 1 ${TEST_PRODUCT_CATEGORY_A}` }).click()

    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    expect(screen.getByTestId('disabled-Product category 2')).toHaveTextContent('true')

    screen.getByRole('button', { name: `select Business function 2 ${TEST_BUSINESS_FUNCTION_B}` }).click()
    await waitFor(() => expect(screen.getByTestId('disabled-Product category 2')).toHaveTextContent('false'))
    expect(screen.getByTestId('value-Business function 1')).toHaveTextContent(String(TEST_BUSINESS_FUNCTION_A))
  })

  it('disables every row control and the "Add" button when `disabled` is set', () => {
    renderHarness({
      knownLines: [
        { id: 1, business_function: { id: 40, name: 'Sales' }, product_category: { id: 50, name: 'Consulting' } },
      ],
      defaultValue: [{ business_function_id: 40, product_category_id: 50 }],
      disabled: true,
    })

    expect(screen.getByTestId('disabled-Business function 1')).toHaveTextContent('true')
    expect(screen.getByTestId('disabled-Product category 1')).toHaveTextContent('true')
    expect(screen.getByRole('button', { name: 'Add product line' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Remove product line' })).toBeDisabled()
  })
})
