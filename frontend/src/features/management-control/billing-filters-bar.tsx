import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { BILLING_GROUP_BYS, type BillingFilters } from '@/features/management-control/billing-types'
import { FilterField, ScopeFilterFields } from '@/features/management-control/scope-filter-fields'

interface BillingFiltersBarProps {
  filters: BillingFilters
  onChange: (filters: BillingFilters) => void
}

/** Filters of the billing page (spec 0271): period of the proforma cycle, scope and grouping of the backlog. */
export function BillingFiltersBar({ filters, onChange }: BillingFiltersBarProps) {
  const { t } = useTranslation()
  const groupById = useId()

  return (
    <section
      aria-label={t('managementControl.billing.filters.title')}
      className="grid grid-cols-1 gap-3 rounded-lg border bg-surface p-3 sm:grid-cols-3 xl:grid-cols-6"
    >
      <ScopeFilterFields
        filters={filters}
        onChange={(patch) => onChange({ ...filters, ...patch })}
        dateFromLabel={t('managementControl.billing.filters.dateFrom')}
      />
      <FilterField label={t('managementControl.billing.filters.groupBy')} htmlFor={groupById}>
        <Select
          value={filters.groupBy}
          onValueChange={(value) => {
            const groupBy = BILLING_GROUP_BYS.find((option) => option === value)
            if (groupBy) {
              onChange({ ...filters, groupBy })
            }
          }}
        >
          <SelectTrigger id={groupById} size="sm" className="w-full text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {BILLING_GROUP_BYS.map((value) => (
              <SelectItem key={value} value={value}>
                {t(`managementControl.billing.filters.groupBys.${value}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterField>
    </section>
  )
}
