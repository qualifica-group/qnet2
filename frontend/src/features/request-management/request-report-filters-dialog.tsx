import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FILTERS_SHEET_BODY_CLASS, FiltersSheet, FiltersSheetFooter } from '@/components/ui/filters-sheet'
import { Form } from '@/components/ui/form'
import { RequestReportFilters } from '@/features/request-management/request-report-filters'
import {
  buildRequestReportSchema,
  categoriesAreBlocked,
  isCategoriesEmpty,
  requestReportDefaultValues,
  type RequestReportFormValues,
} from '@/features/request-management/request-report-schema'
import { useRequestReportCategories } from '@/features/request-management/use-request-report-categories'
import { useRequestReportOperators } from '@/features/request-management/use-request-report-operators'
import { useRequestReportSites } from '@/features/request-management/use-request-report-sites'

/** Narrow form: the sheet opens at this width until the user resizes it (mirrors `ExportDialog`). */
const REPORT_SHEET_DEFAULT_WIDTH = 440

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
  const categoriesQuery = useRequestReportCategories(open)
  const categories = categoriesQuery.data
  const operatorsQuery = useRequestReportOperators(open)
  const operators = operatorsQuery.data
  const sitesQuery = useRequestReportSites(open)
  const sites = sitesQuery.data
  // Each picker's own list is what makes "at least one operator"/"at least one
  // site" a real rule (spec 0109, spec 0112): with nothing on offer there is
  // nothing to require.
  const schema = buildRequestReportSchema(
    t,
    (operators ?? []).map((operator) => operator.key),
    (sites ?? []).map((site) => site.key),
  )

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

  // Same seeding as a first visit (today, every branch/operator/site),
  // kept in the DRAFT: nothing reaches the charts until "Applica".
  // `keepDefaultValues` keeps `isDirty` measured against the applied filters,
  // so the open-sync effect above cannot overwrite the reset.
  const resetFilters = () => {
    form.reset(
      requestReportDefaultValues(
        (categories ?? []).map((category) => category.key),
        (operators ?? []).map((operator) => operator.key),
        (sites ?? []).map((site) => site.key),
      ),
      { keepDefaultValues: true },
    )
  }

  return (
    <FiltersSheet
      open={open}
      onOpenChange={onOpenChange}
      title={t('requestManagement.report.title')}
      description={t('requestManagement.report.description')}
      defaultWidth={REPORT_SHEET_DEFAULT_WIDTH}
      storageKey="sheet-width:request-management-report"
    >
      <Form {...form}>
        <form
          id="request-report-form"
          className={FILTERS_SHEET_BODY_CLASS}
          onSubmit={(event) => void form.handleSubmit(applyFilters)(event)}
        >
          <div className={FILTERS_GRID_CLASS}>
            <RequestReportFilters
              control={form.control}
              categories={categories}
              categoriesLoading={categoriesQuery.isLoading}
              categoriesError={categoriesQuery.isError}
              categoriesEmpty={categoriesEmpty}
              operators={operators}
              operatorsLoading={operatorsQuery.isLoading}
              operatorsError={operatorsQuery.isError}
              sites={sites}
              sitesLoading={sitesQuery.isLoading}
              sitesError={sitesQuery.isError}
              disabled={categoriesBlocked}
            />
          </div>
        </form>
      </Form>

      <FiltersSheetFooter
        formId="request-report-form"
        labels={{
          reset: t('requestManagement.report.buttons.reset'),
          cancel: t('common.cancel'),
          apply: t('requestManagement.report.buttons.apply'),
        }}
        onReset={resetFilters}
        onCancel={() => onOpenChange(false)}
        disabled={categoriesBlocked}
      />
    </FiltersSheet>
  )
}
