import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import type { RowActionState } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/** Row-action key of the "issue invoice" action (spec 0194, permission invoices.create). */
export const INVOICE_ACTION_KEY = 'invoice'

/** True when the row's request already produced its document (`status` issued or an `invoice_id`). */
export function isRequestIssued(row: TableRow): boolean {
  return row.status === 'issued' || typeof row.invoice_id === 'number'
}

/** Per-row state of the `invoice` action: inert once the request is issued. */
export function useInvoiceActionState() {
  const { t } = useTranslation()

  return useCallback(
    (action: TableActionDefinition, row: TableRow): RowActionState | undefined => {
      if (action.key !== INVOICE_ACTION_KEY || !isRequestIssued(row)) {
        return undefined
      }
      return { disabled: true, label: t('invoiceEditor.rowAction.alreadyIssued') }
    },
    [t],
  )
}
