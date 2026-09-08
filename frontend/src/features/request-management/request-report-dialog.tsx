import { useEffect, useRef, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { CheckCircle2, CircleAlert, FileDown, FileSpreadsheet, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { RequestReportFilters } from '@/features/request-management/request-report-filters'
import {
  buildRequestReportSchema,
  categoriesAreBlocked,
  isCategoriesEmpty,
  requestReportDefaultValues,
  type RequestReportFormValues,
} from '@/features/request-management/request-report-schema'
import { useRequestReport } from '@/features/request-management/use-request-report'
import { useRequestReportCategories } from '@/features/request-management/use-request-report-categories'
import { cn } from '@/lib/utils'

/** Narrow form: the sheet opens at this width until the user resizes it (mirrors `ExportDialog`). */
const REPORT_SHEET_DEFAULT_WIDTH = 440

/** Brand-tinted header strip with the icon chip, same band as `AssignManagerGa3Dialog`. */
const HEADER_BAND_CLASS =
  'flex items-start gap-3 border-b bg-gradient-to-br from-card to-primary/[0.06] px-4 pt-4 pr-12 pb-3.5'
const HEADER_ICON_CLASS =
  'flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15'

/**
 * The two date fields share one row; every later group (branch card,
 * row-mode picker, inline notices) spans the full width. Applied here and
 * not inside `RequestReportFilters`, which stays layout-agnostic so the
 * dashboard can keep laying the same controls out as its own four columns.
 */
const FILTERS_GRID_CLASS = 'grid grid-cols-2 gap-x-3 gap-y-4 [&>*:nth-child(n+3)]:col-span-2'

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

export interface RequestReportDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * "Report CSV" dialog (spec 0106, rev-2). On confirm it runs the whole
 * create -> poll -> download cycle (`useRequestReport`) for the chosen date
 * range, branches and row selection; the file lands automatically once the
 * run completes — there is no separate "download" step for the user. The
 * filter controls themselves live in `RequestReportFilters` (spec 0107 D-4),
 * shared verbatim with the dashboard's filter bar.
 */
export function RequestReportDialog({ open, onOpenChange }: RequestReportDialogProps) {
  const { t } = useTranslation()
  const schema = buildRequestReportSchema(t)
  const report = useRequestReport()
  const categoriesQuery = useRequestReportCategories(open)
  const categories = categoriesQuery.data

  const form = useForm<RequestReportFormValues>({
    resolver: zodResolver(schema),
    defaultValues: requestReportDefaultValues(),
  })

  // Seeds the checkbox group with every branch once the list lands (AC-050),
  // exactly once per open — a background refetch must never silently wipe
  // the user's own picks mid-form. React Query v5 dropped `onSuccess` from
  // `useQuery`, so reacting to this one settle is the same one-shot,
  // ref-guarded pattern already used for the auto-download in `useRequestReport`.
  const seededFor = useRef(false)
  useEffect(() => {
    if (categories && categories.length > 0 && !seededFor.current) {
      seededFor.current = true
      form.reset({ ...form.getValues(), category_keys: categories.map((category) => category.key) })
    }
  }, [categories, form])

  const categoriesEmpty = isCategoriesEmpty(categories, categoriesQuery.isLoading, categoriesQuery.isError)
  const categoriesBlocked = categoriesAreBlocked(categoriesQuery.isLoading, categoriesQuery.isError, categoriesEmpty)
  // Disabled for every leg that must block a second confirm click: the
  // branch list still loading/unusable (AC-049/AC-053), create in flight, or
  // the run still processing (AC-045-ter).
  const isBusy = report.isCreating || report.isProcessing || categoriesBlocked

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      report.reset()
      seededFor.current = false
      form.reset(requestReportDefaultValues())
    }
    onOpenChange(next)
  }

  const onSubmit = (values: RequestReportFormValues) => {
    report.create(values)
  }

  return (
    <Sheet open={open} onOpenChange={handleOpenChange}>
      <SheetContent
        className="gap-0"
        defaultWidth={REPORT_SHEET_DEFAULT_WIDTH}
        storageKey="sheet-width:request-management-report"
      >
        <div className={HEADER_BAND_CLASS}>
          <span aria-hidden="true" className={HEADER_ICON_CLASS}>
            <FileSpreadsheet className="size-4.5" />
          </span>
          <SheetHeader className="flex-1 gap-1 p-0">
            <SheetTitle className="text-sm">{t('requestManagement.report.title')}</SheetTitle>
            <SheetDescription className="text-xs">{t('requestManagement.report.description')}</SheetDescription>
          </SheetHeader>
        </div>

        <Form {...form}>
          <form
            id="request-report-form"
            className="flex flex-1 flex-col gap-4 overflow-y-auto bg-surface p-4"
            onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
          >
            <div className={FILTERS_GRID_CLASS}>
              <RequestReportFilters
                control={form.control}
                categories={categories}
                categoriesLoading={categoriesQuery.isLoading}
                categoriesError={categoriesQuery.isError}
                categoriesEmpty={categoriesEmpty}
                disabled={isBusy}
              />
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
          </form>
        </Form>

        <div className="flex justify-end gap-2 border-t bg-gradient-to-t from-primary/[0.05] to-transparent px-4 py-3">
          <Button type="button" size="sm" variant="outline" onClick={() => handleOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="submit"
            form="request-report-form"
            size="sm"
            disabled={isBusy}
            className="min-w-32 gap-1.5 shadow-sm shadow-primary/20 transition-all hover:shadow-md hover:shadow-primary/25 motion-safe:active:translate-y-px"
          >
            <FileDown aria-hidden="true" className="size-3.5" />
            {report.isCreating || report.isProcessing
              ? t('requestManagement.report.buttons.processing')
              : t('requestManagement.report.buttons.confirm')}
          </Button>
        </div>
      </SheetContent>
    </Sheet>
  )
}
