import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CASH_FLOW_HORIZONS, type CashFlowFilters } from '@/features/management-control/cash-flow-types'
import { EXPOSURE_DOCUMENT_TYPES } from '@/features/management-control/exposure-types'
import { FilterField, ScopeSelectionFields } from '@/features/management-control/scope-filter-fields'

interface CashFlowFiltersBarProps {
  filters: CashFlowFilters
  onChange: (filters: CashFlowFilters) => void
}

/** Filters of the cash flow page (spec 0270): scope, document type and horizon. There is no period: the forecast starts today. */
export function CashFlowFiltersBar({ filters, onChange }: CashFlowFiltersBarProps) {
  const { t } = useTranslation()
  const horizonId = useId()
  const documentTypeId = useId()

  return (
    <section
      aria-label={t('managementControl.cashFlow.filters.title')}
      className="grid grid-cols-1 gap-3 rounded-lg border bg-surface p-3 sm:grid-cols-3 lg:grid-cols-5"
    >
      <ScopeSelectionFields filters={filters} onChange={(patch) => onChange({ ...filters, ...patch })} />
      <FilterField label={t('managementControl.cashFlow.filters.documentType')} htmlFor={documentTypeId}>
        <Select
          value={filters.documentType}
          onValueChange={(value) => {
            const documentType = EXPOSURE_DOCUMENT_TYPES.find((option) => option === value)
            if (documentType) {
              onChange({ ...filters, documentType })
            }
          }}
        >
          <SelectTrigger id={documentTypeId} size="sm" className="w-full text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {EXPOSURE_DOCUMENT_TYPES.map((value) => (
              <SelectItem key={value} value={value}>
                {t(`managementControl.cashFlow.filters.documentTypes.${value}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterField>
      <FilterField label={t('managementControl.cashFlow.filters.horizon')} htmlFor={horizonId}>
        <Select
          value={String(filters.horizonMonths)}
          onValueChange={(value) => {
            const horizonMonths = CASH_FLOW_HORIZONS.find((option) => String(option) === value)
            if (horizonMonths) {
              onChange({ ...filters, horizonMonths })
            }
          }}
        >
          <SelectTrigger id={horizonId} size="sm" className="w-full text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {CASH_FLOW_HORIZONS.map((value) => (
              <SelectItem key={value} value={String(value)}>
                {t('managementControl.cashFlow.filters.horizonOption', { count: value })}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterField>
    </section>
  )
}
