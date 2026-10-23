import { toWireQuantity } from '@/features/stock/quantity'
import type { StockMovementFormValues } from '@/features/stock/stock-movement-schema'
import type { StockBalanceGridRow, StockMovementPayload, StockMovementType } from '@/features/stock/types'

export const EMPTY_STOCK_MOVEMENT_FORM: StockMovementFormValues = {
  type: 'LOAD',
  product_id: null,
  operational_site_id: null,
  to_operational_site_id: null,
  user_id: null,
  quantity: '',
  counted_quantity: '',
  moved_on: '',
  note: '',
}

/** Form values for a movement of `type`, prefilled from the grid row it starts from (if any). */
export function stockMovementFormValues(
  type: StockMovementType,
  row?: Pick<StockBalanceGridRow, 'product_id' | 'operational_site_id' | 'user_id'>,
): StockMovementFormValues {
  return {
    ...EMPTY_STOCK_MOVEMENT_FORM,
    type,
    product_id: row?.product_id ?? null,
    operational_site_id: row?.operational_site_id ?? null,
    // ASSIGN starts from a free row: the recipient is chosen in the form.
    user_id: type === 'ASSIGN' ? null : (row?.user_id ?? null),
  }
}

/**
 * Wire payload per type (spec 0307 D-11); null only when the schema was bypassed. LOAD never carries a
 * user; only TRANSFER carries a destination site; ADJUSTMENT sends the counted quantity instead of a quantity.
 */
export function toStockMovementPayload(values: StockMovementFormValues): StockMovementPayload | null {
  if (values.product_id === null || values.operational_site_id === null) {
    return null
  }
  const note = values.note.trim()
  const payload: StockMovementPayload = {
    type: values.type,
    product_id: values.product_id,
    operational_site_id: values.operational_site_id,
  }
  if (values.type !== 'LOAD') {
    payload.user_id = values.user_id
  }
  if (values.type === 'TRANSFER' && values.to_operational_site_id !== null) {
    payload.to_operational_site_id = values.to_operational_site_id
  }
  if (values.type === 'ADJUSTMENT') {
    payload.counted_quantity = toWireQuantity(values.counted_quantity)
  } else {
    payload.quantity = toWireQuantity(values.quantity)
  }
  if (values.moved_on !== '') {
    payload.moved_on = values.moved_on
  }
  if (note !== '') {
    payload.note = note
  }
  return payload
}
