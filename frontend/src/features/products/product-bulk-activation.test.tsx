import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProductsTable } from '@/features/products/products-table'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { BulkProductActivationPayload, BulkProductCostTypeResult } from '@/features/products/types'

/** Spec 0366 AC-011: the "Activate" / "Deactivate" bulk actions of the Products grid. */

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

const setActivationMock = vi.fn<(payload: BulkProductActivationPayload) => Promise<BulkProductCostTypeResult>>()
vi.mock('@/features/products/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/products/api')>()),
  setProductsActivation: (payload: BulkProductActivationPayload) => setActivationMock(payload),
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
  setActivationMock.mockReset().mockResolvedValue({ updated: 2 })
  toastSuccess.mockReset()
  refreshMock.mockReset()
})

describe('Products grid - bulk activation (spec 0366 AC-011)', () => {
  it('is not offered without products.update', () => {
    canMock.mockImplementation((permission) => permission !== 'products.update')

    renderTable()

    expect(screen.queryByRole('button', { name: 'Activate' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Deactivate' })).not.toBeInTheDocument()
  })

  it('activates the selected products, toasts the count and refreshes the grid', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Activate' }))

    await waitFor(() => expect(setActivationMock).toHaveBeenCalledWith({ product_ids: [1, 2], is_active: true }))
    await waitFor(() => expect(toastSuccess).toHaveBeenCalledWith('2 product(s) activated.'))
    expect(refreshMock).toHaveBeenCalled()
  })

  it('deactivates the selected products', async () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'Deactivate' }))

    await waitFor(() => expect(setActivationMock).toHaveBeenCalledWith({ product_ids: [1, 2], is_active: false }))
    await waitFor(() => expect(toastSuccess).toHaveBeenCalledWith('2 product(s) deactivated.'))
  })
})
