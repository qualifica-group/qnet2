import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import type { RowActionState } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { GRID_YES, useReconciledActionState } from '@/features/treasury-movements/use-reconciled-action-state'

/**
 * Row action state of the installment grids: a compensated installment cannot clear its collection/payment (spec 0364
 * D-7, the backend answers 422 "Annulla la compensazione"), a ticked one is locked by spec 0363. The disabled
 * action carries the reason as label (tooltip and accessible name).
 */
export function useInstallmentActionState(lockedActionKeys: readonly string[], compensationLockedKeys: readonly string[]) {
  const { t } = useTranslation()
  const reconciledState = useReconciledActionState(lockedActionKeys)

  return useCallback(
    (action: TableActionDefinition, row: TableRow): RowActionState | undefined =>
      row.compensated === GRID_YES && compensationLockedKeys.includes(action.key)
        ? { disabled: true, label: `${t(action.label)} - ${t('compensations.lock')}` }
        : reconciledState(action, row),
    [compensationLockedKeys, reconciledState, t],
  )
}
