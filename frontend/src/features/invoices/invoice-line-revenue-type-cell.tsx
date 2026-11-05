import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { useAbilities } from '@/features/auth/use-abilities'
import { revenueTypeLabel } from '@/features/revenue-types/group-options'
import { RevenueTypeSelect } from '@/features/revenue-types/revenue-type-select'
import { useSetInvoiceLineRevenueType } from '@/features/invoices/use-set-invoice-line-revenue-type'
import type { InvoiceLine } from '@/features/invoices/types'

const UPDATE_PERMISSION = 'invoices.update'

interface InvoiceLineRevenueTypeCellProps {
  /** Absent where the table is read-only (no document to patch). */
  invoiceId?: number
  line: InvoiceLine
  /** 1-based position, for the accessible name. */
  position: number
}

/**
 * Revenue sub-account of a saved line (spec 0350 D-2/D-8). Editable by whoever holds
 * `invoices.update`, on any document state; a failed change leaves the value as the server has it.
 */
export function InvoiceLineRevenueTypeCell({ invoiceId, line, position }: InvoiceLineRevenueTypeCellProps) {
  const { t, i18n } = useTranslation()
  const { can } = useAbilities()
  const mutation = useSetInvoiceLineRevenueType(invoiceId ?? 0)
  const current = line.revenue_type ?? null
  const label = t('revenueTypes.invoiceLine.change', { line: position })

  if (invoiceId === undefined || !can(UPDATE_PERMISSION)) {
    return current ? (
      <span title={current.path}>{revenueTypeLabel(current)}</span>
    ) : (
      <span className="text-muted-foreground">{t('revenueTypes.unclassified')}</span>
    )
  }

  return (
    <RevenueTypeSelect
      value={line.revenue_type_id ?? null}
      selected={current}
      disabled={mutation.isPending}
      placeholder={t('revenueTypes.unclassified')}
      aria-label={label}
      className="h-7 min-h-7 text-xs"
      onChange={(next) =>
        mutation.mutate(
          { lineId: line.id, revenueTypeId: next },
          {
            onSuccess: () => toast.success(t('revenueTypes.invoiceLine.saved')),
            onError: (error) => toast.error(apiErrorMessage(error, t('revenueTypes.invoiceLine.error'), i18n)),
          },
        )
      }
    />
  )
}
