import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ProjectsView } from '@/features/projects/projects-view'
import type { ModuleStats } from '@/features/stats/types'
import type { ProjectDetail } from '@/features/projects/types'

/**
 * Layout fix (spec 0026): the page must render exactly ONE `PageHeader`
 * (breadcrumb + actions only, no title/subtitle), shared by both views, with
 * `[view toggle, stats toggle, "New project"]` in that row regardless of the
 * grid/table choice — never a second header nested inside `ProjectsTable`.
 */

const canMock = vi.fn<(permission: string) => boolean>()
// Default modal behaviour; force the resolved open mode (spec 0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

// `PageHeader` normally renders `<AppBreadcrumbs>`, which needs an
// `AuthProvider` (mirrors `projects-table.test.tsx`'s own stub) — this suite
// only cares that title/subtitle/actions are passed through (or absent).
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ title, subtitle, actions }: { title?: string; subtitle?: string; actions?: ReactNode }) => (
    <div>
      {title ? <h1>{title}</h1> : null}
      {subtitle ? <p>{subtitle}</p> : null}
      {actions}
    </div>
  ),
}))

vi.mock('@/features/projects/project-card-grid', () => ({
  ProjectCardGrid: () => <div>card-grid</div>,
}))

const mockProject: ProjectDetail = {
  id: 12,
  code: 'PRJ-0012',
  name: 'Acme rollout',
  description: null,
  pipeline_status_id: 1,
  pipeline_status: { id: 1, name: 'Active', color: null },
  country_id: 1,
  country: { id: 1, name: 'Italy' },
  state_id: null,
  state: null,
  province_id: null,
  province: null,
  city_id: null,
  city: null,
  geo_scope: 'country',
  product_lines: [],
  partner_id: null,
  partner: null,
  operational_site_id: null,
  operational_site: null,
  start_date: null,
  end_date: null,
  total_budget: null,
  target_lead: null,
  allocated_budget: '0.00',
  remaining_budget: null,
  campaigns_count: 0,
  created_at: '2026-01-01T00:00:00Z',
}

/**
 * `ProjectForm` submission is owned by a different lane. Stubbed to two
 * buttons that invoke `onSuccess`/`onCancel` directly (mirrors
 * `projects-table.test.tsx`), so this suite can verify what happens to the
 * lifted create Sheet and the lists once it round-trips.
 */
vi.mock('@/features/projects/project-form', () => ({
  ProjectForm: ({
    onSuccess,
    onCancel,
  }: {
    onSuccess: (project: ProjectDetail) => void
    onCancel: () => void
  }) => (
    <div>
      <button type="button" onClick={() => onSuccess(mockProject)}>
        stub-save
      </button>
      <button type="button" onClick={onCancel}>
        stub-cancel
      </button>
    </div>
  ),
}))

const tableRefreshMock = vi.fn()
vi.mock('@/features/projects/projects-table', () => ({
  ProjectsTable: forwardRef<{ refresh: () => void }, { hideHeader?: boolean }>(
    function ProjectsTableStub({ hideHeader }, ref) {
      useImperativeHandle(ref, () => ({ refresh: tableRefreshMock }))
      return <div>projects-table (hideHeader={String(hideHeader)})</div>
    },
  ),
}))

const fetchModuleStatsMock = vi.fn<() => Promise<ModuleStats>>()
vi.mock('@/features/stats/api', () => ({
  fetchModuleStats: () => fetchModuleStatsMock(),
  moduleStatsQueryKey: (domain: string) => ['stats', domain],
}))

function renderView() {
  window.localStorage.clear()
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return {
    client,
    ...render(
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <ProjectsView />
        </MemoryRouter>
      </QueryClientProvider>,
    ),
  }
}

