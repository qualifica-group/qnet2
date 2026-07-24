import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import RewardedReferentsPage from '@/pages/rewarded-referents-page'

/**
 * Spec 0059 AC-025: the page mounts `TableView` on the `rewarded-referents`
 * domain and shows the permission fallback without it when the actor lacks
 * `rewarded-referents.viewAny`. `RewardedReferentsTable` (TableView + the
 * master/detail wiring) is covered by its own suite; here only the gate is
 * under test, so the table is stubbed.
 */

vi.mock('@/features/rewarded-referents/rewarded-referents-table', () => ({
  RewardedReferentsTable: () => <p>rewarded-referents-table-stub</p>,
}))

const canMock = vi.fn()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('RewardedReferentsPage', () => {
  it('shows the forbidden fallback and does not mount the table without rewarded-referents.viewAny', () => {
    canMock.mockReturnValue(false)

    render(<RewardedReferentsPage />)

    expect(screen.getByText("You don't have permission to view rewarded referents.")).toBeInTheDocument()
    expect(screen.queryByText('rewarded-referents-table-stub')).not.toBeInTheDocument()
  })

  it('mounts the table when rewarded-referents.viewAny is granted', () => {
    canMock.mockReturnValue(true)

    render(<RewardedReferentsPage />)

    expect(screen.getByText('rewarded-referents-table-stub')).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view rewarded referents."),
    ).not.toBeInTheDocument()
  })
})
