import { useTranslation } from 'react-i18next'
import { formatEuro } from '@/features/invoices/invoice-format'
import { cn } from '@/lib/utils'

interface MoneyValueProps {
  value: number
  className?: string
}

/**
 * Money in EUR; a negative amount keeps its minus sign (never colour alone),
 * gets the destructive tint and an accessible "negative" prefix.
 */
export function MoneyValue({ value, className }: MoneyValueProps) {
  const { t } = useTranslation()
  const negative = value < 0

  return (
    <span className={cn('tabular-nums', negative && 'text-amount-negative', className)}>
      {negative ? <span className="sr-only">{t('managementControl.negative')} </span> : null}
      {formatEuro(value)}
    </span>
  )
}
