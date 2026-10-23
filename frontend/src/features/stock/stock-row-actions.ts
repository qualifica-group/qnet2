import { ArrowLeftRight, PackageMinus, Scale, Undo2, UserPlus, type LucideIcon } from 'lucide-react'
import type { TableRow } from '@/features/table/types'
import type { StockBalanceGridRow, StockMovementType } from '@/features/stock/types'

/** Action keys of the `stock-balances` row catalogue (the backend lists the allowed ones per row). */
export const STOCK_HISTORY_ACTION = 'history'

/** Movement opened by each writing action. */
export const STOCK_ACTION_TYPES: Record<string, StockMovementType> = {
  assign: 'ASSIGN',
  return: 'RETURN',
  transfer: 'TRANSFER',
  unload: 'UNLOAD',
  adjust: 'ADJUSTMENT',
}

/** Icon names of the catalogue mapped to Lucide components (merged over the shared defaults). */
export const STOCK_ACTION_ICONS: Record<string, LucideIcon> = {
  'user-plus': UserPlus,
  'undo-2': Undo2,
  'arrow-left-right': ArrowLeftRight,
  'package-minus': PackageMinus,
  scale: Scale,
}

function isGridRow(row: TableRow): row is TableRow & StockBalanceGridRow {
  const abilities = row.abilities as { manage?: unknown } | undefined
  return typeof abilities?.manage === 'boolean'
}

/**
 * Narrows the server whitelist of a row to what applies to it (spec 0307 D-19): history is always there;
 * the movements need the actor's `stock.manage` and the row's `abilities.manage`; Assign is for free
 * rows only, Return for assigned rows only.
 */
export function decorateStockRow(row: TableRow, actorCanManage: boolean): TableRow {
  const canManage = actorCanManage && isGridRow(row) && row.abilities.manage
  const isAssigned = row.user_id !== null && row.user_id !== undefined
  const actions = row.actions.filter((key) => {
    if (key === STOCK_HISTORY_ACTION) {
      return true
    }
    if (!(key in STOCK_ACTION_TYPES) || !canManage) {
      return false
    }
    if (key === 'assign') {
      return !isAssigned
    }
    if (key === 'return') {
      return isAssigned
    }
    return true
  })

  return { ...row, actions }
}
