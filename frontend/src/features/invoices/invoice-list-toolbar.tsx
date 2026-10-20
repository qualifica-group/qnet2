import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { InvoiceMonthStrip } from '@/features/invoices/invoice-month-strip'
import { useMonthlySummary } from '@/features/invoices/use-invoice-queries'
import type { InvoiceListFilters } from '@/features/invoices/use-invoice-list-filters'
import type { InvoiceTypeFilter } from '@/features/invoices/types'

const TABS: readonly InvoiceTypeFilter[] = ['all', 'proforma', 'invoice']

interface InvoiceListToolbarProps {
  filters: InvoiceListFilters
}

/** Type tabs (Tutte / Proforma / Fatture) above the month strip box; both write into the grid filter model. */
export function InvoiceListToolbar({ filters }: InvoiceListToolbarProps) {
  const { t } = useTranslation()
  const summary = useMonthlySummary({ year: filters.year, type: filters.type })

  return (
    <div className="flex flex-col gap-3">
      <FilterTabs<InvoiceTypeFilter>
        aria-label={t('invoices.tabs.label')}
        value={filters.type}
        options={TABS.map((tab) => ({ value: tab, label: t(`invoices.tabs.${tab}`) }))}
        onValueChange={filters.setType}
      />
      <div className="rounded-xl border border-border bg-surface p-3">
        <InvoiceMonthStrip
          year={filters.year}
          currentYear={filters.currentYear}
          months={filters.months}
          summary={summary.data}
          isError={summary.isError}
          onYearChange={filters.setYear}
          onToggleMonth={filters.toggleMonth}
          onSelectAll={filters.selectAllMonths}
          onClear={filters.clearMonths}
        />
      </div>
    </div>
  )
}
