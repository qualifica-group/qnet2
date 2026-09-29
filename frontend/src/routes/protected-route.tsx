import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '@/features/auth/use-auth'
import { FullScreenLoader } from '@/components/full-screen-loader'

const FIRST_ACCESS_PATH = '/first-access'

/**
 * Gate for authenticated routes. Redirects to /login when there is no session,
 * and shows a loader while a persisted token is being validated.
 */
export function ProtectedRoute() {
  const { user, impersonator, isAuthenticated, isInitializing } = useAuth()
  const location = useLocation()

  if (isInitializing) {
    return <FullScreenLoader />
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />
  }

  // Spec 0177: a pending first access confines the session to /first-access,
  // except while an admin impersonates (the server exempts those tokens too).
  const mustSetPassword = Boolean(user?.must_set_password) && !impersonator
  if (mustSetPassword && location.pathname !== FIRST_ACCESS_PATH) {
    return <Navigate to={FIRST_ACCESS_PATH} replace />
  }
  if (!mustSetPassword && location.pathname === FIRST_ACCESS_PATH) {
    return <Navigate to="/dashboard" replace />
  }

  return <Outlet />
}
