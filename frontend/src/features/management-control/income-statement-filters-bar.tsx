import { useId, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SegmentedControl, type SegmentedControlOption } from '@/components/ui/segmented-control'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  SEGMENT_BYS,
  REVENUE_BASES,
  WORK_ORDER_TYPES,
  type IncomeStatementFilters,
  type WorkOrderTypeValue,
} from '@/features/management-control/types'

interface IncomeStatementFiltersBarProps {
  filters: IncomeStatementFilters
  onChange: (filters: IncomeStatementFilters) => void
}

function Field({ label, htmlFor, children }: { label: string; htmlFor?: string; children: ReactNode }) {
  return (
    <div className="flex min-w-0 flex-col gap-1">
      <Label htmlFor={htmlFor} className="text-xs text-muted-foreground">
        {label}
      </Label>
      {children}
    </div>
  )
}

/** Common filters of the module (spec 0268): period, analytic dimensions, revenue basis and grouping. */
export function IncomeStatementFiltersBar({ filters, onChange }: IncomeStatementFiltersBarProps) {
  const { t } = useTranslation()
  const fromId = useId()
  const toId = useId()

  const basisOptions: SegmentedControlOption<(typeof REVENUE_BASES)[number]>[] = REVENUE_BASES.map((value) => ({
    value,
    label: t(`managementControl.filters.bases.${value}`),
  }))
  const segmentById = useId()

  const multiLabels = (label: string) => ({
    placeholder: t('managementControl.filters.placeholder'),
    searchPlaceholder: t('managementControl.filters.search'),
    empty: t('managementControl.filters.empty'),
    error: t('managementControl.filters.error'),
    removeLabel: t('managementControl.filters.remove'),
    triggerLabel: label,
    retry: t('managementControl.filters.retry'),
  })

  const toggleType = (type: WorkOrderTypeValue, checked: boolean) => {
    const next = checked ? [...filters.workOrderTypes, type] : filters.workOrderTypes.filter((value) => value !== type)
    onChange({ ...filters, workOrderTypes: WORK_ORDER_TYPES.filter((value) => next.includes(value)) })
  }

  return (
    <section
      aria-label={t('managementControl.filters.title')}
      className="grid grid-cols-1 gap-3 rounded-lg border bg-surface p-3 sm:grid-cols-2 lg:grid-cols-3"
    >
      <Field label={t('managementControl.filters.dateFrom')} htmlFor={fromId}>
        <Input
          id={fromId}
          type="date"
          className="h-8 text-xs"
          value={filters.dateFrom}
          onChange={(event) => onChange({ ...filters, dateFrom: event.target.value })}
        />
      </Field>
      <Field label={t('managementControl.filters.dateTo')} htmlFor={toId}>
        <Input
          id={toId}
          type="date"
          className="h-8 text-xs"
          value={filters.dateTo}
          onChange={(event) => onChange({ ...filters, dateTo: event.target.value })}
        />
      </Field>
      <Field label={t('managementControl.filters.company')}>
        <AsyncPaginatedMultiSelect
          resource="companies"
          value={filters.companyIds}
          onChange={(companyIds) => onChange({ ...filters, companyIds })}
          labels={multiLabels(t('managementControl.filters.company'))}
        />
      </Field>
      <Field label={t('managementControl.filters.companySite')}>
        <AsyncPaginatedMultiSelect
          resource="company-sites"
          value={filters.companySiteIds}
          onChange={(companySiteIds) => onChange({ ...filters, companySiteIds })}
          labels={multiLabels(t('managementControl.filters.companySite'))}
        />
      </Field>
      <Field label={t('managementControl.filters.operationalSite')}>
        <AsyncPaginatedMultiSelect
          resource="operational-sites"
          value={filters.operationalSiteIds}
          onChange={(operationalSiteIds) => onChange({ ...filters, operationalSiteIds })}
          labels={multiLabels(t('managementControl.filters.operationalSite'))}
        />
      </Field>
      <Field label={t('managementControl.filters.businessFunction')}>
        <AsyncPaginatedMultiSelect
          resource="business-functions"
          value={filters.businessFunctionIds}
          onChange={(businessFunctionIds) => onChange({ ...filters, businessFunctionIds })}
          labels={multiLabels(t('managementControl.filters.businessFunction'))}
        />
      </Field>
      <Field label={t('managementControl.filters.commercial')}>
        <AsyncPaginatedMultiSelect
          resource="referents"
          value={filters.commercialIds}
          onChange={(commercialIds) => onChange({ ...filters, commercialIds })}
          labels={multiLabels(t('managementControl.filters.commercial'))}
        />
      </Field>
      <fieldset className="flex min-w-0 flex-col gap-1">
        <legend className="text-xs font-medium text-muted-foreground">{t('managementControl.filters.workOrderType')}</legend>
        <div className="flex flex-wrap gap-3 pt-1.5">
          {WORK_ORDER_TYPES.map((type) => (
            <Label key={type} className="flex items-center gap-1.5 text-xs">
              <Checkbox
                checked={filters.workOrderTypes.includes(type)}
                onCheckedChange={(checked) => toggleType(type, checked === true)}
              />
              {t(`enums.work_order_type.${type}`)}
            </Label>
          ))}
        </div>
      </fieldset>
      <Field label={t('managementControl.filters.revenueBasis')}>
        <SegmentedControl
          aria-label={t('managementControl.filters.revenueBasis')}
          value={filters.revenueBasis}
          options={basisOptions}
          onValueChange={(revenueBasis) => onChange({ ...filters, revenueBasis })}
        />
      </Field>
      <div className="min-w-0 sm:col-span-2 lg:col-span-3">
        <Field label={t('managementControl.filters.segmentBy')} htmlFor={segmentById}>
          <Select
            value={filters.segmentBy}
            onValueChange={(value) => {
              const segmentBy = SEGMENT_BYS.find((option) => option === value)
              if (segmentBy) {
                onChange({ ...filters, segmentBy })
              }
            }}
          >
            <SelectTrigger id={segmentById} size="sm" className="w-full text-xs sm:max-w-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {SEGMENT_BYS.map((value) => (
                <SelectItem key={value} value={value}>
                  {t(`managementControl.filters.segmentBys.${value}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Field>
      </div>
    </section>
  )
}
