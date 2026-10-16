import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { AGENTS_TOPS, type AgentsFilters } from '@/features/management-control/agents-types'
import { CompanyFilterField, FilterField, PeriodFields } from '@/features/management-control/scope-filter-fields'

interface AgentsFiltersBarProps {
  filters: AgentsFilters
  onChange: (filters: AgentsFilters) => void
}

/** Filters of the agents page (spec 0272): period of the accruals and payments, company, number of agents shown. */
export function AgentsFiltersBar({ filters, onChange }: AgentsFiltersBarProps) {
  const { t } = useTranslation()
  const topId = useId()

  return (
    <section
      aria-label={t('managementControl.agents.filters.title')}
      className="grid grid-cols-1 gap-3 rounded-lg border bg-surface p-3 sm:grid-cols-2 lg:grid-cols-4"
    >
      <PeriodFields
        filters={filters}
        onChange={(patch) => onChange({ ...filters, ...patch })}
        dateFromLabel={t('managementControl.agents.filters.dateFrom')}
      />
      <CompanyFilterField value={filters.companyIds} onChange={(companyIds) => onChange({ ...filters, companyIds })} />
      <FilterField label={t('managementControl.agents.filters.top')} htmlFor={topId}>
        <Select
          value={String(filters.top)}
          onValueChange={(value) => {
            const top = AGENTS_TOPS.find((option) => String(option) === value)
            if (top) {
              onChange({ ...filters, top })
            }
          }}
        >
          <SelectTrigger id={topId} size="sm" className="w-full text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {AGENTS_TOPS.map((value) => (
              <SelectItem key={value} value={String(value)}>
                {t('managementControl.agents.filters.topOption', { count: value })}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterField>
    </section>
  )
}
