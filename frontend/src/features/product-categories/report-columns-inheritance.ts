/**
 * Pure helpers for the report-columns picker (spec 0141), split off the RHF
 * hook so the toggle/normalize logic is testable without mounting a form.
 */

/** `keys` deduplicated and ordered like `catalogOrder` (spec 0141 D-2: "ordinate nell'ordine del catalogo"). */
export function sortReportColumnKeys(keys: string[], catalogOrder: string[]): string[] {
  const selected = new Set(keys)
  return catalogOrder.filter((key) => selected.has(key))
}

/**
 * The next override after toggling `key` in the currently checked
 * `effective` set. Emptying the result goes back to inheriting (`null`) —
 * AC-009 "deselezionare tutte = null (ereditato)" — so unchecking the last
 * own column is itself the reset, with no separate action required.
 */
export function toggleReportColumn(
  effective: string[],
  key: string,
  catalogOrder: string[],
): string[] | null {
  const next = effective.includes(key) ? effective.filter((entry) => entry !== key) : [...effective, key]
  return next.length === 0 ? null : sortReportColumnKeys(next, catalogOrder)
}

/** Order-independent equality for the payload diff (`buildUpdatePayload`): both sides are already deduplicated. */
export function sameReportColumns(a: string[] | null, b: string[] | null): boolean {
  if (a === null || b === null) {
    return a === b
  }
  if (a.length !== b.length) {
    return false
  }
  const bSet = new Set(b)
  return a.every((key) => bSet.has(key))
}
