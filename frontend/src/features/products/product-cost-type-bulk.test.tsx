import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductsTable } from '@/features/products/products-table'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { BulkProductCostTypePayload, BulkProductCostTypeResult } from '@/features/products/types'

/** Spec 0251 AC-007: the "Set cost type" bulk action of the Products grid and its dialog. */

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission), hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/auth/can', () => ({ Can: ({ children }: { children: ReactNode }) => <>{children}</> }))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'page' }))
vi.mock('@/components/page-header', () => ({ PageHeader: () => null }))
vi.mock('@/features/stats/module-stats-panel', () => ({ ModuleStatsPanel: () => null }))
vi.mock('@/features/stats/stats-toggle-button', () => ({ StatsToggleButton: () => null }))
vi.mock('@/features/stats/use-stats-panel', () => ({ useStatsPanel: () => ({ isOpen: false, toggle: () => {} }) }))
vi.mock('@/features/stats/use-invalidate-module-stats', () => ({ useInvalidateModuleStats: () => () => {} }))

const toastSuccess = vi.fn()
vi.mock('sonner', () => ({ toast: { success: (...args: unknown[]) => toastSuccess(...args), error: vi.fn() } }))

const setCostTypeMock = vi.fn<(payload: BulkProductCostTypePayload) => Promise<BulkProductCostTypeResult>>()
vi.mock('@/features/products/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/products/api')>()),
  setProductsCostType: (payload: BulkProductCostTypePayload) => setCostTypeMock(payload),
}))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/products/product-test-stubs')).StubAsyncSelect,
}))

const refreshMock = vi.fn()
const SELECTION: TableSelection = {
  ids: [1, 2],
  rows: [
    { id: 1, name: 'Fuel', actions: [] },
    { id: 2, name: 'Toll', actions: [] },
  ],
}

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { getBulkActions?: (selection: TableSelection) => BulkAction[] }>(
    function TableViewStub({ getBulkActions }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshMock }))
      return (
        <div>
          {(getBulkActions?.(SELECTION) ?? []).map((action) => (
            <button key={action.key} type="button" onClick={action.onSelect}>
              {action.label}
            </button>
          ))}
        </div>
      )
    },
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ProductsTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset().mockReturnValue(true)
  setCostTypeMock.mockReset().mockResolvedValue({ updated: 2 })
  toastSuccess.mockReset()
  refreshMock.mockReset()
})

describe('Products grid - bulk "Set cost type" (spec 0251 AC-007)', () => {
  it('is not offered without products.update', () => {
    canMock.mockImplementation((permission) => permission !== 'products.update')

    renderTable()

    expect(screen.queryByRole('button', { name: 'Set cost type' })).not.toBeInTheDocument()
  })

  it('opens a dialog listing the selection, sets the chosen type, toasts the count and refreshes the grid', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Set cost type' }))
    expect(screen.getByRole('list', { name: 'Selected products' })).toHaveTextContent('FuelToll')

    const confirm = screen.getByRole('button', { name: 'Apply to 2 products' })
    expect(confirm).toBeDisabled()

    fireEvent.click(screen.getByTestId('select-Cost type'))
    await waitFor(() => expect(confirm).toBeEnabled())
    fireEvent.click(confirm)

    await waitFor(() => expect(setCostTypeMock).toHaveBeenCalledWith({ product_ids: [1, 2], cost_type_id: 7 }))
    await waitFor(() => expect(toastSuccess).toHaveBeenCalledWith('Cost type updated on 2 product(s).'))
    expect(refreshMock).toHaveBeenCalled()
  })

  it('"None" removes the cost type by sending null', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Set cost type' }))
    fireEvent.click(screen.getByRole('checkbox', { name: 'None (remove the cost type)' }))
    fireEvent.click(screen.getByRole('button', { name: 'Apply to 2 products' }))

    await waitFor(() => expect(setCostTypeMock).toHaveBeenCalledWith({ product_ids: [1, 2], cost_type_id: null }))
  })
})
