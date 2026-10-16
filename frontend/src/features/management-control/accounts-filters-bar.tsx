import { useTranslation } from 'react-i18next'
import type { AccountFlowsFilters } from '@/features/management-control/accounts-types'
import { ScopeFilterFields } from '@/features/management-control/scope-filter-fields'

interface AccountsFiltersBarProps {
  filters: AccountFlowsFilters
  onChange: (filters: AccountFlowsFilters) => void
}

/** Filters of the accounts page (spec 0271): period of the receipts and payments, and scope. */
export function AccountsFiltersBar({ filters, onChange }: AccountsFiltersBarProps) {
  const { t } = useTranslation()

  return (
    <section
      aria-label={t('managementControl.accounts.filters.title')}
      className="grid grid-cols-1 gap-3 rounded-lg border bg-surface p-3 sm:grid-cols-3 lg:grid-cols-5"
    >
      <ScopeFilterFields
        filters={filters}
        onChange={(patch) => onChange({ ...filters, ...patch })}
        dateFromLabel={t('managementControl.accounts.filters.dateFrom')}
      />
    </section>
  )
}
