import { useTranslation } from 'react-i18next'
import { AlertTriangle } from 'lucide-react'
import { Button } from '@/components/ui/button'

export interface BackgroundJobNoticeProps {
  /** i18next namespace holding the copy; omit for the default namespace. */
  namespace?: string
  /**
   * Key prefix under which `hint`, `stalled` and `retry` live (e.g.
   * `imports.background`).
   */
  keyPrefix: string
  /** True once the caller's poll gave up on the run (see `useStallTimeout`). */
  isStalled: boolean
  /** Resumes the poll after a stall; required whenever `isStalled` can be true. */
  onRetry?: () => void
}

/**
 * Line shown inside a dialog while a queued job runs: says the work continues
 * server-side (closing the dialog does not cancel it) and, once the poll gave
 * up, that the client simply stopped asking — with a way to ask again. Used
 * by every dialog-hosted polling flow (generic import, migrations); the
 * full-page import wizard has its own richer `ImportBackgroundNotice`.
 */
export function BackgroundJobNotice({ namespace, keyPrefix, isStalled, onRetry }: BackgroundJobNoticeProps) {
  const { t } = useTranslation(namespace, { keyPrefix })

  if (!isStalled) {
    return (
      <p className="text-xs text-muted-foreground" role="status">
        {t('hint')}
      </p>
    )
  }

  return (
    <div
      role="status"
      className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2.5 text-sm text-amber-700 dark:text-amber-400"
    >
      <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      <div className="flex min-w-0 flex-col items-start gap-2">
        <span>{t('stalled')}</span>
        {onRetry ? (
          <Button type="button" variant="secondary" size="sm" onClick={onRetry}>
            {t('retry')}
          </Button>
        ) : null}
      </div>
    </div>
  )
}
