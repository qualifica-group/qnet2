import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { OpportunitiesTable } from '@/features/opportunities/opportunities-table'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Spec 0335 AC-014 / AC-017: the category strip above the Opportunities grid. The
 * grid itself is stubbed (its scope prop is what matters) and so is the
 * network layer of the strip; the strip, its hooks and the table wiring are real.
 */
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/components/page-header', () => ({ PageHeader: () => <div /> }))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))
vi.mock('@/features/stats/module-stats-panel', () => ({ ModuleStatsPanel: () => null }))
vi.mock('@/features/stats/stats-toggle-button', () => ({ StatsToggleButton: () => null }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/opportunities/api', () => ({
  deleteOpportunity: vi.fn(),
  OPPORTUNITIES_DOMAIN: 'opportunities',
  OPPORTUNITY_ATTACHABLE_ALIAS: 'opportunity',
}))

// A write inside the notes dialog calls `onThreadChanged`, which the adapter wires to `handleMutated`.
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: ({ onThreadChanged }: { onThreadChanged?: () => void }) => (
    <button type="button" onClick={onThreadChanged}>
      change thread
    </button>
  ),
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

const NOTES_ACTION: TableActionDefinition = {
  key: 'notes',
  label: 'actions.notes',
  icon: 'messages-square',
  type: 'action',
  confirm: false,
  count_field: 'notes_count',
}
const ROW: TableRow = { id: 3, actions: ['notes'], notes_count: 0 }

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
        <button type="button" onClick={() => onAction?.(NOTES_ACTION, ROW)}>
          open notes
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
        <OpportunitiesTable />
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
})

afterEach(() => {
  window.localStorage.clear()
})

describe('OpportunitiesTable category tabs (spec 0335 AC-014)', () => {
  it('shows "All" plus one tab per category with its count, read from /opportunities', async () => {
    renderTable()

    expect(await screen.findByRole('tab', { name: /ISO 9001/ })).toHaveTextContent('5')
    expect(screen.getByRole('tab', { name: 'All' })).toBeInTheDocument()
    expect(fetchCategoriesMock).toHaveBeenCalledWith('/opportunities')
    expect(screen.getByRole('region', { name: 'table-opportunities-all' })).toBeInTheDocument()
  })

  it('scopes the grid to the clicked category and persists it under opportunities.category-tab', async () => {
    renderTable()

    fireEvent.mouseDown(await screen.findByRole('tab', { name: /ISO 9001/ }), { button: 0 })

    expect(await screen.findByRole('region', { name: 'table-opportunities-12' })).toBeInTheDocument()
    expect(window.localStorage.getItem('opportunities.category-tab')).toBe('12')
  })

  it('restores the stored selection on mount', async () => {
    window.localStorage.setItem('opportunities.category-tab', '7')
    renderTable()

    expect(await screen.findByRole('region', { name: 'table-opportunities-7' })).toBeInTheDocument()
  })

  it('falls back to "All" when the stored category is gone', async () => {
    window.localStorage.setItem('opportunities.category-tab', '999')
    renderTable()

    await screen.findByRole('tab', { name: /ISO 9001/ })
    expect(screen.getByRole('region', { name: 'table-opportunities-all' })).toBeInTheDocument()
  })
})

describe('OpportunitiesTable category tabs refresh (spec 0335 AC-017)', () => {
  it('re-reads the counts after a grid mutation (a write in the notes dialog)', async () => {
    renderTable()
    await screen.findByRole('tab', { name: /ISO 9001/ })
    expect(fetchCategoriesMock).toHaveBeenCalledTimes(1)

    fetchCategoriesMock.mockResolvedValue([{ id: 12, name: 'ISO 9001', count: 4 }])
    fireEvent.click(screen.getByRole('button', { name: 'open notes' }))
    fireEvent.click(await screen.findByRole('button', { name: 'change thread' }))

    await waitFor(() => expect(fetchCategoriesMock).toHaveBeenCalledTimes(2))
    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })
    await waitFor(() => expect(screen.getByRole('tab', { name: /ISO 9001/ })).toHaveTextContent('4'))
  })
})
