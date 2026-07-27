import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import ProductCategoriesPage from '@/pages/product-categories-page'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { AttributeLayoutData, ProductCategoryTreeNode } from '@/features/product-categories/types'

/**
 * Permission gating of the Product Categories page, now backed by the
 * generic AG Grid SSRM table instead of the removed tree view (mirrors
 * `ProductsPage`'s suite). Also covers the "layout" row action (spec 0062
 * revision): it opens the dedicated attribute-layout Sheet for the clicked
 * row's category, off the shared `TableView` stub (mirrors `ProductsTable`'s
 * suite).
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

// This suite exercises the default modal behaviour; force the resolved open
// mode so it never depends on an AuthProvider (spec 0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

const fetchAttributeLayoutMock = vi.fn<
  (categoryId: number, context: string, formMode: string) => Promise<AttributeLayoutData>
>()
const fetchProductCategoryTreeMock = vi.fn<() => Promise<ProductCategoryTreeNode[]>>()

vi.mock('@/features/product-categories/api', () => ({
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  bulkMoveProductCategories: vi.fn(),
  deleteProductCategory: vi.fn(),
  fetchAttributeLayout: (...args: [number, string, string]) => fetchAttributeLayoutMock(...args),
  saveAttributeLayout: vi.fn(),
}))

const LAYOUT_ROW: TableRow = { id: 9, actions: ['layout'], name: 'Widgets' }
const action = (key: string): TableActionDefinition => ({
  key,
  label: `actions.${key}`,
  icon: key,
  type: 'action',
  confirm: false,
})

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    { domain: string; onAction: RowActionHandler }
  >(function TableViewStub({ domain, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: () => {} }))
    return (
      <div role="region" aria-label={`table-${domain}`}>
        <button type="button" onClick={() => onAction(action('layout'), LAYOUT_ROW)}>
          row-layout
        </button>
      </div>
    )
  }),
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        {/* App.tsx mounts this app-wide; the layout sheet's scope notice consumes it via useConfirm. */}
        <ConfirmDialogProvider>
          <ProductCategoriesPage />
        </ConfirmDialogProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  fetchAttributeLayoutMock.mockReset()
  fetchAttributeLayoutMock.mockResolvedValue({ layout: null, inherited: null, attributes: [] })
  fetchProductCategoryTreeMock.mockReset()
  fetchProductCategoryTreeMock.mockResolvedValue([])
})

describe('ProductCategoriesPage — permission gating', () => {
  it('shows the forbidden fallback and does not mount the grid without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.getByText("You don't have permission to view product categories."),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-product-categories' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="product-categories"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'product-categories.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-product-categories' })).toBeInTheDocument()
  })
})

describe('ProductCategoriesTable — "layout" row action (spec 0062 revision)', () => {
  beforeEach(() => {
    canMock.mockReturnValue(true)
  })

  it('opens the attribute-layout sheet for the clicked row’s category', async () => {
    renderPage()

    fireEvent.click(screen.getByRole('button', { name: 'row-layout' }))

    expect(await screen.findByText('Attribute layout')).toBeInTheDocument()
    expect(screen.getByText('Widgets')).toBeInTheDocument()
    await waitFor(() => expect(fetchAttributeLayoutMock).toHaveBeenCalledWith(9, 'product', 'all'))
  })
})
