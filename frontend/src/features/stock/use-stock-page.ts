import { useCallback, useRef, useState } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import { stockMovementFormValues } from '@/features/stock/stock-movement-payload'
import type { StockMovementFormValues } from '@/features/stock/stock-movement-schema'
import { STOCK_ACTION_TYPES, STOCK_HISTORY_ACTION } from '@/features/stock/stock-row-actions'
import type { StockBalanceGridRow, StockHistoryFilters, StockMovementType, StockView } from '@/features/stock/types'

/** State and handlers of the /stock page: the tab, the movement form and the history of a row. */
export function useStockPage() {
  const balancesRef = useRef<TableViewHandle>(null)
  const movementsRef = useRef<TableViewHandle>(null)
  const [view, setView] = useState<StockView>('balances')
  const [formValues, setFormValues] = useState<StockMovementFormValues | null>(null)
  const [historyTarget, setHistoryTarget] = useState<StockHistoryFilters | null>(null)

  const openMovement = useCallback((type: StockMovementType) => setFormValues(stockMovementFormValues(type)), [])
  const closeMovement = useCallback(() => setFormValues(null), [])
  const closeHistory = useCallback(() => setHistoryTarget(null), [])

  const handleRowAction: RowActionHandler = useCallback((action, row) => {
    const source = row as unknown as StockBalanceGridRow
    if (action.key === STOCK_HISTORY_ACTION) {
      setHistoryTarget({
        product_id: source.product_id,
        operational_site_id: source.operational_site_id,
        ...(source.user_id === null ? {} : { user_id: source.user_id }),
      })
      return
    }
    const type = STOCK_ACTION_TYPES[action.key]
    if (type !== undefined) {
      setFormValues(stockMovementFormValues(type, source))
    }
  }, [])

  const handleRecorded = useCallback(() => {
    setFormValues(null)
    balancesRef.current?.refresh()
    movementsRef.current?.refresh()
  }, [])

  return {
    balancesRef,
    movementsRef,
    view,
    setView,
    formValues,
    historyTarget,
    openMovement,
    closeMovement,
    closeHistory,
    handleRowAction,
    handleRecorded,
  }
}
