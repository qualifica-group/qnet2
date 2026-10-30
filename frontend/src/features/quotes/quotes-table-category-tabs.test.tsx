import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { QuotesTable } from '@/features/quotes/quotes-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Spec 0335 AC-015 / AC-017: the category strip above the Quotes grid. The
 * grid itself is stubbed (its scope prop is what matters) and so is the
 * network layer of the strip; the strip, its hooks and the table wiring are real.
 */
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({ PageHeader: () => <div /> }))
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: () => ({
    openCreate: vi.fn(),
    openCreateWith: vi.fn(),
    openView: vi.fn(),
    openDuplicate: vi.fn(),
    sheet: null,
  }),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const deleteQuoteMock = vi.fn()
vi.mock('@/features/quotes/api', () => ({
  QUOTES_DOMAIN: 'quotes',
  deleteQuote: (...args: unknown[]) => deleteQuoteMock(...args),
  fetchQuote: vi.fn(),
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

const DELETE_ACTION: TableActionDefinition = { key: 'delete', label: 'actions.delete', icon: 'trash', type: 'danger', confirm: true }
const ROW: TableRow = { id: 3, actions: ['delete'], title: 'Offerta Acme', code: 'QUO-0003' }

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<
    { refresh: () => void },
    {
      domain: string
      scope?: { productCategoryId?: number }
      onAction?: (action: TableActionDefinition, row: TableRow) => void
    }
  >(function TableViewStub({ domain, scope, onAction }, ref) {
    useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
    return (
      <div role="region" aria-label={`table-${domain}-${scope?.productCategoryId ?? 'all'}`}>
        <button type="button" onClick={() => onAction?.(DELETE_ACTION, ROW)}>
          delete row
        </button>
      </div>
    )
  }),
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
        <QuotesTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  fetchCategoriesMock.mockReset()
  fetchCategoriesMock.mockResolvedValue(CATEGORIES)
  deleteQuoteMock.mockReset()
})

afterEach(() => {
  window.localStorage.clear()
})

describe('QuotesTable category tabs (spec 0335 AC-015)', () => {
  it('shows "All" plus one tab per category with its count, read from /quotes', async () => {
    renderTable()

    expect(await screen.findByRole('tab', { name: /ISO 9001/ })).toHaveTextContent('5')
    expect(screen.getByRole('tab', { name: 'All' })).toBeInTheDocument()
    expect(fetchCategoriesMock).toHaveBeenCalledWith('/quotes')
    expect(screen.getByRole('region', { name: 'table-quotes-all' })).toBeInTheDocument()
  })

  it('scopes the grid to the clicked category and persists it under quotes.category-tab', async () => {
    renderTable()

    fireEvent.mouseDown(await screen.findByRole('tab', { name: /ISO 9001/ }), { button: 0 })

    expect(await screen.findByRole('region', { name: 'table-quotes-12' })).toBeInTheDocument()
    expect(window.localStorage.getItem('quotes.category-tab')).toBe('12')
  })

  it('restores the stored selection on mount', async () => {
    window.localStorage.setItem('quotes.category-tab', '7')
    renderTable()

    expect(await screen.findByRole('region', { name: 'table-quotes-7' })).toBeInTheDocument()
  })

  it('falls back to "All" when the stored category is gone', async () => {
    window.localStorage.setItem('quotes.category-tab', '999')
    renderTable()

    await screen.findByRole('tab', { name: /ISO 9001/ })
    expect(screen.getByRole('region', { name: 'table-quotes-all' })).toBeInTheDocument()
  })
})

describe('QuotesTable category tabs refresh (spec 0335 AC-017)', () => {
  it('re-reads the counts after a grid mutation', async () => {
    deleteQuoteMock.mockResolvedValue(undefined)
    renderTable()
    await screen.findByRole('tab', { name: /ISO 9001/ })
    expect(fetchCategoriesMock).toHaveBeenCalledTimes(1)

    fetchCategoriesMock.mockResolvedValue([{ id: 12, name: 'ISO 9001', count: 4 }])
    fireEvent.click(screen.getByRole('button', { name: 'delete row' }))

    await waitFor(() => expect(screen.getByRole('tab', { name: /ISO 9001/ })).toHaveTextContent('4'))
    expect(fetchCategoriesMock).toHaveBeenCalledTimes(2)
  })
})
