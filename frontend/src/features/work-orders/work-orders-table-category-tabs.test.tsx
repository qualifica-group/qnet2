import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { WorkOrdersTable } from '@/features/work-orders/work-orders-table'

/**
 * Spec 0335 rev.3 AC-027: the category strip above the Commesse grid. The grid,
 * the row actions and the strip's network layer are stubbed; the strip, its
 * hooks and the table wiring are real.
 */
vi.mock('@/components/page-header', () => ({ PageHeader: () => <div /> }))
vi.mock('@/features/work-orders/work-order-proforma-dialog', () => ({ WorkOrderProformaDialog: () => null }))
vi.mock('@/features/activity-log/resource-activity-dialog', () => ({ ResourceActivityDialog: () => null }))

let onMutatedHandler: (() => void) | undefined
vi.mock('@/features/work-orders/use-work-order-row-actions', () => ({
  useWorkOrderRowActions: ({ onMutated }: { onMutated: () => void }) => {
    onMutatedHandler = onMutated
    return {
      handleAction: vi.fn(),
      isBusy: () => false,
      activityRow: null,
      closeActivity: vi.fn(),
      proformaRow: null,
      closeProforma: vi.fn(),
      resolveActionState: undefined,
      iconMap: {},
      renderers: {},
      sheet: null,
      forceCloseDialog: null,
      progressDialog: null,
    }
  },
}))

const fetchCategoriesMock = vi.fn()
vi.mock('@/features/category-tabs/api', () => ({
  fetchGridCategoryTabs: (...args: unknown[]) => fetchCategoriesMock(...args),
  fetchCategoryTabPreferences: vi.fn().mockResolvedValue({
    favorite_category_ids: [],
    show_only_favorites: false,
    is_default: false,
  }),
  saveCategoryTabPreferences: vi.fn(),
}))

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string; scope?: { productCategoryId?: number } }>(
    function TableViewStub({ domain, scope }, ref) {
      useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
      return <div role="region" aria-label={`table-${domain}-${scope?.productCategoryId ?? 'all'}`} />
    },
  ),
}))

const CATEGORIES = [
  { id: 12, name: 'ISO 9001', count: 5 },
  { id: 7, name: 'Formazione', count: 2 },
]

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <WorkOrdersTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  // These cases drive the single-category strip; the root level is the default since 2026-11-05.
  window.localStorage.setItem('work-orders.category-tab-level', 'all')
  fetchCategoriesMock.mockReset()
  fetchCategoriesMock.mockResolvedValue(CATEGORIES)
  onMutatedHandler = undefined
})

afterEach(() => {
  window.localStorage.clear()
})

describe('WorkOrdersTable category tabs (spec 0335 AC-027)', () => {
  it('shows "All" plus one tab per category with its count, read from /work-orders', async () => {
    renderTable()

    expect(await screen.findByRole('tab', { name: /ISO 9001/ })).toHaveTextContent('5')
    expect(screen.getByRole('tab', { name: 'All' })).toBeInTheDocument()
    expect(fetchCategoriesMock).toHaveBeenCalledWith('/work-orders')
    expect(screen.getByRole('region', { name: 'table-work-orders-all' })).toBeInTheDocument()
  })

  it('scopes the grid to the clicked category and persists it under work-orders.category-tab', async () => {
    renderTable()

    fireEvent.mouseDown(await screen.findByRole('tab', { name: /ISO 9001/ }), { button: 0 })

    expect(await screen.findByRole('region', { name: 'table-work-orders-12' })).toBeInTheDocument()
    expect(window.localStorage.getItem('work-orders.category-tab')).toBe('12')
  })

  it('falls back to "All" when the stored category is gone', async () => {
    window.localStorage.setItem('work-orders.category-tab', '999')
    renderTable()

    await screen.findByRole('tab', { name: /ISO 9001/ })
    expect(screen.getByRole('region', { name: 'table-work-orders-all' })).toBeInTheDocument()
  })

  it('re-reads the counts after a row mutation', async () => {
    renderTable()
    await screen.findByRole('tab', { name: /ISO 9001/ })
    expect(fetchCategoriesMock).toHaveBeenCalledTimes(1)

    fetchCategoriesMock.mockResolvedValue([{ id: 12, name: 'ISO 9001', count: 4 }])
    act(() => onMutatedHandler?.())

    await waitFor(() => expect(screen.getByRole('tab', { name: /ISO 9001/ })).toHaveTextContent('4'))
    expect(fetchCategoriesMock).toHaveBeenCalledTimes(2)
  })
})
