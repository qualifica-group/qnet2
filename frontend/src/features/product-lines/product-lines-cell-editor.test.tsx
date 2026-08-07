import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import i18n from '@/i18n'
import { ProductLinesCellEditor, type ProductLineCellValue } from '@/features/product-lines/product-lines-cell-editor'
import type { TableRow } from '@/features/table/types'

const fetchForSelectMock = vi.fn()

/**
 * Spec 0077 INV-3 (user directive 2026-08-07): the editor resolves the card's
 * management mode off the same cached category tree the form pickers read.
 * Category 71 hangs from a `single` root, category 7 from a `multiple` one.
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
    ...overrides,
  })

  return {
    CATEGORY_TREE: [
      node({
        id: 70,
        name: 'Single root',
        business_function_id: 3,
        is_selectable: false,
        management_mode: 'single',
        single_quote_per_opportunity: false,
        children: [node({ id: 71, name: 'Luce singola', parent_id: 70, management_mode: 'single' })],
      }),
      node({
        id: 6,
        name: 'Multi root',
        business_function_id: 3,
        is_selectable: false,
        children: [node({ id: 7, name: 'Luce', parent_id: 6 })],
      }),
    ],
  }
})

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: CATEGORY_TREE, isPending: false, isError: false }),
}))

vi.mock('@/features/for-select/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/api')>(
    '@/features/for-select/api',
  )
  return {
    ...actual,
    fetchForSelect: (resource: string, params: unknown) => fetchForSelectMock(resource, params),
  }
})

function page(items: { id: number; label: string }[]) {
  return { items, pagination: { offset: 0, limit: 25, total: items.length }, export_link: null }
}

const PAIR: ProductLineCellValue = {
  business_function_id: 3,
  business_function_name: 'Energia',
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
  fetchForSelectMock.mockReset()
  fetchForSelectMock.mockImplementation((resource: string) =>
    Promise.resolve(
      resource === 'business-functions'
        ? page([{ id: 3, label: 'Energia' }])
        : page([{ id: 9, label: 'Gas' }]),
    ),
  )
})

describe('ProductLinesCellEditor (spec 0075)', () => {
  it('AC-013: adds a pair in the form\'s own two steps, category scoped to the picked function', async () => {
    const onValueChange = vi.fn()
    renderEditor([], onValueChange)

    await waitFor(() => expect(screen.getByRole('option', { name: 'Energia' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('option', { name: 'Energia' }))

    await waitFor(() =>
      expect(fetchForSelectMock).toHaveBeenCalledWith(
        'product-categories',
        expect.objectContaining({ params: { business_function_id: 3 } }),
      ),
    )
    fireEvent.click(await screen.findByRole('option', { name: 'Gas' }))

    expect(onValueChange).toHaveBeenCalledWith([
      {
        business_function_id: 3,
        business_function_name: 'Energia',
        product_category_id: 9,
        product_category_name: 'Gas',
      },
    ])
  })

  it('AC-013: lists the pairs already on the record and removes one', async () => {
    const onValueChange = vi.fn()
    renderEditor([PAIR], onValueChange)

    expect(screen.getByText('Luce')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Remove Luce' }))

    expect(onValueChange).toHaveBeenCalledWith([])
  })

  it('AC-014: warns when a product of interest would be left uncovered', () => {
    const row = {
      id: 1,
      products_of_interest: [{ id: 4, name: 'Fibra 1000', category_id: 7 }],
    } as unknown as TableRow

    renderEditor([], vi.fn(), row)

    expect(screen.getByRole('alert')).toHaveTextContent('Fibra 1000')
  })

  it('INV-3: a single-mode card already holding its pair refuses a second one', async () => {
    const onValueChange = vi.fn()
    renderEditor(
      [
        {
          business_function_id: 3,
          business_function_name: 'Energia',
          product_category_id: 71,
          product_category_name: 'Luce singola',
        },
      ],
      onValueChange,
    )

    expect(
      screen.getByText('This product category is managed as a single row: remove the current one to pick another.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Search business functions…' })).toBeDisabled()

    const option = await screen.findByRole('option', { name: 'Energia' })
    expect(option).toBeDisabled()

    fireEvent.click(option)
    expect(onValueChange).not.toHaveBeenCalled()
  })

  it('INV-3: a multiple-mode card stays free to add another pair', async () => {
    renderEditor([PAIR], vi.fn())

    expect(await screen.findByRole('option', { name: 'Energia' })).toBeEnabled()
  })

  it('AC-014: no warning while every product stays covered', () => {
    const row = {
      id: 1,
      products_of_interest: [{ id: 4, name: 'Fibra 1000', category_id: 7 }],
    } as unknown as TableRow

    renderEditor([PAIR], vi.fn(), row)

    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })
})
