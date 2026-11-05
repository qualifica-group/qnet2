import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import type { RowActionState } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Row action state of the installment grids (spec 0363 D-2): the collection/payment actions of a row whose
 * movement is ticked on a bank statement are disabled, with the reason as label (tooltip and accessible name).
 * The backend refuses them anyway (422): this only explains why they are unavailable.
 */
/** Value of a yes/no grid column (the backend sends the enum value, not a boolean). */
export const GRID_YES = 'yes'

export function useReconciledActionState(lockedActionKeys: readonly string[]) {
  const { t } = useTranslation()

  return useCallback(
    (action: TableActionDefinition, row: TableRow): RowActionState | undefined =>
      row.reconciled === GRID_YES && lockedActionKeys.includes(action.key)
        ? { disabled: true, label: `${t(action.label)} - ${t('treasuryMovements.reconciledLock')}` }
        : undefined,
    [lockedActionKeys, t],
  )
}
