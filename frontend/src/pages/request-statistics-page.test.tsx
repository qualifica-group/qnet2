import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import RequestStatisticsPage from '@/pages/request-statistics-page'

/**
 * Spec 0185 AC-009: `/request-statistics` shows the dashboard to whoever holds
 * `request-statistics.view`, and an access-denied message to everyone else.
 * The dashboard itself has its own suite, so it is stubbed to a labelled region.
 */

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

vi.mock('@/features/request-management/request-dashboard', () => ({
  RequestDashboard: () => <section aria-label="dashboard-stub" />,
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
})

describe('RequestStatisticsPage (spec 0185)', () => {
  it('renders the dashboard region for an actor with request-statistics.view', () => {
    canMock.mockImplementation((permission) => permission === 'request-statistics.view')
    render(<RequestStatisticsPage />)

    expect(screen.getByRole('region', { name: 'dashboard-stub' })).toBeInTheDocument()
  })

  it('renders the forbidden message, and no dashboard, without the permission', () => {
    canMock.mockReturnValue(false)
    render(<RequestStatisticsPage />)

    expect(
      screen.getByText("You don't have permission to view Request Management Statistics."),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'dashboard-stub' })).not.toBeInTheDocument()
  })
})
