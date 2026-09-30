import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import ProductsPage from '@/pages/products-page'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * AC-025 — permission gating of the Products page, and AC-A1 (spec 0022) — the
 * row actions and the "New" button navigate to the dedicated pages instead of
 * opening a Sheet.
 */
const canMock = vi.fn<(permission: string) => boolean>()
// Default open mode for products (page, spec 0022/0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'page',
}))

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

const ROW: TableRow = { id: 4, actions: ['view', 'edit'] }
const action = (key: string): TableActionDefinition => ({
  key,
  label: key,
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
        <button type="button" onClick={() => onAction(action('view'), ROW)}>
          row-view
        </button>
        <button type="button" onClick={() => onAction(action('edit'), ROW)}>
          row-edit
        </button>
      </div>
    )
  }),
}))

function LocationProbe() {
  const { pathname } = useLocation()
  return <span>location:{pathname}</span>
}

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/products']}>
        <LocationProbe />
        <Routes>
          <Route path="/products" element={<ProductsPage />} />
          <Route path="*" element={null} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
})

describe('ProductsPage — permission gating (AC-025)', () => {
  it('shows the forbidden fallback and does not mount the grid without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(screen.getByText("You don't have permission to view products.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-products' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="products"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'products.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-products' })).toBeInTheDocument()
  })
})

describe('ProductsTable — navigation to the dedicated pages (AC-A1)', () => {
  beforeEach(() => {
    canMock.mockReturnValue(true)
  })

  it('navigates to the detail page on the view row action', async () => {
    renderPage()

    fireEvent.click(screen.getByRole('button', { name: 'row-view' }))

    expect(screen.getByText('location:/products/4')).toBeInTheDocument()
  })

  it('ignores the retired edit row action: editing starts from the detail page', async () => {
    renderPage()

    fireEvent.click(screen.getByRole('button', { name: 'row-edit' }))

    expect(screen.getByText('location:/products')).toBeInTheDocument()
  })

  it('navigates to the create page from the New product button', async () => {
    renderPage()

    fireEvent.click(screen.getByRole('button', { name: /new product/i }))

    expect(screen.getByText('location:/products/new')).toBeInTheDocument()
  })
})
