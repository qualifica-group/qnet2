import { useTranslation } from 'react-i18next'
import { ScopeFilterFields } from '@/features/management-control/scope-filter-fields'
import type { TrendFilters } from '@/features/management-control/trend-types'

interface TrendFiltersBarProps {
  filters: TrendFilters
  onChange: (filters: TrendFilters) => void
}

/** Filters of the monthly trend page (spec 0270): period by document date and scope. */
export function TrendFiltersBar({ filters, onChange }: TrendFiltersBarProps) {
  const { t } = useTranslation()

  return (
    <section
      aria-label={t('managementControl.trend.filters.title')}
      className="grid grid-cols-1 gap-3 rounded-lg border bg-surface p-3 sm:grid-cols-3 lg:grid-cols-5"
    >
      <ScopeFilterFields
        filters={filters}
        onChange={(patch) => onChange({ ...filters, ...patch })}
        dateFromLabel={t('managementControl.trend.filters.dateFrom')}
      />
    </section>
  )
}
