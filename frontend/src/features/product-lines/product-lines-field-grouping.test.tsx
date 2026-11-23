import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useForm, useWatch } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * spec 0416 (user request 2026-11-18): ONE editor row per parent category,
 * holding every category picked under it (multi-select on a `multiple` root,
 * the single select on a `single` one), flattened back to one wire line per
 * category.
 */

const ROOT_A = 100
const CATEGORY_A1 = 11
const CATEGORY_A2 = 22
const ROOT_B = 200
const LEAF_B = 202
const LEAF_D1 = 402

const { CATEGORY_TREE } = vi.hoisted(() => {
  const node = (overrides: Record<string, unknown>) => ({
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: 1,
    requires_quote: false,
    is_selectable: true,
    is_active: true,
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
      node({ id: 200, name: 'Root B', is_selectable: false, children: [node({ id: 202, name: 'Leaf B', parent_id: 200 })] }),
      node({
        id: 400,
        name: 'Root D',
        is_selectable: false,
        management_mode: 'single',
        children: [node({ id: 402, name: 'Leaf D1', parent_id: 400, management_mode: 'single' })],
      }),
    ],
  }
})

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: CATEGORY_TREE, isPending: false, isError: false, refetch: vi.fn() }),
}))

/** The category quick-create needs the auth context and has its own suites. */
vi.mock('@/components/form/use-quick-create-action', () => ({
  useQuickCreateAction: () => ({ renderAction: () => null }),
}))

vi.mock('@/components/ui/searchable-multi-select', async () =>
  await import('@/features/product-lines/searchable-multi-select-probe'))

/** The root select double: probes plus one button per enabled option. */
vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: ({
    value,
    onChange,
    options,
    labels,
  }: {
    value: number | null
    onChange: (value: number) => void
    options: { id: number; disabled?: boolean }[]
    labels: { triggerLabel?: string }
  }) => (
    <div data-testid={`select-${labels.triggerLabel}`} data-kind="single">
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
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

/** RHF-wired like the real forms, with the wire value exposed for the assertions. */
function Harness({ defaultValue }: { defaultValue: ProductLineRow[] }) {
  const form = useForm<{ product_lines: ProductLineRow[] }>({ defaultValues: { product_lines: defaultValue } })
  const productLines = useWatch({ control: form.control, name: 'product_lines' })

  return (
    <>
      <ProductLinesField value={productLines} onChange={(next) => form.setValue('product_lines', next)} />
      <output data-testid="wire">{JSON.stringify(productLines)}</output>
    </>
  )
}

function renderHarness({ defaultValue = [] }: { defaultValue?: ProductLineRow[] } = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness defaultValue={defaultValue} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ProductLinesField — one row per parent category (spec 0416)', () => {
  const wire = () => JSON.parse(screen.getByTestId('wire').textContent ?? '[]') as ProductLineRow[]

  it('groups the lines of the same parent category into ONE row', () => {
    renderHarness({
      defaultValue: [
        { root_category_id: null, product_category_id: CATEGORY_A1 },
        { root_category_id: null, product_category_id: LEAF_B },
        { root_category_id: null, product_category_id: CATEGORY_A2 },
      ],
    })

    expect(screen.getByTestId('value-Parent category 1')).toHaveTextContent(String(ROOT_A))
    expect(screen.getByTestId('value-Product category 1')).toHaveTextContent(`${CATEGORY_A1},${CATEGORY_A2}`)
    expect(screen.getByTestId('value-Parent category 2')).toHaveTextContent(String(ROOT_B))
    expect(screen.queryByTestId('select-Parent category 3')).not.toBeInTheDocument()
  })

  it('picks several categories in one row and sends one line per category', async () => {
    renderHarness()
    fireEvent.click(screen.getByRole('button', { name: 'Add product line' }))
    fireEvent.click(screen.getByRole('button', { name: `select Parent category 1 ${ROOT_A}` }))
    fireEvent.click(await screen.findByRole('button', { name: `select Product category 1 ${CATEGORY_A1}` }))
    fireEvent.click(screen.getByRole('button', { name: `select Product category 1 ${CATEGORY_A2}` }))

    await waitFor(() =>
      expect(wire()).toEqual([
        { root_category_id: ROOT_A, product_category_id: CATEGORY_A1 },
        { root_category_id: ROOT_A, product_category_id: CATEGORY_A2 },
      ]),
    )
    expect(screen.queryByTestId('select-Parent category 2')).not.toBeInTheDocument()
  })

  it('removing a row removes every line of its parent category', async () => {
    renderHarness({
      defaultValue: [
        { root_category_id: null, product_category_id: CATEGORY_A1 },
        { root_category_id: null, product_category_id: CATEGORY_A2 },
        { root_category_id: null, product_category_id: LEAF_B },
      ],
    })

    fireEvent.click(screen.getAllByRole('button', { name: 'Remove product line' })[0])

    await waitFor(() => expect(wire()).toEqual([{ root_category_id: ROOT_B, product_category_id: LEAF_B }]))
  })

  it('lists a parent category held by another row as disabled', () => {
    renderHarness({
      defaultValue: [
        { root_category_id: null, product_category_id: CATEGORY_A1 },
        { root_category_id: null, product_category_id: null },
      ],
    })

    expect(screen.getByTestId('options-Parent category 2')).toHaveTextContent(`${ROOT_A}:disabled`)
    expect(screen.getByTestId('options-Parent category 1')).not.toHaveTextContent(`${ROOT_A}:disabled`)
  })

  it('keeps the one-category select on a single parent category', () => {
    renderHarness({ defaultValue: [{ root_category_id: null, product_category_id: LEAF_D1 }] })

    expect(screen.getByTestId('select-Product category 1')).toHaveAttribute('data-kind', 'single')
  })

  it('a row with nothing picked yet stays one incomplete line', async () => {
    renderHarness({ defaultValue: [{ root_category_id: null, product_category_id: CATEGORY_A1 }] })

    fireEvent.click(screen.getByRole('button', { name: `select Product category 1 ${CATEGORY_A1}` }))

    await waitFor(() => expect(wire()).toEqual([{ root_category_id: ROOT_A, product_category_id: null }]))
  })
})
