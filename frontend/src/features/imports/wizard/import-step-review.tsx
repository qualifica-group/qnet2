import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AlertTriangle, ListChecks } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { ReviewGrid } from '@/features/imports/wizard/review-grid'
import { ImportBackgroundNotice } from '@/features/imports/wizard/import-background-notice'
import { BusyState, StatTile, StepSectionHeader } from '@/features/imports/wizard/wizard-ui'
import type { ImportRunDetail, ImportRunRowCounts } from '@/features/imports/wizard/types'

export interface ImportStepReviewProps {
  domain: string
  run: ImportRunDetail | null
  onContinue: () => void
  /** True once the staging poll gave up (see `useImportWizard`). */
  isPollingStalled?: boolean
  onRetryPolling?: () => void
}

function countsFromRun(run: ImportRunDetail): ImportRunRowCounts {
  return {
    total: run.total_rows,
    valid_rows: run.valid_rows,
    warning_rows: run.warning_rows,
    error_rows: run.error_rows,
    duplicate_rows: run.duplicate_rows,
    modified_rows: run.modified_rows,
  }
}

/**
 * Review step (AC-023): an AG Grid SSRM grid (`ReviewGrid`) over the staged
 * rows of a `reviewing` run, with inline editing PATCHing each field back to
 * the server. The run stays static while this step is mounted (the wizard
 * only polls during `analyzing`/`staging`/`processing`, see
 * `use-import-wizard.ts`), so the header counters start from the run and are
 * then kept in sync purely from each edit's server-returned counts — no
 * effect needed to reconcile the two.
 */
export function ImportStepReview({
  domain,
  run,
  onContinue,
  isPollingStalled = false,
  onRetryPolling,
}: ImportStepReviewProps) {
  const { t } = useTranslation('importWizard')
  const [counts, setCounts] = useState<ImportRunRowCounts | null>(() => (run ? countsFromRun(run) : null))

  if (run === null) {
    return <BusyState label={t('review.loading')} />
  }

  if (run.status === 'staging') {
    return (
      <ImportBackgroundNotice
        label={t('status.staging')}
        isStalled={isPollingStalled}
        onRetry={onRetryPolling}
      />
    )
  }

  const activeCounts = counts ?? countsFromRun(run)
  const needsAttention = activeCounts.error_rows + activeCounts.warning_rows + activeCounts.duplicate_rows > 0

  return (
    <div className="flex flex-col gap-4">
      <StepSectionHeader
        icon={ListChecks}
        title={t('review.title')}
        description={t('review.hint')}
        aside={
          needsAttention ? (
            <span
              className="inline-flex items-center gap-1.5 rounded-full border border-amber-500/40 bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-400"
              role="alert"
            >
              <AlertTriangle className="size-3.5 shrink-0" aria-hidden="true" />
              {t('review.needsAttention')}
            </span>
          ) : null
        }
      />

        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <StatTile label={t('review.counts.total')} value={activeCounts.total} />
          <StatTile
            label={t('review.counts.valid')}
            value={activeCounts.valid_rows}
            tone={activeCounts.valid_rows > 0 ? 'success' : 'default'}
          />
          <StatTile
            label={t('review.counts.warning')}
            value={activeCounts.warning_rows}
            tone={activeCounts.warning_rows > 0 ? 'warning' : 'default'}
          />
          <StatTile
            label={t('review.counts.error')}
            value={activeCounts.error_rows}
            tone={activeCounts.error_rows > 0 ? 'destructive' : 'default'}
          />
          <StatTile
            label={t('review.counts.duplicate')}
            value={activeCounts.duplicate_rows}
            tone={activeCounts.duplicate_rows > 0 ? 'info' : 'default'}
          />
          <StatTile label={t('review.counts.modified')} value={activeCounts.modified_rows} />
        </div>

        <ReviewGrid domain={domain} run={run} onRowUpdated={(_row, nextCounts) => setCounts(nextCounts)} />

        <Separator />

        <div className="flex justify-end">
          <Button type="button" onClick={onContinue}>
            {t('review.continue')}
          </Button>
        </div>
    </div>
  )
}
