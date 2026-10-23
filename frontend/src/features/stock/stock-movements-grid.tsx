import type { RefObject } from 'react'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { STOCK_MOVEMENTS_DOMAIN } from '@/features/stock/types'

/** The register has no row actions: movements are never edited or removed (spec 0307 D-15). */
function noAction() {}

/** The "Movimenti" tab: the read-only register of every movement. */
export function StockMovementsGrid({ tableRef }: { tableRef: RefObject<TableViewHandle | null> }) {
  return <TableView ref={tableRef} domain={STOCK_MOVEMENTS_DOMAIN} onAction={noAction} />
}
