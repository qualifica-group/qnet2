import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import i18n from '@/i18n'
import { ProductLinesCellEditor, type ProductLineCellValue } from '@/features/product-lines/product-lines-cell-editor'
import type { TableRow } from '@/features/table/types'

/**
 * Spec 0132 AC-020: the editor's "add a pair" flow reads two steps off the
 * cached category TREE — root categories, then a chosen root's selectable
 * descendants — with no `for-select` call of its own. Category 71 hangs from
 * a `single` root (spec 0077 INV-3), category 7 and 9 from a `multiple` one,
 * with 8 a non-selectable container between the root and 9 (disabled
 * context, spec 0132 D-1/D-2).
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
        id: 70,
        name: 'Single root',
        is_selectable: false,
        management_mode: 'single',
        children: [node({ id: 71, name: 'Luce singola', parent_id: 70, management_mode: 'single' })],
      }),
      node({
        id: 6,
        name: 'Multi root',
        is_selectable: false,
        children: [
          node({ id: 7, name: 'Luce', parent_id: 6 }),
          node({
            id: 8,
            name: 'Container',
            parent_id: 6,
            is_selectable: false,
            children: [node({ id: 9, name: 'Gas', parent_id: 8 })],
          }),
        ],
      }),
    ],
  }
})

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: CATEGORY_TREE, isPending: false, isError: false, refetch: vi.fn() }),
}))

const PAIR: ProductLineCellValue = {
  root_category_id: 6,
  root_category_name: 'Multi root',
  product_category_id: 7,
  product_category_name: 'Luce',
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderEditor(
  value: ProductLineCellValue[] | null,
  onValueChange: (next: ProductLineCellValue[] | null) => void,
  data?: TableRow,
) {
  const props = {
    value,
    onValueChange,
    data,
    stopEditing: vi.fn(),
  } as unknown as CustomCellEditorProps<TableRow, ProductLineCellValue[] | null>

  return render(<ProductLinesCellEditor {...props} />, { wrapper: wrapper() })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('ProductLinesCellEditor (spec 0132 AC-020)', () => {
  it('AC-020: step 1 lists the root categories, step 2 the selected root\'s selectable descendants, containers disabled', async () => {
    const onValueChange = vi.fn()
    renderEditor([], onValueChange)

    expect(await screen.findByRole('option', { name: 'Single root' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Multi root' })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('option', { name: 'Multi root' }))

    expect(await screen.findByRole('option', { name: 'Luce' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Gas' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Container' })).toBeDisabled()

    fireEvent.click(screen.getByRole('option', { name: 'Gas' }))

    expect(onValueChange).toHaveBeenCalledWith([
      { root_category_id: 6, root_category_name: 'Multi root', product_category_id: 9, product_category_name: 'Gas' },
    ])
  })

  it('AC-020: the committed pair carries only product_category_id on the wire-facing fields (no business_function_id)', async () => {
    const onValueChange = vi.fn()
    renderEditor([], onValueChange)

    fireEvent.click(await screen.findByRole('option', { name: 'Multi root' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Luce' }))

    const [[committed]] = onValueChange.mock.calls
    expect(committed[0]).not.toHaveProperty('business_function_id')
    expect(committed[0]).not.toHaveProperty('business_function_name')
  })

  it('AC-020: "back" returns to the root-category step', async () => {
    renderEditor([], vi.fn())

    fireEvent.click(await screen.findByRole('option', { name: 'Multi root' }))
    await screen.findByRole('option', { name: 'Luce' })

    fireEvent.click(screen.getByRole('button', { name: 'Back to the parent categories' }))

    expect(await screen.findByRole('option', { name: 'Single root' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'Multi root' })).toBeInTheDocument()
  })

  it('spec 0132: duplicate by category alone — re-picking an already-selected category is refused', async () => {
    const onValueChange = vi.fn()
    renderEditor([PAIR], onValueChange)

    fireEvent.click(await screen.findByRole('option', { name: 'Multi root' }))

    const luce = await screen.findByRole('option', { name: 'Luce' })
    expect(luce).toBeDisabled()
    expect(luce).toHaveAttribute('aria-selected', 'true')

    fireEvent.click(luce)
    expect(onValueChange).not.toHaveBeenCalled()
  })

  it('lists the pairs already on the record and removes one', () => {
    const onValueChange = vi.fn()
    renderEditor([PAIR], onValueChange)

    expect(screen.getByText('Luce')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Remove Luce' }))

    expect(onValueChange).toHaveBeenCalledWith([])
  })

  it('warns when a product of interest would be left uncovered', () => {
    const row = {
      id: 1,
      products_of_interest: [{ id: 4, name: 'Fibra 1000', category_id: 7 }],
    } as unknown as TableRow

    renderEditor([], vi.fn(), row)

    expect(screen.getByRole('alert')).toHaveTextContent('Fibra 1000')
  })

  it('no warning while every product stays covered', () => {
    const row = {
      id: 1,
      products_of_interest: [{ id: 4, name: 'Fibra 1000', category_id: 7 }],
    } as unknown as TableRow

    renderEditor([PAIR], vi.fn(), row)

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('warns which offer rows a classification change would delete (bug 2026-10-05)', () => {
    const row = {
      id: 1,
      offer_lines: [
        { id: 4, name: 'Fibra 1000', category_id: 7 },
        { id: 5, name: 'Corso Excel', category_id: 99 },
      ],
    } as unknown as TableRow

    renderEditor([PAIR], vi.fn(), row)

    const alert = screen.getByRole('alert')
    expect(alert).toHaveTextContent('Corso Excel')
    expect(alert).not.toHaveTextContent('Fibra 1000')
  })

  it('INV-3 / spec 0077 AC-046: on a single-mode card a new pick replaces the current pair instead of adding one', async () => {
    const onValueChange = vi.fn()
    renderEditor(
      [{ root_category_id: 70, root_category_name: 'Single root', product_category_id: 71, product_category_name: 'Luce singola' }],
      onValueChange,
    )

    expect(
      screen.getByText('This product category is managed as a single row: the category you pick replaces the current one.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Search parent category…' })).toBeEnabled()

    fireEvent.click(await screen.findByRole('option', { name: 'Multi root' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Gas' }))

    expect(onValueChange).toHaveBeenCalledWith([
      { root_category_id: 6, root_category_name: 'Multi root', product_category_id: 9, product_category_name: 'Gas' },
    ])
  })

  it('INV-3: on a single-mode card the pair already there stays refused as a duplicate', async () => {
    const onValueChange = vi.fn()
    renderEditor(
      [{ root_category_id: 70, root_category_name: 'Single root', product_category_id: 71, product_category_name: 'Luce singola' }],
      onValueChange,
    )

    fireEvent.click(await screen.findByRole('option', { name: 'Single root' }))

    const current = await screen.findByRole('option', { name: 'Luce singola' })
    expect(current).toBeDisabled()
    fireEvent.click(current)
    expect(onValueChange).not.toHaveBeenCalled()
  })

  it('AC-047: on a multiple-mode card holding a pair, a single-mode root is disabled with the reason', async () => {
    const onValueChange = vi.fn()
    renderEditor([PAIR], onValueChange)

    const singleRoot = await screen.findByRole('option', { name: 'Single root' })
    expect(singleRoot).toBeDisabled()
    expect(
      screen.getByText('Parent categories managed as a single row cannot be added beside other rows.'),
    ).toBeInTheDocument()

    fireEvent.click(singleRoot)
    expect(onValueChange).not.toHaveBeenCalled()
  })

  it('AC-047: an empty card keeps the single-mode root pickable, with no note', async () => {
    renderEditor([], vi.fn())

    expect(await screen.findByRole('option', { name: 'Single root' })).toBeEnabled()
    expect(
      screen.queryByText('Parent categories managed as a single row cannot be added beside other rows.'),
    ).not.toBeInTheDocument()
  })

  it('INV-3: a multiple-mode card stays free to add another pair', async () => {
    const onValueChange = vi.fn()
    renderEditor([PAIR], onValueChange)

    fireEvent.click(await screen.findByRole('option', { name: 'Multi root' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Gas' }))

    expect(onValueChange).toHaveBeenCalledWith([
      PAIR,
      { root_category_id: 6, root_category_name: 'Multi root', product_category_id: 9, product_category_name: 'Gas' },
    ])
  })
})

describe('ProductLinesCellEditor — intermediate filter (user directive 2026-10-08)', () => {
  it('narrows the category step to the picked filter and still commits only the category', async () => {
    const onValueChange = vi.fn()
    renderEditor([], onValueChange)

    fireEvent.click(await screen.findByRole('option', { name: 'Multi root' }))
    // The path reads root, then the step being picked now.
    expect(screen.getByRole('navigation', { name: 'Selection path' })).toHaveTextContent('Multi root')
    expect(screen.getByRole('navigation', { name: 'Selection path' })).toHaveTextContent('Product category')
    fireEvent.click(screen.getByRole('button', { name: 'Filter' }))

    // Only the grouping child is a filter, the direct leaf is not.
    expect(screen.getByRole('option', { name: 'Container' })).toBeEnabled()
    expect(screen.queryByRole('option', { name: 'Luce' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('option', { name: 'Container' }))

    expect(await screen.findByRole('option', { name: 'Gas' })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: 'Luce' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('option', { name: 'Gas' }))

    expect(onValueChange).toHaveBeenCalledWith([
      { root_category_id: 6, root_category_name: 'Multi root', product_category_id: 9, product_category_name: 'Gas' },
    ])
  })

  it('removing the filter lists the whole root again', async () => {
    renderEditor([], vi.fn())

    fireEvent.click(await screen.findByRole('option', { name: 'Multi root' }))
    fireEvent.click(screen.getByRole('button', { name: 'Filter' }))
    fireEvent.click(screen.getByRole('option', { name: 'Container' }))
    fireEvent.click(screen.getByRole('button', { name: 'Remove the Container filter' }))

    expect(await screen.findByRole('option', { name: 'Luce' })).toBeInTheDocument()
  })

  it('offers no filter for a root without grouping children', async () => {
    renderEditor([], vi.fn())

    fireEvent.click(await screen.findByRole('option', { name: 'Single root' }))

    expect(await screen.findByRole('option', { name: 'Luce singola' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Filter' })).not.toBeInTheDocument()
  })
})
