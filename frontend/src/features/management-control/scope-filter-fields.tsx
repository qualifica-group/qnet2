import { useId, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useMultiSelectLabels } from '@/features/management-control/use-multi-select-labels'
import type { ScopeFilters, ScopeSelection } from '@/features/management-control/types'

export function FilterField({ label, htmlFor, children }: { label: string; htmlFor?: string; children: ReactNode }) {
  return (
    <div className="flex min-w-0 flex-col gap-1">
      <Label htmlFor={htmlFor} className="text-xs text-muted-foreground">
        {label}
      </Label>
      {children}
    </div>
  )
}

interface ScopeSelectionFieldsProps {
  filters: ScopeSelection
  onChange: (patch: Partial<ScopeSelection>) => void
}

/** Company, company site and operational site: the part of the scope shared by pages with no period (spec 0270). */
export function ScopeSelectionFields({ filters, onChange }: ScopeSelectionFieldsProps) {
  const { t } = useTranslation()
  const multiLabels = useMultiSelectLabels()

  return (
    <>
      <FilterField label={t('managementControl.filters.company')}>
        <AsyncPaginatedMultiSelect
          resource="companies"
          value={filters.companyIds}
          onChange={(companyIds) => onChange({ companyIds })}
          labels={multiLabels(t('managementControl.filters.company'))}
        />
      </FilterField>
      <FilterField label={t('managementControl.filters.companySite')}>
        <AsyncPaginatedMultiSelect
          resource="company-sites"
          value={filters.companySiteIds}
          onChange={(companySiteIds) => onChange({ companySiteIds })}
          labels={multiLabels(t('managementControl.filters.companySite'))}
        />
      </FilterField>
      <FilterField label={t('managementControl.filters.operationalSite')}>
        <AsyncPaginatedMultiSelect
          resource="operational-sites"
          value={filters.operationalSiteIds}
          onChange={(operationalSiteIds) => onChange({ operationalSiteIds })}
          labels={multiLabels(t('managementControl.filters.operationalSite'))}
        />
      </FilterField>
    </>
  )
}

interface ScopeFilterFieldsProps {
  filters: ScopeFilters
  onChange: (patch: Partial<ScopeFilters>) => void
  /** The meaning of the period depends on the page (work order start, flows of the schedule). */
  dateFromLabel: string
}

/**
 * Fields common to the module pages (spec 0268, 0269 D-14): period, company, company site and
 * operational site. Renders the cells only; the page bar owns the grid that lays them out.
 */
export function ScopeFilterFields({ filters, onChange, dateFromLabel }: ScopeFilterFieldsProps) {
  const { t } = useTranslation()
  const fromId = useId()
  const toId = useId()

  return (
    <>
      <FilterField label={dateFromLabel} htmlFor={fromId}>
        <Input
          id={fromId}
          type="date"
          className="h-8 text-xs"
          value={filters.dateFrom}
          onChange={(event) => onChange({ dateFrom: event.target.value })}
        />
      </FilterField>
      <FilterField label={t('managementControl.filters.dateTo')} htmlFor={toId}>
        <Input
          id={toId}
          type="date"
          className="h-8 text-xs"
          value={filters.dateTo}
          onChange={(event) => onChange({ dateTo: event.target.value })}
        />
      </FilterField>
      <ScopeSelectionFields filters={filters} onChange={onChange} />
    </>
  )
}
