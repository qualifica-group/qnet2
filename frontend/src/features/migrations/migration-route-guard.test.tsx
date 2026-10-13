import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { MigrationRouteGuard } from '@/features/migrations/migration-route-guard'

/**
 * Spec 0013 AC-022: the `/dev/migrations` route renders only for a super-admin;
 * every other authenticated user is redirected to the dashboard. This is the
 * client-side UX gate only -- the backend `EnsureSuperAdmin` middleware
 * remains the actual authorization boundary (every request re-checks it).
 */

const hasRoleMock = vi.fn()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: () => false,
    hasRole: (role: string) => hasRoleMock(role),
    roles: [],
    isLoading: false,
  }),
}))

function renderAt(path: string) {
  render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route element={<MigrationRouteGuard />}>
          <Route path="/dev/migrations" element={<div>Migrations page</div>} />
        </Route>
        <Route path="/dashboard" element={<div>Dashboard page</div>} />
      </Routes>
    </MemoryRouter>,
  )
}

describe('MigrationRouteGuard', () => {
  it('renders the migrations route for a super-admin', () => {
    hasRoleMock.mockImplementation((role: string) => role === 'super-admin')

    renderAt('/dev/migrations')

    expect(screen.getByText('Migrations page')).toBeInTheDocument()
  })

  it('redirects a non super-admin to the dashboard', () => {
    hasRoleMock.mockReturnValue(false)

    renderAt('/dev/migrations')

    expect(screen.getByText('Dashboard page')).toBeInTheDocument()
    expect(screen.queryByText('Migrations page')).not.toBeInTheDocument()
  })
})
