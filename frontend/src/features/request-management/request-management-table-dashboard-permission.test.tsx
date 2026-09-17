import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RequestManagementTable } from '@/features/request-management/request-management-table'

/**
 * The "Statistiche" panel's open state lives in `localStorage`
 * (`stats-panel:request-management`), i.e. per BROWSER, not per user: an
 * impersonator who left it open would reopen it for an impersonated user
 * without `request-management.report` — only the toggle was gated (spec 0107
 * D-6), so that user got a panel stuck on a 403 and no way to close it. Without
 * the permission the page falls back to the grid, and the stored preference is
 * left untouched so the impersonator finds it again on stop.
 *
 * `<TableView>` and the panel are stubbed: this suite is about what the adapter
 * decides to open, not about the panel's own states.
 */

const STORAGE_KEY = 'stats-panel:request-management'
const REPORT_PERMISSION = 'request-management.report'

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'page',
}))

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

vi.mock('@/features/request-management/request-dashboard-panel', () => ({
  RequestDashboardPanel: ({ isOpen }: { isOpen: boolean }) =>
    isOpen ? <div role="region" aria-label="dashboard-panel" /> : null,
}))

vi.mock('@/features/request-management/api', () => ({
  fetchRequestManagementCategories: vi.fn().mockResolvedValue([]),
  deleteRequest: vi.fn(),
  assignRequestOperators: vi.fn(),
  assignRequestManagerGa1: vi.fn(),
  fetchCategoryManagerLabels: vi.fn(),
  transferRequests: vi.fn(),
}))

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void; clearSelection: () => void }, { domain: string }>(
    function TableViewStub({ domain }, ref) {
      useImperativeHandle(ref, () => ({ refresh: () => {}, clearSelection: () => {} }))
      return <div role="region" aria-label={`table-${domain}`} />
    },
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <RequestManagementTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  canMock.mockReset()
})

describe('RequestManagementTable — persisted "Statistiche" panel vs report permission', () => {
  it('falls back to the grid when the panel was left open but the actor lacks the report permission', () => {
    window.localStorage.setItem(STORAGE_KEY, 'true')
    canMock.mockImplementation((permission) => permission !== REPORT_PERMISSION)

    renderTable()

    expect(screen.queryByRole('region', { name: 'dashboard-panel' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Statistics' })).not.toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'table-request-management' })).toBeInTheDocument()
    // Not rewritten: the impersonator gets the panel back once impersonation stops.
    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('true')
  })

  it('restores the panel left open for an actor holding the report permission', () => {
    window.localStorage.setItem(STORAGE_KEY, 'true')
    canMock.mockReturnValue(true)

    renderTable()

    expect(screen.getByRole('region', { name: 'dashboard-panel' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Statistics' })).toHaveAttribute('aria-expanded', 'true')
  })
})
