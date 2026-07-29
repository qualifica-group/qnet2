import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { BusyState, StepAlert } from '@/features/imports/wizard/wizard-ui'

/** History page of the actor's own import runs, where a left run is resumed from. */
const IMPORTS_LIST_PATH = '/imports'

export interface ImportBackgroundNoticeProps {
  /** Caption of the running phase (e.g. "Analyzing the file…"). */
  label: string
  /**
   * Whether reaching the end of this phase sends a notification. Only the
   * final commit phase does (`ProcessStagedImportJob` ->
   * `ImportCompletedNotification`); the intermediate phases must not promise
   * a notification that never arrives.
   */
  notifiesOnCompletion?: boolean
  /** True once the poll gave up on this phase (see `useStallTimeout`). */
  isStalled?: boolean
  /** Resumes the poll after a stall; required whenever `isStalled` can be true. */
  onRetry?: () => void
  /** Extra indicator rendered under the label (e.g. the processing progress bar). */
  children?: ReactNode
}

/**
 * Busy state of an asynchronous import phase (analyzing, staging,
 * processing): every one of them runs in a queued job the wizard only polls,
 * so the user must be told the work continues server-side, that leaving is
 * safe, and — for the commit phase alone — that a notification closes it.
 * Once the phase overruns its window the poll stops and this turns into the
 * stalled notice: the run is NOT lost (the server owns it), the client just
 * stopped asking.
 */
export function ImportBackgroundNotice({
  label,
  notifiesOnCompletion = false,
  isStalled = false,
  onRetry,
  children,
}: ImportBackgroundNoticeProps) {
  const { t } = useTranslation('importWizard')

  if (isStalled) {
    // No live role on the wrapper: `StepAlert` is the live region here.
    return (
      <div className="flex flex-col items-center gap-3 py-10 text-center">
        <StepAlert tone="warning" role="status">
          {t('background.stalled')}
        </StepAlert>
        <div className="flex flex-wrap items-center justify-center gap-2">
          {onRetry ? (
            <Button type="button" variant="secondary" size="sm" onClick={onRetry}>
              {t('background.retry')}
            </Button>
          ) : null}
          <Button asChild variant="outline" size="sm" className="bg-card">
            <Link to={IMPORTS_LIST_PATH}>{t('background.goToList')}</Link>
          </Button>
        </div>
      </div>
    )
  }

  return (
    <BusyState label={label}>
      {children}
      <p className="max-w-sm text-xs text-muted-foreground">
        {notifiesOnCompletion ? t('background.hintNotified') : t('background.hint')}
      </p>
      <Button asChild variant="secondary" size="sm">
        <Link to={IMPORTS_LIST_PATH}>{t('background.goToList')}</Link>
      </Button>
    </BusyState>
  )
}
