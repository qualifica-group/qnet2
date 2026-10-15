import { useId, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SegmentedControl, type SegmentedControlOption } from '@/components/ui/segmented-control'
import type { LedgerDocumentType, LedgerFilters } from '@/features/ledger/types'

const DOCUMENT_TYPES: readonly LedgerDocumentType[] = ['all', 'proforma', 'invoice']

interface LedgerFiltersBarProps {
  filters: LedgerFilters
  onChange: (filters: LedgerFilters) => void
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

/** Type, period and analytic filters of the ledger (spec 0266 D-2, D-3, D-8). */
export function LedgerFiltersBar({ filters, onChange }: LedgerFiltersBarProps) {
  const { t } = useTranslation()
  const fromId = useId()
  const toId = useId()

  const typeOptions: SegmentedControlOption<LedgerDocumentType>[] = DOCUMENT_TYPES.map((value) => ({
    value,
    label: t(`ledger.filters.types.${value}`),
  }))

  const multiLabels = (label: string) => ({
    placeholder: t('ledger.filters.placeholder'),
    searchPlaceholder: t('ledger.filters.search'),
    empty: t('ledger.filters.empty'),
    error: t('ledger.filters.error'),
    removeLabel: t('ledger.filters.remove'),
    triggerLabel: label,
    retry: t('ledger.filters.retry'),
  })

  return (
    <section
      aria-label={t('ledger.filters.title')}
      className="grid grid-cols-1 gap-3 rounded-lg border bg-surface p-3 sm:grid-cols-2 lg:grid-cols-3"
    >
      <Field label={t('ledger.filters.documentType')}>
        <SegmentedControl
          aria-label={t('ledger.filters.documentType')}
          value={filters.documentType}
          options={typeOptions}
          onValueChange={(documentType) => onChange({ ...filters, documentType })}
        />
      </Field>
      <Field label={t('ledger.filters.dateFrom')} htmlFor={fromId}>
        <Input
          id={fromId}
          type="date"
          className="h-8 text-xs"
          value={filters.dateFrom}
          onChange={(event) => onChange({ ...filters, dateFrom: event.target.value })}
        />
      </Field>
      <Field label={t('ledger.filters.dateTo')} htmlFor={toId}>
        <Input
          id={toId}
          type="date"
          className="h-8 text-xs"
          value={filters.dateTo}
          onChange={(event) => onChange({ ...filters, dateTo: event.target.value })}
        />
      </Field>
      <Field label={t('ledger.filters.company')}>
        <AsyncPaginatedMultiSelect
          resource="companies"
          value={filters.companyIds}
          onChange={(companyIds) => onChange({ ...filters, companyIds })}
          labels={multiLabels(t('ledger.filters.company'))}
        />
      </Field>
      <Field label={t('ledger.filters.companySite')}>
        <AsyncPaginatedMultiSelect
          resource="company-sites"
          value={filters.companySiteIds}
          onChange={(companySiteIds) => onChange({ ...filters, companySiteIds })}
          labels={multiLabels(t('ledger.filters.companySite'))}
        />
      </Field>
      <Field label={t('ledger.filters.operationalSite')}>
        <AsyncPaginatedMultiSelect
          resource="operational-sites"
          value={filters.operationalSiteIds}
          onChange={(operationalSiteIds) => onChange({ ...filters, operationalSiteIds })}
          labels={multiLabels(t('ledger.filters.operationalSite'))}
        />
      </Field>
    </section>
  )
}
