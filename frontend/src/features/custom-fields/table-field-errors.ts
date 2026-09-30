/**
 * Reads server/Zod errors out of the RHF error node bound to a table field
 * (`errors.<prefix>.<key>`): `{ rows: { message } | [ { <col>: { message } } ] }`.
 */

interface ErrorLeaf {
  message?: unknown
}

function messageOf(node: unknown): string | undefined {
  const message = (node as ErrorLeaf | undefined)?.message
  return typeof message === 'string' && message.length > 0 ? message : undefined
}

function rowsNode(error: unknown): unknown {
  return (error as { rows?: unknown } | undefined)?.rows
}

/** Message of the cell at `rows[index][columnKey]`, if any. */
export function readCellError(error: unknown, index: number, columnKey: string): string | undefined {
  const rows = rowsNode(error)
  if (!Array.isArray(rows)) {
    return undefined
  }
  return messageOf((rows[index] as Record<string, unknown> | undefined)?.[columnKey])
}

/** Message attached to `rows` itself (min/max rows, more than one selected row). */
export function readRowsError(error: unknown): string | undefined {
  const rows = rowsNode(error)
  return Array.isArray(rows) ? undefined : messageOf(rows)
}
