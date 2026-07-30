import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import type { SearchableSelectOption } from '@/components/ui/searchable-select'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import ProductCategoriesPage from '@/pages/product-categories-page'

/**
 * Bulk move (spec 0063): the table adapter's bulk action, its permission
 * gate, and the destination picker's cycle exclusion.
 */
const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const bulkMoveMock = vi.fn()

vi.mock('@/features/product-categories/api', () => ({
  deleteProductCategory: vi.fn(),
  bulkMoveProductCategories: (...args: unknown[]) => bulkMoveMock(...args),
  fetchProductCategoryTree: () => Promise.resolve(TREE),
}))

const refreshMock = vi.fn()
const clearSelectionMock = vi.fn()

/**
 * Root
 *  └─ Child
 *      └─ Grandchild
 * Sibling
 */
const TREE: ProductCategoryTreeNode[] = [
  {
    id: 1,
    name: 'Root',
    parent_id: null,
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    children: [
      {
        id: 2,
        name: 'Child',
        parent_id: 1,
        attributes_count: 0,
        products_count: 0,
        business_function_id: null,
        requires_quote: false,
        children: [
          {
            id: 3,
            name: 'Grandchild',
            parent_id: 2,
            attributes_count: 0,
            products_count: 0,
            business_function_id: null,
            requires_quote: false,
            children: [],
          },
        ],
      },
    ],
  },
  {
    id: 4,
    name: 'Sibling',
    parent_id: null,
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    children: [],
  },
]

/** The selection fed into `getBulkActions`; each test sets it before rendering. */
let bulkSelection: TableSelection = { ids: [1], rows: [{ id: 1, actions: [] }] }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void; clearSelection: () => void },
    { domain: string; getBulkActions?: (selection: TableSelection) => BulkAction[] }
  >(function TableViewStub({ domain, getBulkActions }, ref) {
    useImperativeHandle(ref, () => ({ refresh: refreshMock, clearSelection: clearSelectionMock }))
    // The real slot renders these inside one dropdown; the stub renders plain
    // buttons so the flow is reachable by accessible label.
    return (
      <div role="region" aria-label={`table-${domain}`}>
        {getBulkActions?.(bulkSelection).map((action) => (
          <button key={action.key} type="button" onClick={() => action.onSelect()}>
            {action.label}
          </button>
        ))}
      </div>
    )
  }),
}))

/** Exposes every destination option as a button, so exclusions are assertable. */
vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: ({
    options,
    onChange,
  }: {
    options: SearchableSelectOption[]
    onChange: (id: number) => void
  }) => (
    <div role="listbox" aria-label="destination-options">
      {options.map((option) => (
        <button key={option.id} type="button" onClick={() => onChange(option.id)}>
          {option.name.trim().replace('↳ ', '')}
        </button>
      ))}
    </div>
  ),
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ProductCategoriesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockImplementation((permission) =>
    ['product-categories.viewAny', 'product-categories.update'].includes(permission),
  )
  bulkMoveMock.mockReset()
  bulkMoveMock.mockResolvedValue({ moved: 2 })
  refreshMock.mockReset()
  clearSelectionMock.mockReset()
  bulkSelection = { ids: [1], rows: [{ id: 1, actions: [] }] }
})

describe('ProductCategoriesTable — bulk move', () => {
  it('AC-011: hides the bulk action without product-categories.update', () => {
    canMock.mockImplementation((permission) => permission === 'product-categories.viewAny')

    renderPage()

    expect(screen.queryByRole('button', { name: 'Move under…' })).not.toBeInTheDocument()
  })

  it('AC-012: posts the selected ids with the chosen destination, then refreshes and clears', async () => {
    bulkSelection = {
      ids: [2, 4],
      rows: [
        { id: 2, actions: [] },
        { id: 4, actions: [] },
      ],
    }

    renderPage()
    fireEvent.click(screen.getByRole('button', { name: 'Move under…' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Root' }))
    fireEvent.click(screen.getByRole('button', { name: 'Move' }))

    await waitFor(() => {
      expect(bulkMoveMock).toHaveBeenCalledWith({ category_ids: [2, 4], parent_id: 1 })
    })
    expect(bulkMoveMock).toHaveBeenCalledTimes(1)
    await waitFor(() => expect(refreshMock).toHaveBeenCalled())
    expect(clearSelectionMock).toHaveBeenCalled()
  })

  it('AC-002: sends parent_id null when the root option is chosen', async () => {

    renderPage()
    fireEvent.click(screen.getByRole('button', { name: 'Move under…' }))
    fireEvent.click(await screen.findByRole('button', { name: 'No parent (root category)' }))
    fireEvent.click(screen.getByRole('button', { name: 'Move' }))

    await waitFor(() => {
      expect(bulkMoveMock).toHaveBeenCalledWith({ category_ids: [1], parent_id: null })
    })
  })

  it('AC-013: excludes the selected categories and their descendants from the destinations', async () => {
    bulkSelection = { ids: [2], rows: [{ id: 2, actions: [] }] }

    renderPage()
    fireEvent.click(screen.getByRole('button', { name: 'Move under…' }))

    // Wait for the tree to land before reading the list: the picker renders
    // with the root option alone while the query is still pending.
    await screen.findByRole('button', { name: 'Sibling' })
    const options = screen.getByRole('listbox', { name: 'destination-options' })
    const labels = Array.from(options.querySelectorAll('button')).map((button) => button.textContent)

    expect(labels).toEqual(['No parent (root category)', 'Root', 'Sibling'])
  })

  it('lists the server conflicts and keeps the dialog open when the batch is refused', async () => {
    bulkMoveMock.mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          errors: {
            reason: 'nested_selection',
            conflicts: [{ id: 3, name: 'Grandchild', detail: 'It already descends from "Child".' }],
          },
        },
      },
    })

    renderPage()
    fireEvent.click(screen.getByRole('button', { name: 'Move under…' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Sibling' }))
    fireEvent.click(screen.getByRole('button', { name: 'Move' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('The selection contains categories nested inside one another.')
    expect(alert).toHaveTextContent('Grandchild')
    expect(screen.getByRole('button', { name: 'Move' })).toBeInTheDocument()
    expect(refreshMock).not.toHaveBeenCalled()
  })
})
