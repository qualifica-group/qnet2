import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { RequestDashboardToggle } from '@/features/request-management/request-dashboard-toggle'

/**
 * Spec 0107 AC-040: unlike every other module's `StatsToggleButton` (never
 * `<Can>`-gated because the whole page is already permission-gated), this
 * one IS gated by `request-management.report` (D-6).
 */

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    isLoading: false,
  }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
})

describe('RequestDashboardToggle (spec 0107 AC-040)', () => {
  it('is absent without request-management.report', () => {
    canMock.mockReturnValue(false)
    render(<RequestDashboardToggle domain="request-management" isOpen={false} onToggle={vi.fn()} />)

    expect(screen.queryByRole('button', { name: 'Statistics' })).not.toBeInTheDocument()
  })

  it('is offered with request-management.report, wired to the panel it drives', () => {
    canMock.mockReturnValue(true)
    render(<RequestDashboardToggle domain="request-management" isOpen onToggle={vi.fn()} />)

    const toggle = screen.getByRole('button', { name: 'Statistics' })
    expect(toggle).toHaveAttribute('aria-expanded', 'true')
    expect(toggle).toHaveAttribute('aria-controls', 'stats-panel-request-management')
  })
})
