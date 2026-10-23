/** Stock (Dotazioni) types. Source of truth: the frozen `data_contract` of spec 0307. Dates are `Y-m-d`; quantities are decimal strings. */

export const STOCK_BALANCES_DOMAIN = 'stock-balances'
export const STOCK_MOVEMENTS_DOMAIN = 'stock-movements'

export const STOCK_VIEW_PERMISSION = 'stock.viewAny'
export const STOCK_MANAGE_PERMISSION = 'stock.manage'

export const STOCK_MOVEMENT_TYPES = ['LOAD', 'UNLOAD', 'TRANSFER', 'ASSIGN', 'RETURN', 'ADJUSTMENT'] as const
export type StockMovementType = (typeof STOCK_MOVEMENT_TYPES)[number]

/** Tabs of the page. */
export const STOCK_VIEWS = ['balances', 'movements'] as const
export type StockView = (typeof STOCK_VIEWS)[number]

export interface StockProductSummary {
  id: number
  code: string
  name: string
  unit_of_measure: { id: number; code: string; name: string }
}

/** One stock row: product x site x (optional) user holding it. */
export interface StockBalance {
  id: number
  product: StockProductSummary
  operational_site: { id: number; label: string; is_active: boolean }
  user: { id: number; name: string; is_active: boolean } | null
  quantity: string
  last_moved_on: string
}

export interface StockMovement {
  id: number
  type: StockMovementType
  product: StockProductSummary
  quantity: string
  from_operational_site: { id: number; label: string } | null
  from_user: { id: number; name: string } | null
  to_operational_site: { id: number; label: string } | null
  to_user: { id: number; name: string } | null
  moved_on: string
  note: string | null
  created_by: { id: number; name: string }
  created_at: string
}

/** Body of `POST /stock-movements`. */
export interface StockMovementPayload {
  type: StockMovementType
  product_id: number
  operational_site_id: number
  user_id?: number | null
  to_operational_site_id?: number
  quantity?: string
  counted_quantity?: string
  moved_on?: string
  note?: string
}

/** `data` of `POST /stock-movements`: the movement and the rows it touched (rows at zero are absent). */
export interface StockMovementResult {
  movement: StockMovement
  balances: StockBalance[]
}

/** Query of `GET /stock-balances` (at least one filter). */
export interface StockBalanceFilters {
  product_id?: number
  user_id?: number
  operational_site_id?: number
}

/** Query of `GET /stock-movements`: the history of one row. `user_id` omitted = the free row. */
export interface StockHistoryFilters {
  product_id: number
  operational_site_id: number
  user_id?: number
}

/** Grid row of `stock-balances` (the ids and abilities the backend adds to the columns). */
export interface StockBalanceGridRow {
  id: number | string
  product_id: number
  operational_site_id: number
  user_id: number | null
  abilities: { manage: boolean }
}
