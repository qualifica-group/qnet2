import { useTranslation } from 'react-i18next'
import { Badge, type badgeVariants } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { BackgroundJobNotice } from '@/components/background-job-notice'
import { ImportErrorReportLink } from '@/features/imports/import-error-report-link'
import type { ImportRun, ImportStatus } from '@/features/imports/types'
import type { VariantProps } from 'class-variance-authority'

type BadgeVariant = NonNullable<VariantProps<typeof badgeVariants>['variant']>

/** Badge tone per status; failed reads as destructive, completed as the positive default. */
const STATUS_BADGE_VARIANT: Record<ImportStatus, BadgeVariant> = {
  validating: 'secondary',
  awaiting_confirmation: 'secondary',
  processing: 'secondary',
  completed: 'default',
  failed: 'destructive',
}

/** Statuses still running in the background (drives the indeterminate bar). */
const ACTIVE_STATUSES: ReadonlySet<ImportStatus> = new Set(['validating', 'processing'])

interface ImportProgressProps {
  domain: string
  importRun: ImportRun
  /** Called when the user dismisses a terminal (completed/failed) run. */
  onClose: () => void
  /** True once the poll gave up on this run (see `useImport`). */
  isPollingStalled?: boolean
  onRetryPolling?: () => void
}

/**
 * Status view shown while `validating`/`processing` (polling in progress) and
 * on the terminal `completed`/`failed` outcome. Purely presentational: the
 * poll itself is driven by `useImport`.
 */
export function ImportProgress({
  domain,
  importRun,
  onClose,
  isPollingStalled = false,
  onRetryPolling,
}: ImportProgressProps) {
  const { t } = useTranslation()
  const isActive = ACTIVE_STATUSES.has(importRun.status)
  const isTerminal = importRun.status === 'completed' || importRun.status === 'failed'

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        <Badge variant={STATUS_BADGE_VARIANT[importRun.status]}>
          {t(`imports.status.${importRun.status}`)}
        </Badge>
        <span className="truncate text-sm text-muted-foreground">
          {importRun.original_filename}
        </span>
      </div>

      <div
        role="progressbar"
        aria-valuetext={t(`imports.status.${importRun.status}`)}
        className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
      >
        <div
          className={`h-full rounded-full ${
            importRun.status === 'failed' ? 'bg-destructive' : 'bg-primary'
          } ${isActive ? 'w-1/2 animate-pulse' : 'w-full'}`}
        />
      </div>

      {isActive ? (
        <BackgroundJobNotice
          keyPrefix="imports.background"
          isStalled={isPollingStalled}
          onRetry={onRetryPolling}
        />
      ) : null}

      {importRun.total_rows > 0 ? (
        <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
          <div>
            <dt className="text-muted-foreground">{t('imports.summary.total')}</dt>
            <dd className="font-medium">{importRun.total_rows}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">{t('imports.summary.valid')}</dt>
            <dd className="font-medium">{importRun.valid_rows}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">{t('imports.summary.invalid')}</dt>
            <dd className="font-medium">{importRun.invalid_rows}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">{t('imports.summary.imported')}</dt>
            <dd className="font-medium">{importRun.imported_rows ?? '—'}</dd>
          </div>
        </dl>
      ) : null}

      {importRun.status === 'failed' ? (
        <p className="text-sm text-destructive" role="alert">
          {t('imports.errors.jobFailed')}
        </p>
      ) : null}

      {importRun.has_error_report ? (
        <ImportErrorReportLink domain={domain} importRunId={importRun.id} />
      ) : null}

      {isTerminal ? (
        <div className="flex justify-end">
          <Button type="button" onClick={onClose}>
            {t('imports.buttons.close')}
          </Button>
        </div>
      ) : null}
    </div>
  )
}
