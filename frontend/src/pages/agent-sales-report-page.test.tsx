import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import AgentSalesReportPage from '@/pages/agent-sales-report-page'

/**
 * Spec 0365 AC-015: `/agent-sales-report` mounts the report grid for whoever
 * holds `agent-sales-report.view` and shows the access-denied message to
 * everyone else. The grid adapter has its own suite, so it is stubbed here.
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

vi.mock('@/features/agent-sales-report/agent-sales-report-table', () => ({
  AgentSalesReportTable: () => <section aria-label="report-grid-stub" />,
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
})

describe('AgentSalesReportPage (spec 0365)', () => {
  it('mounts the report grid for an actor with agent-sales-report.view', () => {
    canMock.mockImplementation((permission) => permission === 'agent-sales-report.view')
    render(<AgentSalesReportPage />)

    expect(screen.getByRole('region', { name: 'report-grid-stub' })).toBeInTheDocument()
  })

  it('shows the forbidden message, and no grid, without the permission', () => {
    canMock.mockReturnValue(false)
    render(<AgentSalesReportPage />)

    expect(screen.getByText('You do not have permission to view the agent sales report.')).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'report-grid-stub' })).not.toBeInTheDocument()
  })
})
