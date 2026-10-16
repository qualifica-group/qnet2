import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { SegmentedControl, type SegmentedControlOption } from '@/components/ui/segmented-control'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { FilterField, ScopeFilterFields } from '@/features/management-control/scope-filter-fields'
import { EXPOSURE_DOCUMENT_TYPES, EXPOSURE_GROUP_BYS, EXPOSURE_TOPS, type ExposureFilters } from '@/features/management-control/exposure-types'

interface ExposureFiltersBarProps {
  filters: ExposureFilters
  onChange: (filters: ExposureFilters) => void
}

/** Filters of the schedule and exposure page (spec 0269): period of the flows, scope, document type, grouping, top. */
export function ExposureFiltersBar({ filters, onChange }: ExposureFiltersBarProps) {
  const { t } = useTranslation()
  const topId = useId()
  const groupById = useId()

  const documentTypeOptions: SegmentedControlOption<(typeof EXPOSURE_DOCUMENT_TYPES)[number]>[] =
    EXPOSURE_DOCUMENT_TYPES.map((value) => ({
      value,
      label: t(`managementControl.exposure.filters.documentTypes.${value}`),
    }))

  return (
    <section
      aria-label={t('managementControl.exposure.filters.title')}
      className="grid grid-cols-1 gap-3 rounded-lg border bg-surface p-3 sm:grid-cols-2 lg:grid-cols-4"
    >
      <ScopeFilterFields
        filters={filters}
        onChange={(patch) => onChange({ ...filters, ...patch })}
        dateFromLabel={t('managementControl.exposure.filters.dateFrom')}
      />
      <FilterField label={t('managementControl.exposure.filters.documentType')}>
        <SegmentedControl
          aria-label={t('managementControl.exposure.filters.documentType')}
          value={filters.documentType}
          options={documentTypeOptions}
          onValueChange={(documentType) => onChange({ ...filters, documentType })}
        />
      </FilterField>
      <FilterField label={t('managementControl.exposure.filters.groupBy')} htmlFor={groupById}>
        <Select
          value={filters.groupBy}
          onValueChange={(value) => {
            const groupBy = EXPOSURE_GROUP_BYS.find((option) => option === value)
            if (groupBy) {
              onChange({ ...filters, groupBy })
            }
          }}
        >
          <SelectTrigger id={groupById} size="sm" className="w-full text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {EXPOSURE_GROUP_BYS.map((value) => (
              <SelectItem key={value} value={value}>
                {t(`managementControl.exposure.filters.groupBys.${value}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterField>
      <FilterField label={t('managementControl.exposure.filters.top')} htmlFor={topId}>
        <Select
          value={String(filters.top)}
          onValueChange={(value) => {
            const top = EXPOSURE_TOPS.find((option) => String(option) === value)
            if (top) {
              onChange({ ...filters, top })
            }
          }}
        >
          <SelectTrigger id={topId} size="sm" className="w-full text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {EXPOSURE_TOPS.map((value) => (
              <SelectItem key={value} value={String(value)}>
                {t('managementControl.exposure.filters.topOption', { count: value })}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterField>
    </section>
  )
}
