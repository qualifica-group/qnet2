import type { ReactNode } from 'react'
import { type Control, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { CircleAlert, Loader2 } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { RequestReportKeyGroup } from '@/features/request-management/request-report-key-group'
import {
  ROW_MODES,
  type RequestReportFormValues,
} from '@/features/request-management/request-report-schema'
import type { RequestReportCategory, RequestReportOperator } from '@/features/request-management/report-api'
import { cn } from '@/lib/utils'

/** Compact control label, shared by every group (ui-design.md §2: smaller end of the scale). */
const FIELD_LABEL_CLASS = 'text-xs font-medium'

/** Row mode: one segmented control (track + raised active segment), not three stacked buttons. */
const ROW_MODE_TRACK_CLASS = 'grid grid-cols-3 gap-1 rounded-lg border border-field-border bg-muted/40 p-1'
const ROW_MODE_OPTION_CLASS =
  'truncate rounded-md px-2 py-1.5 text-center text-xs font-medium outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60'
const ROW_MODE_OPTION_SELECTED_CLASS = 'bg-card text-foreground shadow-xs ring-1 ring-border'
const ROW_MODE_OPTION_IDLE_CLASS = 'text-muted-foreground hover:text-foreground'

/** Inline notices (list loading / unusable), one grid cell each like every other group. */
const NOTICE_CLASS = 'flex items-center gap-2 text-xs text-muted-foreground'
const NOTICE_ERROR_CLASS =
  'flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive'

function LoadingNotice({ children }: { children: ReactNode }) {
  return (
    <p className={NOTICE_CLASS}>
      <Loader2 aria-hidden="true" className="size-3.5 shrink-0 motion-safe:animate-spin" />
      {children}
    </p>
  )
}

function ErrorNotice({ children }: { children: ReactNode }) {
  return (
    <p className={NOTICE_ERROR_CLASS} role="alert">
      <CircleAlert aria-hidden="true" className="mt-px size-3.5 shrink-0" />
      {children}
    </p>
  )
}

export interface RequestReportFiltersProps {
  control: Control<RequestReportFormValues>
  categories: RequestReportCategory[] | undefined
  categoriesLoading: boolean
  categoriesError: boolean
  categoriesEmpty: boolean
  /** The GA2 the actor may filter by (spec 0108); undefined while loading. */
  operators: RequestReportOperator[] | undefined
  operatorsLoading: boolean
  operatorsError: boolean
  /** Disables every field: report creation/processing in the dialog, or the branch list unusable. */
  disabled: boolean
}

/**
 * Filter controls of specs 0106/0107/0108 (rev-2 D-4, 0107 D-4): date range,
 * branch group, GA2 operator group, and the row-mode choice. One selection
 * now drives both consumers — the charts and the CSV (user directive
 * 2026-09-08) — so `RequestReportDialog` is its only host; the file stays
 * split off it purely for size.
 *
 * The operator group is mounted only for the row modes that actually emit
 * operator rows, `operators_only` and `all` (spec 0108, D-4): under
 * `total_only` there are no operator rows to narrow, so the control would
 * promise something the output cannot show. The selection itself is NOT
 * cleared when it hides — switching back restores it (AC-045).
 *
 * Presentational only: the caller owns the `useForm()` instance, both list
 * fetches, and must render this inside its own `<Form {...form}>` (the
 * `FormField`s below read RHF context from it). Renders a FLAT fragment of
 * one element per group — the host lays them out as cells of its own grid, so
 * nothing here may wrap them in a container.
 */
export function RequestReportFilters({
  control,
  categories,
  categoriesLoading,
  categoriesError,
  categoriesEmpty,
  operators,
  operatorsLoading,
  operatorsError,
  disabled,
}: RequestReportFiltersProps) {
  const { t } = useTranslation()
  const rowMode = useWatch({ control, name: 'row_mode' })
  const showOperators = rowMode !== 'total_only'

  return (
    <>
      <FormField
        control={control}
        name="date_from"
        render={({ field }) => (
          <FormItem className="gap-1.5">
            <FormLabel required className={FIELD_LABEL_CLASS}>
              {t('requestManagement.report.fields.dateFrom')}
            </FormLabel>
            <FormControl>
              <Input type="date" disabled={disabled} {...field} />
            </FormControl>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />

      <FormField
        control={control}
        name="date_to"
        render={({ field }) => (
          <FormItem className="gap-1.5">
            <FormLabel required className={FIELD_LABEL_CLASS}>
              {t('requestManagement.report.fields.dateTo')}
            </FormLabel>
            <FormControl>
              <Input type="date" disabled={disabled} {...field} />
            </FormControl>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />

      {categoriesLoading ? (
        <LoadingNotice>{t('requestManagement.report.status.loadingCategories')}</LoadingNotice>
      ) : null}

      {categoriesError ? (
        <ErrorNotice>{t('requestManagement.report.errors.categoriesLoadFailed')}</ErrorNotice>
      ) : null}

      {categoriesEmpty ? <ErrorNotice>{t('requestManagement.report.errors.categoriesEmpty')}</ErrorNotice> : null}

      {categories && categories.length > 0 ? (
        <FormField
          control={control}
          name="category_keys"
          render={({ field }) => (
            <FormItem className="gap-1.5">
              <FormControl>
                <RequestReportKeyGroup
                  label={t('requestManagement.report.fields.categories')}
                  selectAllLabel={t('requestManagement.report.fields.selectAllCategories')}
                  options={categories}
                  value={field.value}
                  onChange={field.onChange}
                  disabled={disabled}
                />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
      ) : null}

      {showOperators && operatorsLoading ? (
        <LoadingNotice>{t('requestManagement.report.status.loadingOperators')}</LoadingNotice>
      ) : null}

      {showOperators && operatorsError ? (
        <ErrorNotice>{t('requestManagement.report.errors.operatorsLoadFailed')}</ErrorNotice>
      ) : null}

      {showOperators && operators && operators.length > 0 ? (
        <FormField
          control={control}
          name="operator_keys"
          render={({ field }) => (
            <FormItem className="gap-1.5">
              <FormControl>
                <RequestReportKeyGroup
                  label={t('requestManagement.report.fields.operators')}
                  selectAllLabel={t('requestManagement.report.fields.selectAllOperators')}
                  options={operators}
                  value={field.value}
                  onChange={field.onChange}
                  disabled={disabled}
                />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
      ) : null}

      <FormField
        control={control}
        name="row_mode"
        render={({ field }) => (
          <FormItem className="gap-1.5">
            {/* Plain text, not `FormLabel`: a `<label for>` pointing at the
                radiogroup wrapper would label a non-labelable element. */}
            <span className={FIELD_LABEL_CLASS}>{t('requestManagement.report.fields.rowMode')}</span>
            <FormControl>
              <div
                role="radiogroup"
                aria-label={t('requestManagement.report.fields.rowMode')}
                className={ROW_MODE_TRACK_CLASS}
              >
                {ROW_MODES.map((mode) => {
                  const label = t(`requestManagement.report.rowModes.${mode}`)
                  const isSelected = field.value === mode

                  return (
                    <button
                      key={mode}
                      type="button"
                      role="radio"
                      aria-checked={isSelected}
                      title={label}
                      disabled={disabled}
                      onClick={() => field.onChange(mode)}
                      className={cn(
                        ROW_MODE_OPTION_CLASS,
                        isSelected ? ROW_MODE_OPTION_SELECTED_CLASS : ROW_MODE_OPTION_IDLE_CLASS,
                      )}
                    >
                      {label}
                    </button>
                  )
                })}
              </div>
            </FormControl>
            <FormMessage className="text-xs" />
          </FormItem>
        )}
      />
    </>
  )
}
