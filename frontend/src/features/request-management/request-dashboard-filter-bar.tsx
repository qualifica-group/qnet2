import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { CheckCircle2, CircleAlert, FileDown, Loader2, SlidersHorizontal } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import type { RequestReportFormValues } from '@/features/request-management/request-report-schema'
import { useRequestReport } from '@/features/request-management/use-request-report'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

/** Rung 2 under the page body, so the rung-3 (`bg-card`) buttons read as raised on it. */
const BAR_CLASS = 'flex flex-col gap-2 rounded-xl border bg-surface px-3 py-2'
const BAR_ROW_CLASS = 'flex flex-wrap items-center justify-between gap-2'

const STATUS_NOTE_CLASS = 'flex items-start gap-2 rounded-lg border px-3 py-2 text-xs'

type ReportStatusTone = 'progress' | 'success' | 'error'

const STATUS_TONE_CLASS: Record<ReportStatusTone, string> = {
  progress: 'bg-card text-muted-foreground',
  success: 'border-success/30 bg-success/10 text-foreground',
  error: 'border-destructive/30 bg-destructive/10 text-destructive',
}

/**
 * One run-state line (generating / done / failed). `error` keeps the
 * `role="alert"` the accessibility tests assert on; the two non-error tones
 * are polite status text and must NOT announce as alerts.
 */
function ReportStatusNote({ tone, children }: { tone: ReportStatusTone; children: ReactNode }) {
  const Icon = tone === 'progress' ? Loader2 : tone === 'success' ? CheckCircle2 : CircleAlert

  return (
    <p role={tone === 'error' ? 'alert' : undefined} className={cn(STATUS_NOTE_CLASS, STATUS_TONE_CLASS[tone])}>
      <Icon
        aria-hidden="true"
        className={cn('mt-px size-3.5 shrink-0', tone === 'progress' && 'motion-safe:animate-spin')}
      />
      <span>{children}</span>
    </p>
  )
}

export interface RequestDashboardFilterBarProps {
  /** Filters currently applied to the charts; the CSV is generated for these same values. */
  filters: RequestReportFormValues
  /** Branches the report offers in total; the summary reads "selected/total". */
  categoryCount: number
  /** False while the applied filters cannot drive a request (branch list not seeded yet). */
  filtersReady: boolean
  onEdit: () => void
}

/**
 * Replaces the filter controls the dashboard used to render inline (user
 * directive 2026-09-08): a read-only summary of what the charts below show,
 * the CSV action, and the button that opens the sheet where the filters are
 * edited. The report runs on the APPLIED filters — the same values the charts
 * were built from, so the file and the screen can never disagree — through
 * the create -> poll -> download cycle of `useRequestReport`; the file lands
 * automatically once the run completes, there is no separate download step.
 */
export function RequestDashboardFilterBar({
  filters,
  categoryCount,
  filtersReady,
  onEdit,
}: RequestDashboardFilterBarProps) {
  const { t } = useTranslation()
  const report = useRequestReport()
  const isBusy = !filtersReady || report.isCreating || report.isProcessing

  return (
    <div className={BAR_CLASS}>
      <div className={BAR_ROW_CLASS}>
        <p className="min-w-0 truncate text-xs text-muted-foreground">
          {t('requestManagement.dashboard.filtersSummary', {
            from: formatDate(filters.date_from),
            to: formatDate(filters.date_to),
            selected: filters.category_keys.length,
            total: categoryCount,
            rowMode: t(`requestManagement.report.rowModes.${filters.row_mode}`),
          })}
        </p>

        <div className="flex items-center gap-2">
          {/* The permission ships unassigned by default, and the backend
              re-authorizes the three report routes regardless (spec 0106). */}
          <Can permission="request-management.report">
            <Button
              type="button"
              size="sm"
              variant="secondary"
              className="gap-1.5"
              disabled={isBusy}
              onClick={() => report.create(filters)}
            >
              <FileDown aria-hidden="true" className="size-3.5" />
              {report.isCreating || report.isProcessing
                ? t('requestManagement.report.buttons.processing')
                : t('requestManagement.report.action')}
            </Button>
          </Can>

          <Button type="button" size="sm" variant="outline" onClick={onEdit}>
            <SlidersHorizontal aria-hidden="true" className="size-3.5" />
            {t('requestManagement.dashboard.editFilters')}
          </Button>
        </div>
      </div>

      {report.isProcessing ? (
        <ReportStatusNote tone="progress">{t('requestManagement.report.status.processing')}</ReportStatusNote>
      ) : null}

      {report.reportRun?.status === 'completed' ? (
        <ReportStatusNote tone="success">{t('requestManagement.report.status.completed')}</ReportStatusNote>
      ) : null}

      {report.reportRun?.status === 'failed' ? (
        <ReportStatusNote tone="error">{t('requestManagement.report.errors.jobFailed')}</ReportStatusNote>
      ) : null}

      {report.createError ? <ReportStatusNote tone="error">{report.createError}</ReportStatusNote> : null}

      {report.downloadError ? <ReportStatusNote tone="error">{report.downloadError}</ReportStatusNote> : null}
    </div>
  )
}
