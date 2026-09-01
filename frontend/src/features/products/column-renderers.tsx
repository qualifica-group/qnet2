/* eslint-disable react-refresh/only-export-components -- renderer registry module: cells are AG Grid render functions, not route/page components */
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { DateTimeCell } from '@/features/table/cell-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { ProductCategorySummary } from '@/features/products/types'

/**
 * Formats a decimal amount using the active UI locale, em dash when null/invalid (spec AC-025).
 * Accepts a numeric string too: Laravel's `decimal:2` cast serializes cost/price as strings
 * (e.g. "10.00"), so a strict `typeof === 'number'` check would blank every value.
 */
export function formatDecimal(value: unknown): string {
  const numeric =
    typeof value === 'number'
      ? value
      : typeof value === 'string' && value.trim() !== ''
        ? Number(value)
        : NaN
  if (!Number.isFinite(numeric)) {
    return ''
  }
  return new Intl.NumberFormat(i18n.language, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numeric)
}

/** Renders a `cost`/`price` decimal cell, em dash when null. */
function DecimalCell({ value }: ICellRendererParams) {
  const formatted = formatDecimal(value)
  return formatted ? <span>{formatted}</span> : <span className="text-muted-foreground">—</span>
}

/** Renders the `category` column: the derived category name, em dash when unset (spec AC-025). */
function CategoryCell({ value }: ICellRendererParams) {
  const category = value as ProductCategorySummary | null
  return category ? (
    <span>{category.name}</span>
  ) : (
    <span className="text-muted-foreground">—</span>
  )
}

/**
 * Custom cell renderers keyed by the backend column `id`. `name`/`description`
 * fall back to the AG Grid default text cell; `created_at` reuses the shared
 * domain-agnostic renderer (mirrors `referentTypeColumnRenderers`).
 */
export const productColumnRenderers: TableRendererMap = {
  cost: (params) => <DecimalCell {...params} />,
  price: (params) => <DecimalCell {...params} />,
  category: (params) => <CategoryCell {...params} />,
  created_at: (params) => <DateTimeCell {...params} />,
}
