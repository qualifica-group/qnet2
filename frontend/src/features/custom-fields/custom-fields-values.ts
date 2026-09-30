import { isTableFieldValue, readRows } from '@/features/custom-fields/table-field-model'
import type { CustomFieldValue, TableFieldRow } from '@/features/custom-fields/types'

/**
 * Value-level helpers shared by the dynamic schema and the payload builders
 * (spec 0021 AC-023) so "what counts as empty/changed" is defined exactly
 * once.
 */

/** `null`/`undefined`, a blank string, or an empty array all count as "not set". `false` does not. */
export function isEmptyCustomFieldValue(value: unknown): boolean {
  if (value === null || value === undefined) {
    return true
  }
  if (typeof value === 'string') {
    return value.trim().length === 0
  }
  if (Array.isArray(value)) {
    return value.length === 0
  }
  if (isTableFieldValue(value)) {
    return !Array.isArray(value.rows) || value.rows.length === 0
  }
  return false
}

/** Row order is significant; `summary` is server-computed so it never counts as a change. */
function isEqualTableRows(a: TableFieldRow[], b: TableFieldRow[]): boolean {
  return (
    a.length === b.length &&
    a.every((row, index) => {
      const other = b[index]
      const keys = new Set([...Object.keys(row), ...Object.keys(other)])
      return [...keys].every((key) => (row[key] ?? null) === (other[key] ?? null))
    })
  )
}

/** Order-independent equality for arrays (enum multiselect / relation many), deep and order-sensitive for tables, strict for scalars. */
export function isEqualCustomFieldValue(a: CustomFieldValue, b: CustomFieldValue): boolean {
  if (isTableFieldValue(a) || isTableFieldValue(b)) {
    return isEqualTableRows(readRows(a), readRows(b))
  }
  if (Array.isArray(a) && Array.isArray(b)) {
    if (a.length !== b.length) {
      return false
    }
    const sortedA = [...a].sort()
    const sortedB = [...b].sort()
    return sortedA.every((item, index) => item === sortedB[index])
  }
  return a === b
}
