import type { ICellRendererParams } from 'ag-grid-community'
import { RefNamesCell } from '@/features/table/rich-cells'

interface OfferLineRef {
  id: number
  name: string
  category_name?: string | null
}

/**
 * The "Linee di prodotto" cell of Contratti and Offerte: each product of the
 * offer's revenue lines followed by its category, "Prodotto (Categoria)"
 * (user request 2026-10-15). Gestione Richieste keeps the bare names.
 */
export function OfferLinesCell(params: ICellRendererParams) {
  const value = Array.isArray(params.value)
    ? (params.value as OfferLineRef[]).map((line) => ({
        id: line.id,
        name: line.category_name ? `${line.name} (${line.category_name})` : line.name,
      }))
    : params.value
  return <RefNamesCell {...params} value={value} />
}
