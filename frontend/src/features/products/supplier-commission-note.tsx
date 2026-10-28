import { useTranslation } from 'react-i18next'
import { Percent } from 'lucide-react'
import { cn } from '@/lib/utils'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { SupplierCommission } from '@/features/products/product-suppliers'

/** A percentage reads "10%", not "10,00%"; two decimals cover the rule's precision. */
const PERCENT_DECIMALS = 2

interface SupplierCommissionNoteProps {
  commission: SupplierCommission | null
  id?: string
  className?: string
}

/**
 * "Commissione 10% pagata" / "Commissione fissa € 25,00 ricevuta" (spec 0320 rev.3 D-22): a compact pill on a
 * tint veil, so it reads as a property of the supplier and not as an error. The title explains why the cost is 0.
 */
export function SupplierCommissionNote({ commission, id, className }: SupplierCommissionNoteProps) {
  const { t, i18n } = useTranslation()
  if (commission === null) {
    return null
  }
  const amount = commission.type === 'PERCENTAGE' ? `${new Intl.NumberFormat(i18n.language, { maximumFractionDigits: PERCENT_DECIMALS }).format(Number(commission.value))}%` : formatEuro(commission.value)

  return (
    <span
      id={id}
      title={t('products.supplierCommission.hint')}
      className={cn('inline-flex w-fit max-w-full items-center gap-1 rounded-md bg-muted px-1.5 py-0.5 text-[11px] leading-tight text-muted-foreground', className)}
    >
      <Percent className="size-3 shrink-0" aria-hidden="true" />
      <span className="truncate">
        {t(`products.supplierCommission.${commission.type}`, {
          value: amount,
          direction: t(`products.supplierCommission.direction.${commission.direction}`),
        })}
      </span>
    </span>
  )
}
