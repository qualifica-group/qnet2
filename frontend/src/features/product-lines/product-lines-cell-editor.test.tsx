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

  it('INV-3: a single-mode card already holding its pair refuses a second one, including a new root pick', async () => {
    const onValueChange = vi.fn()
    renderEditor(
      [{ root_category_id: 70, root_category_name: 'Single root', product_category_id: 71, product_category_name: 'Luce singola' }],
      onValueChange,
    )

    expect(
      screen.getByText('This product category is managed as a single row: remove the current one to pick another.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Search parent category…' })).toBeDisabled()

    const option = await screen.findByRole('option', { name: 'Multi root' })
    expect(option).toBeDisabled()

    fireEvent.click(option)
    expect(onValueChange).not.toHaveBeenCalled()
  })

  it('INV-3: a multiple-mode card stays free to add another pair', async () => {
    renderEditor([PAIR], vi.fn())

    expect(await screen.findByRole('option', { name: 'Multi root' })).toBeEnabled()
  })
})
