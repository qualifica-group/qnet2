import type { ReactNode } from 'react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TooltipProvider } from '@/components/ui/tooltip'
import { TimeEntriesDashboard } from '@/features/time-entries/time-entries-dashboard'
import type {
  OverviewStats,
  PulseStats,
  TeamPulse,
  TimeEntriesListMeta,
  TimeEntriesListParams,
  TimeEntriesListResponse,
} from '@/features/time-entries/types'

/**
 * Spec 0122 MT-F7: page assembly. `TimeEntriesPeriodCard`/`TimeEntriesViewSwitch`/
 * `TimeEntriesTeamPulse`/`TimeEntriesStatsPanels` are mounted for real (AC-037
 * needs the actual footer + team tree click-through); `TimeEntryCreateSheet`/
 * `TimeEntryEditSheet`/`TimeEntriesDayList` are shallow stubs so this suite
 * does not depend on `features/time-entries/{form,days}` internals owned by
 * other in-flight work.
 */

const fetchTimeEntriesMock = vi.fn<(params: TimeEntriesListParams) => Promise<TimeEntriesListResponse>>()
const fetchTimeEntriesOverviewMock = vi.fn()
const fetchTimeEntriesPulseMock = vi.fn()
const fetchTimeEntriesTeamMock = vi.fn()

vi.mock('@/features/time-entries/api', () => ({
  fetchTimeEntries: (params: TimeEntriesListParams) => fetchTimeEntriesMock(params),
  fetchTimeEntriesOverview: () => fetchTimeEntriesOverviewMock(),
  fetchTimeEntriesPulse: () => fetchTimeEntriesPulseMock(),
  fetchTimeEntriesTeam: () => fetchTimeEntriesTeamMock(),
  fetchFilteredTimeEntriesExport: vi.fn(),
  fetchMonthlyTimeEntriesExport: vi.fn(),
}))

// The real header renders router-bound breadcrumbs; only its actions slot matters here.
vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({
    user: { id: 1, name: 'Anna Ceo', email: 'anna@example.test', locale: 'en', roles: [], avatar_url: null },
    isAuthenticated: true,
    isInitializing: false,
    login: vi.fn(),
    logout: vi.fn(),
    impersonator: null,
    impersonate: vi.fn(),
    stopImpersonation: vi.fn(),
  }),
}))

interface CreateSheetStubProps {
  open: boolean
  defaultDate?: string
  userId?: number
}
interface EditSheetStubProps {
  open: boolean
  timeEntryId: number
}
interface DayListStubProps {
  canWrite: boolean
  selectedUserId?: number
  onEditEntry: (id: number) => void
  onCreateForDate: (date: string) => void
}

vi.mock('@/features/time-entries/form/time-entry-create-sheet', () => ({
  TimeEntryCreateSheet: ({ open, defaultDate, userId }: CreateSheetStubProps) =>
    open ? (
      <div data-testid="create-sheet" data-default-date={defaultDate ?? ''} data-user-id={userId ?? ''} />
    ) : null,
}))
vi.mock('@/features/time-entries/form/time-entry-edit-sheet', () => ({
  TimeEntryEditSheet: ({ open, timeEntryId }: EditSheetStubProps) =>
    open ? <div data-testid="edit-sheet" data-time-entry-id={timeEntryId} /> : null,
}))
vi.mock('@/features/time-entries/days/time-entries-day-list', () => ({
  TimeEntriesDayList: ({ canWrite, selectedUserId, onEditEntry, onCreateForDate }: DayListStubProps) => (
    <div data-testid="day-list" data-can-write={canWrite} data-selected-user-id={selectedUserId ?? ''}>
      <button type="button" onClick={() => onCreateForDate('2026-02-02')}>
        trigger-create-for-date
      </button>
      <button type="button" onClick={() => onEditEntry(42)}>
        trigger-edit
      </button>
    </div>
  ),
}))

function listResponse(meta: TimeEntriesListMeta): TimeEntriesListResponse {
  return {
    items: [],
    export_link: null,
    pagination: { total: 0, offset: 0, limit: 15, total_pages: 0 },
    meta,
  }
}

function selfMeta(overrides: Partial<TimeEntriesListMeta> = {}): TimeEntriesListMeta {
  return {
    selected_user: { id: 1, name: 'Anna Ceo' },
    daily_target_minutes: 480,
    can_write: true,
    can_filter_users: false,
    can_export: false,
    can_export_monthly: false,
    team: { can_view: true, is_full_list: false, members_count: 2 },
    ...overrides,
  }
}

