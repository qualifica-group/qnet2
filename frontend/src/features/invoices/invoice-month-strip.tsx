import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { cn } from '@/lib/utils'
import { formatEuro } from '@/features/invoices/invoice-format'
import { ALL_MONTHS } from '@/features/invoices/use-invoice-list-filters'
import type { MonthlySummary } from '@/features/invoices/types'

/** Years offered around the current one (current +/- this many). */
export const YEAR_SPAN = 5
const MONTH_LABEL_LENGTH = 3

interface InvoiceMonthStripProps {
  year: number
  currentYear: number
  /** Selected months (1..12). */
  months: readonly number[]
  summary: MonthlySummary | undefined
  isError: boolean
  onYearChange: (year: number) => void
  onToggleMonth: (month: number) => void
  onSelectAll: () => void
  onClear: () => void
}

/** Year select plus 12 compact multi-select month cells with the per-month count and total. */
export function InvoiceMonthStrip({
  year,
  currentYear,
  months,
  summary,
  isError,
  onYearChange,
  onToggleMonth,
  onSelectAll,
  onClear,
}: InvoiceMonthStripProps) {
  const { t } = useTranslation()
  const years = Array.from({ length: YEAR_SPAN * 2 + 1 }, (_, index) => currentYear - YEAR_SPAN + index)
  const allSelected = months.length === ALL_MONTHS.length

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-xs font-medium text-muted-foreground">{t('invoices.monthStrip.label')}</span>
        <Select value={String(year)} onValueChange={(value) => onYearChange(Number(value))}>
          <SelectTrigger size="sm" className="h-7 w-24 bg-card text-xs" aria-label={t('invoices.monthStrip.year')}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {years.map((option) => (
              <SelectItem key={option} value={String(option)}>
                {option}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Button type="button" size="xs" variant="secondary" onClick={allSelected ? onClear : onSelectAll}>
          {allSelected ? t('invoices.monthStrip.deselectAll') : t('invoices.monthStrip.selectAll')}
        </Button>
        {isError ? <span className="text-xs text-destructive">{t('invoices.monthStrip.loadError')}</span> : null}
      </div>
      <div className="grid grid-cols-3 gap-1.5 sm:grid-cols-6 lg:grid-cols-12">
        {ALL_MONTHS.map((month) => {
          const entry = summary?.months.find((item) => item.month === month)
          const selected = months.includes(month)
          const name = t(`invoices.months.${month}`)
          return (
            <button
              key={month}
              type="button"
              aria-pressed={selected}
              aria-label={name}
              onClick={() => onToggleMonth(month)}
              className={cn(
                'flex min-w-0 flex-col items-start rounded-md border px-2 py-1 text-left text-xs transition-colors',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                selected ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card hover:bg-muted',
              )}
            >
              <span className="font-semibold">{name.slice(0, MONTH_LABEL_LENGTH)}</span>
              <span className={cn('tabular-nums', selected ? 'text-primary-foreground/80' : 'text-muted-foreground')}>
                {t('invoices.monthStrip.count', { count: entry?.count ?? 0 })}
              </span>
              <span className="w-full truncate font-medium tabular-nums">
                {entry ? formatEuro(entry.total_amount) : '-'}
              </span>
            </button>
          )
        })}
      </div>
    </div>
  )
}
