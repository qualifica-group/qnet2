import { useId } from 'react'
import { useController, type Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { CircleAlert, Columns3, ListRestart } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { FieldHint } from '@/components/field-hint'
import { cn } from '@/lib/utils'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { ProductCategoryReportColumnTile } from '@/features/product-categories/product-category-report-column-tile'
import { toggleReportColumn } from '@/features/product-categories/report-columns-inheritance'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'
import { useReportColumnsCatalog } from '@/features/product-categories/use-report-columns-catalog'
import {
  reportColumnsResetVisible,
  useReportColumnsInheritance,
} from '@/features/product-categories/use-report-columns-inheritance'

interface ProductCategoryReportColumnsFieldProps {
  control: Control<ProductCategoryFormValues>
  mode: ProductCategoryFormMode
  className?: string
}

/** Same icon-chip treatment as `ProductCategoryRuleCard`: tinted while the field has any effective column. */
const ICON_CHIP_CLASS =
  'flex size-8 shrink-0 items-center justify-center rounded-lg border transition-colors'

const GRID_CLASS = 'grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3'
const SKELETON_TILE_COUNT = 6

/**
 * The statistics-column picker (spec 0141 AC-009): a full-width card, next
 * to the rule tiles, shown only while the category is EFFECTIVELY reportable
 * (the caller gates mounting on that). Lets the operator choose which of the
 * catalogue columns Gestione Richieste computes for this category's report
 * AND dashboard — the same selection drives both.
 *
 * `report_columns: null` inherits the nearest configured ancestor's own
 * selection; checking any catalogue tile starts an OWN selection; unchecking
 * every tile returns to null automatically (`toggleReportColumn`). The header
 * actions mirror that: "All" always available, and exactly one of "None"
 * (origin — nothing to fall back to) / "Back to inherited" (overriding a
 * configured ancestor) shows at a time — `reportColumnsResetVisible` is what
 * rev-1 fixed so the latter never appears with nothing behind it.
 *
 * Bypasses `MetaField` on purpose: its stacked slot order (label, control,
 * description) does not fit a card whose header carries a counter badge, a
 * status chip AND action buttons on one line — the visibility/disabled
 * resolution below is the same two lines `MetaField` itself runs internally.
 */
export function ProductCategoryReportColumnsField({
  control,
  mode,
  className,
}: ProductCategoryReportColumnsFieldProps) {
  const { t } = useTranslation()
  const headingId = useId()
  const { field: fieldPermission } = useResourcePermissions()
  const permission = fieldPermission('report_columns')
  const { field, fieldState } = useController({ control, name: 'report_columns' })
  const catalogQuery = useReportColumnsCatalog(true)
  const catalog = catalogQuery.data ?? []
  const catalogOrder = catalog.map((option) => option.key)
  const inheritance = useReportColumnsInheritance(control, mode)
  const { override, inherited, inheritedDataAvailable, effective } = inheritance

  if (!permission.visible) {
    return null
  }
  const disabled = permission.disabled || !permission.editable

  const own = override !== null
  const resetVisible = reportColumnsResetVisible(inheritance)
  // The "None" quick action only makes sense where "Back to inherited" isn't
  // already offering the same reset with a more specific label (rev-1).
  const clearVisible = own && !resetVisible
  const allSelected = catalog.length > 0 && catalogOrder.every((key) => effective.includes(key))
  const active = effective.length > 0

  const description = reportColumnsDescription({
    t,
    own,
    sourceName: inherited.sourceCategory?.name ?? null,
    hasFallback: inherited.keys.length > 0,
    dataAvailable: inheritedDataAvailable,
  })

  return (
    <div className={cn('rounded-lg border bg-card p-3', className)}>
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="flex min-w-0 items-start gap-2.5">
          <span
            className={cn(
              ICON_CHIP_CLASS,
              active ? 'border-primary/20 bg-primary/10 text-primary' : 'border-border bg-muted/40 text-muted-foreground',
            )}
          >
            <Columns3 className="size-4" aria-hidden="true" />
          </span>
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-1.5">
              <h4 id={headingId} className="text-sm font-semibold text-foreground">
                {t('productCategories.form.reportColumns')}
              </h4>
              <FieldHint
                text={t('productCategories.form.reportColumnsInfo')}
                label={t('productCategories.form.reportColumnsInfoLabel')}
              />
              {catalog.length > 0 ? (
                <Badge variant="secondary" className="text-[11px] font-normal">
                  {t('productCategories.form.reportColumnsCount', {
                    selected: effective.length,
                    total: catalog.length,
                  })}
                </Badge>
              ) : null}
              <ReportColumnsStatusBadge own={own} sourceName={inherited.sourceCategory?.name ?? null} />
            </div>
          </div>
        </div>

        {catalog.length > 0 && !disabled ? (
          <div className="flex shrink-0 flex-wrap items-center gap-1.5">
            <Button
              type="button"
              variant="ghost"
              size="xs"
              disabled={allSelected}
              onClick={() => field.onChange(catalogOrder)}
            >
              {t('productCategories.form.reportColumnsSelectAll')}
            </Button>
            {resetVisible ? (
              <Button type="button" variant="ghost" size="xs" onClick={() => field.onChange(null)}>
                <ListRestart aria-hidden="true" />
                {t('productCategories.form.reportColumnsResetToInherited')}
              </Button>
            ) : clearVisible ? (
              <Button type="button" variant="ghost" size="xs" onClick={() => field.onChange(null)}>
                {t('productCategories.form.reportColumnsSelectNone')}
              </Button>
            ) : null}
          </div>
        ) : null}
      </div>

      <div className="mt-3">
        {catalogQuery.isPending ? (
          <div className={GRID_CLASS}>
            {Array.from({ length: SKELETON_TILE_COUNT }).map((_, index) => (
              <Skeleton key={index} className="h-8 rounded-lg" />
            ))}
          </div>
        ) : null}

        {catalogQuery.isError ? (
          <div className="flex items-center gap-2 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
            <CircleAlert aria-hidden="true" className="size-3.5 shrink-0" />
            <span className="flex-1">{t('productCategories.form.reportColumnsError')}</span>
            <Button type="button" variant="outline" size="xs" onClick={() => void catalogQuery.refetch()}>
              {t('common.retry')}
            </Button>
          </div>
        ) : null}

        {catalog.length > 0 ? (
          <div role="group" aria-labelledby={headingId} className={GRID_CLASS}>
            {catalog.map((option) => (
              <ProductCategoryReportColumnTile
                key={option.key}
                id={`product-category-report-column-${option.key}`}
                label={option.label}
                checked={effective.includes(option.key)}
                inherited={!own && inherited.keys.includes(option.key)}
                disabled={disabled}
                onToggle={() => field.onChange(toggleReportColumn(effective, option.key, catalogOrder))}
              />
            ))}
          </div>
        ) : null}
      </div>

      {fieldState.error ? (
        <p role="alert" className="mt-2 text-xs text-destructive">
          {String(fieldState.error.message ?? '')}
        </p>
      ) : (
        <p className="mt-2 text-xs text-muted-foreground">{description}</p>
      )}
    </div>
  )
}

interface ReportColumnsStatusBadgeProps {
  own: boolean
  sourceName: string | null
}

/** The three status chips the header may show: own / inherited-from-X / none. */
function ReportColumnsStatusBadge({ own, sourceName }: ReportColumnsStatusBadgeProps) {
  const { t } = useTranslation()

  if (own) {
    return (
      <Badge variant="secondary" className="text-[11px] font-normal">
        {t('productCategories.form.reportColumnsStatusOwn')}
      </Badge>
    )
  }
  if (sourceName !== null) {
    return (
      <Badge variant="outline" className="text-[11px] font-normal">
        {t('productCategories.form.inheritedFrom', { category: sourceName })}
      </Badge>
    )
  }
  return (
    <Badge variant="outline" className="text-[11px] font-normal text-muted-foreground">
      {t('productCategories.form.reportColumnsStatusNone')}
    </Badge>
  )
}

interface ReportColumnsDescriptionArgs {
  t: (key: string, options?: Record<string, unknown>) => string
  own: boolean
  sourceName: string | null
  /** Whether an ancestor configures anything to fall back to — same condition `reportColumnsResetVisible` checks. */
  hasFallback: boolean
  dataAvailable: boolean
}

/**
 * The five caption variants: own-overriding-an-ancestor / own-as-origin /
 * inherited-with-source / inherited-no-source / unknown (data not
 * available). Rendered under the grid — the header's status chip already
 * gives the short answer, this is the one full sentence explaining it.
 */
function reportColumnsDescription({ t, own, sourceName, hasFallback, dataAvailable }: ReportColumnsDescriptionArgs): string {
  if (own) {
    return hasFallback
      ? t('productCategories.form.reportColumnsOverridesHint', { category: sourceName ?? '' })
      : t('productCategories.form.reportColumnsOwnHint')
  }
  if (!dataAvailable) {
    return t('productCategories.form.reportColumnsUnknownHint')
  }
  if (sourceName !== null) {
    return t('productCategories.form.reportColumnsInheritedHint', { category: sourceName })
  }
  return t('productCategories.form.reportColumnsNoSourceHint')
}
