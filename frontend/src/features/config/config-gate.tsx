import { useEffect, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { BootErrorScreen } from '@/components/boot-error-screen'
import {
  APP_SPLASH_EXIT_DURATION_MS,
  APP_SPLASH_MIN_DURATION_MS,
  AppSplashScreen,
} from '@/components/app-splash-screen'
import { useConfig } from '@/features/config/use-config'

/**
 * Boot gate enforcing the config-first bootstrap (ADR 0009): GET /api/config is
 * the first call and nothing downstream — in particular the AuthProvider's `me`
 * fetch — mounts until the config has loaded.
 *
 * - pending: brand splash, children withheld.
 * - error: full-screen message with a Retry button, children withheld.
 * - success: children mount immediately, but the splash stays until the minimum
 *   duration has elapsed and then lifts away, so a fast boot is not a flash and
 *   the app is already painted underneath when the curtain goes up.
 */
export function ConfigGate({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const { isError, isFetching, isSuccess, refetch } = useConfig()
  const [hasMinDurationElapsed, setHasMinDurationElapsed] = useState(false)
  const [hasCompletedExit, setHasCompletedExit] = useState(false)

  // Treat any non-success state as "still booting": withhold children until the
  // config is in the cache. Covers the initial pending load and keeps children
  // unmounted across a manual refetch triggered after an error.
  const isBooting = !isSuccess || !hasMinDurationElapsed

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setHasMinDurationElapsed(true)
    }, APP_SPLASH_MIN_DURATION_MS)

    return () => window.clearTimeout(timer)
  }, [])

  useEffect(() => {
    if (isBooting || hasCompletedExit) {
      return
    }

    const timer = window.setTimeout(() => {
      setHasCompletedExit(true)
    }, APP_SPLASH_EXIT_DURATION_MS)

    return () => window.clearTimeout(timer)
  }, [hasCompletedExit, isBooting])

  if (isError) {
    return (
      <BootErrorScreen
        description={t('config.error.description')}
        isRetrying={isFetching}
        onRetry={() => void refetch()}
      />
    )
  }

  if (isBooting) {
    return isSuccess ? (
      <>
        {children}
        <AppSplashScreen />
      </>
    ) : (
      <AppSplashScreen />
    )
  }

  if (!hasCompletedExit) {
    return (
      <>
        {children}
        <AppSplashScreen isExiting />
      </>
    )
  }

  return <>{children}</>
}
