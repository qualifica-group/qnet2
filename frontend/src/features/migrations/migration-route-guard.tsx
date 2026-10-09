import { Navigate, Outlet } from 'react-router-dom'
import { FullScreenLoader } from '@/components/full-screen-loader'
import { useAbilities } from '@/features/auth/use-abilities'

/**
 * Hard-gate role for the Migrations section (spec 0013), mirroring the
 * backend's `UserService::PRIVILEGED_ROLE`. The backend (`EnsureSuperAdmin`
 * middleware, every endpoint) remains the sole authorization boundary; this
 * guard only hides the route client-side (UX, not security).
 */
const PRIVILEGED_ROLE = 'super-admin'

/**
 * Route guard for `/dev/migrations`: renders the nested route only for a
 * super-admin, redirecting everyone else to the dashboard. Sits inside
 * `ProtectedRoute`, so a session is already guaranteed here.
 */
export function MigrationRouteGuard() {
  const { hasRole, isLoading } = useAbilities()

  if (isLoading) {
    return <FullScreenLoader />
  }

  if (!hasRole(PRIVILEGED_ROLE)) {
    return <Navigate to="/dashboard" replace />
  }

  return <Outlet />
}
