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
  AbsenceRequestsGrid: ({ onOpen }: { onOpen: (id: number) => void }) => (
    <button type="button" onClick={() => onOpen(5)}>
      open-5
    </button>
  ),
}))
vi.mock('@/features/absences/api', () => ({
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
    expect(await screen.findByText(label('absences.requests.form.createTitle'))).toBeInTheDocument()
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
})
