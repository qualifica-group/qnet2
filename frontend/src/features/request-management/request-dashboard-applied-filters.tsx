import { useTranslation } from 'react-i18next'
import { Filter } from 'lucide-react'
import type {
  RequestReportCategory,
  RequestReportOperator,
  RequestReportSite,
} from '@/features/request-management/report-api'
import type { RequestReportFormValues } from '@/features/request-management/request-report-schema'
import { operatorsForSites } from '@/features/request-management/request-report-site-operators'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

/** Picked labels named in a chip before the rest collapses into "+N". */
const NAMED_LABELS_MAX = 2

const CHIP_CLASS = 'inline-flex max-w-72 min-w-0 items-center gap-1 rounded-md px-2 py-0.5 text-xs ring-1 ring-inset'
/** A dimension that actually narrows the data: tinted so "this is filtered" reads at a glance. */
const CHIP_ACTIVE_CLASS = 'bg-primary/10 text-primary ring-primary/20'
const CHIP_IDLE_CLASS = 'bg-card text-foreground ring-border'

interface AppliedChipProps {
  label: string
  value: string
  /** Every picked name, for the tooltip when the chip shows only some. */
  title?: string
  active: boolean
}

function AppliedChip({ label, value, title, active }: AppliedChipProps) {
  return (
    <li className={cn(CHIP_CLASS, active ? CHIP_ACTIVE_CLASS : CHIP_IDLE_CLASS)} title={title ?? value}>
      <span className={cn('shrink-0', active ? 'text-primary/80' : 'text-muted-foreground')}>{label}</span>
      <span className="truncate font-medium">{value}</span>
    </li>
  )
}

interface KeyedItem {
  key: string
  label: string
}

/** The picked entries in list order, and whether they cover everything on offer. */
function pickedOf(items: KeyedItem[], keys: string[]): { picked: KeyedItem[]; isEverything: boolean } {
  const picked = items.filter((item) => keys.includes(item.key))

  return { picked, isEverything: picked.length === items.length }
}

export interface RequestDashboardAppliedFiltersProps {
  filters: RequestReportFormValues
  categories: RequestReportCategory[]
  sites: RequestReportSite[]
  operators: RequestReportOperator[]
}

/**
 * The filters the charts below were built from, one chip per dimension (user
 * directive 2026-09-18): a dimension that narrows the data is tinted, one
 * left at "everything" stays neutral, so a filtered dashboard is recognisable
 * without opening the sheet. The Sedi and operator chips follow the same rule
 * as the sheet: shown only for the row modes that emit operator rows, and
 * only when the list has something to offer.
 */
export function RequestDashboardAppliedFilters({
  filters,
  categories,
  sites,
  operators,
}: RequestDashboardAppliedFiltersProps) {
  const { t } = useTranslation()
  const showNarrowingGroups = filters.row_mode !== 'total_only'

  const namesOf = (picked: KeyedItem[]): string => {
    const named = picked.slice(0, NAMED_LABELS_MAX).map((item) => item.label).join(', ')
    const rest = picked.length - NAMED_LABELS_MAX

    return rest > 0 ? `${named} ${t('requestManagement.dashboard.applied.more', { count: rest })}` : named
  }
  const fullNamesOf = (picked: KeyedItem[]): string => picked.map((item) => item.label).join(', ')

  const categorySelection = pickedOf(categories, filters.category_keys)
  const siteSelection = pickedOf(sites, filters.site_keys)
  const offeredOperators = operatorsForSites(
    operators,
    filters.site_keys,
    sites.map((site) => site.key),
  )
  const operatorSelection = pickedOf(operators, filters.operator_keys)
  const coversOfferedOperators = offeredOperators.every((operator) => filters.operator_keys.includes(operator.key))

  const operatorValue = operatorSelection.isEverything
    ? t('requestManagement.report.picker.allOperators')
    : coversOfferedOperators
      ? t('requestManagement.dashboard.applied.allOperatorsOfSites')
      : namesOf(operatorSelection.picked)

  return (
    <div className="flex min-w-0 flex-wrap items-center gap-1.5">
      <span className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
        <Filter aria-hidden="true" className="size-3.5" />
        {t('requestManagement.dashboard.applied.title')}
      </span>
      <ul aria-label={t('requestManagement.dashboard.applied.title')} className="flex min-w-0 flex-wrap gap-1.5">
        <AppliedChip
          label={t('requestManagement.dashboard.applied.period')}
          value={t('requestManagement.dashboard.applied.periodValue', {
            from: formatDate(filters.date_from),
            to: formatDate(filters.date_to),
          })}
          active
        />
        <AppliedChip
          label={t('requestManagement.dashboard.applied.categories')}
          value={
            categorySelection.isEverything
              ? t('requestManagement.report.picker.allCategories')
              : namesOf(categorySelection.picked)
          }
          title={fullNamesOf(categorySelection.picked)}
          active={!categorySelection.isEverything}
        />
        {showNarrowingGroups && sites.length > 0 ? (
          <AppliedChip
            label={t('requestManagement.dashboard.applied.sites')}
            value={
              siteSelection.isEverything ? t('requestManagement.report.picker.allSites') : namesOf(siteSelection.picked)
            }
            title={fullNamesOf(siteSelection.picked)}
            active={!siteSelection.isEverything}
          />
        ) : null}
        {showNarrowingGroups && operators.length > 0 ? (
          <AppliedChip
            label={t('requestManagement.dashboard.applied.operators')}
            value={operatorValue}
            title={fullNamesOf(operatorSelection.picked)}
            active={!operatorSelection.isEverything}
          />
        ) : null}
        <AppliedChip
          label={t('requestManagement.dashboard.applied.rowMode')}
          value={t(`requestManagement.report.rowModes.${filters.row_mode}`)}
          active={false}
        />
      </ul>
    </div>
  )
}
