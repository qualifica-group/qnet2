import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import {
  CheckCircle2,
  ChevronDown,
  ChevronsDownUp,
  ChevronsUpDown,
  CircleAlert,
  FileDown,
  FileSpreadsheet,
  FileText,
  Loader2,
  SlidersHorizontal,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Can } from '@/features/auth/can'
import type { ExportFormat } from '@/features/exports/types'
import type {
  RequestReportCategory,
  RequestReportFilterPayload,
  RequestReportOperator,
  RequestReportSite,
} from '@/features/request-management/report-api'
import { RequestDashboardAppliedFilters } from '@/features/request-management/request-dashboard-applied-filters'
import type { RequestReportFormValues } from '@/features/request-management/request-report-schema'
import { useRequestReport } from '@/features/request-management/use-request-report'
import { cn } from '@/lib/utils'

/** Rung 2 under the page body, so the rung-3 (`bg-card`) buttons read as raised on it. */
const BAR_CLASS = 'flex flex-col gap-2 rounded-xl border bg-surface px-3 py-2'
const BAR_ROW_CLASS = 'flex flex-wrap items-start justify-between gap-2'

/** The formats the backend enables (`config('exports.formats')`) — the same two the table export offers. */
const REPORT_FORMATS: readonly ExportFormat[] = ['csv', 'xlsx']

const FORMAT_ICON: Record<ExportFormat, typeof FileText> = {
  csv: FileText,
  xlsx: FileSpreadsheet,
}

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
  /** The caller's `module.permission('report')` (spec 0130), same gate `RequestDashboardToggle` renders on. */
  reportPermission: string
  /** Filters currently applied to the charts, shown as chips. */
  filters: RequestReportFormValues
  /**
   * The SAME normalized payload the charts were fetched with (spec 0109 D-9):
   * the file must be generated from it, not from `filters`, or the two would
   * apply the operator selection differently.
   */
  payload: RequestReportFilterPayload
  /** The lists the report offers, so the chips can name what is picked. */
  categories: RequestReportCategory[]
  sites: RequestReportSite[]
  operators: RequestReportOperator[]
  /** False while the applied filters cannot drive a request (branch list not seeded yet). */
  filtersReady: boolean
  onEdit: () => void
  /** Every collapsible block of the loaded dashboard is expanded: the button then collapses. */
  allExpanded: boolean
  /** False while no section is rendered (loading, error, empty): there is nothing to fold. */
  canToggleExpanded: boolean
  onToggleExpanded: () => void
}

/**
 * Replaces the filter controls the dashboard used to render inline (user
 * directive 2026-09-08): the applied-filter chips of what the charts below show,
 * the report action, and the button that opens the sheet where the filters
 * are edited. The report runs on the APPLIED filters — the same values the charts
 * were built from, so the file and the screen can never disagree — through
 * the create -> poll -> download cycle of `useRequestReport`; the file lands
 * automatically once the run completes, there is no separate download step.
 * The format is picked from the action itself (user directive 2026-09-08),
 * not stored with the filters: it changes the file, never the charts.
 */
export function RequestDashboardFilterBar({
  reportPermission,
  filters,
  payload,
  categories,
  sites,
  operators,
  filtersReady,
  onEdit,
  allExpanded,
  canToggleExpanded,
  onToggleExpanded,
}: RequestDashboardFilterBarProps) {
  const { t } = useTranslation()
  const report = useRequestReport()
  const isBusy = !filtersReady || report.isCreating || report.isProcessing
  const expandLabel = allExpanded
    ? t('requestManagement.dashboard.collapseAll')
    : t('requestManagement.dashboard.expandAll')
  const ExpandIcon = allExpanded ? ChevronsDownUp : ChevronsUpDown

  return (
    <div className={BAR_CLASS}>
      <div className={BAR_ROW_CLASS}>
        <RequestDashboardAppliedFilters
          filters={filters}
          categories={categories}
          sites={sites}
          operators={operators}
        />

        <div className="flex shrink-0 items-center gap-2">
          {/* The permission ships unassigned by default, and the backend
              re-authorizes the three report routes regardless (spec 0106). */}
          <Can permission={reportPermission}>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button type="button" size="sm" variant="outline" disabled={isBusy}>
                  <FileDown aria-hidden="true" className="size-3.5" />
                  {report.isCreating || report.isProcessing
                    ? t('requestManagement.report.buttons.processing')
                    : t('requestManagement.report.action')}
                  <ChevronDown aria-hidden="true" className="size-3.5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                {REPORT_FORMATS.map((format) => {
                  const Icon = FORMAT_ICON[format]

                  return (
                    <DropdownMenuItem key={format} onSelect={() => report.create({ ...payload, format })}>
                      <Icon aria-hidden="true" />
                      {t(`exports.formats.${format}`)}
                    </DropdownMenuItem>
                  )
                })}
              </DropdownMenuContent>
            </DropdownMenu>
          </Can>

          {/* Icon-only like Filters: the label names the action it will perform next. */}
          <Button
            type="button"
            size="icon-sm"
            variant="outline"
            onClick={onToggleExpanded}
            disabled={!canToggleExpanded}
            aria-label={expandLabel}
            title={expandLabel}
          >
            <ExpandIcon aria-hidden="true" className="size-3.5" />
          </Button>

          {/* Icon-only (user directive 2026-09-22): the label stays as the accessible name and hover hint. */}
          <Button
            type="button"
            size="icon-sm"
            variant="outline"
            onClick={onEdit}
            aria-label={t('requestManagement.dashboard.editFilters')}
            title={t('requestManagement.dashboard.editFilters')}
          >
            <SlidersHorizontal aria-hidden="true" className="size-3.5" />
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
