import { useTranslation } from 'react-i18next'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '@/features/auth/use-auth'
import { BootErrorScreen } from '@/components/boot-error-screen'
import { FullScreenLoader } from '@/components/full-screen-loader'
import { FirstAccessDialog } from '@/features/auth/first-access-dialog'

/**
 * Gate for authenticated routes. Redirects to /login when there is no session,
 * shows a loader while a persisted token is being validated (a Retry screen if
 * that validation timed out), and renders the first-access gate INSTEAD of the
 * app while a password change is pending (spec 0355 D-2), so no query of the
 * layout starts.
 */
export function ProtectedRoute() {
  const { t } = useTranslation()
  const { isAuthenticated, isInitializing, hasInitTimedOut, retryInit, user, impersonator } = useAuth()
  const location = useLocation()

  if (isInitializing) {
    return <FullScreenLoader />
  }

  if (hasInitTimedOut) {
    return <BootErrorScreen description={t('config.error.timeout')} onRetry={retryInit} />
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />
  }

  if (user?.must_set_password && !impersonator) {
    return <FirstAccessDialog />
  }

  return <Outlet />
}
