import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ContractsTable } from '@/features/contracts/contracts-table'

/**
 * Spec 0335 AC-023: the category strip above the Contracts grid. The grid
 * itself is stubbed (its scope prop is what matters) and so is the network
 * layer of the strip; the strip, its hooks and the table wiring are real.
 */
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({ PageHeader: () => <div /> }))

let onSavedHandler: (() => void) | undefined
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: (_domain: string, options?: { onSaved?: () => void }) => {
    onSavedHandler = options?.onSaved
    return {
      openCreate: vi.fn(),
      openCreateWith: vi.fn(),
      openView: vi.fn(),
      openDuplicate: vi.fn(),
      sheet: null,
    }
  },
}))
vi.mock('@/features/contracts/contract-multi-program-dialog', () => ({ ContractMultiProgramDialog: () => null }))

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
        <ContractsTable />
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
  onSavedHandler = undefined
})

afterEach(() => {
  window.localStorage.clear()
})

describe('ContractsTable category tabs (spec 0335 AC-023)', () => {
  it('shows "All" plus one tab per category with its count, read from /contracts', async () => {
    renderTable()

    expect(await screen.findByRole('tab', { name: /ISO 9001/ })).toHaveTextContent('5')
    expect(screen.getByRole('tab', { name: 'All' })).toBeInTheDocument()
    expect(fetchCategoriesMock).toHaveBeenCalledWith('/contracts')
    expect(screen.getByRole('region', { name: 'table-contracts-all' })).toBeInTheDocument()
  })

  it('scopes the grid to the clicked category and persists it under contracts.category-tab', async () => {
    renderTable()

    fireEvent.mouseDown(await screen.findByRole('tab', { name: /ISO 9001/ }), { button: 0 })

    expect(await screen.findByRole('region', { name: 'table-contracts-12' })).toBeInTheDocument()
    expect(window.localStorage.getItem('contracts.category-tab')).toBe('12')
  })

  it('restores the stored selection on mount', async () => {
    window.localStorage.setItem('contracts.category-tab', '7')
    renderTable()

    expect(await screen.findByRole('region', { name: 'table-contracts-7' })).toBeInTheDocument()
  })

  it('falls back to "All" when the stored category is gone', async () => {
    window.localStorage.setItem('contracts.category-tab', '999')
    renderTable()

    await screen.findByRole('tab', { name: /ISO 9001/ })
    expect(screen.getByRole('region', { name: 'table-contracts-all' })).toBeInTheDocument()
  })
})

describe('ContractsTable category tabs refresh (spec 0335 AC-023)', () => {
  it('re-reads the counts after a save from the detail', async () => {
    renderTable()
    await screen.findByRole('tab', { name: /ISO 9001/ })
    expect(fetchCategoriesMock).toHaveBeenCalledTimes(1)

    fetchCategoriesMock.mockResolvedValue([{ id: 12, name: 'ISO 9001', count: 4 }])
    act(() => onSavedHandler?.())

    await waitFor(() => expect(screen.getByRole('tab', { name: /ISO 9001/ })).toHaveTextContent('4'))
    expect(fetchCategoriesMock).toHaveBeenCalledTimes(2)
  })
})
