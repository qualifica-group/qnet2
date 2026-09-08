import { useTranslation } from 'react-i18next'
import { env } from '@/config/env'

/** Floor on how long the splash stays up, so a fast boot is not a flash. */
export const APP_SPLASH_MIN_DURATION_MS = 1800
/** Must match the app-splash-curtain animation in index.css. */
export const APP_SPLASH_EXIT_DURATION_MS = 650

const LOGO_ROTATE_ANIMATION = 'app-splash-logo-rotate 1.25s cubic-bezier(0.22, 1, 0.36, 1) forwards'
const LOGO_PARTIAL_REVEAL_ANIMATION = 'app-splash-logo-partial-reveal 0.35s ease-out 0.88s forwards'

/**
 * Full-screen brand splash covering the config-first bootstrap. `isExiting`
 * freezes the logo in its settled position and lifts the screen away.
 *
 * The white ground is deliberate and outside the surface scale: the brand marks
 * are fixed-colour assets (navy logo, white counters in the partial mark) and
 * would disappear on the dark theme background.
 */
export function AppSplashScreen({ isExiting = false }: { isExiting?: boolean }) {
  const { t } = useTranslation()

  return (
    <div
      role="status"
      aria-live="polite"
      className="fixed inset-0 z-[2000] flex min-h-svh items-center justify-center bg-white"
      style={{
        animation: isExiting
          ? `app-splash-curtain ${APP_SPLASH_EXIT_DURATION_MS}ms cubic-bezier(0.7, 0, 0.2, 1) forwards`
          : undefined,
      }}
    >
      <div className="flex items-center justify-center gap-0.5">
        <img
          alt={env.appName}
          className="h-24 w-auto"
          src="/brands/logo.svg"
          style={{
            animation: isExiting ? undefined : LOGO_ROTATE_ANIMATION,
            filter: 'drop-shadow(0 12px 14px rgb(0 0 0 / 0.08))',
            transformOrigin: 'center',
            transform: isExiting ? 'translateY(0) translateX(-10px) rotate(0deg)' : undefined,
          }}
        />
        <img
          alt=""
          aria-hidden="true"
          className="-ml-[20px] h-11 w-auto"
          src="/brands/logo_parziale.svg"
          style={{
            animation: isExiting ? undefined : LOGO_PARTIAL_REVEAL_ANIMATION,
            filter: 'drop-shadow(0 8px 12px rgb(0 0 0 / 0.07))',
            opacity: isExiting ? 1 : 0,
            transform: isExiting ? 'translateX(0)' : undefined,
          }}
        />
      </div>
      <span className="sr-only">{t('common.loading')}</span>
    </div>
  )
}
