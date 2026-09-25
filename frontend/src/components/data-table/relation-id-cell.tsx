/**
 * Cell for an `attr.<code>` relation column (bug 2026-09-25, "Sede corso"
 * showed a number): the row carries the BARE stored id (spec 0064 contract —
 * the backend never formats attribute values), so the label is resolved here
 * through the column's own `/for-select` resource, the same `useForSelectLabels`
 * lookup the work panel's picker uses. Between an inline pick and the server's
 * re-mapped row the cell holds the editor's `{id, name}` projection, shown as is.
 */
import { useMemo } from 'react'
import type { ICellRendererParams } from 'ag-grid-community'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { RelationCell } from '@/features/table/rich-cells'

/** Extra renderer prop: which `/for-select` resource names the stored id. */
export interface RelationIdCellProps {
  resource: string
}

/** The stored id when the value is one (a number, or a digit string from imported data), else null. */
function toBareId(value: unknown): number | null {
  if (typeof value === 'number') {
    return value
  }
  return typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : null
}

export function RelationIdCell(params: ICellRendererParams & RelationIdCellProps) {
  const { value, resource } = params
  const bareId = toBareId(value)
  const ids = useMemo(() => (bareId === null ? [] : [bareId]), [bareId])
  const labels = useForSelectLabels({ resource, ids })

  const resolved = bareId === null ? value : { name: labels.get(bareId)?.label ?? null }

  return <RelationCell {...params} value={resolved} />
}
