import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import AbsencesPage from '@/features/absences/absences-page'
import { absenceWrapper, buildRequest } from '@/features/absences/requests/absence-request-fixtures'

const permissions = new Set<string>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => permissions.has(permission), hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ title, actions }: { title?: string; actions?: React.ReactNode }) => (
    <header>
      <h1>{title}</h1>
      {actions}
    </header>
  ),
}))
vi.mock('@/features/absences/balances/absence-balances-card', () => ({
  AbsenceBalancesCard: () => <section aria-label="balances" />,
}))
vi.mock('@/features/absences/requests/absence-requests-grid', () => ({
  AbsenceRequestsGrid: ({ onOpen, scope, toApproveCount }: { onOpen: (id: number) => void; scope: string; toApproveCount: number }) => (
    <div>
      <p>{`scope:${scope} count:${toApproveCount}`}</p>
      <button type="button" onClick={() => onOpen(5)}>
        open-5
      </button>
    </div>
  ),
}))
vi.mock('@/features/absences/calendar/absence-calendar', () => ({
  AbsenceCalendar: ({ onOpen }: { onOpen: (id: number) => void }) => (
    <button type="button" onClick={() => onOpen(5)}>
      calendar-open-5
    </button>
  ),
}))
const summaryMock = vi.fn()
vi.mock('@/features/absences/api', () => ({
  getAbsenceSummary: () => summaryMock(),
  fetchAbsenceRequest: () => Promise.resolve(buildRequest()),
  fetchAbsenceTypeOptions: () => Promise.resolve([]),
  fetchAbsenceBalances: vi.fn(),
}))

const label = (key: string) => i18n.t(key)

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  permissions.clear()
  permissions.add('absence-requests.viewAny')
  summaryMock.mockResolvedValue({ to_approve_count: 0, my_pending_count: 0, daily_minutes: 480, my_upcoming: [], absent_today: [] })
})

describe('AbsencesPage', () => {
  it('shows the balances card above the grid and hides "New request" without the create permission', () => {
    render(<AbsencesPage />, { wrapper: absenceWrapper() })

    expect(screen.getByRole('region', { name: 'balances' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'open-5' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: label('absences.page.newRequest') })).not.toBeInTheDocument()
  })

  it('opens the create form from "New request" with the create permission', async () => {
    permissions.add('absence-requests.create')
    render(<AbsencesPage />, { wrapper: absenceWrapper() })

    fireEvent.click(screen.getByRole('button', { name: label('absences.page.newRequest') }))
    expect(await screen.findByRole('dialog', { name: label('absences.requests.form.createTitle') })).toBeInTheDocument()
  })

  it('opens the detail from the `?request=` deep link of the notifications', async () => {
    render(<AbsencesPage />, { wrapper: absenceWrapper('/absences?request=5') })

    expect(await screen.findByText('Anna Rossi')).toBeInTheDocument()
  })

  it('opens the detail when a grid row is opened', async () => {
    render(<AbsencesPage />, { wrapper: absenceWrapper() })

    fireEvent.click(screen.getByRole('button', { name: 'open-5' }))
    expect(await screen.findByText('Anna Rossi')).toBeInTheDocument()
  })

  it('shows the forbidden notice without viewAny', () => {
    permissions.clear()
    render(<AbsencesPage />, { wrapper: absenceWrapper() })

    expect(screen.getByText(label('absences.forbidden'))).toBeInTheDocument()
  })

  it('spec 0279: hides the Calendar view without any calendar permission', () => {
    render(<AbsencesPage />, { wrapper: absenceWrapper() })

    expect(screen.queryByRole('tab', { name: label('absences.page.views.calendar') })).not.toBeInTheDocument()
  })

  it('spec 0279: any calendar permission shows the Calendar view, which opens the detail', async () => {
    permissions.add('absence-requests.calendarOwn')
    render(<AbsencesPage />, { wrapper: absenceWrapper() })

    fireEvent.click(screen.getByRole('tab', { name: label('absences.page.views.calendar') }))
    expect(screen.queryByRole('region', { name: 'balances' })).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'calendar-open-5' }))
    expect(await screen.findByText('Anna Rossi')).toBeInTheDocument()
  })

  it('spec 0304: opens on "To approve" with the counter when the summary has requests to decide', async () => {
    summaryMock.mockResolvedValue({ to_approve_count: 3, my_pending_count: 0, daily_minutes: 480, my_upcoming: [], absent_today: [] })
    render(<AbsencesPage />, { wrapper: absenceWrapper() })

    expect(await screen.findByText('scope:to_approve count:3')).toBeInTheDocument()
  })

  it('spec 0304: the banner button moves the grid to "To approve"', async () => {
    summaryMock.mockResolvedValue({ to_approve_count: 2, my_pending_count: 0, daily_minutes: 480, my_upcoming: [], absent_today: [] })
    render(<AbsencesPage />, { wrapper: absenceWrapper() })

    // The first resolution already lands on the tab; the banner button keeps it there
    fireEvent.click(await screen.findByRole('button', { name: label('absences.summary.toApproveLink') }))
    expect(screen.getByText('scope:to_approve count:2')).toBeInTheDocument()
  })

  it('spec 0304: stays on "Mine" with nothing to approve and shows no banner', async () => {
    render(<AbsencesPage />, { wrapper: absenceWrapper() })

    expect(await screen.findByText(label('absences.summary.upcomingEmpty'))).toBeInTheDocument()
    expect(screen.getByText('scope:mine count:0')).toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })
})
