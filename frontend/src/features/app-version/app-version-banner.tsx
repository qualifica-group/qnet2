import { RefreshCw } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { env } from '@/config/env'
import { useAppVersion } from '@/features/app-version/use-app-version'

/**
 * Persistent notice shown from the moment a newer build is detected until the
 * user reloads onto it. Deliberately not dismissible: the whole point is that
 * nobody keeps operating on a stale client after a deploy. It sits above the
 * app header, in flow rather than fixed, so it never overlaps the chrome.
 *
 * Enabled only in a production bundle — `vite dev` and Vitest have no deployed
 * build to compare against.
 */
export function AppVersionBanner() {
  const { t } = useTranslation()
  const { isOutdated } = useAppVersion(env.isProductionBuild)

  if (!isOutdated) {
    return null
  }

  return (
    <div
      role="status"
      className="flex items-center justify-between gap-2 border-b border-primary/40 bg-primary/10 px-4 py-1 text-xs text-foreground"
    >
      <span className="flex min-w-0 items-center gap-1.5">
        <RefreshCw className="size-3.5 shrink-0 text-primary" aria-hidden="true" />
        <span className="truncate">{t('appVersion.available')}</span>
      </span>
      <Button
        type="button"
        variant="secondary"
        size="xs"
        onClick={() => window.location.reload()}
      >
        {t('appVersion.reload')}
      </Button>
    </div>
  )
}
