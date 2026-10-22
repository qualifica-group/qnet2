import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect, type AsyncPaginatedSelectLabels } from '@/components/ui/async-paginated-select'
import { Input } from '@/components/ui/input'
import { SegmentedControl } from '@/components/ui/segmented-control'
import { balanceYearOptions } from '@/features/absences/balances/balance-format'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import type { useBalanceOverviewFilters } from '@/features/absences/balance-overview/use-balance-overview-filters'

type OverviewFilters = ReturnType<typeof useBalanceOverviewFilters>

interface BalanceOverviewFiltersProps {
  filters: OverviewFilters
}

function useSelectLabels(scope: 'function' | 'site'): AsyncPaginatedSelectLabels {
  const { t } = useTranslation()
  const base = `absences.balances.overview.filters.${scope}`

  return {
    placeholder: t(`${base}.placeholder`),
    searchPlaceholder: t(`${base}.search`),
    empty: t(`${base}.empty`),
    error: t(`${base}.error`),
    clearLabel: t('common.clear'),
    triggerLabel: t(`${base}.label`),
    retry: t('common.retry'),
  }
}

/** Year, business function, operational site and name search of the HR overview. */
export function BalanceOverviewFilters({ filters }: BalanceOverviewFiltersProps) {
  const { t } = useTranslation()
  const functionLabels = useSelectLabels('function')
  const siteLabels = useSelectLabels('site')

  return (
    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
      <SegmentedControl
        aria-label={t('absences.balances.year')}
        value={String(filters.year)}
        options={balanceYearOptions(new Date().getFullYear())}
        onValueChange={(value) => filters.setYear(Number(value))}
      />
      <AsyncPaginatedSelect
        resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
        value={filters.businessFunctionId}
        onChange={filters.setBusinessFunctionId}
        labels={functionLabels}
      />
      <AsyncPaginatedSelect
        resource={OPERATIONAL_SITES_FOR_SELECT_RESOURCE}
        value={filters.siteId}
        onChange={filters.setSiteId}
        labels={siteLabels}
      />
      <Input
        type="search"
        value={filters.searchText}
        maxLength={100}
        placeholder={t('absences.balances.overview.filters.search')}
        aria-label={t('absences.balances.overview.filters.search')}
        onChange={(event) => filters.setSearchText(event.target.value)}
      />
    </div>
  )
}