/** Radix `TabsTrigger` activates on `mouseDown`, not `click` (`@radix-ui/react-tabs`). */
function switchToTable() {
  fireEvent.mouseDown(screen.getByRole('tab', { name: 'Table' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  fetchModuleStatsMock.mockReset()
  fetchModuleStatsMock.mockResolvedValue({ widgets: [] })
  tableRefreshMock.mockReset()
})

describe('ProjectsView — single unified header (layout fix)', () => {
  it('renders no title/subtitle: only the breadcrumb + actions row, like every other module', () => {
    renderView()

    expect(screen.queryByRole('heading')).not.toBeInTheDocument()
  })

  it('shows all three controls — view toggle, stats toggle, "New project" — in ONE row, in grid mode', () => {
    renderView()

    expect(screen.getByRole('tab', { name: 'Grid' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Statistics' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /new project/i })).toBeInTheDocument()
  })

  it('keeps the exact same three controls in table mode: the toggle never appears alone', () => {
    renderView()

    switchToTable()

    expect(screen.getByText(/projects-table/)).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Table' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Statistics' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /new project/i })).toBeInTheDocument()
  })

  it('mounts `<ProjectsTable hideHeader>` in table mode, never its own header', () => {
    renderView()

    switchToTable()

    expect(screen.getByText('projects-table (hideHeader=true)')).toBeInTheDocument()
  })

  it('switches views without duplicating the grid/card content', () => {
    renderView()

    expect(screen.getByText('card-grid')).toBeInTheDocument()
    expect(screen.queryByText(/projects-table/)).not.toBeInTheDocument()

    switchToTable()

    expect(screen.queryByText('card-grid')).not.toBeInTheDocument()
    expect(screen.getByText(/projects-table/)).toBeInTheDocument()
  })

  it('hides "New project" without projects.create, in both views', () => {
    canMock.mockImplementation((permission) => permission !== 'projects.create')
    renderView()

    expect(screen.queryByRole('button', { name: /new project/i })).not.toBeInTheDocument()

    switchToTable()
    expect(screen.queryByRole('button', { name: /new project/i })).not.toBeInTheDocument()
  })
})

describe('ProjectsView — statistics panel independent of the view (spec 0026)', () => {
  it('issues no request to /api/stats/projects until the toggle is opened (AC-007)', () => {
    renderView()

    expect(fetchModuleStatsMock).not.toHaveBeenCalled()
  })

  it('stays open across a grid/table switch, without a second request', async () => {
    renderView()

    fireEvent.click(screen.getByRole('button', { name: 'Statistics' }))
    await waitFor(() => expect(fetchModuleStatsMock).toHaveBeenCalledTimes(1))

    switchToTable()

    expect(screen.getByRole('button', { name: 'Statistics' })).toHaveAttribute(
      'aria-expanded',
      'true',
    )
    expect(fetchModuleStatsMock).toHaveBeenCalledTimes(1)
  })
})

describe('ProjectsView — "New project" opens the SAME create Sheet in both views', () => {
  it('opens the create Sheet from grid mode and refreshes the card grid on success', async () => {
    const { client } = renderView()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    fireEvent.click(screen.getByRole('button', { name: /new project/i }))
    expect(await screen.findByText('stub-save')).toBeInTheDocument()

    fireEvent.click(screen.getByText('stub-save'))

    await waitFor(() => expect(screen.queryByText('stub-save')).not.toBeInTheDocument())
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['projects', 'cards'] })
    // The table isn't mounted in grid mode: no crash calling its ref.
    expect(tableRefreshMock).not.toHaveBeenCalled()
  })

  it('opens the create Sheet from table mode and refreshes the table on success', async () => {
    const { client } = renderView()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')
    switchToTable()

    fireEvent.click(screen.getByRole('button', { name: /new project/i }))
    expect(await screen.findByText('stub-save')).toBeInTheDocument()

    fireEvent.click(screen.getByText('stub-save'))

    await waitFor(() => expect(tableRefreshMock).toHaveBeenCalled())
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['projects', 'cards'] })
    expect(screen.queryByText('stub-save')).not.toBeInTheDocument()
  })

  it('closes the Sheet on cancel without refreshing anything', async () => {
    renderView()

    fireEvent.click(screen.getByRole('button', { name: /new project/i }))
    expect(await screen.findByText('stub-cancel')).toBeInTheDocument()

    fireEvent.click(screen.getByText('stub-cancel'))

    await waitFor(() => expect(screen.queryByText('stub-cancel')).not.toBeInTheDocument())
    expect(tableRefreshMock).not.toHaveBeenCalled()
  })
})
