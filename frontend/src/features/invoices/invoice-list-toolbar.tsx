import { useTranslation } from 'react-i18next'
import { cn } from '@/lib/utils'
import { InvoiceMonthStrip } from '@/features/invoices/invoice-month-strip'
import { useMonthlySummary } from '@/features/invoices/use-invoice-queries'
import type { InvoiceListFilters } from '@/features/invoices/use-invoice-list-filters'
import type { InvoiceTypeFilter } from '@/features/invoices/types'

const TABS: readonly InvoiceTypeFilter[] = ['all', 'proforma', 'invoice']

interface InvoiceListToolbarProps {
  filters: InvoiceListFilters
}

/** Type tabs (Tutte / Proforma / Fatture) above the month strip; both write into the grid filter model. */
export function InvoiceListToolbar({ filters }: InvoiceListToolbarProps) {
  const { t } = useTranslation()
  const summary = useMonthlySummary({ year: filters.year, type: filters.type })

  return (
    <div className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-3">
      <div role="tablist" aria-label={t('invoices.tabs.label')} className="flex flex-wrap items-center gap-1">
        {TABS.map((tab) => {
          const selected = filters.type === tab
          return (
            <button
              key={tab}
              type="button"
              role="tab"
              aria-selected={selected}
              onClick={() => filters.setType(tab)}
              className={cn(
                'rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                selected ? 'bg-primary text-primary-foreground' : 'bg-card text-foreground hover:bg-muted',
              )}
            >
              {t(`invoices.tabs.${tab}`)}
            </button>
          )
        })}
      </div>
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
  )
}
