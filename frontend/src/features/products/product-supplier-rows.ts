import type { ProductSupplierOption } from '@/features/products/product-suppliers'

/**
 * One row of the product form's suppliers editor (spec 0320). The form holds
 * the supplier's name too, only to label the picker's trigger and the closed
 * row; it never reaches the server.
 */
export interface ProductSupplierRow {
  supplier_id: number | null
  name: string
  price: number | null
  cost: number | null
  is_default: boolean
}

/** The wire shape of one `suppliers[]` entry on store/update. */
export interface ProductSupplierPayload {
  supplier_id: number
  price: number
  cost: number
  is_default: boolean
}

/** Server cap on `suppliers` (spec 0320 data_contract). */
export const MAX_PRODUCT_SUPPLIERS = 50

export const EMPTY_SUPPLIER_ROWS: ProductSupplierRow[] = []

export function rowsFromOptions(options: readonly ProductSupplierOption[] | null | undefined): ProductSupplierRow[] {
  return (options ?? []).map((option) => ({
    supplier_id: option.supplier_id,
    name: option.name,
    price: Number(option.price),
    cost: Number(option.cost),
    is_default: option.is_default,
  }))
}

/** A new empty row; the first row of a list is the default automatically (spec 0320 D-2). */
export function addSupplierRow(rows: readonly ProductSupplierRow[]): ProductSupplierRow[] {
  return [...rows, { supplier_id: null, name: '', price: 0, cost: 0, is_default: rows.length === 0 }]
}

/** Removes a row; if it was the default, the first remaining row takes over. */
export function removeSupplierRow(rows: readonly ProductSupplierRow[], index: number): ProductSupplierRow[] {
  const remaining = rows.filter((_, position) => position !== index)
  if (remaining.length > 0 && !remaining.some((row) => row.is_default)) {
    remaining[0] = { ...remaining[0], is_default: true }
  }
  return remaining
}

export function patchSupplierRow(
  rows: readonly ProductSupplierRow[],
  index: number,
  patch: Partial<ProductSupplierRow>,
): ProductSupplierRow[] {
  return rows.map((row, position) => (position === index ? { ...row, ...patch } : row))
}

/** Exactly one default: the chosen row. */
export function setDefaultSupplierRow(rows: readonly ProductSupplierRow[], index: number): ProductSupplierRow[] {
  return rows.map((row, position) => ({ ...row, is_default: position === index }))
}

/**
 * Only complete rows reach the server (the form schema has already refused the
 * incomplete ones). Price and cost always go as typed (rev.3 D-20); the server
 * saves 0 as the cost of a supplier that earns a commission.
 */
export function toSuppliersPayload(rows: readonly ProductSupplierRow[]): ProductSupplierPayload[] {
  return rows.flatMap((row) =>
    row.supplier_id !== null
      ? [{ supplier_id: row.supplier_id, price: row.price ?? 0, cost: row.cost ?? 0, is_default: row.is_default }]
      : [],
  )
}

/** Order-insensitive: the server sorts default-first, the list is a set keyed by supplier. */
export function sameSupplierRows(next: readonly ProductSupplierRow[], original: readonly ProductSupplierRow[]): boolean {
  return (
    next.length === original.length &&
    next.every((row) =>
      original.some(
        (candidate) =>
          candidate.supplier_id === row.supplier_id &&
          candidate.price === row.price &&
          candidate.cost === row.cost &&
          candidate.is_default === row.is_default,
      ),
    )
  )
}

/**
 * The amount the KPI strip and its margin read (spec 0320 D-4, rev.2 D-18):
 * the default supplier's price/cost once the product has suppliers, else the
 * product's standard amount.
 */
export function effectiveAmountOfRows(
  rows: readonly { price: string | number | null; cost: string | number | null; is_default: boolean }[],
  field: 'price' | 'cost',
  standard: string | number | null,
): string | number | null {
  if (rows.length === 0) {
    return standard
  }

  return (rows.find((row) => row.is_default) ?? rows[0])[field]
}
