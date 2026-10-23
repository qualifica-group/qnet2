import { useCallback, type RefObject } from 'react'
import { useAbilities } from '@/features/auth/use-abilities'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import { STOCK_ACTION_ICONS, decorateStockRow } from '@/features/stock/stock-row-actions'
import type { TableRow } from '@/features/table/types'
import { STOCK_BALANCES_DOMAIN, STOCK_MANAGE_PERMISSION } from '@/features/stock/types'

interface StockBalancesGridProps {
  tableRef: RefObject<TableViewHandle | null>
  onAction: RowActionHandler
}

/** The "Giacenze" tab: the backend-driven grid of stock rows with their per-row actions (spec 0307 D-19). */
export function StockBalancesGrid({ tableRef, onAction }: StockBalancesGridProps) {
  const { can } = useAbilities()
  const canManage = can(STOCK_MANAGE_PERMISSION)
  const decorateRow = useCallback((row: TableRow) => decorateStockRow(row, canManage), [canManage])

  return (
    <TableView
      ref={tableRef}
      domain={STOCK_BALANCES_DOMAIN}
      onAction={onAction}
      decorateRow={decorateRow}
      iconMap={STOCK_ACTION_ICONS}
      inlineAllActions
    />
  )
}
