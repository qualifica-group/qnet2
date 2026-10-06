import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import type { RowActionState } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

const OVERDUE_STATUSES: ReadonlySet<unknown> = new Set(['overdue', 'seriously_overdue'])

/** `remind` is inert (with a reason in its tooltip) unless the document has overdue installments (spec 0195 D-13). */
export function useInvoiceActionState() {
  const { t } = useTranslation()
  return useCallback(
    (action: TableActionDefinition, row: TableRow): RowActionState | undefined => {
      if (action.key !== 'remind' || OVERDUE_STATUSES.has(row.payment_status)) {
        return undefined
      }
      return { disabled: true, label: t('invoices.actions.remindDisabled') }
    },
    [t],
  )
}
