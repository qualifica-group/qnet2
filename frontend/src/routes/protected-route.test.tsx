import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import i18n from '@/i18n'
import { ProtectedRoute } from '@/routes/protected-route'

/** Spec 0355 AC-012: the first-access gate replaces the app (Outlet) while a password change is pending. */

interface AuthState {
  isAuthenticated: boolean
  isInitializing: boolean
  user: { name: string; must_set_password: boolean } | null
  impersonator: { id: number } | null
}
let authState: AuthState

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ ...authState, logout: vi.fn() }),
}))

vi.mock('@/features/auth/first-access-dialog', () => ({
  FirstAccessDialog: () => <div role="dialog" aria-label="first access gate" />,
}))

function renderRoute() {
  return render(
    <MemoryRouter initialEntries={['/app']}>
      <Routes>
        <Route element={<ProtectedRoute />}>
          <Route path="/app" element={<p>app layout</p>} />
        </Route>
        <Route path="/login" element={<p>login page</p>} />
      </Routes>
    </MemoryRouter>,
  )
}

function stateWith(overrides: Partial<AuthState>): AuthState {
  return {
    isAuthenticated: true,
    isInitializing: false,
    user: { name: 'Ada', must_set_password: false },
    impersonator: null,
    ...overrides,
  }
}

describe('ProtectedRoute first-access gate', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('renders the gate and not the layout when a password change is pending', () => {
    authState = stateWith({ user: { name: 'Ada', must_set_password: true } })
    renderRoute()

    expect(screen.getByRole('dialog', { name: 'first access gate' })).toBeInTheDocument()
    expect(screen.queryByText('app layout')).not.toBeInTheDocument()
  })

  it('renders the outlet when the flag is false', () => {
    authState = stateWith({})
    renderRoute()

    expect(screen.getByText('app layout')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('renders the outlet while impersonating, even with the flag set', () => {
    authState = stateWith({ user: { name: 'Ada', must_set_password: true }, impersonator: { id: 1 } })
    renderRoute()

    expect(screen.getByText('app layout')).toBeInTheDocument()
  })

  it('redirects to login without a session', () => {
    authState = stateWith({ isAuthenticated: false, user: null })
    renderRoute()

    expect(screen.getByText('login page')).toBeInTheDocument()
  })
})
