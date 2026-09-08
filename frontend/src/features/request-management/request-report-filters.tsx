import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { CircleAlert, Loader2 } from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import {
  ROW_MODES,
  type RequestReportFormValues,
} from '@/features/request-management/request-report-schema'
import type { RequestReportCategory } from '@/features/request-management/report-api'
import { cn } from '@/lib/utils'

/** Compact control label, shared by the three groups (ui-design.md §2: smaller end of the scale). */
const FIELD_LABEL_CLASS = 'text-xs font-medium'

/** Branch group: raised card (rung 3) so the list reads as one unit on either host surface. */
const CATEGORY_GROUP_CLASS = 'rounded-lg border bg-card py-1.5 shadow-xs'
const CATEGORY_LEGEND_CLASS = 'ml-2.5 px-1 text-xs font-medium'
const CATEGORY_HEADER_CLASS = 'flex items-center justify-between gap-2 border-b border-border/60 px-2.5 pb-2'
const CATEGORY_LIST_CLASS = 'grid max-h-52 gap-0.5 overflow-y-auto p-1.5'
const CATEGORY_ROW_CLASS =
  'flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs transition-colors'
const CATEGORY_ROW_SELECTED_CLASS = 'bg-primary/10 text-foreground'
const CATEGORY_ROW_IDLE_CLASS = 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'

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

/** Adds/removes one branch key from the checkbox group's current selection (rev-2 AC-050). */
function toggleCategoryKey(current: string[], key: string, checked: boolean): string[] {
  return checked ? [...current, key] : current.filter((value) => value !== key)
}

/** Tri-state of the "select all" control (rev-2 AC-055): checked / indeterminate / unchecked. */
function selectAllState(selectedKeys: string[], allKeys: string[]): boolean | 'indeterminate' {
  if (allKeys.length === 0 || selectedKeys.length === 0) return false
  return selectedKeys.length === allKeys.length ? true : 'indeterminate'
}

export interface RequestReportFiltersProps {
  control: Control<RequestReportFormValues>
  categories: RequestReportCategory[] | undefined
  categoriesLoading: boolean
  categoriesError: boolean
  categoriesEmpty: boolean
  /** Disables every field: report creation/processing in the dialog, or the branch list unusable. */
  disabled: boolean
}

/**
 * Filter controls of spec 0106/0107 (rev-2 D-4, 0107 D-4): date range,
 * branch checkbox group with a tri-state "select all", and the row-mode
 * choice. One selection now drives both consumers — the charts and the CSV
 * (user directive 2026-09-08) — so `RequestReportDialog` is its only host;
 * the file stays split off it purely for size.
 *
 * Presentational only: the caller owns the `useForm()` instance, the branch
 * fetch (`useRequestReportCategories`), and must render this inside its own
 * `<Form {...form}>` (the `FormField`s below read RHF context from it).
 * Renders a FLAT fragment of one element per group — the host lays them out
 * as cells of its own grid, so nothing here may wrap them in a container.
 */
export function RequestReportFilters({
  control,
  categories,
  categoriesLoading,
  categoriesError,
  categoriesEmpty,
  disabled,
}: RequestReportFiltersProps) {
  const { t } = useTranslation()

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
        <p className={NOTICE_CLASS}>
          <Loader2 aria-hidden="true" className="size-3.5 shrink-0 motion-safe:animate-spin" />
          {t('requestManagement.report.status.loadingCategories')}
        </p>
      ) : null}

      {categoriesError ? (
        <p className={NOTICE_ERROR_CLASS} role="alert">
          <CircleAlert aria-hidden="true" className="mt-px size-3.5 shrink-0" />
          {t('requestManagement.report.errors.categoriesLoadFailed')}
        </p>
      ) : null}

      {categoriesEmpty ? (
        <p className={NOTICE_ERROR_CLASS} role="alert">
          <CircleAlert aria-hidden="true" className="mt-px size-3.5 shrink-0" />
          {t('requestManagement.report.errors.categoriesEmpty')}
        </p>
      ) : null}

      {categories && categories.length > 0 ? (
        <FormField
          control={control}
          name="category_keys"
          render={({ field }) => {
            const allCategoryKeys = categories.map((category) => category.key)
            const allState = selectAllState(field.value, allCategoryKeys)

            return (
              <FormItem className="gap-1.5">
                <FormControl>
                  <fieldset disabled={disabled} className={CATEGORY_GROUP_CLASS}>
                    <legend className={CATEGORY_LEGEND_CLASS}>
                      {t('requestManagement.report.fields.categories')}
                      <span aria-hidden="true" className="ml-1 text-destructive">*</span>
                    </legend>
                    <div className={CATEGORY_HEADER_CLASS}>
                      <label className="flex cursor-pointer items-center gap-2 text-xs font-medium">
                        <Checkbox
                          checked={allState}
                          onCheckedChange={() => field.onChange(allState === true ? [] : allCategoryKeys)}
                        />
                        {t('requestManagement.report.fields.selectAllCategories')}
                      </label>
                      <span className="text-[11px] tabular-nums text-muted-foreground">
                        {field.value.length}/{allCategoryKeys.length}
                      </span>
                    </div>
                    <div className={CATEGORY_LIST_CLASS}>
                      {categories.map((category) => {
                        const isSelected = field.value.includes(category.key)

                        return (
                          <label
                            key={category.key}
                            className={cn(
                              CATEGORY_ROW_CLASS,
                              isSelected ? CATEGORY_ROW_SELECTED_CLASS : CATEGORY_ROW_IDLE_CLASS,
                            )}
                          >
                            <Checkbox
                              checked={isSelected}
                              onCheckedChange={(checked) =>
                                field.onChange(toggleCategoryKey(field.value, category.key, checked === true))
                              }
                            />
                            <span className="truncate">{category.label}</span>
                          </label>
                        )
                      })}
                    </div>
                  </fieldset>
                </FormControl>
                <FormMessage className="text-xs" />
              </FormItem>
            )
          }}
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
