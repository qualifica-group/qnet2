import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { Euro } from 'lucide-react'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { RowActionState } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { ProformaStatus } from '@/features/proforma-requests/types'

/** The work orders row-action key of the "€" proforma request (spec 0193). */
export const PROFORMA_ACTION_KEY = 'proforma'

/** Icons the work orders grid adds to the shared action icon map. */
export const WORK_ORDER_ACTION_ICONS: ActionIconMap = { euro: Euro }

/** Icon tint per state: grey = no request, blue = pending, yellow = issued (spec 0193 D-5). */
const STATUS_CLASS: Record<ProformaStatus, string> = {
  none: 'text-muted-foreground',
  pending: 'text-blue-600 hover:text-blue-600 dark:text-blue-400',
  issued: 'text-yellow-600 dark:text-yellow-400 disabled:opacity-100',
}

/** The row's `proforma_status`, or null when the server did not send one. */
export function proformaStatusOf(row: TableRow): ProformaStatus | null {
  const value = row.proforma_status
  return value === 'none' || value === 'pending' || value === 'issued' ? value : null
}

/**
 * Per-row state of the "€" action: tint and label by request state, inert once
 * issued (it stays visible, yellow, so the row still tells it was billed).
 */
export function useProformaActionState() {
  const { t } = useTranslation()

  return useCallback(
    (action: TableActionDefinition, row: TableRow): RowActionState | undefined => {
      if (action.key !== PROFORMA_ACTION_KEY) {
        return undefined
      }
      const status = proformaStatusOf(row) ?? 'none'
      return {
        className: STATUS_CLASS[status],
        disabled: status === 'issued',
        label: t(`proformaRequests.cell.${status}`),
      }
    },
    [t],
  )
}
