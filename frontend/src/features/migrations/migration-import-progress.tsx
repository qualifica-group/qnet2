import { useTranslation } from 'react-i18next'
import { Badge, type badgeVariants } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { BackgroundJobNotice } from '@/components/background-job-notice'
import type { MigrationRun, MigrationRunStatus } from '@/features/migrations/types'
import type { VariantProps } from 'class-variance-authority'

type BadgeVariant = NonNullable<VariantProps<typeof badgeVariants>['variant']>

/** Badge tone per status; failed reads as destructive, completed as the positive default. */
const STATUS_BADGE_VARIANT: Record<MigrationRunStatus, BadgeVariant> = {
  pending: 'secondary',
  processing: 'secondary',
  completed: 'default',
  failed: 'destructive',
}

/** Statuses still running in the background (drives the indeterminate bar). */
const ACTIVE_STATUSES: ReadonlySet<MigrationRunStatus> = new Set(['pending', 'processing'])

interface MigrationImportProgressProps {
  run: MigrationRun
  /** Called when the user dismisses a terminal (completed/failed) run. */
  onClose: () => void
  /** True once the poll gave up on this run (see `useMigrationImport`). */
  isPollingStalled?: boolean
  onRetryPolling?: () => void
}

/**
 * Status view shown while `pending`/`processing` (polling in progress) and on
 * the terminal `completed`/`failed` outcome: progress indicator, running
 * created/skipped/failed summary, and the report's warnings/errors once the
 * run finishes. Purely presentational: the poll itself is driven by
 * `useMigrationImport`.
 */
export function MigrationImportProgress({
  run,
  onClose,
  isPollingStalled = false,
  onRetryPolling,
}: MigrationImportProgressProps) {
  const { t } = useTranslation('migrations')
  const isActive = ACTIVE_STATUSES.has(run.status)
  const isTerminal = run.status === 'completed' || run.status === 'failed'

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        <Badge variant={STATUS_BADGE_VARIANT[run.status]}>
          {t(`status.${run.status}`)}
        </Badge>
      </div>

      <div
        role="progressbar"
        aria-valuetext={t(`status.${run.status}`)}
        className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
      >
        <div
          className={`h-full rounded-full ${
            run.status === 'failed' ? 'bg-destructive' : 'bg-primary'
          } ${isActive ? 'w-1/2 animate-pulse' : 'w-full'}`}
        />
      </div>

      {isActive ? (
        <BackgroundJobNotice
          namespace="migrations"
          keyPrefix="background"
          isStalled={isPollingStalled}
          onRetry={onRetryPolling}
        />
      ) : null}

      <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        <div>
          <dt className="text-muted-foreground">{t('import.summary.total')}</dt>
          <dd className="font-medium">{run.total_rows}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{t('import.summary.created')}</dt>
          <dd className="font-medium">{run.created_rows}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{t('import.summary.skipped')}</dt>
          <dd className="font-medium">{run.skipped_rows}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{t('import.summary.failed')}</dt>
          <dd className="font-medium">{run.failed_rows}</dd>
        </div>
      </dl>

      {run.status === 'failed' ? (
        <p className="text-sm text-destructive" role="alert">
          {t('errors.jobFailed')}
        </p>
      ) : null}

      {run.report && run.report.length > 0 ? (
        <section className="flex flex-col gap-2">
          <h3 className="text-sm font-semibold">{t('import.reportTitle')}</h3>
          <ul className="max-h-48 list-none overflow-auto rounded-md border text-sm">
            {run.report.map((entry, index) => (
              // The report carries no stable id; index is safe here (static list).
              <li
                key={index}
                className="flex items-start gap-2 border-t px-3 py-2 first:border-t-0"
              >
                <Badge variant={entry.level === 'error' ? 'destructive' : 'secondary'}>
                  {t(`import.reportLevel.${entry.level}`)}
                </Badge>
                <span className="flex-1">{entry.message}</span>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {isTerminal ? (
        <div className="flex justify-end">
          <Button type="button" onClick={onClose}>
            {t('import.close')}
          </Button>
        </div>
      ) : null}
    </div>
  )
}
