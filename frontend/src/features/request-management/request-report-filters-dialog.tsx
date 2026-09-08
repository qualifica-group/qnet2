import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { SlidersHorizontal } from 'lucide-react'
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
  type RequestReportFormValues,
} from '@/features/request-management/request-report-schema'
import { useRequestReportCategories } from '@/features/request-management/use-request-report-categories'

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

export interface RequestReportFiltersDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Filters currently applied to the dashboard: every open starts from these. */
  value: RequestReportFormValues
  /** Lifts the edited filters back to the dashboard; BOTH actions apply them. */
  onApply: (values: RequestReportFormValues) => void
}

/**
 * The single filter surface of Gestione Richieste (user directive
 * 2026-09-08): the dashboard no longer shows its filters inline, it opens
 * this sheet — and the one selection it applies drives BOTH the charts and
 * the CSV, which `RequestDashboardFilterBar` generates from the applied
 * values. Editing filters and running the report are therefore two separate
 * affordances over one state, not two forms.
 *
 * Controlled: the applied filters live in `RequestDashboardPanel` (which
 * seeds them from the branch list and drives the aggregates query off them),
 * never here — a second copy of that state would let the charts and the sheet
 * disagree about what is selected.
 */
export function RequestReportFiltersDialog({
  open,
  onOpenChange,
  value,
  onApply,
}: RequestReportFiltersDialogProps) {
  const { t } = useTranslation()
  const schema = buildRequestReportSchema(t)
  const categoriesQuery = useRequestReportCategories(open)
  const categories = categoriesQuery.data

  const form = useForm<RequestReportFormValues>({
    resolver: zodResolver(schema),
    defaultValues: value,
  })

  // Follows the applied filters on the open transition and, while nothing has
  // been touched yet, on a later change of `value` too: the branch list can
  // land AFTER the sheet is already open, and an untouched form must then show
  // the seeded selection instead of the empty one it opened on. Once the user
  // edits, the form is theirs — `isDirty` stops the sync, so applying (which
  // lifts the values into the parent) never resets the sheet under their hands.
  const { isDirty } = form.formState
  const wasOpen = useRef(false)
  useEffect(() => {
    if (open && (!wasOpen.current || !isDirty)) {
      form.reset(value)
    }
    wasOpen.current = open
  }, [open, value, isDirty, form])

  const categoriesEmpty = isCategoriesEmpty(categories, categoriesQuery.isLoading, categoriesQuery.isError)
  const categoriesBlocked = categoriesAreBlocked(categoriesQuery.isLoading, categoriesQuery.isError, categoriesEmpty)

  const applyFilters = (values: RequestReportFormValues) => {
    onApply(values)
    onOpenChange(false)
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        className="gap-0"
        defaultWidth={REPORT_SHEET_DEFAULT_WIDTH}
        storageKey="sheet-width:request-management-report"
      >
        <div className={HEADER_BAND_CLASS}>
          <span aria-hidden="true" className={HEADER_ICON_CLASS}>
            <SlidersHorizontal className="size-4.5" />
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
            onSubmit={(event) => void form.handleSubmit(applyFilters)(event)}
          >
            <div className={FILTERS_GRID_CLASS}>
              <RequestReportFilters
                control={form.control}
                categories={categories}
                categoriesLoading={categoriesQuery.isLoading}
                categoriesError={categoriesQuery.isError}
                categoriesEmpty={categoriesEmpty}
                disabled={categoriesBlocked}
              />
            </div>
          </form>
        </Form>

        <div className="flex flex-wrap justify-end gap-2 border-t bg-gradient-to-t from-primary/[0.05] to-transparent px-4 py-3">
          <Button type="button" size="sm" variant="outline" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            type="submit"
            form="request-report-form"
            size="sm"
            disabled={categoriesBlocked}
            className="min-w-24 shadow-sm shadow-primary/20 transition-all hover:shadow-md hover:shadow-primary/25 motion-safe:active:translate-y-px"
          >
            {t('requestManagement.report.buttons.apply')}
          </Button>
        </div>
      </SheetContent>
    </Sheet>
  )
}