const OVERVIEW: OverviewStats = {
  period: { date_from: '2026-01-01', date_to: '2026-01-07' },
  daily_target_minutes: 480,
  period_target_minutes: 2400,
  working_days: 5,
  tracked_minutes: 100,
  tracked_days: 2,
  average_daily_focus_minutes: 50,
  anomalies: { over_target_days: 0, under_target_days: 0 },
}
const PULSE: PulseStats = {
  coverage: { percentage: 10, working_days: 5, tracked_days: 2 },
  primary_cluster: null,
  other_clusters: [],
}
const TEAM: TeamPulse = {
  is_full_list: false,
  items: [
    {
      user: { id: 2, name: 'Bruno Manager', email: 'bruno@example.test', avatar_url: null },
      manager_ids: [1],
      job_description: null,
      roles: [],
      business_functions: [],
      operational_site: null,
      coverage: { percentage: 60, working_days: 5, tracked_days: 3 },
      primary_cluster: null,
    },
  ],
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  window.localStorage.clear()
  canMock.mockReset().mockReturnValue(true)
  fetchTimeEntriesMock.mockReset().mockResolvedValue(listResponse(selfMeta()))
  fetchTimeEntriesOverviewMock.mockReset().mockResolvedValue(OVERVIEW)
  fetchTimeEntriesPulseMock.mockReset().mockResolvedValue(PULSE)
  fetchTimeEntriesTeamMock.mockReset().mockResolvedValue(TEAM)
})

function renderDashboard() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <TooltipProvider>
        <TimeEntriesDashboard />
      </TooltipProvider>
    </QueryClientProvider>,
  )
}

describe('TimeEntriesDashboard — "Nuovo segnatempo" gating', () => {
  it('hides the button when meta.can_write is false', async () => {
    fetchTimeEntriesMock.mockResolvedValue(listResponse(selfMeta({ can_write: false })))
    renderDashboard()

    await screen.findByTestId('day-list')
    expect(screen.queryByRole('button', { name: /New time entry/ })).not.toBeInTheDocument()
  })

  it('shows the button and opens the create sheet when clicked', async () => {
    renderDashboard()

    const button = await screen.findByRole('button', { name: /New time entry/ })
    fireEvent.click(button)

    const sheet = await screen.findByTestId('create-sheet')
    expect(sheet).toHaveAttribute('data-default-date', '')
    expect(sheet).toHaveAttribute('data-user-id', '')
  })
})

describe('TimeEntriesDashboard — day list callbacks', () => {
  it('opens the create sheet with the day date from onCreateForDate', async () => {
    renderDashboard()

    fireEvent.click(await screen.findByText('trigger-create-for-date'))

    const sheet = await screen.findByTestId('create-sheet')
    expect(sheet).toHaveAttribute('data-default-date', '2026-02-02')
  })

  it('opens the edit sheet with the clicked entry id', async () => {
    renderDashboard()

    fireEvent.click(await screen.findByText('trigger-edit'))

    const sheet = await screen.findByTestId('edit-sheet')
    expect(sheet).toHaveAttribute('data-time-entry-id', '42')
  })
})

describe('TimeEntriesDashboard — view switch (AC-037)', () => {
  it('switches to the team tree, drills into a member and comes back', async () => {
    renderDashboard()

    // Personal is the default view: the day list is scoped to the actor.
    const dayList = await screen.findByTestId('day-list')
    expect(dayList).toHaveAttribute('data-selected-user-id', '1')

    fireEvent.click(await screen.findByRole('button', { name: /My team \(2\)/ }))

    // No member picked yet: the team tree replaces the day list.
    expect(screen.queryByTestId('day-list')).not.toBeInTheDocument()
    const memberRow = await screen.findByText('Bruno Manager')
    fireEvent.click(memberRow.closest('button') as HTMLButtonElement)

    // A member is now selected: the dashboard drills into their read-only data.
    await waitFor(() =>
      expect(fetchTimeEntriesMock).toHaveBeenCalledWith(expect.objectContaining({ user_id: 2 })),
    )
    expect(await screen.findByTestId('day-list')).toHaveAttribute('data-selected-user-id', '2')
    expect(screen.getAllByText('Bruno Manager').length).toBeGreaterThan(0)

    fireEvent.click(screen.getByRole('button', { name: 'Back to team list' }))

    expect(await screen.findByText('Bruno Manager')).toBeInTheDocument()
    expect(screen.queryByTestId('day-list')).not.toBeInTheDocument()
  })
})
