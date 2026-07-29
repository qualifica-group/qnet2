import { useTranslation } from 'react-i18next'
import { Badge, type badgeVariants } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { BackgroundJobNotice } from '@/components/background-job-notice'
import type {
  MassMigrationRun,
  MigrationRun,
  MigrationRunStatus,
} from '@/features/migrations/types'
import type { VariantProps } from 'class-variance-authority'

type BadgeVariant = NonNullable<VariantProps<typeof badgeVariants>['variant']>

/** Per-source display state: the child run's status, or `not-run` when the chain stopped before reaching it. */
type SourceState = MigrationRunStatus | 'not-run'

const STATE_BADGE_VARIANT: Record<SourceState, BadgeVariant> = {
  pending: 'secondary',
  processing: 'secondary',
  completed: 'default',
  failed: 'destructive',
  'not-run': 'outline',
}

/** Statuses still running in the background (drives the indeterminate bar). */
const ACTIVE_STATUSES: ReadonlySet<MigrationRunStatus> = new Set(['pending', 'processing'])

/**
 * A source with no child run yet is either still queued (run active) or was
 * never reached because an earlier source failed and stopped the chain (run
 * already failed).
 */
function sourceState(child: MigrationRun | undefined, overall: MigrationRunStatus): SourceState {
  if (child) {
    return child.status
  }
  return overall === 'failed' ? 'not-run' : 'pending'
}

interface MassImportProgressProps {
  run: MassMigrationRun
  /** Called when the user dismisses a terminal (completed/failed) run. */
  onClose: () => void
  /** True once the poll gave up on this run (see `useMassMigration`). */
  isPollingStalled?: boolean
  onRetryPolling?: () => void
}

/**
 * Per-source progress for an "Import all" run (spec 0046): the aggregate status
 * bar plus one row per planned source with its child run's status and counters.
 * On a stop-on-failure the failing source reads as destructive and the sources
 * after it read as "not run". Purely presentational; the poll lives in
 * `useMassMigration`.
 */
export function MassImportProgress({
  run,
  onClose,
  isPollingStalled = false,
  onRetryPolling,
}: MassImportProgressProps) {
  const { t } = useTranslation('migrations')
  const isActive = ACTIVE_STATUSES.has(run.status)
  const isTerminal = run.status === 'completed' || run.status === 'failed'

  const childBySource = new Map(run.runs.map((child) => [child.source, child]))
  const sourceLabel = (source: string) => t(`sources.${source}`, { defaultValue: source })
  const stateLabel = (state: SourceState) =>
    state === 'not-run' ? t('massImport.sourceStatus.notRun') : t(`status.${state}`)

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        <Badge variant={STATE_BADGE_VARIANT[run.status]}>{t(`status.${run.status}`)}</Badge>
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

      <ul className="flex list-none flex-col gap-1.5">
        {run.sources.map((source) => {
          const child = childBySource.get(source)
          const state = sourceState(child, run.status)

          return (
            <li
              key={source}
              className="flex flex-col gap-1 rounded-md border px-2 py-1.5 text-sm"
            >
              <div className="flex items-center justify-between gap-2">
                <span className="min-w-0 truncate">{sourceLabel(source)}</span>
                <Badge variant={STATE_BADGE_VARIANT[state]}>{stateLabel(state)}</Badge>
              </div>
              {child ? (
                <span className="text-xs text-muted-foreground">
                  {t('massImport.perSourceCounts', {
                    created: child.created_rows,
                    skipped: child.skipped_rows,
                    failed: child.failed_rows,
                  })}
                </span>
              ) : null}
            </li>
          )
        })}
      </ul>

      {run.status === 'failed' ? (
        <p className="text-sm text-destructive" role="alert">
          {t('massImport.stopped')}
        </p>
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
