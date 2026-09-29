import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import i18n from '@/i18n'
import { ProtectedRoute } from '@/routes/protected-route'

/** Spec 0177 AC-018. */

interface AuthState {
  user: { must_set_password: boolean } | null
  impersonator: { id: number } | null
}
let authState: AuthState

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ ...authState, isAuthenticated: true, isInitializing: false }),
}))

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route element={<ProtectedRoute />}>
          <Route path="/first-access" element={<p>first access page</p>} />
          <Route path="/dashboard" element={<p>dashboard page</p>} />
          <Route path="/users" element={<p>users page</p>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  )
}

describe('ProtectedRoute first access', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('redirects to /first-access while the password must be set', () => {
    authState = { user: { must_set_password: true }, impersonator: null }
    renderAt('/users')

    expect(screen.getByText('first access page')).toBeInTheDocument()
  })

  it('renders the requested page when the flag is false', () => {
    authState = { user: { must_set_password: false }, impersonator: null }
    renderAt('/users')

    expect(screen.getByText('users page')).toBeInTheDocument()
  })

  it('does not redirect an impersonation session', () => {
    authState = { user: { must_set_password: true }, impersonator: { id: 1 } }
    renderAt('/users')

    expect(screen.getByText('users page')).toBeInTheDocument()
  })

  it('sends a user without the flag away from /first-access to the dashboard', () => {
    authState = { user: { must_set_password: false }, impersonator: null }
    renderAt('/first-access')

    expect(screen.getByText('dashboard page')).toBeInTheDocument()
  })
})
